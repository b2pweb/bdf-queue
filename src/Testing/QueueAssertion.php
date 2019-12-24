<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Message\QueuedMessage;
use Psr\Container\ContainerInterface;

/**
 * Queue assertions helper
 *
 * All queues for testing should use the prime driver. By default the table name is "queue".
 */
trait QueueAssertion
{
    /**
     * Gets the container
     *
     * @return ContainerInterface
     */
    abstract public function container(): ContainerInterface;

    /**
     * Create or truncate the queue table
     *
     * @param null|string $connectionName
     * @param string $queue
     */
    public function initializeQueue(string $connectionName = null, string $queue = 'queue')
    {
        $connection = $this->connection($connectionName);

        if ($connection instanceof ManageableQueueInterface) {
            $connection->declareQueue($queue);
        }
    }

    /**
     * Assert queue should be empty
     *
     * @param string $queue
     * @param string $connectionName
     * @param string $message
     *
     */
    public function assertQueueEmpty(string $queue, string $connectionName = null, string $message = null)
    {
        $this->assertQueueNumber(0, $queue, $connectionName, $message);
    }

    /**
     * Assert queue number rows
     *
     * @param int    $expected
     * @param string $queue
     * @param string $connectionName
     * @param string $message
     *
     */
    public function assertQueueNumber(int $expected, string $queue, string $connectionName = null, string $message = null)
    {
        $helper = new QueueHelper($this->container());

        $this->assertSame(
            $expected,
            $count = $helper->count($queue, $connectionName),
            $message ?? 'Queue "'.$queue.'" has not "'.$expected.'" rows. "'.$count.'" rows has been found.'
        );
    }

    /**
     * Assert job exists in queue
     *
     * @param string $expected  The expected job
     * @param string $queue
     * @param string $connectionName
     * @param string $message
     */
    public function assertQueueHasJob(string $expected, string $queue, string $connectionName = null, string $message = null)
    {
        $this->assertQueueContains(
            "@$expected",
            $queue,
            $connectionName,
            $message ?? 'Fail asserting job exists "'.$expected.'" in queue "'.$queue.'".'
        );
    }

    /**
     * Assert queue contains value in raw
     *
     * @param string $expected  The expected string
     * @param string $queue
     * @param string $connectionName
     * @param string $message
     */
    public function assertQueueContains(string $expected, string $queue, string $connectionName = null, string $message = null)
    {
        $helper = new QueueHelper($this->container());

        $this->assertTrue(
            $helper->contains($expected, $queue, $connectionName),
            $message ?? 'Fail asserting "'.$expected.'" in raw queue "'.$queue.'".'
        );
    }

    /**
     * Get awaiting messages
     *
     * @param string $queue
     * @param string $connectionName
     * @param int $number
     *
     * @return QueuedMessage[]
     */
    public function getAwaitingMessages(string $queue, string $connectionName = null, int $number = 20): array
    {
        $helper = new QueueHelper($this->container());

        return $helper->peek($number, $queue, $connectionName);
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
        $helper = new QueueHelper($this->container());
        $helper->consume($number, $destination, $queue, $configurator);
    }

    /**
     * Returns the connection driver
     *
     * @param string $connection
     *
     * @return ConnectionDriverInterface
     */
    public function connection(string $connection): ConnectionDriverInterface
    {
        return $this->container()->get(ConnectionDriverFactoryInterface::class)->create($connection);
    }

    /**
     * Returns the destination manager
     *
     * @return DestinationManager
     */
    public function destination(): DestinationManager
    {
        return $this->container()->get(DestinationManager::class);
    }
}