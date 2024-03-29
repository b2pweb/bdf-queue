<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\Receiver\NextInterface;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Testing\StackMessagesReceiver;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Class BinderReceiverTest
 */
class BinderReceiverTest extends TestCase
{
    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $connections;

    /**
     * @var DestinationManager
     */
    private $manager;

    /**
     * @var DestinationInterface
     */
    private $destination;


    protected function setUp(): void
    {
        $container = new Container();
        $container->add('queue.connections', ['test' => 'memory:']);
        (new QueueServiceProvider())->configure($container);

        $this->connections = $container->get(ConnectionDriverFactoryInterface::class);
        $this->manager = $container->get(DestinationManager::class);
        $this->destination = $this->manager->queue('test', 'test');
    }

    /**
     *
     */
    public function test_process_without_binder()
    {
        $this->destination->send((new Message($payload = ['foo' => 'bar']))->setName('MyBinderEvent'));
        $envelope = $this->consume([]);

        $this->assertSame($payload, $envelope->message()->data());
    }

    /**
     *
     */
    public function test_process_with_binder_none_match()
    {
        $this->destination->send((new Message($payload = ['foo' => 'bar']))->setName('MyBinderEvent'));
        $envelope = $this->consume([new AliasBinder([])]);

        $this->assertSame($payload, $envelope->message()->data());
    }

    /**
     *
     */
    public function test_process_with_binder()
    {
        $this->destination->send((new Message(['data' => 'bar']))->setName('MyBinderEvent'));
        $envelope = $this->consume([new AliasBinder([
            'MyBinderEvent' => MyBinderEvent::class
        ])]);

        $this->assertEquals(new MyBinderEvent('bar'), $envelope->message()->data());
    }

    /**
     *
     */
    public function test_start()
    {
        $next = $this->createMock(NextInterface::class);

        $receiver = new BinderReceiver([]);

        $next->expects($this->once())->method('start')->with($next);

        $receiver->start($next);
    }

    /**
     *
     */
    public function test_start_legacy()
    {
        $inner = $this->createMock(ReceiverInterface::class);
        $consumer = $this->createMock(ConsumerInterface::class);

        $receiver = new BinderReceiver($inner, []);

        $inner->expects($this->once())->method('start')->with($consumer);

        $receiver->start($consumer);
    }

    /**
     *
     */
    public function test_receiveTimeout()
    {
        $next = $this->createMock(NextInterface::class);

        $receiver = new BinderReceiver([]);

        $next->expects($this->once())->method('receiveTimeout')->with($next);

        $receiver->receiveTimeout($next);
    }

    /**
     *
     */
    public function test_receiveTimeout_legacy()
    {
        $inner = $this->createMock(ReceiverInterface::class);
        $consumer = $this->createMock(ConsumerInterface::class);

        $receiver = new BinderReceiver($inner, []);

        $inner->expects($this->once())->method('receiveTimeout')->with($consumer);

        $receiver->receiveTimeout($consumer);
    }

    /**
     *
     */
    public function test_receiveStop()
    {
        $next = $this->createMock(NextInterface::class);

        $receiver = new BinderReceiver([]);

        $next->expects($this->once())->method('receiveStop')->with($next);

        $receiver->receiveStop($next);
    }

    /**
     *
     */
    public function test_receiveStop_legacy()
    {
        $inner = $this->createMock(ReceiverInterface::class);
        $consumer = $this->createMock(ConsumerInterface::class);

        $receiver = new BinderReceiver($inner, []);

        $inner->expects($this->once())->method('receiveStop')->with($consumer);

        $receiver->receiveStop($consumer);
    }

    /**
     *
     */
    public function test_terminate()
    {
        $next = $this->createMock(NextInterface::class);

        $receiver = new BinderReceiver([]);

        $next->expects($this->once())->method('terminate')->with($next);

        $receiver->terminate($next);
    }

    /**
     *
     */
    public function test_terminate_legacy()
    {
        $inner = $this->createMock(ReceiverInterface::class);
        $consumer = $this->createMock(ConsumerInterface::class);

        $receiver = new BinderReceiver($inner, []);

        $inner->expects($this->once())->method('terminate')->with($consumer);

        $receiver->terminate($consumer);
    }

    /**
     * @param array $binders
     *
     * @return \Bdf\Queue\Message\EnvelopeInterface
     */
    private function consume($binders)
    {
        $inner = new StackMessagesReceiver();

        $this->destination->consumer(new StopWhenEmptyReceiver(new BinderReceiver($inner, $binders)))->consume(0);

        return $inner->last();
    }
}

class MyBinderEvent
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
}
