<?php

namespace Bdf\Queue\Connection\Null;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;

/**
 * NullConnection
 */
class NullConnection implements ConnectionDriverInterface
{
    use ConnectionNamed;

    /**
     * NullConnection constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function config(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function queue(): QueueDriverInterface
    {
        return new NullQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function topic(): TopicDriverInterface
    {
        return new NullTopic($this);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
    }
}
