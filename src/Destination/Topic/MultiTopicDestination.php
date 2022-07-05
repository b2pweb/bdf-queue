<?php

namespace Bdf\Queue\Destination\Topic;

use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\ManageableTopicInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Consumer\TopicConsumer;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Message\Message;

/**
 * Destination for multiple topics on single connection
 * Read-only destination : cannot send a message to this destination
 */
final class MultiTopicDestination implements DestinationInterface
{
    /**
     * @var TopicDriverInterface
     */
    private $driver;

    /**
     * The topic names
     *
     * @var string[]
     */
    private $topics;


    /**
     * TopicDestination constructor.
     *
     * @param TopicDriverInterface $driver
     * @param string[] $topics The topic names
     */
    public function __construct(TopicDriverInterface $driver, array $topics)
    {
        $this->driver = $driver;
        $this->topics = $topics;
    }

    /**
     * {@inheritdoc}
     */
    public function consumer(ReceiverInterface $receiver): ConsumerInterface
    {
        // Clone the topic driver to ensure that it will not be modified by the consumer
        // because subscribe is a mutable operation
        return new TopicConsumer(clone $this->driver, $receiver, $this->topics);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message): PromiseInterface
    {
        throw new \BadMethodCallException('Multi-topic destination is read-only');
    }

    /**
     * {@inheritdoc}
     */
    public function raw($payload, array $options = []): void
    {
        throw new \BadMethodCallException('Multi-topic destination is read-only');
    }

    /**
     * {@inheritdoc}
     */
    public function declare(): void
    {
        $connection = $this->driver->connection();

        if (!$connection instanceof ManageableTopicInterface) {
            return;
        }

        foreach ($this->topics as $topic) {
            $connection->declareTopic($topic);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $connection = $this->driver->connection();

        if (!$connection instanceof ManageableTopicInterface) {
            return;
        }

        foreach ($this->topics as $topic) {
            $connection->deleteTopic($topic);
        }
    }

    /**
     * Creates the multi topic destination by a DSN
     *
     * @param ConnectionDriverInterface $connection
     * @param DsnRequest $dsn
     *
     * @return self
     *
     * @deprecated Since 1.4. Use TopicDestinationFactory::createMultipleByDsn() instead
     */
    public static function createByDsn(ConnectionDriverInterface $connection, DsnRequest $dsn): self
    {
        return new self($connection->topic(), explode(',', trim($dsn->getPath(), '/')));
    }
}
