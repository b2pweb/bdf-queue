<?php

namespace Bdf\Queue\Destination;

use Bdf\Dsn\Dsn;
use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Destination\Queue\MultiQueueDestination;
use Bdf\Queue\Destination\Queue\QueueDestination;
use Bdf\Queue\Destination\Topic\TopicDestinationFactory;
use InvalidArgumentException;

use function array_keys;

/**
 * Default destination factory
 *
 * Use the DSN to create the destination. Each scheme is mapped to a factory.
 * A configuration can be used to map a name to a DSN.
 */
final class DestinationFactory implements DestinationFactoryInterface
{
    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $connections;

    /**
     * @var array<string, string>
     */
    private $config;

    /**
     * Allow to create a destination from a DSN without configuration
     *
     * @var bool
     */
    private $allowUndeclared = true;

    /**
     * @var array<string, DestinationInterface>
     */
    private $cache = [];

    /**
     * Destination factories, indexed by the scheme name
     * Takes as parameter the connection driver and the parsed DSN
     *
     * @var array<string, callable(DsnRequest):DestinationInterface>
     */
    private $factories = [];

    /**
     * @param ConnectionDriverFactoryInterface $connections
     * @param string[] $config The configuration. The key is the destination name, the value is the DSN.
     * @param bool $allowUndeclared Allow to create a destination from a DSN without configuration
     */
    public function __construct(ConnectionDriverFactoryInterface $connections, array $config = [], bool $allowUndeclared = true)
    {
        $this->connections = $connections;
        $this->config = $config;
        $this->allowUndeclared = $allowUndeclared;

        $this->registerDefaultFactories();
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $destination): DestinationInterface
    {
        if (!isset($this->config[$destination])) {
            if (!$this->allowUndeclared) {
                throw new InvalidArgumentException('The destination '.$destination.' is not configured');
            }
        } else {
            $destination = $this->config[$destination];
        }

        if (($cached = $this->cache[$destination] ?? null)) {
            return $cached;
        }

        $dsn = Dsn::parse($destination);
        $factory = $this->factories[$dsn->getScheme()] ?? null;

        if (!$factory) {
            throw new InvalidArgumentException('The destination type '.$dsn->getScheme().' is not supported, for destination '.$destination);
        }

        return $this->cache[$destination] = $factory($dsn);
    }

    /**
     * {@inheritdoc}
     */
    public function destinationNames(): array
    {
        return array_keys($this->config);
    }

    /**
     * Register a new destination factory for the given scheme
     *
     * @param string $name The scheme name
     * @param callable(DsnRequest):DestinationInterface $factory The factory. Takes as parameter the parsed DSN.
     *
     * @return void
     */
    public function register(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /**
     * Register a new destination factory following the DSN format : [type]://[connection]/[queue or topic name]?[options]
     *
     * @param string $name The scheme name
     * @param callable(ConnectionDriverInterface, DsnRequest):DestinationInterface $factory Destination factory. Takes as parameter the connection driver and the parsed DSN.
     *
     * @return void
     */
    public function registerSimpleFactory(string $name, callable $factory): void
    {
        $this->register($name, function (DsnRequest $request) use ($factory): DestinationInterface {
            $connection = $this->connections->create($request->getHost());
            return $factory($connection, $request);
        });
    }

    private function registerDefaultFactories(): void
    {
        $this->registerSimpleFactory('queue', [QueueDestination::class, 'createByDsn']);
        $this->registerSimpleFactory('queues', [MultiQueueDestination::class, 'createByDsn']);
        $this->registerSimpleFactory('topic', [TopicDestinationFactory::class, 'createByDsn']);
        $this->registerSimpleFactory('topics', [TopicDestinationFactory::class, 'createMultipleByDsn']);
        $this->register('aggregate', function (DsnRequest $request): DestinationInterface {
            return AggregateDestination::createByDsn($this, $request);
        });
    }
}
