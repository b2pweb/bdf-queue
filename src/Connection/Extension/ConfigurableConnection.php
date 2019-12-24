<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\Connection\ConnectionDriverInterface;

/**
 * Implements configuration on connection drivers
 *
 * @see ConnectionDriverInterface::config()
 * @see ConnectionDriverInterface::setConfig()
 */
trait ConfigurableConnection
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @see ConnectionDriverInterface::setConfig()
     */
    public function setConfig(array $config): void
    {
        $this->config = $config + $this->defaultConfiguration();
    }

    /**
     * @see ConnectionDriverInterface::config()
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Get the default configuration parameters
     *
     * @return array
     */
    protected function defaultConfiguration(): array
    {
        return [];
    }
}
