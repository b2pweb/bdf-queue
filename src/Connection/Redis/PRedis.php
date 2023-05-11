<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\PubSub\Consumer as PubSubConsumer;
use Predis\Response\ServerException as BaseServerException;

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
     *     read_write_timeout: float,
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
        try {
            $this->redis = new Client($this->config);
            $this->redis->connect();
        } catch (ConnectionException $e) {
            throw new ConnectionFailedException($e->getMessage(), $e->getCode(), $e);
        }
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
        try {
            return $this->redis->sAdd($key, (array)$value);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sRem($key, $member)
    {
        try {
            return $this->redis->sRem($key, (array)$member);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function del($key)
    {
        try {
            return $this->redis->del((array)$key);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function zAdd($key, $score, $value)
    {
        try {
            return $this->redis->zAdd($key, [$value => $score]);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rPush($key, $value)
    {
        try {
            return $this->redis->rPush($key, (array)$value);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function blPop(array $keys, $timeout)
    {
        try {
            return $this->redis->blPop($keys, $timeout);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate($script, array $keys = [], array $args = [])
    {
        try {
            return call_user_func_array([$this->redis, 'eval'], array_merge([$script, count($keys)], $keys, $args));
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lLen($key)
    {
        try {
            return $this->redis->lLen($key);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function zCard($key)
    {
        try {
            return $this->redis->zCard($key);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sMembers($key)
    {
        try {
            return $this->redis->sMembers($key);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
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
        try {
            return $this->redis->publish($channel, $message);
        } catch (ConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
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
