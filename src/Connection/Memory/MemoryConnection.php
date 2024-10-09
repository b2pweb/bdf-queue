<?php

namespace Bdf\Queue\Connection\Memory;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConfigurableConnection;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\Serializer;
use Bdf\Queue\Serializer\SerializerInterface;
use SplObjectStorage;

/**
 * MemoryConnection
 */
class MemoryConnection implements ConnectionDriverInterface, ManageableQueueInterface
{
    use ConfigurableConnection;
    use ConnectionNamed;
    use MessageSerializationTrait;

    /**
     * @var Storage
     */
    private $storage;

    /**
     * MemoryConnection constructor.
     *
     * @param string $name
     * @param SerializerInterface|null $serializer
     */
    public function __construct(string $name = 'memory', ?SerializerInterface $serializer = null)
    {
        $this->name = $name;
        $this->storage = new Storage();

        $this->setSerializer($serializer ?: new Serializer());
    }

    /**
     * {@inheritdoc}
     */
    public function queue(): QueueDriverInterface
    {
        return new MemoryQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function topic(): TopicDriverInterface
    {
        return new MemoryTopic($this);
    }

    /**
     * Get all the queues name
     *
     * @return string[]
     */
    public function getQueues()
    {
        return array_keys($this->storage->queues);
    }

    /**
     * {@inheritdoc}
     */
    public function declareQueue(string $queue): void
    {
        if (!isset($this->storage->queues[$queue])) {
            $this->storage->queues[$queue] = new SplObjectStorage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteQueue(string $queue): void
    {
        unset($this->storage->queues[$queue]);
    }

    /**
     * Gets internal storage
     *
     * @return Storage;
     */
    public function storage()
    {
        return $this->storage;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
    }
}
