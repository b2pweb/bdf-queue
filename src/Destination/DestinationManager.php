<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Destination\Queue\MultiQueueDestination;
use Bdf\Queue\Destination\Queue\QueueDestination;
use Bdf\Queue\Destination\Topic\TopicDestination;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * Facade for manage destinations in high level layer
 */
final class DestinationManager implements DestinationFactoryInterface
{
    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $connectionFactory;

    /**
     * @var DestinationFactoryInterface
     */
    private $destinationFactory;


    /**
     * DestinationFactory constructor.
     *
     * @param ConnectionDriverFactoryInterface $connectionFactory
     * @param DestinationFactoryInterface $destinationFactory
     */
    public function __construct(ConnectionDriverFactoryInterface $connectionFactory, DestinationFactoryInterface $destinationFactory)
    {
        $this->connectionFactory = $connectionFactory;
        $this->destinationFactory = $destinationFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $destination): DestinationInterface
    {
        return $this->destinationFactory->create($destination);
    }

    /**
     * Creates a topic destination for perform broadcasting
     *
     * @param string $connection The connection name
     * @param string $topic The topic name
     *
     * @return DestinationInterface
     */
    public function topic(string $connection, string $topic): DestinationInterface
    {
        return new TopicDestination($this->connectionFactory->create($connection)->topic(), $topic);
    }

    /**
     * Creates a queue destination
     *
     * <code>
     * $factory->queue(null); // Get the default queue
     * $factory->queue('foo'); // Get the default queue for connection "foo"
     * $factory->queue('foo', 'bar'); // Get the queue "bar" into "foo" connection
     * $factory->queue('foo', ['q1', 'q2']); // Multi queues
     * </code>
     *
     * @param string|null $connection The connection name. Use null for the default connection
     * @param string|string[]|null $queue The queue name. Use an array of queue name for multi-queue reading. Use null for the default queue
     *
     * @return DestinationInterface
     */
    public function queue(?string $connection, $queue = null): DestinationInterface
    {
        $connection = $this->connectionFactory->create($connection);

        // Extract queue name from connection config
        if (!$queue) {
            if (empty($connection->config()['queue'])) {
                throw new \InvalidArgumentException('The queue name is missing');
            }

            $queue = $connection->config()['queue'];
        }

        if (is_array($queue)) {
            return new MultiQueueDestination($connection->queue(), $queue);
        }

        return new QueueDestination($connection->queue(), $queue, $connection->config()['prefetch'] ?? 0);
    }

    /**
     * Create the destination for the given message
     *
     * @param Message $message
     *
     * @return DestinationInterface
     */
    public function for(Message $message): DestinationInterface
    {
        if ($message->queue()) {
            return $this->queue($message->connection(), $message->queue());
        }

        if ($message->topic()) {
            return $this->topic($message->connection(), $message->topic());
        }

        return $this->guess($message->destination());
    }

    /**
     * Get the destination from a name
     *
     * @param string|null $destination
     *
     * @return DestinationInterface
     */
    public function guess(?string $destination): DestinationInterface
    {
        // @todo legacy : should be removed when default connection is removed
        if (empty($destination)) {
            return $this->queue(null);
        }

        // Get the queue from the connection
        if (strpos($destination, '::') !== false) {
            list($connection, $queue) = explode('::', $destination, 2);

            // Manage multi queues
            if (strpos($queue, ',') !== false) {
                $queue = explode(',', $queue);
            }

            return $this->queue($connection, $queue);
        }

        try {
            return $this->create($destination);
        } catch (\Exception $e) {
            // Destination not configured : fallback to legacy connection queue configuration
            return $this->queue($destination);
        }
    }

    /**
     * Send the message to the configured destination
     * The method may return a response if asked and if the destination supports
     *
     * <code>
     * $manager->send((new Message('foo'))->setQueue('my-queue')); // Send message to "my-queue"
     * $manager->send((new Message('foo'))->setQueue('my-queue')->setNeedsReply())->await(); // Send a message and waits for the response
     * </code>
     *
     * @param Message $message
     *
     * @return PromiseInterface
     */
    public function send(Message $message): PromiseInterface
    {
        return $this->for($message)->send($message);
    }

    /**
     * Perform an RPC call using queue system
     * Sends the message into a queue, and waits for its response
     *
     * @param Message $message The message to send
     * @param int $timeout The waiting time, in milliseconds
     *
     * @return QueuedMessage|null The response message, if available
     */
    public function call(Message $message, int $timeout = 0): ?QueuedMessage
    {
        return $this->send($message->setNeedsReply())->await($timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function destinationNames(): array
    {
        return $this->destinationFactory->destinationNames();
    }

    /**
     * List all available connection names
     *
     * @return string[]
     */
    public function connectionNames(): array
    {
        return $this->connectionFactory->connectionNames();
    }
}
