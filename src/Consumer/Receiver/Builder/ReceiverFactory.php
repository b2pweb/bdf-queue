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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
     * ReceiverBuilder constructor.
     *
     * @param ContainerInterface $container
     * @param InstantiatorInterface $instantiator
     */
    public function __construct(ContainerInterface $container, InstantiatorInterface $instantiator = null)
    {
        $this->container = $container;
        $this->instantiator = $instantiator ?: $container->get(InstantiatorInterface::class);

        $this->factories = [
            MessageLoggerReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver) {
                return new MessageLoggerReceiver($receiver, $factory->logger());
            },
            RateLimiterReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver, int $number, int $duration = 3) {
                return new RateLimiterReceiver($receiver, $factory->logger(), $number, $duration);
            },
            MessageCountLimiterReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver, int $number) {
                return new MessageCountLimiterReceiver($receiver, $number, $factory->logger());
            },
            MemoryLimiterReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver, int $bytes) {
                return new MemoryLimiterReceiver($receiver, $bytes, $factory->logger());
            },
            TimeLimiterReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver, int $expire) {
                return new TimeLimiterReceiver($receiver, $expire, $factory->logger());
            },
            RetryMessageReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver, int $tries, int $delay) {
                return new RetryMessageReceiver($receiver, $factory->logger(), $tries, $delay);
            },
            StopWhenEmptyReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver) {
                return new StopWhenEmptyReceiver($receiver, $factory->logger());
            },
            NoFailureReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver) {
                return new NoFailureReceiver($receiver);
            },
            MessageStoreReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver) {
                return new MessageStoreReceiver($receiver, $this->container->get(FailedJobStorageInterface::class), $factory->logger());
            },
            BinderReceiver::class => function(ReceiverFactory $factory, ReceiverInterface $receiver, array $binders) {
                return new BinderReceiver($receiver, $binders);
            },
        ];

        $this->addFactory([BenchReceiver::class, 'bench'], function(ReceiverFactory $factory, ReceiverInterface $receiver, int $maxJobs = 100, int $maxHistory = 10) {
            return new BenchReceiver($receiver, $factory->logger(), $maxJobs, $maxHistory);
        });
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
     *
     * @return $this
     */
    public function addFactory($middlewares, callable $factory)
    {
        foreach ((array)$middlewares as $middleware) {
            $this->factories[$middleware] = $factory;
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

        // No factory: launch the instantiator
        return $this->instantiator->make($middleware, $parameters);
    }

    /**
     * Add a logger
     *
     * @param LoggerInterface $logger
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
}
