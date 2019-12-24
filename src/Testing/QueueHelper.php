<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\PeekableQueueDriverInterface;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader;
use Bdf\Queue\Consumer\Receiver\MessageCountLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\DestinationManager;
use Psr\Container\ContainerInterface;

/**
 * Queue helper
 */
class QueueHelper
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * QueueHelper constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Assert queue number rows
     *
     * @param string $queue
     * @param string $connectionName
     *
     * @return int
     *
     */
    public function count(string $queue, string $connectionName = null): int
    {
        return $this->connection($connectionName)->queue()->count($queue) ?? 0;
    }

    /**
     * Assert queue contains value in raw
     *
     * @param string $expected  The expected string
     * @param string $queueName
     * @param string $connectionName
     *
     * @return bool
     */
    public function contains(string $expected, string $queueName, string $connectionName = null): bool
    {
        $queue = $this->connection($connectionName)->queue();

        if (!$queue instanceof PeekableQueueDriverInterface) {
            throw new \LogicException(__METHOD__.' works only with peekable connection.');
        }

        $page = 1;
        while ($messages = $queue->peek($queueName, 20, $page)) {
            foreach ($messages as $message) {
                if (strpos($message->raw(), $expected) !== false) {
                    return true;
                }
            }
            $page++;
        }

        return false;
    }

    /**
     * Get the queue jobs
     *
     * @param int $number
     * @param string $queueName
     * @param string $connectionName
     *
     * @return array
     */
    public function peek(int $number, string $queueName, string $connectionName = null): array
    {
        $queue = $this->connection($connectionName)->queue();

        if (!$queue instanceof PeekableQueueDriverInterface) {
            throw new \LogicException(__METHOD__.' works only with peekable connection.');
        }

        return $queue->peek($queueName, $number);
    }

    /**
     * Consume a number of job from the queue.
     *
     * The work is stopped if the number max of loop is reached or if it had to sleep.
     *
     * @param int $number  Number of loop before stopping the worker
     * @param DestinationInterface|string $destination
     * @param string|array $queue
     * @param \Closure $configurator
     */
    public function consume(int $number = 1, $destination = null, $queue = null, \Closure $configurator = null)
    {
        /** @var ReceiverBuilder $builder */
        $builder = $this->container->get(ReceiverLoader::class)->load(is_string($destination) ? $destination : '');

        // Cannot add receivers here, because it will be added before configurator
        $extension = $builder
            //->stopWhenEmpty()
            //->max($number)
            ->build()
        ;

        if ($configurator !== null) {
            $extension = $configurator($extension);
        }

        $extension = new StopWhenEmptyReceiver($extension);
        $extension = new MessageCountLimiterReceiver($extension, $number);

        if (!$destination instanceof DestinationInterface) {
            $destination = $this->destination()->queue($destination, $queue);
        }

        $destination->consumer($extension)->consume(0);
    }

    /**
     * Returns the connection driver
     *
     * @param null|string $connection
     *
     * @return ConnectionDriverInterface
     */
    private function connection(?string $connection)
    {
        return $this->container->get(ConnectionDriverFactoryInterface::class)->create($connection);
    }

    /**
     * Returns the destination manager
     *
     * @return DestinationManager
     */
    private function destination(): DestinationManager
    {
        return $this->container->get(DestinationManager::class);
    }
}