<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

/**
 * ReceiverLoaderInterface
 */
interface ReceiverLoaderInterface
{
    /**
     * Load the receiver build from the configuration
     *
     * @param string $name The destination name
     *
     * @return ReceiverBuilder
     */
    public function load(string $name): ReceiverBuilder;
}
