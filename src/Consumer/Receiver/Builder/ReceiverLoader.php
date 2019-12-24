<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Psr\Container\ContainerInterface;

/**
 * Loader for receiver configuration
 */
class ReceiverLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var callable[]
     */
    private $configuration;


    /**
     * ReceiverLoader constructor.
     *
     * @param ContainerInterface $container
     * @param callable[] $configuration
     */
    public function __construct(ContainerInterface $container, array $configuration)
    {
        $this->container = $container;
        $this->configuration = $configuration;
    }

    /**
     * Load the receiver build from the configuration
     *
     * @todo Remove nillable on $name on 1.7
     *
     * @param string $name The destination name
     *
     * @return ReceiverBuilder
     */
    public function load(?string $name): ReceiverBuilder
    {
        $builder = new ReceiverBuilder($this->container);

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
