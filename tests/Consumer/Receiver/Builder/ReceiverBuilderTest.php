<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\Receiver\BenchReceiver;
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
use Bdf\Queue\Consumer\Receiver\Binder\BinderInterface;
use Bdf\Queue\Consumer\Receiver\Binder\AliasBinder;
use Bdf\Queue\Processor\JobHintProcessorResolver;
use Bdf\Queue\Processor\MapProcessorResolver;
use Bdf\Queue\Processor\ProcessorInterface;
use Bdf\Queue\Processor\SingleProcessorResolver;
use Bdf\Queue\Testing\MessageWatcherReceiver;
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

    /**
     * @var ReceiverFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));
        $this->factory = new ReceiverFactory($this->container);

        $this->builder = new ReceiverBuilder($this->container, null, $this->factory);
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
            new ReceiverPipeline([
                new StopWhenEmptyReceiver(new NullLogger()),
                new MyReceiver(
                    new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                    'bar'
                ),
            ]),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_add_with_container_service()
    {
        $this->container->add('my_receiver', MyNoFactoryReceiver::class)->addArgument('bar');

        $this->builder->add('my_receiver');

        $this->assertEquals(
            new ReceiverPipeline([
                new MyNoFactoryReceiver('bar'),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_add_only_legacy()
    {
        // Redefine factory to force legacy behavior
        $this->factory->addFactory(StopWhenEmptyReceiver::class, function (ReceiverFactory $factory, ReceiverInterface $next) {
            return new StopWhenEmptyReceiver($next, $factory->logger());
        });

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
                ),
                new NullLogger()
            ),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_add_pipeline()
    {
        $this->builder->add(StopWhenEmptyReceiver::class);

        $this->assertEquals(
            new ReceiverPipeline([
                new StopWhenEmptyReceiver(new NullLogger()),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );

        $this->builder->add('bench');

        $this->assertEquals(
            new ReceiverPipeline([
                new BenchReceiver(new NullLogger()),
                new StopWhenEmptyReceiver(new NullLogger()),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_remove()
    {
        $this->builder->outlet($outlet = $this->createMock(ReceiverInterface::class));
        $this->builder->add(MyReceiver::class, ['bar']);
        $this->builder->remove(MyReceiver::class);

        $this->assertSame($outlet, $this->builder->build());
    }

    /**
     *
     */
    public function test_add_same_receiver_should_overrides_parameters_and_keep_same_position()
    {
        $this->builder
            ->add(MyReceiver::class, ['bar'])
            ->stopWhenEmpty()
            ->add(MyReceiver::class, ['rab'])
        ;

        $this->assertEquals(
            new ReceiverPipeline([
                new StopWhenEmptyReceiver(new LoggerProxy(new NullLogger())),
                new MyReceiver(
                    new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
                    'rab'
                ),
            ]),
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
            new ReceiverPipeline([
                new MessageLoggerReceiver(new LoggerProxy(new NullLogger())),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_log_in_container()
    {
        $this->container->add('logger', $logger = $this->createMock(LoggerInterface::class));
        $this->builder = new ReceiverBuilder($this->container);

        $this->builder->log();

        $this->assertEquals(
            new ReceiverPipeline([
                new MessageLoggerReceiver(new LoggerProxy($logger)),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new MessageLoggerReceiver(new LoggerProxy($logger)),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new RateLimiterReceiver(new LoggerProxy(new NullLogger()), 5, 3),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new MessageCountLimiterReceiver(5, new LoggerProxy(new NullLogger())),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new MemoryLimiterReceiver(5, new LoggerProxy(new NullLogger())),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new RetryMessageReceiver(new LoggerProxy(new NullLogger()), 5, 15),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new StopWhenEmptyReceiver(new LoggerProxy(new NullLogger())),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_noFailure()
    {
        $this->builder->noFailure();

        $this->assertEquals(new ReceiverPipeline([
                new NoFailureReceiver(),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new MessageStoreReceiver($failer, new NullLogger()),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new BinderReceiver([$binder1, $binder2]),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );

        $this->builder->binder(
            $binder3 = $this->createMock(BinderInterface::class)
        );

        $this->assertEquals(
            new ReceiverPipeline([
                new BinderReceiver([$binder1, $binder2, $binder3]),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_binder_with_legacy_definition()
    {
        $this->builder->add(
            BinderReceiver::class,
            [[
                $binder1 = $this->createMock(BinderInterface::class),
                $binder2 = $this->createMock(BinderInterface::class),
            ]]
        );

        $this->assertEquals(
            new ReceiverPipeline([
                new BinderReceiver(
                    [$binder1, $binder2]
                ),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );

        $this->builder->binder(
            $binder3 = $this->createMock(BinderInterface::class)
        );

        $this->assertEquals(
            new ReceiverPipeline([
                new BinderReceiver(
                    [$binder1, $binder2, $binder3]
                ),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new BinderReceiver([new AliasBinder(['Foo' => Foo::class], $serializer)]),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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
            new ReceiverPipeline([
                new BinderReceiver([new ClassNameBinder($serializer, function () {})]),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
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

    /**
     *
     */
    public function test_exists()
    {
        $this->assertFalse($this->builder->exists('foo'));
        $this->factory->addFactory('foo', function() {});
        $this->assertTrue($this->builder->exists('foo'));
    }

    /**
     *
     */
    public function test_expire()
    {
        $this->builder->expire(10);

        $this->assertEquals(
            new ReceiverPipeline([
                new TimeLimiterReceiver(10, new LoggerProxy(new NullLogger())),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );
    }

    /**
     *
     */
    public function test_watch()
    {
        $callable = function() {};

        $this->builder->watch($callable);

        $this->assertEquals(new ReceiverPipeline([
                new MessageWatcherReceiver($callable),
                new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class))),
            ]),
            $this->builder->build()
        );
    }

    public function test_add_new_receiver_system()
    {
        $a = new class implements ReceiverInterface {
            use DelegateHelper;
        };
        $b = new class implements ReceiverInterface {
            use DelegateHelper;
        };

        $this->builder->add($a);
        $this->builder->add($b);

        $this->assertEquals(new ReceiverPipeline([
            $b,
            $a,
            new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class)))
        ]), $this->builder->build());
    }

    public function test_add_mixing_legacy_and_new()
    {
        $this->factory->addFactory('custom', function (ReceiverFactory $factory, ReceiverInterface $next, $foo) {
            return new MyReceiver($next, $foo);
        });

        $a = new class implements ReceiverInterface {
            use DelegateHelper;
        };
        $b = new class implements ReceiverInterface {
            use DelegateHelper;
        };
        $c = new class implements ReceiverInterface {
            use DelegateHelper;
        };

        $this->builder->add($a);
        $this->builder->add($b);
        $this->builder->add('custom', ['bar']);
        $this->builder->add($c);

        $this->assertEquals(new ReceiverPipeline([
            $c,
            new MyReceiver(
                new ReceiverPipeline([
                    $b,
                    $a,
                    new ProcessorReceiver(new JobHintProcessorResolver($this->container->get(InstantiatorInterface::class)))
                ]),
                'bar'
            ),
        ]), $this->builder->build());
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

    public function start(ConsumerInterface $consumer): void {}
    public function receive($message, ConsumerInterface $consumer): void {}
    public function receiveTimeout(ConsumerInterface $consumer): void {}
    public function receiveStop(ConsumerInterface $consumer): void {}
    public function terminate(ConsumerInterface $consumer): void {}
}

class MyHandler
{
    public function handle()
    {

    }
}
