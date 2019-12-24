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
use Bdf\Queue\Consumer\Receiver\RetryMessageReceiver;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Processor\JobHintProcessorResolver;
use Bdf\Queue\Processor\MapProcessorResolver;
use Bdf\Queue\Processor\ProcessorInterface;
use Bdf\Queue\Processor\SingleProcessorResolver;
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
     * Stack of middleware
     *
     * The key is the middleware class (or container id)
     * The value is the constructor parameters (expecting the previous receiver)
     *
     * @var array
     */
    private $stack = [];

    /**
     * @var ReceiverInterface
     */
    private $outlet;

    /**
     * @var LoggerInterface
     */
    private $logger;


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
    }

    /**
     * Add (or overrides) a middleware receiver
     *
     * The add operation keeps the middleware order, and the last added will be the outer middleware
     * On overrides (the same receiver is already added), the last position is kept, and only parameters are changed
     *
     * @param string $receiver The receiver class name, or container id
     * @param array $parameters The constructor parameters (without previous receiver)
     *
     * @return $this
     */
    public function add(string $receiver, array $parameters = []): ReceiverBuilder
    {
        $this->stack[$receiver] = $parameters;

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
            $this->logger = $logger;
        }

        return $this->add(MessageLoggerReceiver::class, [$this->logger()]);
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
        return $this->add(RateLimiterReceiver::class, [$this->logger(), $number, $duration]);
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
        return $this->add(MessageCountLimiterReceiver::class, [$number, $this->logger()]);
    }

    /**
     * Limit the total memory usage of the current runtime
     * When the limit is reached, the consumer is stopped
     *
     * Used to limit memory leaks effects
     *
     * @param int $bytes Memory limit, in bytes
     *
     * @return $this
     *
     * @see MemoryLimiterReceiver
     */
    public function memory(int $bytes): ReceiverBuilder
    {
        return $this->add(MemoryLimiterReceiver::class, [$bytes, $this->logger()]);
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
        return $this->add(RetryMessageReceiver::class, [$this->logger(), $tries, $delay]);
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
        return $this->add(StopWhenEmptyReceiver::class, [$this->logger()]);
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
        return $this->add(NoFailureReceiver::class);
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
        return $this->add(MessageStoreReceiver::class, [$this->container->get(FailedJobStorageInterface::class), $this->logger()]);
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
        if (!isset($this->stack[BinderReceiver::class])) {
            $this->stack[BinderReceiver::class] = [$binders];
        } else {
            $this->stack[BinderReceiver::class][0] = array_merge($this->stack[BinderReceiver::class][0], $binders);
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
     */
    public function build(): ReceiverInterface
    {
        $receiver = $this->createOutlet();

        foreach ($this->stack as $middleware => $parameters) {
            array_unshift($parameters, $receiver);

            $receiver = $this->instantiator->make($middleware, $parameters);
        }

        return $receiver;
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

    /**
     * Get the logger
     *
     * @fixme Q&D fix for get the logger no matter if provided
     *
     * @return LoggerInterface
     */
    private function logger(): LoggerInterface
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
