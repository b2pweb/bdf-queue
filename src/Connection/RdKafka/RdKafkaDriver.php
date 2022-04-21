<?php

namespace Bdf\Queue\Connection\RdKafka;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\EnvelopeHelper;
use Bdf\Queue\Connection\Extension\Subscriber;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
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

        $topic = $this->connection->producer()->newTopic($message->queue());
        $topic->produce(
            $message->header('partition', RD_KAFKA_PARTITION_UA),
            0,
            $this->connection->serializer()->serialize($message),
            $message->header('key')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
        $topic = $this->connection->producer()->newTopic($queue);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $raw);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        $topic = $this->connection->producer()->newTopic($message->topic());
        $topic->produce(
            $message->header('partition', RD_KAFKA_PARTITION_UA),
            0,
            $this->connection->serializer()->serialize($message),
            $message->header('key')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $topic, $payload): void
    {
        $topic = $this->connection->producer()->newTopic($topic);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RdKafka\Exception
     * @throws \Exception
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        return $this->popKafkaMessage($this->connection->queueConsumer($queue), $duration);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RdKafka\Exception
     * @throws \Exception
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
     */
    private function popKafkaMessage(KafkaConsumer $consumer, int $duration, $forTopic = false): ?EnvelopeInterface
    {
        $kafkaMessage = $consumer->consume($duration > 0 ? $duration * 1000 : 1000); //ms

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
                    throw new \Exception($kafkaMessage->errstr(), $kafkaMessage->err);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        if ($this->connection->commitAsync()) {
            $this->connection->queueConsumer($message->queue())->commitAsync($message->internalJob());
        } else {
            $this->connection->queueConsumer($message->queue())->commit($message->internalJob());
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
     * @return null
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
}
