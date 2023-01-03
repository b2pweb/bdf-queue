<?php

namespace Bdf\Queue\Tests;

use Bdf\Queue\Connection\AmqpLib\AmqpLibConnection;
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
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverFactory;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoaderInterface;
use Bdf\Queue\Destination\CachedDestinationFactory;
use Bdf\Queue\Destination\ConfigurationDestinationFactory;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Destination\DestinationFactoryInterface;
use Bdf\Queue\Destination\DsnDestinationFactory;
use Bdf\Queue\Serializer\BdfSerializer;
use Bdf\Queue\Serializer\Serializer;
use Bdf\Queue\Serializer\SerializerInterface;
use Bdf\Serializer\Context\NormalizationContext;
use Bdf\Serializer\SerializerInterface as BdfSerializerInterface;
use Enqueue\ConnectionFactoryFactory;
use League\Container\Container;

/**
 * <pre>
 * External configuration:
 *   queue.connections = (array) Array of connections to queue servers
 *   queue.destination = (array) Array of destination to queue servers
 *   queue.serializer.name = (string) serializer name: default|bdf.
 *   queue.consumer.config = (string) PHP file to configure the consumers with receiver builder.
 * </pre>
 */
class QueueServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function configure(Container $container)
    {
        $this->configureConnectionDrivers($container);
        $this->configureDestinations($container);

        $container->share('queue.serializer', function() use($container) {
            $driver =  $container->has('queue.serializer.name')
                ? $container->get('queue.serializer.name')
                : 'bdf';

            return $container->get('queue.serializer.'.$driver);
        });

        $container->share('queue.serializer.native', function() {
            return new Serializer();
        });

        $container->share('queue.serializer.bdf', function() use($container) {
            return new BdfSerializer(
                $container->has(BdfSerializerInterface::class) ? $container->get(BdfSerializerInterface::class) : null
            );
        });

        $container->share('queue.serializer.bdf_json', function() use($container) {
            return new BdfSerializer(
                $container->has(BdfSerializerInterface::class) ? $container->get(BdfSerializerInterface::class) : null,
                'json',
                [NormalizationContext::META_TYPE => false]
            );
        });

        $container->share(ReceiverFactory::class, function() use($container) {
            return new ReceiverFactory($container);
        });

        $container->share(ReceiverLoaderInterface::class, function() use($container) {
            return new ReceiverLoader(
                $container,
                $container->has('queue.consumer.config') ? require $container->get('queue.consumer.config') : [],
                $container->get(ReceiverFactory::class)
            );
        });

        $container->share(ReceiverLoader::class, function() use($container) {
            return $container->get(ReceiverLoaderInterface::class);
        });
    }

    /**
     * Configure the connection drivers and factories (low level API)
     *
     * @param Container $container
     */
    private function configureConnectionDrivers(Container $container)
    {
        $container->share(ResolverConnectionDriverFactory::class, function() use($container) {
            $connections = $container->has('queue.connections') ? $container->get('queue.connections') : [];

            $factory = new ResolverConnectionDriverFactory($connections);
            $factory->setDriverResolver($container->get('queue.connection.drivers-resolver'));

            return $factory;
        });

        $container->share(ConnectionDriverFactoryInterface::class, function() use($container) {
            return new CachedConnectionDriverFactory($container->get(ResolverConnectionDriverFactory::class));
        });

        $container->share('queue.connection.drivers-resolver', function() use($container) {
            return [
                'null' => function($config) {
                    return new NullConnection($config['connection']);
                },
                'memory' => function($config) use($container) {
                    return new MemoryConnection($config['connection'], $this->getSerializer($container, $config['serializer'] ?? null));
                },
                'gearman' => function($config) use($container) {
                    return new GearmanConnection($config['connection'], $this->getSerializer($container, $config['serializer'] ?? null));
                },
                'amqp-lib' => function($config) use($container) {
                    return new AmqpLibConnection(
                        $config['connection'],
                        $this->getSerializer($container, $config['serializer'] ?? null),
                        isset($config['exchange_resolver']) ? $container->get($config['exchange_resolver']) : null
                    );
                },
                'pheanstalk' => function($config) use($container) {
                    return new PheanstalkConnection($config['connection'], $this->getSerializer($container, $config['serializer'] ?? null));
                },
                'doctrine' => function($config) use($container) {
                    return new DoctrineConnection(
                        $config['connection'],
                        $this->getSerializer($container, $config['serializer'] ?? null)
                    );
                },
                'rdkafka' => function($config) use($container) {
                    return new RdKafkaConnection($config['connection'], $this->getSerializer($container, $config['serializer'] ?? null));
                },
                'redis' => function($config) use($container) {
                    return new RedisConnection($config['connection'], $this->getSerializer($container, $config['serializer'] ?? null));
                },
                'enqueue' => function ($config) use($container) {
                    return new EnqueueConnection(
                        $config['connection'],
                        $this->getSerializer($container, $config['serializer'] ?? null),
                        $container->get(ConnectionFactoryFactory::class)
                    );
                }
            ];
        });

        $container->share(ConnectionFactoryFactory::class, function() {
            return new ConnectionFactoryFactory();
        });
    }

    /**
     * Configure the destinations handling (high level API)
     *
     * @param Container $container
     */
    private function configureDestinations(Container $container)
    {
        $container->share(DsnDestinationFactory::class, function() use($container) {
            return new DsnDestinationFactory(
                $container->get(ConnectionDriverFactoryInterface::class)
            );
        });

        $container->share(ConfigurationDestinationFactory::class, function() use($container) {
            return new ConfigurationDestinationFactory(
                $container->has('queue.destinations') ? $container->get('queue.destinations') : [],
                $container->get(DsnDestinationFactory::class)
            );
        });

        $container->share(DestinationFactoryInterface::class, function() use($container) {
            return new CachedDestinationFactory(
                $container->get(ConfigurationDestinationFactory::class)
            );
        });

        $container->share(DestinationManager::class, function() use($container) {
            return new DestinationManager(
                $container->get(ConnectionDriverFactoryInterface::class),
                $container->get(DestinationFactoryInterface::class)
            );
        });
    }

    /**
     * @param Container $container
     * @param string $name
     * @param array $config
     *
     * @return SerializerInterface
     */
    private function getSerializer($container, $name, array $config = [])
    {
        // No name: let s the global config choose the serializer.
        if (!$name) {
            return $container->get('queue.serializer');
        }

        // Connection overload the serializer
        return $container->get("queue.serializer.{$name}");
    }
}
