<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\Receiver\BenchReceiver;
use Bdf\Queue\Consumer\Receiver\Binder\BinderReceiver;
use Bdf\Queue\Consumer\Receiver\MemoryLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\MessageCountLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\MessageLoggerReceiver;
use Bdf\Queue\Consumer\Receiver\MessageStoreReceiver;
use Bdf\Queue\Consumer\Receiver\NoFailureReceiver;
use Bdf\Queue\Consumer\Receiver\RateLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\RetryMessageReceiver;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Consumer\Receiver\TimeLimiterReceiver;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Testing\MessageWatcherReceiver;
use Closure;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

use function is_array;
use function is_object;
use function is_string;

/**
 *
 */
class ReceiverFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var InstantiatorInterface
     */
    private $instantiator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var callable[]
     */
    private $factories = [];

    /**
     * Does the factory in key is legacy (i.e. take next receiver as first parameter)
     * If this metadata is not set, it should be resolved using reflection
     *
     * @var array<string, bool|null>
     */
    private $factoryTakeNextReceiverAsFirstParameter = [];

    /**
     * ReceiverBuilder constructor.
     *
     * @param ContainerInterface $container
     * @param InstantiatorInterface $instantiator
     */
    public function __construct(ContainerInterface $container, InstantiatorInterface $instantiator = null)
    {
        $this->container = $container;
        $this->instantiator = $instantiator ?: $container->get(InstantiatorInterface::class);

        $this->addFactory(MessageLoggerReceiver::class, function (ReceiverFactory $factory) {
            return new MessageLoggerReceiver($factory->logger());
        }, false);

        $this->addFactory(RateLimiterReceiver::class, function (ReceiverFactory $factory, int $number, int $duration = 3) {
            return new RateLimiterReceiver($factory->logger(), $number, $duration);
        }, false);

        $this->addFactory(MessageCountLimiterReceiver::class, function (ReceiverFactory $factory, int $number) {
            return new MessageCountLimiterReceiver($number, $factory->logger());
        }, false);

        $this->addFactory(MemoryLimiterReceiver::class, function (ReceiverFactory $factory, int $bytes, callable $memoryResolver = null) {
            return new MemoryLimiterReceiver($bytes, $factory->logger(), $memoryResolver);
        }, false);

        $this->addFactory(TimeLimiterReceiver::class, function (ReceiverFactory $factory, int $expire) {
            return new TimeLimiterReceiver($expire, $factory->logger());
        }, false);

        $this->addFactory(RetryMessageReceiver::class, function (ReceiverFactory $factory, int $tries, int $delay) {
            return new RetryMessageReceiver($factory->logger(), $tries, $delay);
        }, false);

        $this->addFactory(StopWhenEmptyReceiver::class, function (ReceiverFactory $factory) {
            return new StopWhenEmptyReceiver($factory->logger());
        }, false);

        $this->addFactory(NoFailureReceiver::class, function () {
            return new NoFailureReceiver();
        }, false);

        $this->addFactory(MessageStoreReceiver::class, function (ReceiverFactory $factory) {
            return new MessageStoreReceiver($this->container->get(FailedJobStorageInterface::class), $factory->logger());
        }, false);

        $this->addFactory(BinderReceiver::class, function (ReceiverFactory $factory, array $binders) {
            return new BinderReceiver($binders);
        }, false);

        $this->addFactory(MessageWatcherReceiver::class, function (ReceiverFactory $factory, callable $callable = null) {
            return new MessageWatcherReceiver($callable);
        }, false);

        $this->addFactory([BenchReceiver::class, 'bench'], function (ReceiverFactory $factory, int $maxJobs = 100, int $maxHistory = 10) {
            return new BenchReceiver($factory->logger(), $maxJobs, $maxHistory);
        }, false);
    }

    /**
     * Check whether the middleware has a defined factory
     *
     * @param string $middleware  The receiver name
     *
     * @return bool
     */
    public function hasFactory(string $middleware): bool
    {
        return isset($this->factories[$middleware]);
    }

    /**
     * Add a middleware factory
     *
     * @param string|string[] $middlewares  The names of the receiver
     * @param callable $factory             The receiver factory
     * @param bool|null $isLegacy           Does the factory is in legacy format ? (i.e. takes as first parameter the next receiver). Use null the resolve using reflection.
     *
     * @return $this
     */
    public function addFactory($middlewares, callable $factory, ?bool $isLegacy = null)
    {
        foreach ((array)$middlewares as $middleware) {
            $this->factories[$middleware] = $factory;
            $this->factoryTakeNextReceiverAsFirstParameter[$middleware] = $isLegacy;
        }

        return $this;
    }

    /**
     * Build all the receiver stack
     *
     * @param string $middleware The receiver name
     * @param array $parameters  The parameters to give to the factory
     *
     * @return ReceiverInterface
     */
    public function create(string $middleware, array $parameters = []): ReceiverInterface
    {
        if (isset($this->factories[$middleware])) {
            return ($this->factories[$middleware])($this, ...$parameters);
        }

        if (empty($parameters) && $this->container->has($middleware)) {
            return $this->container->get($middleware);
        }

        @trigger_error('Using bdf-instantiator for create receiver instance is deprecated since 1.4 and will be removed in 2.0', E_USER_DEPRECATED);

        // No factory: launch the instantiator
        return $this->instantiator->make($middleware, $parameters);
    }

    /**
     * Add a logger
     *
     * @param LoggerInterface $logger
     * @deprecated Since 1.4, will be removed in 2.0
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    public function logger(): LoggerInterface
    {
        if ($this->logger) {
            return $this->logger;
        }

        return $this->logger =
            $this->container->has(LoggerInterface::class)
                ? $this->container->get(LoggerInterface::class) : (
                    $this->container->has('logger') ? $this->container->get('logger') : new NullLogger()
                )
        ;
    }

    /**
     * Does the factory requires to pass the next receiver as fist parameter ?
     * @internal Used for compatibility, will be removed in 2.0
     *
     * @param string $middleware Middleware name
     * @param bool $hasParameters Whether if parameters are given to the factory
     *
     * @return bool
     */
    public function factoryTakeNextReceiverAsFirstParameter(string $middleware, bool $hasParameters = true): bool
    {
        // No factory registered : instantiation will be forwarded to instantiator if it's not explicitly defined in the container
        // Or if it has parameters
        // This is a deprecated behavior, so assume that is follows the legacy behavior
        if (!$factory = $this->factories[$middleware] ?? null) {
            return $hasParameters || !$this->container->has($middleware);
        }

        if (isset($this->factoryTakeNextReceiverAsFirstParameter[$middleware])) {
            return $this->factoryTakeNextReceiverAsFirstParameter[$middleware];
        }

        if (is_string($factory) || $factory instanceof Closure) {
            $reflection = new ReflectionFunction($factory);
        } elseif (is_object($factory)) {
            $reflection = new ReflectionMethod($factory, '__invoke');
        } elseif (is_array($factory) && is_object($factory[0])) {
            $reflection = new ReflectionMethod($factory[0], $factory[1]);
        } else {
            return true; // Should not occur
        }

        // Has no parameters or the second parameter is not ReceiverInterface (the first one should be the receiver factory instance)
        if (
            $reflection->getNumberOfParameters() < 2
            || !$reflection->getParameters()[1]->hasType()
            || !($type = $reflection->getParameters()[1]->getType()) instanceof ReflectionNamedType
            || $type->getName() !== ReceiverInterface::class
        ) {
            return $this->factoryTakeNextReceiverAsFirstParameter[$middleware] = false;
        }

        return $this->factoryTakeNextReceiverAsFirstParameter[$middleware] = true;
    }
}
