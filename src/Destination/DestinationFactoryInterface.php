<?php

namespace Bdf\Queue\Destination;

/**
 * Factory for destinations
 */
interface DestinationFactoryInterface
{
    /**
     * Creates the destination by its name
     *
     * @param string $destination The destination string (format depends of the factory). If null, try to resolve a default destination
     *
     * @return DestinationInterface
     *
     * @throws \InvalidArgumentException If the destination cannot be found, or is invalid
     */
    public function create(string $destination): DestinationInterface;
}
