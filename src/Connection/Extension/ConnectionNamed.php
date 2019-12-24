<?php

namespace Bdf\Queue\Connection\Extension;

/**
 * ConnectionNamed
 */
trait ConnectionNamed
{
    /**
     * The connection name
     *
     * @var string
     */
    private $name;

    /**
     * Set the connection name. The method is immutable.
     *
     * @param string $name
     */
    public function setName(string $name)
    {
        if (!$this->name) {
            $this->name = $name;
        }
    }

    /**
     * Get the connection name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}