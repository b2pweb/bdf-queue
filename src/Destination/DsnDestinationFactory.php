<?php

namespace Bdf\Queue\Destination;

use Bdf\Dsn\Dsn;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Destination\Queue\MultiQueueDestination;
use Bdf\Queue\Destination\Queue\QueueDestination;
use Bdf\Queue\Destination\Topic\MultiTopicDestination;
use Bdf\Queue\Destination\Topic\TopicDestination;

/**
 * Factory for destination using DSN
 *
 * DSN format : [type]://[connection]/[queue or topic name]?[options]
 *
 * Ex: queue://my-connection/my-queue?prefetch=10
 */
final class DsnDestinationFactory implements DestinationFactoryInterface
{
    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $factory;

    /**
     * Destination factories, indexed by the scheme name
     * Takes as parameter the connection driver and the parsed DSN
     *
     * @var callable[]
     */
    private $factories = [];


    /**
     * DsnDestinationFactory constructor.
     *
     * @param ConnectionDriverFactoryInterface $factory The connection driver factory
     */
    public function __construct(ConnectionDriverFactoryInterface $factory)
    {
        $this->factory = $factory;

        $this->factories = $this->defaultFactories();
    }

    /**
     * Register a new destination factory for the given scheme
     *
     * @param string $name The scheme name
     * @param callable $factory The factory. Takes as parameter the connection driver and the parsed DSN
     */
    public function register(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $destination): DestinationInterface
    {
        $dsn = Dsn::parse($destination);

        $connection = $this->factory->create($dsn->getHost());

        if (!isset($this->factories[$dsn->getScheme()])) {
            throw new \InvalidArgumentException('The destination type '.$dsn->getScheme().' is not supported, for destination '.$destination);
        }

        return $this->factories[$dsn->getScheme()]($connection, $dsn);
    }

    /**
     * @return callable[]
     */
    private function defaultFactories()
    {
        return [
            'queue' => [QueueDestination::class, 'createByDsn'],
            'queues' => [MultiQueueDestination::class, 'createByDsn'],
            'topic' => [TopicDestination::class, 'createByDsn'],
            'topics' => [MultiTopicDestination::class, 'createByDsn'],
        ];
    }
}
