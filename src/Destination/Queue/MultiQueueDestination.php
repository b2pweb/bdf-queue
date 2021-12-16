<?php

namespace Bdf\Queue\Destination\Queue;

use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\QueueConsumer;
use Bdf\Queue\Consumer\Reader\MultiQueueReader;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Message\Message;

/**
 * Destination for multiple queues on single connection
 * Read-only destination : cannot send a message to this destination
 */
final class MultiQueueDestination implements DestinationInterface
{
    /**
     * @var QueueDriverInterface
     */
    private $driver;

    /**
     * @var string[]
     */
    private $queues;


    /**
     * MultiQueueDestination constructor.
     *
     * @param QueueDriverInterface $driver
     * @param string[] $queues The queues names
     */
    public function __construct(QueueDriverInterface $driver, array $queues)
    {
        $this->driver = $driver;
        $this->queues = $queues;
    }

    /**
     * {@inheritdoc}
     */
    public function consumer(ReceiverInterface $receiver): ConsumerInterface
    {
        return new QueueConsumer(new MultiQueueReader($this->driver, $this->queues), $receiver);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message): PromiseInterface
    {
        if ($message->needsReply()) {
            throw new \BadMethodCallException('Bdf multi-queue destination does not support reply option.');
        }

        $message->setConnection($this->driver->connection()->getName());

        foreach ($this->queues as $queue) {
            $toSend = clone $message;
            $toSend->setQueue($queue);

            $this->driver->push($toSend);
        }

        return NullPromise::instance();
    }

    /**
     * {@inheritdoc}
     */
    public function raw($payload, array $options = []): void
    {
        $delay = $options['delay'] ?? 0;

        foreach ($this->queues as $queue) {
            $this->driver->pushRaw($payload, $queue, $delay);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function declare(): void
    {
        $connection = $this->driver->connection();

        if (!$connection instanceof ManageableQueueInterface) {
            return;
        }

        foreach ($this->queues as $queue) {
            $connection->declareQueue($queue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $connection = $this->driver->connection();

        if (!$connection instanceof ManageableQueueInterface) {
            return;
        }

        foreach ($this->queues as $queue) {
            $connection->deleteQueue($queue);
        }
    }

    /**
     * Creates the multi queue destination by a DSN
     *
     * @param ConnectionDriverInterface $connection
     * @param DsnRequest $dsn
     *
     * @return self
     */
    public static function createByDsn(ConnectionDriverInterface $connection, DsnRequest $dsn): self
    {
        return new MultiQueueDestination($connection->queue(), explode(',', trim($dsn->getPath(), '/')));
    }
}
