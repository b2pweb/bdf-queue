<?php

namespace Bdf\Queue\Connection\Redis;

use Redis;

/**
 * PhpRedis
 */
class PhpRedis implements RedisInterface
{
    /**
     * @var Redis
     */
    private $redis;

    /**
     * The version of the redis extension
     *
     * @var string
     */
    private $version;

    /**
     * @var array
     */
    private $config;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
        $this->version = phpversion('redis');
    }

    /**
     *
     */
    private function connect()
    {
        $this->redis = new Redis();
        $this->redis->connect($this->config['host'], $this->config['port'], $this->config['timeout']);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->redis->close();
    }

    /**
     * {@inheritdoc}
     */
    public function sAdd($key, $value)
    {
        return $this->redis->sAdd($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function sRem($key, $member)
    {
        return $this->redis->sRem($key, $member);
    }

    /**
     * {@inheritdoc}
     */
    public function del($key)
    {
        return $this->redis->del($key);
    }

    /**
     * {@inheritdoc}
     */
    public function zAdd($key, $score, $value)
    {
        return $this->redis->zAdd($key, $score, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function rPush($key, $value)
    {
        return $this->redis->rPush($key, $value);
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
        return $this->redis->evaluate($script, array_merge($keys, $args), count($keys));
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
        $this->redis->setOption(Redis::OPT_READ_TIMEOUT, $timeout);
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

        try {
            $this->redis->psubscribe($patterns, function($redis, $filter, $channel, $payload) use($callback, &$count) {
                ++$count;
                $callback($filter, $channel, $payload);
            });
        } catch (\RedisException $exception) {
            // Read timeout or internal error. We reset the connection.
            $this->redis->close();

            // Bugfix: Redis version < 4.0: reset the redis instance to manage the next read timeout
            if (version_compare($this->version, '4.0', '<')) {
                $this->connect();
            }
        }

        return $count;
    }
}