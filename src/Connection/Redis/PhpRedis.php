<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Redis;
use RedisException;

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
        try {
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
        } catch (RedisException $e) {
            throw new ConnectionFailedException($e->getMessage(), $e->getCode(), $e);
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
        try {
            /** @psalm-suppress InvalidCast */
            return (int) $this->checkError($this->redis->sAdd($key, $value));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sRem($key, $member)
    {
        try {
            /** @psalm-suppress InvalidCast */
            return (int) $this->checkError($this->redis->sRem($key, $member));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function del($key)
    {
        try {
            /** @psalm-suppress InvalidCast */
            return (int) $this->checkError($this->redis->del($key));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function zAdd($key, $score, $value)
    {
        try {
            return $this->checkError($this->redis->zAdd($key, $score, $value));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rPush($key, $value)
    {
        try {
            return $this->checkError($this->redis->rPush($key, $value));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function blPop(array $keys, $timeout)
    {
        try {
            return $this->checkError($this->redis->blPop($keys, $timeout));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate($script, array $keys = [], array $args = [])
    {
        try {
            return $this->checkError($this->redis->evaluate($script, array_merge($keys, $args), count($keys)));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lLen($key)
    {
        try {
            return $this->checkError($this->redis->lLen($key));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function zCard($key)
    {
        try {
            return $this->checkError($this->redis->zCard($key));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sMembers($key)
    {
        try {
            return $this->checkError($this->redis->sMembers($key));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
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
        try {
            return $this->checkError($this->redis->publish($channel, $message));
        } catch (RedisException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        }
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
        } catch (RedisException $exception) {
            // Read timeout or internal error. We reset the connection.
            $this->redis->close();

            // Bugfix: Redis version < 4.0: reset the redis instance to manage the next read timeout
            if (version_compare($this->version, '4.0', '<')) {
                $this->connect();
            }
        }

        return $count;
    }

    /**
     * Check the result of a Redis command, and throw an exception if it failed.
     *
     * @param T $result
     * @return T
     *
     * @template T
     * @throws ServerException
     */
    private function checkError($result)
    {
        if ($result === false) {
            $error = $this->redis->getLastError();
            $this->redis->clearLastError();

            throw new ServerException($error);
        }

        return $result;
    }
}
