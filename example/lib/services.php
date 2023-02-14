<?php

use Bdf\Queue\Connection\AmqpLib\AmqpLibConnection;
use Bdf\Queue\Connection\AmqpLib\Exchange\NamespaceExchangeResolver;
use Bdf\Queue\Connection\Doctrine\DoctrineConnection;
use Bdf\Queue\Connection\Enqueue\EnqueueConnection;
use Bdf\Queue\Connection\Factory\CachedConnectionDriverFactory;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Connection\Gearman\GearmanConnection;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Connection\Pheanstalk\PheanstalkConnection;
use Bdf\Queue\Connection\RdKafka\RdKafkaConnection;
use Bdf\Queue\Connection\Redis\RedisConnection;
use Bdf\Queue\Destination\DestinationFactory;
use Bdf\Queue\Destination\DestinationFactoryInterface;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Failer\FailedJobRepositoryInterface;
use Bdf\Queue\Serializer\BdfSerializer;
use Bdf\Queue\Serializer\SerializerInterface;
use Bdf\Serializer\SerializerBuilder;
use Enqueue\ConnectionFactoryFactory;

/**
 * Create the destination from its name
 *
 * @param string $destination  The destination name OR the connection name
 * @param string $queue        The queue name if $destination is a connection name
 * @param string $topic        The topic name if $destination is a connection name
 *
 * @return DestinationInterface
 */
function createDestination(string $destination, string $queue = null, string $topic = null): DestinationInterface
{
    $destinationManager = getDestinationManager();

    if ($queue !== null) {
        return $destinationManager->queue($destination, strpos($queue, ',') === false ? $queue : explode(',', $queue));
    }

    if ($topic !== null) {
        return $destinationManager->topic($destination, $topic);
    }

    return $destinationManager->guess($destination);
}

//--- Configuration of destinations

function getDestinationManager(): DestinationManager
{
    static $manager;

    if ($manager === null) {
        $manager = new DestinationManager(getConnectionsDriverFactory(), getDestinationFactory());
    }

    return $manager;
}

function getDestinationFactory(): DestinationFactoryInterface
{
    return new DestinationFactory(
        getConnectionsDriverFactory(),
        require __DIR__.'/../config/destinations.php'
    );
}

//--- Configuration of connections

function getConnectionsDriverFactory(): ConnectionDriverFactoryInterface
{
    $factory = new ResolverConnectionDriverFactory(require __DIR__.'/../config/connections.php');
    $factory->setDriverResolver(getDriverResolvers());

    return new CachedConnectionDriverFactory($factory);
}

function getDriverResolvers(): array
{
    return [
        'null' => function($config) {
            return new NullConnection($config['connection']);
        },
        'memory' => function($config) {
            return new MemoryConnection($config['connection'], getSerializer());
        },
        'doctrine' => function ($config) {
            return new DoctrineConnection(
                $config['connection'],
                getSerializer()
            );
        },
        'gearman' => function($config) {
            return new GearmanConnection($config['connection'], getSerializer());
        },
        'amqp-lib' => function($config) {
            return new AmqpLibConnection(
                $config['connection'],
                getSerializer(),
                new NamespaceExchangeResolver()
            );
        },
        'pheanstalk' => function($config) {
            return new PheanstalkConnection($config['connection'], getSerializer());
        },
        'rdkafka' => function($config) {
            return new RdKafkaConnection($config['connection'], getSerializer());
        },
        'redis' => function($config) {
            return new RedisConnection($config['connection'], getSerializer());
        },
        'enqueue' => function ($config) {
            return new EnqueueConnection(
                $config['connection'],
                getSerializer(),
                new ConnectionFactoryFactory()
            );
        },
    ];
}

function getSerializer(): SerializerInterface
{
    return new BdfSerializer(SerializerBuilder::create()->build());
}

//--- The failer storage

function getFailerStorage(): FailedJobRepositoryInterface
{
    return require __DIR__.'/../config/failer.php';
}
