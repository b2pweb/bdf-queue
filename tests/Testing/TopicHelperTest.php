<?php

namespace Bdf\Queue\Testing;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoaderInterface;
use Bdf\Queue\Consumer\Receiver\ProcessorReceiver;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\TopicEnvelope;
use Bdf\Queue\Processor\MapProcessorResolver;
use Bdf\Queue\Processor\ProcessorInterface;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class TopicHelperTest
 */
class TopicHelperTest extends TestCase
{
    /**
     * @var TopicHelper
     */
    private $helper;

    /**
     * @var DestinationManager
     */
    private $manager;

    /**
     * @var MockObject|ProcessorInterface
     */
    private $processor;

    /**
     *
     */
    public function setUp(): void
    {
        $container = new Container();
        $container->add(InstantiatorInterface::class, new Instantiator($container));
        $container->add('queue.connections', [
            'test' => 'memory:'
        ]);
        $container->add('queue.destinations', [
            'foo' => 'topic://test/topic1',
            'bar' => 'topic://test/topic2',
            'all' => 'topic://test/*',
        ]);
        (new QueueServiceProvider())->configure($container);

        $processor = new ProcessorReceiver(new MapProcessorResolver([
            'job' => $this->processor = $this->createMock(ProcessorInterface::class),
        ], null, function () { return 'job'; }));

        $container->get(ReceiverLoaderInterface::class)->register('foo', function (ReceiverBuilder $builder) use($processor) {
            $builder->outlet($processor);
        });
        $container->get(ReceiverLoaderInterface::class)->register('bar', function (ReceiverBuilder $builder) use($processor) {
            $builder->outlet($processor);
        });

        $this->helper = new TopicHelper($container);
        $this->manager = $container->get(DestinationManager::class);
    }

    /**
     *
     */
    public function test_consume_not_initialized()
    {
        $this->helper->destination('foo')->send((new Message('Hello World !')));
        $this->assertSame($this->helper, $this->helper->consume('foo'));
        $this->assertSame([], $this->helper->messages('foo'));
    }

    /**
     *
     */
    public function test_consume_empty()
    {
        $this->helper->init('foo')->consume('foo');
        $this->assertSame([], $this->helper->messages('foo'));
    }

    /**
     *
     */
    public function test_consume_one()
    {
        $this->helper->init('foo');
        $this->helper->destination('foo')->send((new Message('Hello World !')));

        $this->processor->expects($this->once())->method('process');

        $this->helper->consume('foo');

        $this->assertCount(1, $this->helper->messages('foo'));
        $this->assertEquals('Hello World !', $this->helper->messages('foo')[0]->message()->data());
    }

    /**
     *
     */
    public function test_consume_multiple()
    {
        $this->helper->init('foo');

        $this->helper->destination('foo')->send((new Message('a')));
        $this->helper->destination('foo')->send((new Message('b')));
        $this->helper->destination('foo')->send((new Message('c')));

        $this->processor->expects($this->exactly(3))->method('process');

        $this->helper->consume('foo');

        $this->assertCount(3, $this->helper->messages('foo'));
    }

    /**
     *
     */
    public function test_consumeAll()
    {
        $this->helper->init('foo', 'bar');

        $this->helper->destination('foo')->send((new Message('a')));
        $this->helper->destination('bar')->send((new Message('b')));
        $this->helper->destination('foo')->send((new Message('c')));

        $this->processor->expects($this->exactly(3))->method('process');

        $this->helper->consumeAll();

        $this->assertCount(2, $this->helper->messages('foo'));
        $this->assertCount(1, $this->helper->messages('bar'));
    }

    /**
     *
     */
    public function test_peek_messages()
    {
        $this->helper->init('foo', 'bar', 'all');

        $this->helper->destination('foo')->send((new Message('a')));
        $this->helper->destination('bar')->send((new Message('b')));
        $this->helper->destination('foo')->send((new Message('c')));

        $this->assertCount(2, $this->helper->peek('foo'));
        $this->assertCount(1, $this->helper->peek('bar'));

        $this->assertEquals(['a', 'c'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->helper->peek('foo')));
        $this->assertEquals(['b'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->helper->peek('bar')));
        $this->assertEquals(['a', 'b', 'c'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->helper->peek('all')));

        $this->assertEquals(['a', 'b'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->helper->peek('all', 2)));
        $this->assertEquals(['c'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->helper->peek('all', 2, 2)));
        $this->assertEquals([], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->helper->peek('all', 2, 10)));
    }

    /**
     *
     */
    public function test_default_destination()
    {
        $this->helper->setDefaultDestination('foo');

        $this->assertEquals($this->helper->destination('foo'), $this->helper->destination());
        $this->helper->init('foo');

        $this->helper->destination()->send(new Message());
        $this->helper->consume();

        $this->assertCount(1, $this->helper->messages());
    }

    /**
     *
     */
    public function test_contains_empty()
    {
        $this->assertFalse($this->helper->contains('bar', 'foo'));
    }

    /**
     *
     */
    public function test_contains_value()
    {
        $this->helper->init('foo');

        $this->helper->destination('foo')->send((new Message('a')));

        $this->helper->consume('foo');

        $this->assertTrue($this->helper->contains('a', 'foo'));
        $this->assertFalse($this->helper->contains('not_found', 'foo'));
    }

    /**
     *
     */
    public function test_contains_constraint()
    {
        $this->helper->init('foo');

        $this->helper->destination('foo')->send((new Message('a')));

        $this->helper->consume('foo');

        $this->assertTrue($this->helper->contains($this->isType('string'), 'foo'));
        $this->assertFalse($this->helper->contains($this->isType('int'), 'foo'));
    }

    /**
     *
     */
    public function test_contains_closure()
    {
        $this->helper->init('foo');

        $this->helper->destination('foo')->send((new Message('a')));

        $this->helper->consume('foo');

        $this->assertTrue($this->helper->contains(function (TopicEnvelope $envelope) use(&$called) {
            $called = true;
            return $envelope->message()->data() === 'a';
        }, 'foo'));

        $this->assertFalse($this->helper->contains(function () { return false; }, 'foo'));

        $this->assertTrue($called);
    }

    /**
     *
     */
    public function test_contains_constraint_on_message()
    {
        $this->helper->init('foo');

        $this->helper->destination('foo')->send((new Message('a')));

        $this->helper->consume('foo');

        $this->assertTrue($this->helper->contains($this->isInstanceOf(QueuedMessage::class), 'foo'));
    }

    /**
     *
     */
    public function test_consume_without_reinit()
    {
        $this->helper->init('foo');
        $this->helper->destination('foo')->send(new Message());
        $this->helper->consume('foo');

        $this->assertCount(1, $this->helper->messages('foo'));

        $this->helper->destination('foo')->send(new Message());
        $this->helper->consume('foo');

        $this->assertCount(1, $this->helper->messages('foo'));
    }

    /**
     *
     */
    public function test_consume_with_reinit()
    {
        $this->helper->init('foo');
        $this->helper->destination('foo')->send(new Message());
        $this->helper->consume('foo', true);

        $this->assertCount(1, $this->helper->messages('foo'));

        $this->helper->destination('foo')->send(new Message());
        $this->helper->consume('foo', true);

        $this->assertCount(2, $this->helper->messages('foo'));
    }

    /**
     *
     */
    public function test_consumeAll_with_reinit()
    {
        $this->helper->init('foo', 'bar');

        $this->helper->destination('foo')->send(new Message());
        $this->helper->destination('bar')->send(new Message());
        $this->helper->consumeAll(true);

        $this->assertCount(1, $this->helper->messages('foo'));
        $this->assertCount(1, $this->helper->messages('bar'));

        $this->helper->destination('foo')->send(new Message());
        $this->helper->consumeAll(true);

        $this->assertCount(2, $this->helper->messages('foo'));
    }

    /**
     *
     */
    public function test_destination()
    {
        $this->assertEquals($this->manager->topic('test', 'topic'), $this->helper->destination('test::topic'));
        $this->assertEquals($this->manager->create('foo'), $this->helper->destination('foo'));
    }

    /**
     *
     */
    public function test_clear()
    {
        $this->helper->init('foo', 'bar');

        $this->helper->destination('foo')->send(new Message());
        $this->helper->destination('bar')->send(new Message());
        $this->helper->consumeAll(true);

        $this->assertCount(1, $this->helper->messages('foo'));
        $this->assertCount(1, $this->helper->messages('bar'));

        $this->helper->clear();

        $this->assertCount(0, $this->helper->messages('foo'));
        $this->assertCount(0, $this->helper->messages('bar'));
    }
}
