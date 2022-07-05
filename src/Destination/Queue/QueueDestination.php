<?php

namespace Bdf\Queue\Destination\Queue;

use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableDriverInterface;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\PeekableDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\QueueConsumer;
use Bdf\Queue\Consumer\Reader\BufferedReader;
use Bdf\Queue\Consumer\Reader\QueueReader;
use Bdf\Queue\Consumer\Reader\QueueReaderInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Destination\ReadableDestinationInterface;
use Bdf\Queue\Message\Message;

/**
 * Destination for simple queue
 */
final class QueueDestination implements DestinationInterface, ReadableDestinationInterface
{
    /**
     * @var QueueDriverInterface
     */
    private $driver;

    /**
     * The queue name
     *
     * @var string
     */
    private $queue;

    /**
     * @var int
     */
    private $prefetch;


    /**
     * QueueDestination constructor.
     *
     * @param QueueDriverInterface $driver
     * @param string $queue The queue name
     * @param int $prefetch The prefetched number of messages. Enabled if higher than 1. The driver connection must implements ReservableQueueDriverInterface for use prefetching
     */
    public function __construct(QueueDriverInterface $driver, string $queue, int $prefetch = 1)
    {
        $this->driver = $driver;
        $this->queue = $queue;
        $this->prefetch = $prefetch;
    }

    /**
     * {@inheritdoc}
     */
    public function consumer(ReceiverInterface $receiver): ConsumerInterface
    {
        return new QueueConsumer($this->reader(), $receiver);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message): PromiseInterface
    {
        $message->setConnection($this->driver->connection()->getName());
        $message->setQueue($this->queue);

        if ($message->needsReply()) {
            $this->driver->push(QueuePromise::prepareMessage($message));

            return QueuePromise::fromMessage($this->driver, $message);
        }

        $this->driver->push($message);

        return NullPromise::instance();
    }

    /**
     * {@inheritdoc}
     */
    public function raw($payload, array $options = []): void
    {
        $this->driver->pushRaw($payload, $this->queue, $options['delay'] ?? 0);
    }

    /**
     * {@inheritdoc}
     */
    public function declare(): void
    {
        $connection = $this->driver->connection();

        if ($connection instanceof ManageableQueueInterface) {
            $connection->declareQueue($this->queue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $connection = $this->driver->connection();

        if ($connection instanceof ManageableQueueInterface) {
            $connection->deleteQueue($this->queue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        $queue = $this->driver->connection()->queue();

        if (!$queue instanceof CountableDriverInterface) {
            throw new \BadMethodCallException(__METHOD__.' works only with countable connection.');
        }

        return $queue->count($this->queue);
    }

    /**
     * {@inheritdoc}
     */
    public function peek(int $rowCount = 20, int $page = 1): array
    {
        $queue = $this->driver->connection()->queue();

        if (!$queue instanceof PeekableDriverInterface) {
            throw new \BadMethodCallException(__METHOD__.' works only with peekable connection.');
        }

        return $queue->peek($this->queue, $rowCount, $page);
    }

    /**
     * Get the reader corresponding to the configuration
     *
     * @return QueueReaderInterface
     */
    private function reader(): QueueReaderInterface
    {
        if ($this->prefetch > 1) {
            return new BufferedReader($this->driver, $this->queue, $this->prefetch);
        }

        return new QueueReader($this->driver, $this->queue);
    }

    /**
     * Creates the queue destination by a DSN
     *
     * @param ConnectionDriverInterface $connection
     * @param DsnRequest $dsn
     *
     * @return self
     */
    public static function createByDsn(ConnectionDriverInterface $connection, DsnRequest $dsn): self
    {
        return new QueueDestination(
            $connection->queue(),
            trim($dsn->getPath(), '/'),
            $dsn->query('prefetch', $connection->config()['prefetch'] ?? 0)
        );
    }
}
