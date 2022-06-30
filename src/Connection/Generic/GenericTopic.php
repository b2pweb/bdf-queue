<?php

namespace Bdf\Queue\Connection\Generic;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\Subscriber;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Consumer\Reader\MultiQueueReader;
use Bdf\Queue\Message\Message;

/**
 * GenericTopic
 *
 * This architecture creates a pub/sub pattern for connections that do not support it.
 * The generic topic will create destination queue with topic name and group as "group/topic"
 *
 * @experimental 1.3
 */
class GenericTopic implements TopicDriverInterface
{
    use ConnectionBearer;
    use Subscriber {
        subscribe as addSubscribe;
    }

    /**
     * @var QueueNamingStrategyInterface
     */
    private $namingStrategy;

    /**
     * @param ConnectionDriverInterface $connection
     * @param array{wildcard?: string, group_separator?: string} $options
     */
    public function __construct(ConnectionDriverInterface $connection, array $options = [])
    {
        $this->connection = $connection;
        $this->namingStrategy = new RegexQueueNamingStrategy(
            $options['wildcard'] ?? RegexQueueNamingStrategy::WILDCARD,
            $options['group_separator'] ?? '/',
            $connection->config()['group'] ?? RegexQueueNamingStrategy::DEFAULT_GROUP
        );
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message): void
    {
        $driver = $this->connection->queue();

        foreach ($this->getRoutes($driver->stats()['queues'] ?? [], $message->topic()) as $queue) {
            $clone = clone $message;
            $clone->setQueue($queue);

            $driver->push($clone);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $topic, $payload): void
    {
        $driver = $this->connection->queue();

        foreach ($this->getRoutes($driver->stats()['queues'] ?? [], $topic) as $queue) {
            $driver->pushRaw($payload, $queue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int
    {
        $reader = new MultiQueueReader($this->connection->queue(), $this->getSubscribedTopics());
        $envelope = $reader->read($duration);

        if ($envelope === null) {
            return 0;
        }

        foreach ($this->getSubscribers($envelope->message()->queue()) as $callback) {
            $callback($envelope);
        }

        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(array $topics, callable $callback): void
    {
        $newTopics = [];

        foreach ($topics as $key => $topic) {
            $newTopics[$key] = $this->namingStrategy->topicNameToQueue($topic);
        }

        $this->addSubscribe($newTopics, $callback);
    }

    /**
     * Find topic in defined queues
     *
     * @param array $queuesInfo
     * @param string $topic
     *
     * @return iterable
     */
    private function getRoutes(array $queuesInfo, string $topic): iterable
    {
        foreach ($queuesInfo as $info) {
            if (!empty($info['queue']) && $this->namingStrategy->queueMatchWithTopic($info['queue'], $topic)) {
                yield $info['queue'];
            }
        }
    }
}
