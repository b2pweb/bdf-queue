<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\Receiver\Binder\AliasBinder;
use Bdf\Queue\Consumer\Receiver\Binder\BinderInterface;
use Bdf\Queue\Consumer\Receiver\Binder\BinderReceiver;
use Bdf\Queue\Consumer\Receiver\Binder\ClassNameBinder;
use Bdf\Queue\Consumer\Receiver\MemoryLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\MessageCountLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\MessageLoggerReceiver;
use Bdf\Queue\Consumer\Receiver\MessageStoreReceiver;
use Bdf\Queue\Consumer\Receiver\NoFailureReceiver;
use Bdf\Queue\Consumer\Receiver\ProcessorReceiver;
use Bdf\Queue\Consumer\Receiver\RateLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\ReceiverPipeline;
use Bdf\Queue\Consumer\Receiver\RetryMessageReceiver;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Consumer\Receiver\TimeLimiterReceiver;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Processor\JobHintProcessorResolver;
use Bdf\Queue\Processor\MapProcessorResolver;
use Bdf\Queue\Processor\ProcessorInterface;
use Bdf\Queue\Processor\SingleProcessorResolver;
use Bdf\Queue\Testing\MessageWatcherReceiver;
use Bdf\Serializer\SerializerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builder for creates the receivers stack
 *
 * The receivers stack is composed of an outlet receiver which handle the message, and a list of middleware receivers
 * Only one type of each middleware receiver can be added
 * The last added middleware is the outer middleware (i.e. the first called middleware)
 * If no outlet is defined, JobHintProcessorResolver will be used
 *
 * <code>
 * $receiver = $builder
 *     ->store()
 *     ->bind(['Foo' => Foo::class])
 *     ->limit(100)
 *     ->jobProcessor('my-processor@handle')
 *     ->build()
 * ;
 *
 * $destination->consumer($receiver)->consume();
 * </code>
 *
 * @see JobHintProcessorResolver The default processor
 *
 * @fixme The container should not be passed at constructor : the build should not handle the receiver dependencies
 */
class ReceiverBuilder
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
     * @var ReceiverFactory
     */
    private $factory;

    /**
     * Stack of middleware
     *
     * The key is the middleware class (or container id)
     * The value is the constructor parameters (expecting the previous receiver)
     *
     * @var array<class-string<ReceiverInterface>, array|ReceiverInterface>
     */
    private $stack = [];

    /**
     * @var ReceiverInterface
     */
    private $outlet;

    /**
     * @var LoggerProxy
     */
    private $logger;

    /**
     * ReceiverBuilder constructor.
     *
     * @param ContainerInterface $container
     * @param InstantiatorInterface $instantiator
     * @param ReceiverFactory $factory
     */
    public function __construct(ContainerInterface $container, InstantiatorInterface $instantiator = null, ReceiverFactory $factory = null)
    {
        $this->container = $container;
        $this->instantiator = $instantiator ?: $container->get(InstantiatorInterface::class);
        $this->factory = $factory ?: new ReceiverFactory($this->container, $this->instantiator);
        $this->logger = new LoggerProxy(
            $this->container->has(LoggerInterface::class)
                ? $this->container->get(LoggerInterface::class)
                : ($this->container->has('logger') ? $this->container->get('logger') : new NullLogger())
        ); // @todo logger au constructeur ?
    }

    /**
     * Check whether the middleware has a defined factory
     *
     * @param string $middleware
     *
     * @return bool
     */
    public function exists(string $middleware): bool
    {
        return $this->factory->hasFactory($middleware);
    }

    /**
     * Add (or overrides) a middleware receiver
     *
     * The add operation keeps the middleware order, and the last added will be the outer middleware
     * On overrides (the same receiver is already added), the last position is kept, and only parameters are changed
     *
     * @param class-string<ReceiverInterface>|ReceiverInterface $receiver The receiver class name, or container id
     * @param array $parameters The constructor parameters (without previous receiver)
     *
     * @return $this
     */
    public function add($receiver, array $parameters = []): ReceiverBuilder
    {
        if (is_object($receiver)) {
            $parameters = $receiver;
            $receiver = get_class($receiver);
        }

        $this->stack[$receiver] = $parameters;

        return $this;
    }

    /**
     * Remove a declared middleware receiver
     *
     * @param class-string<ReceiverInterface>|ReceiverInterface $receiver The receiver class name, or container id
     *
     * @return $this
     */
    public function remove($receiver): ReceiverBuilder
    {
        if (is_object($receiver)) {
            $receiver = get_class($receiver);
        }

        unset($this->stack[$receiver]);

        return $this;
    }

    /**
     * Add logger middleware
     *
     * @param LoggerInterface|null $logger
     *
     * @return $this
     *
     * @see MessageLoggerReceiver
     */
    public function log(LoggerInterface $logger = null): ReceiverBuilder
    {
        if ($logger) {
            $this->factory->setLogger($logger);
            $this->logger->setLogger($logger);
        }

        return $this->add(new MessageLoggerReceiver($this->logger));
    }

    /**
     * Limit the received message rate
     *
     * @param int $number Number of messages to handle
     * @param int $duration The sleep duration in seconds when the limit is reached, before continue consumption
     *
     * @return $this
     *
     * @see RateLimiterReceiver
     */
    public function limit(int $number, int $duration = 3): ReceiverBuilder
    {
        return $this->add(new RateLimiterReceiver($this->logger, $number, $duration));
    }

    /**
     * Limit the number of received message
     * When the limit is reached, the consumer is stopped
     *
     * @param int $number Number of messages
     *
     * @return $this
     *
     * @see MessageCountLimiterReceiver
     */
    public function max(int $number): ReceiverBuilder
    {
        return $this->add(new MessageCountLimiterReceiver($number, $this->logger));
    }

    /**
     * Limit the number of received message
     * When the limit is reached, the consumer is stopped
     *
     * @param int $seconds Number of messages
     *
     * @return $this
     *
     * @see MessageCountLimiterReceiver
     */
    public function expire(int $seconds): ReceiverBuilder
    {
        return $this->add(new TimeLimiterReceiver($seconds, $this->logger));
    }

    /**
     * Limit the total memory usage of the current runtime
     * When the limit is reached, the consumer is stopped
     *
     * Used to limit memory leaks effects
     *
     * @param int $bytes Memory limit, in bytes
     * @param null|callable():int $memoryResolver Resolve current memory. By default, call `memory_get_usage()`
     *
     * @return $this
     *
     * @see MemoryLimiterReceiver
     */
    public function memory(int $bytes, callable $memoryResolver = null): ReceiverBuilder
    {
        return $this->add(new MemoryLimiterReceiver($bytes, $this->logger, $memoryResolver));
    }

    /**
     * Retry failed jobs (i.e. throwing an exception)
     *
     * @param int $tries The maximum tries count
     * @param int $delay The re-execution delay, in seconds
     *
     * @return $this
     *
     * @see RetryMessageReceiver
     */
    public function retry(int $tries, int $delay = 10): ReceiverBuilder
    {
        return $this->add(new RetryMessageReceiver($this->logger, $tries, $delay));
    }

    /**
     * Stops consumption when the destination is empty (i.e. no messages are received during the waiting duration)
     *
     * @return $this
     *
     * @see StopWhenEmptyReceiver
     */
    public function stopWhenEmpty(): ReceiverBuilder
    {
        return $this->add(new StopWhenEmptyReceiver($this->logger));
    }

    /**
     * Catch all exceptions to ensure that the consumer will no crash (and will silently fail)
     *
     * Note: This middleware must not be registered before retry() or store() and can complicate debugging
     *
     * @return $this
     *
     * @see NoFailureReceiver
     */
    public function noFailure(): ReceiverBuilder
    {
        return $this->add(new NoFailureReceiver());
    }

    /**
     * Store the failed messages
     *
     * @return $this
     *
     * @see MessageStoreReceiver
     * @see FailedJobStorageInterface
     */
    public function store(): ReceiverBuilder
    {
        // Use receiver factory because it contains a dependency
        return $this->add(MessageStoreReceiver::class);
    }

    /**
     * Register binders
     * On multiple calls, binders will be merged
     *
     * @param BinderInterface ...$binders
     *
     * @return $this
     *
     * @see ReceiverBuilder::bind() For register simple alias binder
     * @see BinderReceiver
     */
    public function binder(BinderInterface... $binders): ReceiverBuilder
    {
        /** @var BinderReceiver|array|null $binder */
        $receiver = $this->stack[BinderReceiver::class] ?? null;

        if (!$receiver) {
            return $this->add(new BinderReceiver($binders));
        }

        if ($receiver instanceof BinderReceiver) {
            $receiver->add($binders);
        } else {
            $this->stack[BinderReceiver::class][0] = array_merge($receiver[0], $binders);
        }

        return $this;
    }

    /**
     * Register binding events mapping
     *
     * <code>
     * $builder->bind([
     *     'Foo' => Foo::class,
     *     'Bar' => Bar::class,
     * ]);
     * </code>
     *
     * @param string[] $eventsMap The events name mapping, with name as key and class name as value
     *
     * @return $this
     */
    public function bind(array $eventsMap): ReceiverBuilder
    {
        return $this->binder(
            new AliasBinder(
                $eventsMap,
                $this->container->has(SerializerInterface::class) ? $this->container->get(SerializerInterface::class) : null
            )
        );
    }

    /**
     * Bind with the message name as class name
     *
     * <code>
     * $builder->bindByClassName(function ($className, $data) {
     *     return is_subclass_of($className, BaseEvent::class);
     * });
     * </code>
     *
     * @param callable|null $validator Validates the class name. Takes the class name and data as parameters, and returns boolean (true is valid)
     *
     * @return $this
     *
     * @see ClassNameBinder
     */
    public function bindByClassName(callable $validator = null): ReceiverBuilder
    {
        return $this->binder(
            new ClassNameBinder(
                $this->container->has(SerializerInterface::class) ? $this->container->get(SerializerInterface::class) : null,
                $validator
            )
        );
    }

    /**
     * Add a watcher on the middleware stack
     *
     * @param callable $callable Receive the message and the consumer in parameters. The message is null in case of timeout
     *
     * @return $this
     */
    public function watch(callable $callable): ReceiverBuilder
    {
        return $this->add(new MessageWatcherReceiver($callable));
    }

    /**
     * Register the outlet receiver
     *
     * @param ReceiverInterface $receiver
     *
     * @return $this
     */
    public function outlet(ReceiverInterface $receiver): ReceiverBuilder
    {
        $this->outlet = $receiver;

        return $this;
    }

    /**
     * Set unique processor as outlet receiver
     *
     * @param ProcessorInterface $processor The processor instance
     *
     * @return $this
     */
    public function processor(ProcessorInterface $processor): ReceiverBuilder
    {
        return $this->outlet(new ProcessorReceiver(
            new SingleProcessorResolver($processor)
        ));
    }

    /**
     * Set unique handler as outlet receiver
     *
     * @param string|callable $handler The handler name for container
     *
     * @return $this
     */
    public function handler($handler): ReceiverBuilder
    {
        return $this->outlet(new ProcessorReceiver(
            new SingleProcessorResolver($handler, $this->instantiator)
        ));
    }

    /**
     * Set JobHintProcessorResolver as outlet receiver
     *
     * @return $this
     */
    public function jobProcessor(): ReceiverBuilder
    {
        return $this->outlet(new ProcessorReceiver(new JobHintProcessorResolver($this->instantiator)));
    }

    /**
     * Set MapProcessorResolver as outlet receiver
     *
     * @param string[] $mapping
     * @param callable $keyBuilder
     *
     * @return $this
     */
    public function mapProcessor(array $mapping, callable $keyBuilder = null): ReceiverBuilder
    {
        return $this->outlet(new ProcessorReceiver(
            new MapProcessorResolver($mapping, new JobHintProcessorResolver($this->instantiator), $keyBuilder)
        ));
    }

    /**
     * Build all the receiver stack
     *
     * @return ReceiverInterface
     *
     * @todo Manage order in middleware
     */
    public function build(): ReceiverInterface
    {
        $stack = [$this->createOutlet()];

        foreach ($this->stack as $middleware => $parameters) {
            if ($parameters instanceof ReceiverInterface) {
                // Push front : last receivers must be the first ones
                array_unshift($stack, $parameters);
                continue;
            }

            // @todo gérer les factory non legacy (permet de gérer les receiver ayant des dépendances)
            // Legacy : the stack of middleware is handled by delegate passed on constructor
            // So create the pipeline as previous receiver
            $receiver = count($stack) === 1 ? $stack[0] : new ReceiverPipeline($stack);

            array_unshift($parameters, $receiver);

            $stack = [$this->factory->create($middleware, $parameters)];
        }

        return count($stack) === 1 ? $stack[0] : new ReceiverPipeline($stack);
    }

    /**
     * Create the default outlet if not already set
     *
     * @return ReceiverInterface
     */
    private function createOutlet(): ReceiverInterface
    {
        if (!$this->outlet) {
            $this->jobProcessor();
        }

        return $this->outlet;
    }
}
