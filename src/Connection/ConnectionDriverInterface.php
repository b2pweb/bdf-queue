<?php

namespace Bdf\Queue\Connection;

/**
 * ContextDriverInterface
 */
interface ConnectionDriverInterface
{
    const DURATION = 3;

    /**
     * Sets the driver configuration.
     *
     * @param array $config
     */
    public function setConfig(array $config): void;

    /**
     * Get the current driver configuration
     *
     * @return array
     */
    public function config(): array;

    /**
     * Gets the connection name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Gets the queue driver
     *
     * @return QueueDriverInterface
     */
    public function queue(): QueueDriverInterface;

    /**
     * Gets the topic driver
     *
     * @return TopicDriverInterface
     */
    public function topic(): TopicDriverInterface;

    /**
     * Close the message broker connection.
     */
    public function close(): void;
}
