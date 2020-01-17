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
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Message\Message;

/**
 * Destination for topic (broadcast)
 */
final class TopicDestination implements DestinationInterface
{
    /**
     * @var TopicDriverInterface
     */
    private $driver;

    /**
     * The topic name
     *
     * @var string
     */
    private $topic;


    /**
     * TopicDestination constructor.
     *
     * @param TopicDriverInterface $driver
     * @param string $topic The topic name
     */
    public function __construct(TopicDriverInterface $driver, string $topic)
    {
        $this->driver = $driver;
        $this->topic = $topic;
    }

    /**
     * {@inheritdoc}
     */
    public function consumer(ReceiverInterface $receiver): ConsumerInterface
    {
        // Clone the topic driver to ensure that it will not be modified by the consumer
        // because subscribe is a mutable operation
        return new TopicConsumer(clone $this->driver, $receiver, [$this->topic]);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message): PromiseInterface
    {
        $message->setConnection($this->driver->connection()->getName());
        $message->setTopic($this->topic);

        $this->driver->publish($message);

        return NullPromise::instance();
    }

    /**
     * {@inheritdoc}
     */
    public function raw($payload, array $options = []): void
    {
        $this->driver->publishRaw($this->topic, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function declare(): void
    {
        $connection = $this->driver->connection();

        if ($connection instanceof ManageableTopicInterface) {
            $connection->declareTopic($this->topic);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $connection = $this->driver->connection();

        if ($connection instanceof ManageableTopicInterface) {
            $connection->deleteTopic($this->topic);
        }
    }

    /**
     * Creates the topic destination by a DSN
     *
     * @param ConnectionDriverInterface $connection
     * @param DsnRequest $dsn
     *
     * @return self
     */
    public static function createByDsn(ConnectionDriverInterface $connection, DsnRequest $dsn): self
    {
        return new TopicDestination($connection->topic(), trim($dsn->getPath(), '/'));
    }
}
