<?php

namespace Bdf\Queue\Testing;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Destination\Queue\QueueDestination;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class QueueHelperTest extends TestCase
{
    /** @var Container */
    private $container;
    /** @var QueueHelper */
    private $helper;


    /**
     *
     */
    public function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));
        $this->container->add('queue.connections', [
            'test' => ['driver' => 'memory', 'prefetch' => 5]
        ]);
        (new QueueServiceProvider())->configure($this->container);


        $this->helper = new QueueHelper($this->container);
        $this->helper->init('test::myQueue');
    }

    /**
     *
     */
    public function test_legacy()
    {
        $this->helper->init('test', 'legacy');

        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class.'@handle', 'data', 'legacy'));

        $this->assertSame(1, $this->helper->count('legacy'));
        $this->assertSame(1, $this->helper->count('legacy', 'test'));
        $this->assertNotEmpty($this->helper->peek(1, 'legacy'));
        $this->assertNotEmpty($this->helper->peek(1, 'legacy', 'test'));
        $this->assertTrue($this->helper->contains('@handle', 'legacy'));
        $this->assertTrue($this->helper->contains('@handle', 'legacy', 'test'));
        $this->assertInstanceOf(DestinationManager::class, $this->helper->destination());

        $called = false;
        $this->helper->consume(1, 'test', 'legacy', function($extension) use(&$called) {
            $called = true;
            $this->assertInstanceOf(ReceiverInterface::class, $extension);
            return $extension;
        });
        $this->assertTrue($called);
        $this->assertSame(0, $this->helper->count('test::myQueue'));
    }

    /**
     *
     */
    public function test_accessors()
    {
        $this->assertInstanceOf(DestinationManager::class, $this->helper->destinations());
        $this->assertInstanceOf(QueueDestination::class, $this->helper->destination('test::myQueue'));
    }

    /**
     *
     */
    public function test_empty_assertion()
    {
        $this->assertSame(0, $this->helper->count('test::myQueue'));
        $this->assertSame([], $this->helper->peek(1, 'test::myQueue'));
    }

    /**
     *
     */
    public function test_assertion_job()
    {
        $this->helper->destination()->send(Message::createFromJob('handler@handle', 'data')->setDestination('test::myQueue'));

        $this->assertSame(1, $this->helper->count('test::myQueue'));
        $this->assertTrue($this->helper->contains('@handle', 'test::myQueue'));
        $this->assertTrue($this->helper->contains('@handle', 'test::myQueue'));
        $this->assertTrue($this->helper->contains('handler', 'test::myQueue'));
        $this->assertTrue($this->helper->contains('handler@handle', 'test::myQueue'));
        $this->assertTrue($this->helper->contains('data', 'test::myQueue'));
        $this->assertNotEmpty($this->helper->peek(1, 'test::myQueue'));
    }

    /**
     *
     */
    public function test_consume()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1')->setDestination('test::myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2')->setDestination('test::myQueue'));

        $this->helper->consume(2, 'test::myQueue');

        $this->assertSame(0, $this->helper->count('test::myQueue'));
        $this->assertSame(2, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_one()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1')->setDestination('test::myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2')->setDestination('test::myQueue'));

        $this->helper->consume(1, 'test::myQueue');

        $this->assertSame(1, $this->helper->count('test::myQueue'));
        $this->assertSame(1, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_many()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1')->setDestination('test::myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2')->setDestination('test::myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2')->setDestination('test::myQueue'));
        $this->helper->consume(2, 'test::myQueue');

        $this->assertSame(1, $this->helper->count('test::myQueue'));
        $this->assertSame(2, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_with_prefetch()
    {
        TestQueueAssertionHandler::$count = 0;

        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1')->setDestination('test::myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2')->setDestination('test::myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data3')->setDestination('test::myQueue'));

        $this->helper->consume(2, 'test::myQueue', function(ReceiverBuilder $builder) {
            $builder->watch(function() {
                // Assert all the 3 jobs are reserved
                foreach ($this->helper->peek(10, 'test::myQueue') as $message) {
                    $this->assertSame(true, $message->internalJob()->metadata['reserved']);
                }
            });
        });

        $this->assertSame(1, $this->helper->count('test::myQueue'));
        $this->assertTrue($this->helper->contains('data3', 'test::myQueue'));
        $this->assertSame(2, TestQueueAssertionHandler::$count);

        // The job is available
        $message = $this->helper->peek(1, 'test::myQueue')[0];
        $this->assertSame(false, $message->internalJob()->metadata['reserved']);
    }
}

class TestQueueAssertionHandler
{
    public static $count = 0;
    public function handle()
    {
        self::$count++;
    }
}