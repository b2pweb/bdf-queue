<?php

namespace Bdf\Queue\Connection\Redis;

use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\PubSub\Consumer as PubSubConsumer;

/**
 * PRedis
 */
class PRedis implements RedisInterface
{
    /**
     * @var Client
     */
    private $redis;

    /**
     * @var array{
     *     host: string,
     *     port: int,
     *     timeout: float|null,
     *     path?: string,
     *     scheme?: string,
     *     password?: string,
     *     database?: int,
     *     persistent?: bool,
     * }
     */
    private $config;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $config)
    {
        $this->config = $config + ['read_write_timeout' => 0];
        $this->connect();
    }

    /**
     *
     */
    private function connect()
    {
        $this->redis = new Client($this->config);
        $this->redis->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->redis->disconnect();
    }

    /**
     * {@inheritdoc}
     */
    public function sAdd($key, $value)
    {
        return $this->redis->sAdd($key, (array)$value);
    }

    /**
     * {@inheritdoc}
     */
    public function sRem($key, $member)
    {
        return $this->redis->sRem($key, (array)$member);
    }

    /**
     * {@inheritdoc}
     */
    public function del($key)
    {
        return $this->redis->del((array)$key);
    }

    /**
     * {@inheritdoc}
     */
    public function zAdd($key, $score, $value)
    {
        return $this->redis->zAdd($key, [$value => $score]);
    }

    /**
     * {@inheritdoc}
     */
    public function rPush($key, $value)
    {
        return $this->redis->rPush($key, (array)$value);
    }

    /**
     * {@inheritdoc}
     */
    public function blPop(array $keys, $timeout)
    {
        return $this->redis->blPop($keys, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate($script, array $keys = [], array $args = [])
    {
        return call_user_func_array([$this->redis, 'eval'], array_merge([$script, count($keys)], $keys, $args));
    }

    /**
     * {@inheritdoc}
     */
    public function lLen($key)
    {
        return $this->redis->lLen($key);
    }

    /**
     * {@inheritdoc}
     */
    public function zCard($key)
    {
        return $this->redis->zCard($key);
    }

    /**
     * {@inheritdoc}
     */
    public function sMembers($key)
    {
        return $this->redis->sMembers($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setReadTimeout($timeout)
    {
        if ($this->config['read_write_timeout'] !== $timeout) {
            $this->config['read_write_timeout'] = $timeout;
            $this->connect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publish($channel, $message)
    {
        return $this->redis->publish($channel, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function psubscribe($patterns, $callback)
    {
        $count = 0;

        $pubsub = $this->redis->pubSubLoop();
        $pubsub->psubscribe($patterns);

        try {
            /** @var object $message */
            foreach ($pubsub as $message) {
                ++$count;

                if ($message->kind === PubSubConsumer::PMESSAGE) {
                    $callback($message->pattern, $message->channel, $message->payload);
                }
            }
        } catch (ConnectionException $exception) {
            // Timeout
        }

        return $count;
    }
}
