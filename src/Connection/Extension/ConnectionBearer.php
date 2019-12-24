<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\Connection\ConnectionDriverInterface;

/**
 * ConnectionBearer
 */
trait ConnectionBearer
{
    /**
     * The driver connection
     *
     * @var ConnectionDriverInterface
     */
    private $connection;

    /**
     * Get the internal connection
     *
     * @return ConnectionDriverInterface
     */
    public function connection(): ConnectionDriverInterface
    {
        return $this->connection;
    }
}