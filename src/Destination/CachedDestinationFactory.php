<?php

namespace Bdf\Queue\Destination;

/**
 * Cache the created destinations
 */
final class CachedDestinationFactory implements DestinationFactoryInterface
{
    /**
     * @var DestinationFactoryInterface
     */
    private $factory;

    /**
     * @var DestinationInterface[]
     */
    private $cache = [];


    /**
     * DsnDestinationFactory constructor.
     *
     * @param DestinationFactoryInterface $factory The real factory
     */
    public function __construct(DestinationFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $name): DestinationInterface
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        return $this->cache[$name] = $this->factory->create($name);
    }
}
