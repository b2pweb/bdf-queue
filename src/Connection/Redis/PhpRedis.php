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

        $host = isset($this->config['scheme']) && $this->config['scheme'] === 'unix' ? $this->config['path'] : $this->config['host'];
        $port = $this->config['port'] ?? 6379;
        $timeout = $this->config['timeout'] ?? 0.0;

        if (empty($this->config['persistent'])) {
            $this->redis->connect($host, $port, $timeout);
        } else {
            $this->redis->pconnect($host, $port, $timeout);
        }

        if (!empty($this->config['password'])) {
            $this->redis->auth($this->config['password']);
        }

        if (isset($this->config['database'])) {
            $this->redis->select($this->config['database']);
        }
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
        return (int) $this->redis->sAdd($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function sRem($key, $member)
    {
        return (int) $this->redis->sRem($key, $member);
    }

    /**
     * {@inheritdoc}
     */
    public function del($key)
    {
        return (int) $this->redis->del($key);
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
            /** @psalm-suppress InvalidArgument */
            $this->redis->psubscribe($patterns, function ($redis, $filter, $channel, $payload) use ($callback, &$count) {
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
