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
class ReceiverFactoryTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var ReceiverFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));
        $this->factory = new ReceiverFactory($this->container);
    }

    /**
     *
     */
    public function test_has_factory()
    {
        $this->assertFalse($this->factory->hasFactory('foo'));
        $this->factory->addFactory('foo', function() {});
        $this->assertTrue($this->factory->hasFactory('foo'));
    }

    /**
     *
     */
    public function test_custom_receiver()
    {
        $receiver = $this->createMock(ReceiverInterface::class);

        $this->factory->addFactory('foo', function() use($receiver) {
            return $receiver;
        });

        $this->assertSame($receiver, $this->factory->create('foo'));
    }

    /**
     *
     */
    public function test_default_logger()
    {
        $this->assertInstanceOf(NullLogger::class, $this->factory->logger());
    }

    /**
     *
     */
    public function test_container_logger()
    {
        $logger = $this->createMock(LoggerInterface::class);

        $this->container->add(LoggerInterface::class, $logger);

        $this->assertSame($logger, $this->factory->logger());
    }

    /**
     *
     */
    public function test_set_logger()
    {
        $logger = $this->createMock(LoggerInterface::class);

        $this->factory->setLogger($logger);

        $this->assertSame($logger, $this->factory->logger());
    }

    /**
     *
     */
    public function test_receiver_creation_without_factory()
    {
        $this->assertInstanceOf(MyNoFactoryReceiver::class, $receiver = $this->factory->create(MyNoFactoryReceiver::class, ['foo']));
        $this->assertSame('foo', $receiver->foo);
    }
}


class MyNoFactoryReceiver implements ReceiverInterface
{
    public $foo;

    public function __construct($foo)
    {
        $this->foo = $foo;
    }

    public function start(ConsumerInterface $consumer): void {}
    public function receive($message, ConsumerInterface $consumer): void {}
    public function receiveTimeout(ConsumerInterface $consumer): void {}
    public function receiveStop(ConsumerInterface $consumer): void {}
    public function terminate(ConsumerInterface $consumer): void {}
}