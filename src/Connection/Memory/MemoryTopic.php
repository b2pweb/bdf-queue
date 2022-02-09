<?php

namespace Bdf\Queue\Connection\Memory;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\Subscriber;
use Bdf\Queue\Connection\Extension\TopicEnvelopeHelper;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\Message;

/**
 * MemoryTopic
 */
class MemoryTopic implements TopicDriverInterface
{
    use ConnectionBearer;
    use TopicEnvelopeHelper;
    use Subscriber {
        subscribe as addSubscriber;
    }

    /**
     * MemoryTopic constructor.
     *
     * @param MemoryConnection $connection
     */
    public function __construct(MemoryConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        $this->publishRaw($message->topic(), $this->connection->serializer()->serialize($message));
    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $topic, $payload): void
    {
        foreach (array_keys($this->connection->storage()->awaitings) as $consumerId) {
            $this->connection->storage()->awaitings[$consumerId][] = [
                'topic' => $topic,
                'payload' => $payload
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int
    {
        $count = 0;
        $consumerId = spl_object_hash($this);
        $awaiting = $this->connection->storage()->awaitings[$consumerId] ?? [];
        $this->connection->storage()->awaitings[$consumerId] = [];

        foreach ($awaiting as $metadata) {
            $callbacks = $this->getSubscribers($metadata['topic']);

            foreach ($callbacks as $callback) {
                $envelope = $this->toTopicEnvelope($this->connection->toQueuedMessage($metadata['payload'], $metadata['topic']));
                $callback($envelope);
            }

            if ($callbacks) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(array $topics, callable $callback): void
    {
        $consumerId = spl_object_hash($this);

        if (!isset($this->connection->storage()->awaitings[$consumerId])) {
            $this->connection->storage()->awaitings[$consumerId] = [];
        }

        $this->addSubscriber($topics, $callback);
    }

    /**
     * Get number of awaiting event
     *
     * @return int
     */
    public function awaiting()
    {
        return count($this->connection->storage()->awaitings[spl_object_hash($this)] ?? []);
    }

    /**
     * Remove the subscriber queue on destruct
     */
    public function __destruct()
    {
        unset($this->connection->storage()->awaitings[spl_object_hash($this)]);
    }
}
