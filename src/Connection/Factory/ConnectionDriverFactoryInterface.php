<?php

namespace Bdf\Queue\Connection\Factory;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use InvalidArgumentException;

/**
 * Factory for connection driver
 */
interface ConnectionDriverFactoryInterface
{
    /**
     * Create a queue connection driver
     *
     * @param string $name The connection name. Null for get the default connection
     *
     * @return ConnectionDriverInterface
     *
     * @throws InvalidArgumentException  If connection could not be created
     */
    public function create(?string $name): ConnectionDriverInterface;

    /**
     * Get the default connection name
     * This connection is used by calling create() without name, or defaultConnection()
     *
     * @return string
     *
     * @deprecated Do not use : only for backward compatibility purpose
     */
    public function defaultConnectionName(): string;

    /**
     * Get the default connection
     *
     * @return ConnectionDriverInterface
     *
     * @throws \LogicException If no default connection is configured
     *
     * @deprecated Do not use : only for backward compatibility purpose
     */
    public function defaultConnection(): ConnectionDriverInterface;

    /**
     * List all available connection names
     *
     * @return string[]
     */
    public function connectionNames(): array;
}
