<?php

namespace Bdf\Queue\Testing;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
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
        $this->helper->init('test', 'myQueue');
    }

    /**
     *
     */
    public function test_empty_assertion()
    {
        $this->assertSame(0, $this->helper->count('myQueue'));
        $this->assertSame([], $this->helper->peek(1, 'myQueue'));
    }

    /**
     *
     */
    public function test_assertion_job()
    {
        $this->helper->destination()->send(Message::createFromJob('handler@handle', 'data', 'myQueue'));

        $this->assertSame(1, $this->helper->count('myQueue'));
        $this->assertTrue($this->helper->contains('@handle', 'myQueue'));
        $this->assertTrue($this->helper->contains('handler', 'myQueue'));
        $this->assertTrue($this->helper->contains('handler@handle', 'myQueue'));
        $this->assertTrue($this->helper->contains('data', 'myQueue'));
        $this->assertNotEmpty($this->helper->peek(1, 'myQueue'));
    }

    /**
     *
     */
    public function test_consume()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));

        $this->helper->consume(2, 'test', 'myQueue');

        $this->assertSame(0, $this->helper->count('myQueue'));
        $this->assertSame(2, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_one()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));

        $this->helper->consume(1, 'test', 'myQueue');

        $this->assertSame(1, $this->helper->count('myQueue'));
        $this->assertSame(1, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_many()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));
        $this->helper->consume(2, 'test', 'myQueue');

        $this->assertSame(1, $this->helper->count('myQueue'));
        $this->assertSame(2, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_with_prefetch()
    {
        TestQueueAssertionHandler::$count = 0;

        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));
        $this->helper->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data3', 'myQueue'));

        $this->helper->consume(2, 'test', 'myQueue', function($extension) {
            return new MessageWatcherReceiver($extension, function() {
                // Assert all the 3 jobs are reserved
                foreach ($this->helper->peek(10, 'myQueue', 'test') as $message) {
                    $this->assertSame(true, $message->internalJob()->metadata['reserved']);
                }
            });
        });

        $this->assertSame(1, $this->helper->count('myQueue'));
        $this->assertTrue($this->helper->contains('data3', 'myQueue'));
        $this->assertSame(2, TestQueueAssertionHandler::$count);

        // The job is available
        $message = $this->helper->peek(1, 'myQueue', 'test')[0];
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