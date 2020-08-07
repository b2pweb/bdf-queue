<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Psr\Container\ContainerInterface;

/**
 * Loader for receiver configuration
 */
class ReceiverLoader implements ReceiverLoaderInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ReceiverFactory
     */
    private $factory;

    /**
     * @var callable[]
     */
    private $configuration;


    /**
     * ReceiverLoader constructor.
     *
     * @param ContainerInterface $container
     * @param callable[] $configuration
     * @param ReceiverFactory|null $factory
     */
    public function __construct(ContainerInterface $container, array $configuration, ReceiverFactory $factory = null)
    {
        $this->container = $container;
        $this->configuration = $configuration;
        $this->factory = $factory;
    }

    /**
     * Load the receiver build from the configuration
     *
     * @param string $name The destination name
     *
     * @return ReceiverBuilder
     */
    public function load(string $name): ReceiverBuilder
    {
        $builder = new ReceiverBuilder($this->container, null, $this->factory);

        if (!isset($this->configuration[$name])) {
            return $builder;
        }

        $this->configuration[$name]($builder);

        return $builder;
    }

    /**
     * Register a destination configuration
     *
     * @param string $destination The destination name
     * @param callable $configuration The configurator. Takes the build as parameter
     */
    public function register(string $destination, callable $configuration): void
    {
        $this->configuration[$destination] = $configuration;
    }
}
