<?php

namespace Bdf\Queue\Connection\Factory;

use Bdf\Queue\Connection\ConnectionDriverInterface;

/**
 * Store the new created connection instances for reuse it
 */
final class CachedConnectionDriverFactory implements ConnectionDriverFactoryInterface
{
    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $factory;

    /**
     * @var ConnectionDriverInterface[]
     */
    private $cached = [];


    /**
     * CachedConnectionDriverFactory constructor.
     *
     * @param ConnectionDriverFactoryInterface $factory
     */
    public function __construct(ConnectionDriverFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function create(?string $name): ConnectionDriverInterface
    {
        if (!$name) {
            $name = $this->defaultConnectionName();
        }

        if (isset($this->cached[$name])) {
            return $this->cached[$name];
        }

        return $this->cached[$name] = $this->factory->create($name);
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConnectionName(): string
    {
        return $this->factory->defaultConnectionName();
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConnection(): ConnectionDriverInterface
    {
        return $this->create($this->defaultConnectionName());
    }
}
