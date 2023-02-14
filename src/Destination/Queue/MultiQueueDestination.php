<?php

namespace Bdf\Queue\Destination\Queue;

use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableQueueDriverInterface;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\PeekableQueueDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\QueueConsumer;
use Bdf\Queue\Consumer\Reader\MultiQueueReader;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Destination\ReadableDestinationInterface;
use Bdf\Queue\Message\Message;

/**
 * Destination for multiple queues on single connection
 */
final class MultiQueueDestination implements DestinationInterface, ReadableDestinationInterface
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
     * {@inheritdoc}
     */
    public function count(): int
    {
        $count = 0;
        $queue = $this->driver->connection()->queue();

        if (!$queue instanceof CountableQueueDriverInterface) {
            throw new \BadMethodCallException(__METHOD__.' works only with countable connection.');
        }

        foreach ($this->queues as $queueName) {
            $count += $queue->count($queueName);
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function peek(int $rowCount = 20, int $page = 1): array
    {
        $queue = $this->driver->connection()->queue();

        if (!$queue instanceof PeekableQueueDriverInterface) {
            throw new \BadMethodCallException(__METHOD__.' works only with peekable connection.');
        }

        $items = [];
        foreach ($this->queues as $queueName) {
            $items = array_merge($items, $queue->peek($queueName, $rowCount, $page));

            if (count($items) >= $rowCount) {
                break;
            }
        }

        return $items;
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
