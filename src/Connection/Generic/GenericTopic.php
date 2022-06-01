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
 * @experimental 1.7
 */
class GenericTopic implements TopicDriverInterface
{
    use ConnectionBearer;
    use Subscriber {
        subscribe as addSubscribe;
    }

    /**
     * Available options:
     *  - "wildcard": the wildcard representation string
     *  - "group_separator": separate the group name and the topic name
     *
     * @var array
     */
    private $options;

    /**
     * GearmanTopic constructor.
     *
     * @param ConnectionDriverInterface $connection
     */
    public function __construct(ConnectionDriverInterface $connection, array $options = [])
    {
        $this->connection = $connection;
        $this->options = $options + [
            'wildcard' => '*',
            'group_separator' => '/',
        ];
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
     *
     * @todo export strategy for queue naming
     */
    public function subscribe(array $topics, callable $callback): void
    {
        $group = $this->connection->config()['group'] ?? '';
        $newTopics = [];

        foreach ($topics as $key => $topic) {
            $topic = str_replace('*', $this->options['wildcard'], $topic);
            $newTopics[$key] = "$group{$this->options['group_separator']}$topic";
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
            if (empty($info['queue']) || strpos($info['queue'], $this->options['group_separator']) === false) {
                continue;
            }

            list(, $queue) = explode($this->options['group_separator'], $info['queue'], 2);

            // Create the regex pattern if the topic has '*' char.
            if ($queue === $topic) {
                yield $info['queue'];
            } elseif (strpos($queue, $this->options['wildcard']) !== false) {
                $regex = '/'.str_replace(['.', $this->options['wildcard'], '/'], ['\.', '.*', '\\/'], $queue).'/';

                if (preg_match($regex, $topic)) {
                    yield $info['queue'];
                }
            }
        }
    }
}
