<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\TopicEnvelopeHelper;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\Message;

/**
 * RedisTopic
 *
 * This architecture allows:
 *  - pub/sub pattern
 *
 * This architecture does not allow:
 *  - group of workers
 *  - the configuration of the message persitence by group
 */
class RedisTopic implements TopicDriverInterface
{
    use ConnectionBearer;
    use TopicEnvelopeHelper;

    /**
     * The Redis connection.
     *
     * @var RedisConnection
     */
    private $connection;

    /**
     * @var array
     */
    private $subscribers = [];

    /**
     * PhpRedisTopic constructor.
     *
     * @param RedisConnection $connection
     */
    public function __construct(RedisConnection $connection)
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
        $redis = $this->connection->connect();

        try {
            $redis->publish($topic, $payload);
        } catch (\RedisException $e) {
            // Auto-reconnect on timeout
            // May be useless after redis extension update ?
            $this->connection->close();
            $redis = $this->connection->connect();

            $redis->publish($topic, $payload);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(array $topics, callable $callback): void
    {
        foreach ($topics as $pattern) {
            $this->subscribers[$pattern][] = $callback;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int
    {
        $redis = $this->connection->connect();
        $redis->setReadTimeout($duration);

        return $redis->psubscribe(array_keys($this->subscribers), [$this, 'callSubscriber']);
    }

    /**
     * Call the subscriber of this channel
     *
     * @param string $filter
     * @param string $topic
     * @param mixed $payload
     *
     * @internal
     */
    public function callSubscriber($filter, $topic, $payload)
    {
        // Get the subscriber from the filter because the broadcaster subscribe a callback on pattern
        if (!isset($this->subscribers[$filter])) {
            return;
        }

        $envelope = $this->toTopicEnvelope($this->connection->toQueuedMessage($payload, $topic));

        foreach ($this->subscribers[$filter] as $callback) {
            $callback($envelope);
        }
    }
}
