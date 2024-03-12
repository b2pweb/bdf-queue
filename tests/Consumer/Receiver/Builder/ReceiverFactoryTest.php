<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
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

        $this->factory->addFactory(LegacyReceiver::class, function (ReceiverFactory $factory, ReceiverInterface $next) {
            return new LegacyReceiver($next);
        });
        $this->factory->addFactory(NewReceiver::class, function (ReceiverFactory $factory) {
            return new NewReceiver();
        });

        $this->assertInstanceOf(LegacyReceiver::class, $this->factory->create(LegacyReceiver::class, [$this->createMock(ReceiverInterface::class)]));
        $this->assertInstanceOf(NewReceiver::class, $this->factory->create(NewReceiver::class, []));
    }

    public function test_factoryTakeNextReceiverAsFirstParameter()
    {
        $this->assertTrue($this->factory->factoryTakeNextReceiverAsFirstParameter('not_found'));
        $this->assertTrue($this->factory->addFactory('closure', function (ReceiverFactory $factory, ReceiverInterface $receiver) {})->factoryTakeNextReceiverAsFirstParameter('closure'));
        $this->assertTrue($this->factory->addFactory('object', new class { public function __invoke(ReceiverFactory $factory, ReceiverInterface $receiver) {}})->factoryTakeNextReceiverAsFirstParameter('object'));
        $this->assertTrue($this->factory->addFactory('array', [new class { public function foo(ReceiverFactory $factory, ReceiverInterface $receiver) {}}, 'foo'])->factoryTakeNextReceiverAsFirstParameter('array'));
        $this->assertTrue($this->factory->addFactory('function', __NAMESPACE__.'\\legacy_receiver_factory')->factoryTakeNextReceiverAsFirstParameter('function'));

        $this->assertFalse($this->factory->addFactory('new_closure', function (ReceiverFactory $factory, int $foo) {})->factoryTakeNextReceiverAsFirstParameter('new_closure'));
        $this->assertFalse($this->factory->addFactory('new_object', new class { public function __invoke(ReceiverFactory $factory, int $foo) {}})->factoryTakeNextReceiverAsFirstParameter('new_object'));
        $this->assertFalse($this->factory->addFactory('new_array', [new class { public function foo(ReceiverFactory $factory, int $foo) {}}, 'foo'])->factoryTakeNextReceiverAsFirstParameter('new_array'));
        $this->assertFalse($this->factory->addFactory('new_function', __NAMESPACE__.'\\new_receiver_factory')->factoryTakeNextReceiverAsFirstParameter('new_function'));

        $this->assertFalse($this->factory->addFactory('no_params', function () {})->factoryTakeNextReceiverAsFirstParameter('no_params'));
        $this->assertFalse($this->factory->addFactory('one_param', function (ReceiverFactory $factory) {})->factoryTakeNextReceiverAsFirstParameter('one_param'));
        $this->assertFalse($this->factory->addFactory('no_typehint', function (ReceiverFactory $factory, $foo) {})->factoryTakeNextReceiverAsFirstParameter('no_typehint'));

        $this->assertFalse($this->factory->factoryTakeNextReceiverAsFirstParameter('bench'));

        $this->container->add('test', new \stdClass());
        $this->assertFalse($this->factory->factoryTakeNextReceiverAsFirstParameter('test', false));
        $this->assertTrue($this->factory->factoryTakeNextReceiverAsFirstParameter('test', true));
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

    /**
     *
     */
    public function test_receiver_creation_use_service_from_container()
    {
        $this->container->add('my_receiver', MyNoFactoryReceiver::class)->addArgument('bar');

        $this->assertInstanceOf(MyNoFactoryReceiver::class, $receiver = $this->factory->create('my_receiver'));
        $this->assertSame('bar', $receiver->foo);
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

class LegacyReceiver implements ReceiverInterface
{
    use DelegateHelper;

    public function __construct(ReceiverInterface $next)
    {
        $this->delegate = $next;
    }
}

class NewReceiver implements ReceiverInterface
{
    use DelegateHelper;
}

function legacy_receiver_factory(ReceiverFactory $factory, ReceiverInterface $receiver)
{

}
function new_receiver_factory(ReceiverFactory $factory, int $foo)
{

}
