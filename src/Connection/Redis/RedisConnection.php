<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\SerializerInterface;

/**
 * RedisConnection
 */
class RedisConnection implements ConnectionDriverInterface, ManageableQueueInterface
{
    use ConnectionNamed;
    use MessageSerializationTrait;

    public const PREFIX = 'queues:';
    public const QUEUE_KEY = 'queues';

    /**
     * The Redis instance.
     *
     * @var RedisInterface
     */
    private $redis;

    /**
     * The config
     *
     * @var array
     */
    private $config;

    /**
     * RedisConnection constructor.
     *
     * @param string $name
     * @param SerializerInterface $serializer
     */
    public function __construct(string $name, SerializerInterface $serializer)
    {
        $this->name = $name;
        $this->setSerializer($serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
        $this->config = $config + [
            'host'      => '127.0.0.1',
            'port'      => 6379,
            'timeout'   => 0,
            'prefix'    => self::PREFIX,

            /*
             * Redis needs queue declaration.
             * This option allows auto declaration when pushing and poping
             */
            'auto_declare' => false,

            // Driver to use
            'vendor' => 'phpredis',
        ];
    }

    /**
     * Gets the global config
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Connect to redis from config
     */
    public function connect(): RedisInterface
    {
        if ($this->redis === null) {
            $driverClass = $this->config['vendor'] === 'phpredis'
                ? PhpRedis::class
                : PRedis::class;

            $this->redis = new $driverClass($this->config);
        }

        return $this->redis;
    }

    /**
     * Set the redis client
     *
     * @param RedisInterface $redis
     */
    public function setRedis(RedisInterface $redis)
    {
        $this->redis = $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->redis !== null) {
            $this->redis->close();
            $this->redis = null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return RedisQueue
     */
    public function queue(): QueueDriverInterface
    {
        return new RedisQueue($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return RedisTopic
     */
    public function topic(): TopicDriverInterface
    {
        return new RedisTopic($this);
    }

    /**
     * {@inheritdoc}
     */
    public function declareQueue(string $queue): void
    {
        $this->connect();

        $this->redis->sAdd(self::QUEUE_KEY, $queue);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteQueue(string $queue): void
    {
        $this->connect();

        $this->redis->sRem(self::QUEUE_KEY, $queue);
        $this->redis->del($this->config['prefix'].$queue);
        $this->redis->del($this->config['prefix'].$queue.':delayed');
    }

    /**
     * Should the queue be auto declared
     *
     * @return bool
     */
    public function shouldAutoDeclare(): bool
    {
        return $this->config['auto_declare'];
    }

    /**
     * Gets the queue prefix
     *
     * @return string
     */
    public function queuePrefix(): string
    {
        return $this->config['prefix'];
    }
}
