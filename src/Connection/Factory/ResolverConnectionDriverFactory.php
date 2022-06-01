<?php

namespace Bdf\Queue\Connection\Factory;

use Bdf\Dsn\Dsn;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use InvalidArgumentException;

/**
 * Factory for connection driver using driver resolver and a DSN as connection configuration
 */
final class ResolverConnectionDriverFactory implements ConnectionDriverFactoryInterface
{
    /**
     * The array of queue driver resolvers {@see ConnectionDriverInterface}
     *
     * @var callable[]
     */
    private $driverResolvers = [];

    /**
     * The array of connection configurations
     *
     * @var array
     */
    private $configs = [];

    /**
     * @var string
     */
    private $defaultConnection;


    /**
     * Create a new queue manager instance.
     *
     * @param string[] $configs The connections configurations. The key is the connection name, and the value is the configuration DSN
     * @param string|null $defaultConnection The default connection name, or null to use the first configured connection as default
     */
    public function __construct(array $configs = [], ?string $defaultConnection = null)
    {
        $this->configs = $configs;
        $this->setDefaultConnection($defaultConnection);
    }

    /**
     * {@inheritdoc}
     */
    public function create(?string $name): ConnectionDriverInterface
    {
        $config = $this->getConfig($name);

        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('No queue driver has been set for '.$name);
        }

        $driver = $this->getDriver($config['driver'], $config);
        $driver->setConfig($config);

        return $driver;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConnectionName(): string
    {
        @trigger_error('Default connection is deprecated', E_USER_DEPRECATED);

        if (!$this->defaultConnection) {
            throw new \LogicException('No default connection is configured');
        }

        return $this->defaultConnection;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConnection(): ConnectionDriverInterface
    {
        return $this->create($this->defaultConnectionName());
    }

    /**
     * {@inheritdoc}
     */
    public function connectionNames(): array
    {
        return array_keys($this->configs);
    }

    /**
     * Get the config connection
     *
     * @param string $name
     *
     * @return array
     *
     * @throws InvalidArgumentException  If config is not found
     */
    public function getConfig($name)
    {
        if (!$name) {
            $name = $this->defaultConnectionName();
        }

        if (!isset($this->configs[$name])) {
            throw new InvalidArgumentException('No queue config found for '.$name);
        }

        $config = $this->configs[$name];

        //TODO ne pas reevaluer la config

        if (is_string($config)) {
            $config = static::parseDsn($config);
        }

        if (empty($config['queue'])) {
            $config['queue'] = $name;
        }

        $config['connection'] = $name;

        return $config;
    }

    /**
     * Add a queue driver resolver.
     *
     * <code>
     * $factory->addDriverResolver('redis', function (array $config) {
     *     return new RedisConnection();
     * });
     * </code>
     *
     * @param string $driver The driver name. It's used as scheme of the DSN
     * @param callable $resolver The connection resolver. Takes the configuration as parameter, and returns the connection driver instance
     */
    public function addDriverResolver(string $driver, callable $resolver)
    {
        $this->driverResolvers[$driver] = $resolver;
    }

    /**
     * Set all queue driver resolvers
     *
     * @param callable[] $factory
     */
    public function setDriverResolver(array $factory)
    {
        $this->driverResolvers = $factory;
    }

    /**
     * Get an instance of driver
     *
     * @param string $name
     * @param array $config
     *
     * @return ConnectionDriverInterface
     *
     * @throws InvalidArgumentException If driver is not known
     */
    private function getDriver($name, array $config)
    {
        if (!isset($this->driverResolvers[$name])) {
            throw new InvalidArgumentException('No queue connector has been set for '.$name);
        }

        return $this->driverResolvers[$name]($config);
    }

    /**
     * Get queue configuration from dsn format
     *
     * The format of the supplied DSN is in its fullest form:
     * <code>
     *  driver://user:password@protocol+host/queue?option=8&another=true
     * </code>
     *
     * Most variations are allowed:
     * <code>
     *  driver://user:password@protocol+host:110//usr/db_file.db?mode=0644
     *  driver://user:password@host/queue
     *  driver://user:password@host
     *  driver://user@host
     *  driver://host/queue
     *  driver://host
     *  driver+vendor://host
     *  driver
     * </code>
     *
     * @param string $dsn Data Source Name to be parsed
     *
     * @return array an associative array with the following keys:
     *               + driver:  Driver backend used in PHP (gearman, rabbitMQ etc.)
     *               + vendor: The vendor used by the driver. Used to specify the connection on enqueue driver
     *               + host: Host specification (hostname[:port])
     *               + user: User name for login
     *               + password: Password for login
     *               + queue: Queue identifier
     *               + queue options
     */
    public static function parseDsn($dsn)
    {
        $request = Dsn::parse($dsn);

        $driver = explode('+', $request->getScheme(), 2);

        $config = $request->getQuery() + [
            'driver' => $driver[0],
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'user' => $request->getUser(),
            'password' => $request->getPassword(),
        ];

        if (isset($driver[1])) {
            $config['vendor'] = $driver[1];
        }

        if (!isset($config['queue'])) {
            $config['queue'] = trim((string) $request->getPath(), '/');
        }

        return array_filter($config);
    }

    /**
     * Configure the default connection name
     * If no default connection is given, the first configured connection is used
     *
     * @param string|null $defaultConnection
     */
    private function setDefaultConnection(?string $defaultConnection)
    {
        reset($this->configs);

        $this->defaultConnection = $defaultConnection ?: key($this->configs);
    }
}
