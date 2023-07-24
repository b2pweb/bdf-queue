<?php

namespace Bdf\Queue\Connection\AmqpLib;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\TopicEnvelopeHelper;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueueEnvelope;
use Exception;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOWaitException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * AmqpLibTopic
 *
 * Manage pub/sub pattern of rabbitmq
 *
 *               ________________
 *              |                |----->| Queue #1: "group1/foo" |----->| Workers
 * publish ---->|  Topic: "foo"  |
 *              |________________|----->| Queue #2: "group2/foo" |----->| Workers
 *
 * This architecture allows:
 *  - group of workers
 *  - persistence of messages
 *
 * The routing keys for binding should be declared first.
 */
class AmqpLibTopic implements TopicDriverInterface
{
    use ConnectionBearer;
    use TopicEnvelopeHelper;

    /**
     * The Redis connection.
     *
     * @var AmqpLibConnection
     */
    private $connection;

    /**
     * @var array
     */
    private $subscribers = [];

    /**
     * AmqpLibTopic constructor.
     *
     * @param AmqpLibConnection $connection
     */
    public function __construct(AmqpLibConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        $this->produce(
            $message->topic(),
            $this->connection->serializer()->serialize($message),
            $message->header('routing_key', $message->topic()),
            $message->header('flags', AmqpLibConnection::FLAG_NOPARAM)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $topic, $payload): void
    {
        $this->produce($topic, $payload, $topic);
    }

    /**
     * @param string $topic
     * @param string $payload
     * @param string $routingKey
     * @param int $flags
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    private function produce(string $topic, string $payload, string $routingKey = '', int $flags = 0)
    {
        if ($this->connection->shouldAutoDeclare()) {
            $this->connection->declareTopic($topic);
        }

        $message = new AMQPMessage($payload, [
            'Content-Type'  => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->connection->publish($message, $topic, $routingKey, $flags);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(array $topics, callable $callback): void
    {
        foreach ($topics as $topic) {
            $flags = AmqpLibConnection::FLAG_NOPARAM;
            $queue = $this->connection->bind($topic, [$topic]);

            try {
                $this->connection->channel()->basic_consume(
                    $queue,
                    '',
                    false, // TODO manage no_local flag
                    (bool) ($flags & AmqpLibConnection::FLAG_CONSUMER_NOACK),
                    (bool) ($flags & AmqpLibConnection::FLAG_QUEUE_EXCLUSIVE),
                    (bool) ($flags & AmqpLibConnection::FLAG_QUEUE_NOWAIT),
                    function (AMQPMessage $message) use ($callback, $queue) {
                        // The binding queue allows rabbit to consume a message from a queue rather than a topic.
                        // The callback will receive a queue envelope instead of a topic envelope.
                        $callback(
                            new QueueEnvelope(
                                $this->connection->queue(),
                                $this->connection->toQueuedMessage($message->body, $queue, $message)
                            )
                        );
                    }
                );
            } catch (AMQPConnectionClosedException $e) {
                throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
            } catch (AMQPRuntimeException $e) {
                throw new ServerException($e->getMessage(), $e->getCode(), $e);
            } catch (ConnectionException $e) {
                throw $e;
            } catch (Exception $e) {
                throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int
    {
        try {
            $this->connection->channel()->wait(null, false, $duration);

            // Consume one by one ?
            return 1;
        } catch (AMQPTimeoutException $e) {
            // the timeout parameter from wait trigger AMQPTimeoutException
        } catch (AMQPIOWaitException $e) {
            // socket interruption trigger AMQPIOWaitException. Could happen with CTRL^C
        } catch (AMQPConnectionClosedException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (AMQPRuntimeException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        } catch (ConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }
}
