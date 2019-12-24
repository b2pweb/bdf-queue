<?php

namespace Bdf\Queue\Destination;

/**
 * Factory for destination using configuration
 */
final class ConfigurationDestinationFactory implements DestinationFactoryInterface
{
    /**
     * @var string[]
     */
    private $config;

    /**
     * @var DestinationFactoryInterface
     */
    private $factory;


    /**
     * DsnDestinationFactory constructor.
     *
     * @param string[] $config DSN configurations, with destination name as key
     * @param DestinationFactoryInterface $factory The inner factory. If not given, will use DSN factory
     */
    public function __construct(array $config, DestinationFactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $name): DestinationInterface
    {
        if (!isset($this->config[$name])) {
            throw new \InvalidArgumentException('The destination '.$name.' is not configured');
        }

        return $this->factory->create($this->config[$name]);
    }
}
