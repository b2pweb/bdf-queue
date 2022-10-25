<?php

namespace Bdf\Queue\Connection\Redis;

interface RedisInterface
{
    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     timeout: float|null,
     *     path?: string,
     *     scheme?: string,
     *     password?: string,
     *     database?: int,
     *     persistent?: bool,
     * } $config
     */
    public function __construct(array $config);

    /**
     * Close connection
     *
     * @return void
     */
    public function close();

    // Queue management

    /**
     * @param string $key
     * @param string|array $value
     *
     * @return int
     */
    public function sAdd($key, $value);

    /**
     * @param string $key
     * @param string|array $member
     *
     * @return int
     */
    public function sRem($key, $member);

    /**
     * @param string|array $key
     *
     * @return int
     */
    public function del($key);

    // Queueing
    public function zAdd($key, $score, $value);
    public function rPush($key, $value);
    public function blPop(array $keys, $timeout);
    public function evaluate($script, array $keys = [], array $args = []);

    // Stats
    public function lLen($key);
    public function zCard($key);
    public function sMembers($key);

    // Topic
    public function setReadTimeout($timeout);
    public function publish($channel, $message);
    public function psubscribe($patterns, $callback);
}
