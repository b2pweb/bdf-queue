<?php

namespace Bdf\Queue\Connection\Enqueue;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Connection\Extension\QueueEnvelopeHelper;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Interop\Queue\Exception;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;

/**
 * Queue driver for enqueue
 */
class EnqueueQueue implements QueueDriverInterface
{
    use QueueEnvelopeHelper;

    /**
     * @var EnqueueConnection
     */
    private $connection;


    /**
     * EnqueueQueue constructor.
     *
     * @param EnqueueConnection $connection
     */
    public function __construct(EnqueueConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function connection(): ConnectionDriverInterface
    {
        return $this->connection;
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
            $message->delay()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
        $context = $this->connection->context();
        $queue = $this->connection->queueDestination($queue);

        $producer = $context->createProducer();

        if ($delay > 0) {
            try {
                $producer->setDeliveryDelay($delay);
            } catch (DeliveryDelayNotSupportedException $e) {
                throw new ConnectionException($e);
            }
        }

        try {
            $producer->send($queue, $context->createMessage($raw));
        } catch (InvalidDestinationException | InvalidMessageException $e) {
            throw new ConnectionException($e);
        } catch (Exception $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        $context = $this->connection->context();
        $queue = $this->connection->queueDestination($queue);

        try {
            $message = $context->createConsumer($queue)->receive($duration);
        } catch (Exception $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$message) {
            return null;
        }

        return $this->toQueueEnvelope($this->connection->toQueuedMessage($message->getBody(), $queue->getQueueName(), $message));
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        $context = $this->connection->context();

        try {
            $consumer = $context->createConsumer($context->createQueue($message->queue()));
            $consumer->acknowledge($message->internalJob());
        } catch (Exception $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        $context = $this->connection->context();

        try {
            $consumer = $context->createConsumer($this->connection->context()->createQueue($message->queue()));
            $consumer->reject($message->internalJob(), true);
        } catch (Exception $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @deprecated Since 1.2: Enqueue does not support count method. Will be removed in 2.0
     *
     * @param string $queue
     *
     * @return null
     */
    public function count(string $queue): ?int
    {
        @trigger_error("Since 1.2: Enqueue does not support count method. Will be removed in 2.0", \E_USER_DEPRECATED);

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
