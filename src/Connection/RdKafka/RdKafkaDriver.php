<?php

namespace Bdf\Queue\Connection\RdKafka;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\EnvelopeHelper;
use Bdf\Queue\Connection\Extension\Subscriber;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use RdKafka\Exception as KafkaException;
use RdKafka\KafkaConsumer;

/**
 * RdKafkaDriver
 *
 * This architecture allows:
 *  - pub/sub pattern
 *  - group of workers
 *  - specific consumer to alert all workers for stopping process
 *  - Recover missed events
 *
 * This architecture does not allow:
 *  - the configuration of the message persitence by group (-> all message are persist)
 */
class RdKafkaDriver implements QueueDriverInterface, TopicDriverInterface
{
    use ConnectionBearer;
    use EnvelopeHelper;
    use Subscriber;

    /**
     * RdKafkaDriver constructor.
     *
     * @param RdKafkaConnection $connection
     */
    public function __construct(RdKafkaConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function push(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        try {
            $topic = $this->connection->producer()->newTopic($message->queue());
            $topic->produce(
                $message->header('partition', RD_KAFKA_PARTITION_UA),
                0,
                $this->connection->serializer()->serialize($message),
                $message->header('key')
            );
        } catch (KafkaException $e) {
            $this->handleException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
        try {
            $topic = $this->connection->producer()->newTopic($queue);
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $raw);
        } catch (KafkaException $e) {
            $this->handleException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        try {
            $topic = $this->connection->producer()->newTopic($message->topic());
            $topic->produce(
                $message->header('partition', RD_KAFKA_PARTITION_UA),
                0,
                $this->connection->serializer()->serialize($message),
                $message->header('key')
            );
        } catch (KafkaException $e) {
            $this->handleException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $topic, $payload): void
    {
        try {
            $topic = $this->connection->producer()->newTopic($topic);
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
        } catch (KafkaException $e) {
            $this->handleException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        try {
            return $this->popKafkaMessage($this->connection->queueConsumer($queue), $duration);
        } catch (KafkaException $e) {
            $this->handleException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int
    {
        $topics = $this->getSubscribedTopics();
        $envelope = $this->popKafkaMessage($this->connection->topicConsumer($topics), $duration, true);

        if ($envelope === null) {
            return 0;
        }

        foreach ($this->getSubscribers($envelope->message()->queue()) as $callback) {
            $callback($envelope);
        }

        return 1;
    }

    /**
     * Pop a kafka message from a consumer
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    private function popKafkaMessage(KafkaConsumer $consumer, int $duration, $forTopic = false): ?EnvelopeInterface
    {
        try {
            $kafkaMessage = $consumer->consume($duration > 0 ? $duration * 1000 : 1000); //ms
        } catch (KafkaException $e) {
            $this->handleException($e);
        }

        if ($kafkaMessage !== null) {
            switch ($kafkaMessage->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $message = $this->connection->toQueuedMessage($kafkaMessage->payload, $kafkaMessage->topic_name, $kafkaMessage);
                    $message->addHeader('partition', $kafkaMessage->partition);
                    $message->addHeader('key', $kafkaMessage->key);

                    return $forTopic
                        ? $this->toTopicEnvelope($message)
                        : $this->toQueueEnvelope($message);

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;

                default:
                    throw new ServerException($kafkaMessage->errstr(), $kafkaMessage->err);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        try {
            if ($this->connection->commitAsync()) {
                $this->connection->queueConsumer($message->queue())->commitAsync($message->internalJob());
            } else {
                $this->connection->queueConsumer($message->queue())->commit($message->internalJob());
            }
        } catch (KafkaException $e) {
            $this->handleException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        $this->acknowledge($message);
        $this->push($message);
    }

    /**
     * @deprecated Since 1.2: Rdkafka does not support count method. Will be removed in 2.0
     *
     * @param string $queue
     *
     * @return int
     */
    public function count(string $queue): ?int
    {
        @trigger_error("Since 1.2: Rdkafka does not support count method. Will be removed in 2.0", \E_USER_DEPRECATED);

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        return [];
    }

    /**
     * Transform the kafka exception to one of bdf queue exception
     */
    private function handleException(KafkaException $e): void
    {
        switch ($e->getCode()) {
            case RD_KAFKA_RESP_ERR__TRANSPORT:
            case RD_KAFKA_RESP_ERR_NETWORK_EXCEPTION:
                throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);

            default:
                throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
