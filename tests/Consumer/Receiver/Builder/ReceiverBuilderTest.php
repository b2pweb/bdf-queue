<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
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
use Bdf\Queue\Consumer\Receiver\Binder\BinderInterface;
use Bdf\Queue\Consumer\Receiver\Binder\AliasBinder;
use Bdf\Queue\Processor\JobHintProcessorResolver;
use Bdf\Queue\Processor\MapProcessorResolver;
use Bdf\Queue\Processor\ProcessorInterface;
use Bdf\Queue\Processor\SingleProcessorResolver;
use Bdf\Serializer\SerializerInterface;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class ReceiverBuilderTest
 */
class ReceiverBuilderTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var ReceiverBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));

        $this->builder = new ReceiverBuilder($this->container);
    }

    /**
     *
     */
    public function test_build_defaults()
    {
        $this->assertEquals(
            new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_outlet()
    {
        $this->builder->outlet($outlet = $this->createMock(ReceiverInterface::class));

        $this->assertSame($outlet, $this->builder->build());
    }

    /**
     *
     */
    public function test_add()
    {
        $this->builder->add(MyReceiver::class, ['bar']);

        $this->assertEquals(
            new MyReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                'bar'
            ),
            $this->builder->build()
        );

        $this->builder->add(StopWhenEmptyReceiver::class);

        $this->assertEquals(
            new StopWhenEmptyReceiver(
                new MyReceiver(
                    new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                    'bar'
                )
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_add_same_receiver_should_overrides_parameters_and_keep_same_position()
    {
        $this->builder
            ->add(MyReceiver::class, ['bar'])
            ->add(StopWhenEmptyReceiver::class)
            ->add(MyReceiver::class, ['rab'])
        ;

        $this->assertEquals(
            new StopWhenEmptyReceiver(
                new MyReceiver(
                    new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                    'rab'
                )
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_log()
    {
        $this->builder->log();

        $this->assertEquals(
            new MessageLoggerReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                new NullLogger()
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_log_in_container()
    {
        $this->container->add('logger', $logger = $this->createMock(LoggerInterface::class));

        $this->builder->log();

        $this->assertEquals(
            new MessageLoggerReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                $logger
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_log_with_custom_logger()
    {
        $this->builder->log($logger = $this->createMock(LoggerInterface::class));

        $this->assertEquals(
            new MessageLoggerReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                $logger
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_limit()
    {
        $this->builder->limit(5);

        $this->assertEquals(
            new RateLimiterReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                new NullLogger(),
                5, 3
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_max()
    {
        $this->builder->max(5);

        $this->assertEquals(
            new MessageCountLimiterReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                5,
                new NullLogger()
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_memory()
    {
        $this->builder->memory(5);

        $this->assertEquals(
            new MemoryLimiterReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                5,
                new NullLogger()
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_retry()
    {
        $this->builder->retry(5, 15);

        $this->assertEquals(
            new RetryMessageReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                new NullLogger(),
                5, 15
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_stopWhenEmpty()
    {
        $this->builder->stopWhenEmpty();

        $this->assertEquals(
            new StopWhenEmptyReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                new NullLogger()
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_noFailure()
    {
        $this->builder->noFailure();

        $this->assertEquals(
            new NoFailureReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class)))
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_store()
    {
        $this->container->add(FailedJobStorageInterface::class, $failer = $this->createMock(FailedJobStorageInterface::class));

        $this->builder->store();

        $this->assertEquals(
            new MessageStoreReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                $failer,
                new NullLogger()
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_binder()
    {
        $this->builder->binder(
            $binder1 = $this->createMock(BinderInterface::class),
            $binder2 = $this->createMock(BinderInterface::class)
        );

        $this->assertEquals(
            new BinderReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                [$binder1, $binder2]
            ),
            $this->builder->build()
        );

        $this->builder->binder(
            $binder3 = $this->createMock(BinderInterface::class)
        );

        $this->assertEquals(
            new BinderReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                [$binder1, $binder2, $binder3]
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_bind()
    {
        $this->container->add(SerializerInterface::class, $serializer = $this->createMock(SerializerInterface::class));
        $this->builder->bind(['Foo' => Foo::class]);

        $this->assertEquals(
            new BinderReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                [new AliasBinder(['Foo' => Foo::class], $serializer)]
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_bindByClassName()
    {
        $this->container->add(SerializerInterface::class, $serializer = $this->createMock(SerializerInterface::class));
        $this->builder->bindByClassName(function () {});

        $this->assertEquals(
            new BinderReceiver(
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                [new ClassNameBinder($serializer, function () {})]
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_handler()
    {
        $this->container->add('my-handler', new MyHandler());

        $this->assertEquals(
            new ProcessorReceiver(new SingleProcessorResolver('my-handler', $this->container->get(InstantiatorInterface::class))),
            $this->builder->handler('my-handler')->build()
        );
    }

    /**
     *
     */
    public function test_processor()
    {
        $processor = $this->createMock(ProcessorInterface::class);

        $this->assertEquals(
            new ProcessorReceiver(new SingleProcessorResolver($processor)),
            $this->builder->processor($processor)->build()
        );
    }

    /**
     *
     */
    public function test_jobProcessor()
    {
        $this->assertEquals(
            new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            $this->builder->jobProcessor()->build()
        );
    }

    /**
     *
     */
    public function test_mapProcessor()
    {
        $receiver = new ProcessorReceiver(
            new MapProcessorResolver(['queue' => 'foo'], new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class)))
        );

        $this->assertEquals(
            $receiver,
            $this->builder->mapProcessor(['queue' => 'foo'])->build()
        );
    }
}

class MyReceiver implements ReceiverInterface
{
    public $receiver;
    public $foo;

    public function __construct($receiver, $foo)
    {
        $this->receiver = $receiver;
        $this->foo = $foo;
    }

    public function receive($message, ConsumerInterface $consumer): void {}
    public function receiveTimeout(ConsumerInterface $consumer): void {}
    public function receiveStop(): void {}
    public function terminate(): void {}
}

class MyHandler
{
    public function handle()
    {

    }
}