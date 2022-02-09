<?php

namespace Bdf\Queue\Connection\AmqpLib;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\QueueEnvelopeHelper;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * AmqpLibQueue
 */
class AmqpLibQueue implements QueueDriverInterface
{
    use ConnectionBearer;
    use QueueEnvelopeHelper;

    /**
     * PhpRedisQueue constructor.
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
    public function push(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        $this->pushRaw(
            $this->connection->serializer()->serialize($message),
            $message->queue(),
            $message->delay(),
            $message->header('flags', AmqpLibConnection::FLAG_NOPARAM)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0, int $flags = 0): void
    {
        if ($this->connection->shouldAutoDeclare()) {
            $this->connection->declareQueue($queue);
        }

        $message = new AMQPMessage($raw, [
            'Content-Type'  => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->connection->publish(
            $message,
            '',
            $this->connection->declareDelayedQueue($queue, $delay),
            $flags
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        if ($this->connection->shouldAutoDeclare()) {
            $this->connection->declareQueue($queue);
        }

        $expire = time() + $duration;
        $flags = $this->connection->consumerFlags();

        while (time() < $expire) {
            $message = $this->connection->channel()->basic_get(
                $queue,
                (bool) ($flags & AmqpLibConnection::FLAG_CONSUMER_NOACK)
            );

            if ($message instanceof AMQPMessage) {
                return $this->toQueueEnvelope($this->connection->toQueuedMessage($message->body, $queue, $message));
            }

            // sleep for 200 ms before next check
            usleep($this->connection->internalSleepDuration());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        $this->connection->channel()->basic_ack($message->internalJob()->delivery_info['delivery_tag']);
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        $this->connection->channel()->basic_nack($message->internalJob()->delivery_info['delivery_tag'], false, true);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $queue): ?int
    {
//        return $this->connection->channel()->queue_declare($queue, true)[1];
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        return [];
    }
}
