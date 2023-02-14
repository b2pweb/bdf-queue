<?php

namespace Bdf\Queue\Destination;

/**
 * Cache the created destinations
 *
 * @deprecated Since 1.5. Use DestinationFactory instead.
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
    public function create(string $destination): DestinationInterface
    {
        if (isset($this->cache[$destination])) {
            return $this->cache[$destination];
        }

        return $this->cache[$destination] = $this->factory->create($destination);
    }

    /**
     * {@inheritdoc}
     */
    public function destinationNames(): array
    {
        return $this->factory->destinationNames();
    }
}
