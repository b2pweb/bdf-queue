<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Testing\MessageStacker;
use Bdf\Queue\Testing\MessageWatcherReceiver;
use PHPUnit\Framework\TestCase;

class ReceiverPipelineTest extends TestCase
{
    /**
     * @dataProvider provideSimpleMethod
     */
    public function test_simple_receiver_method_call(string $method)
    {
        $consumer = $this->createMock(ConsumerInterface::class);
        $stack = new \ArrayObject();
        $pipeline = new ReceiverPipeline([
            new SimpleReceiver('A', $stack),
            new SimpleReceiver('B', $stack),
            new SimpleReceiver('C', $stack),
        ]);

        $pipeline->$method($consumer);

        $this->assertSame(['A::'.$method, 'B::'.$method, 'C::'.$method], $stack->getArrayCopy());
    }

    public function provideSimpleMethod()
    {
        return [
            ['start'],
            ['receiveTimeout'],
            ['receiveStop'],
            ['terminate'],
        ];
    }

    public function test_calling_consumer_from_receiver()
    {
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer2 = $this->createMock(ConsumerInterface::class);
        $pipeline = new ReceiverPipeline([
            new class implements ReceiverInterface {
                use DelegateHelper;

                public function receive($message, ConsumerInterface $consumer): void
                {
                    $consumer->stop();
                    $consumer->connection();
                    $consumer->consume(0);
                    $consumer->receive($message, $consumer);
                }
            }
        ]);

        $consumer->expects($this->once())->method('stop');
        $consumer->expects($this->once())->method('connection');
        $consumer->expects($this->once())->method('consume');
        $pipeline->receive(new \stdClass(), $consumer);

        $consumer2->expects($this->once())->method('stop');
        $pipeline->receive(new \stdClass(), $consumer2);
    }

    public function test_receive_stack()
    {
        $consumer = $this->createMock(ConsumerInterface::class);
        $pipeline = new ReceiverPipeline([
            new class implements ReceiverInterface {
                use DelegateHelper;

                public function receive($message, ConsumerInterface $consumer): void
                {
                    $consumer->receive((object) ['A' => $message], $consumer);
                }
            },
            new class implements ReceiverInterface {
                use DelegateHelper;

                public function receive($message, ConsumerInterface $consumer): void
                {
                    $consumer->receive((object) ['B' => $message], $consumer);
                }
            },
            new MessageWatcherReceiver(function ($message) use(&$last) {
                $last = $message;
            })
        ]);

        $pipeline->receive((object) ['foo' => 'bar'], $consumer);
        $this->assertEquals((object) [
            'B' => (object) [
                'A' => (object) ['foo' => 'bar']
            ]
        ], $last);
    }

    public function test_toString()
    {
        $pipeline = new ReceiverPipeline([
            new class implements ReceiverInterface {
                use DelegateHelper;

                public function __toString()
                {
                    return 'Foo';
                }
            },
            new MessageWatcherReceiver(),
            new class implements ReceiverInterface {
                use DelegateHelper;

                public function __toString()
                {
                    return 'Bar';
                }
            },
        ]);

        $this->assertEquals('Foo->'.MessageWatcherReceiver::class.'->Bar', (string) $pipeline);
    }
}

class SimpleReceiver implements ReceiverInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var \ArrayObject
     */
    private $stack;

    public function __construct(string $name, \ArrayObject $stack)
    {
        $this->name = $name;
        $this->stack = $stack;
    }

    /**
     * {@inheritdoc}
     */
    public function start(ConsumerInterface $consumer): void
    {
        $this->stack->append($this->name.'::start');
        $consumer->start($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->stack->append($this->name.'::receive');
        $consumer->receive($message, $consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->stack->append($this->name.'::receiveTimeout');
        $consumer->receiveTimeout($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(ConsumerInterface $consumer): void
    {
        $this->stack->append($this->name.'::receiveStop');
        $consumer->receiveStop($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(ConsumerInterface $consumer): void
    {
        $this->stack->append($this->name.'::terminate');
        $consumer->terminate($consumer);
    }
}
