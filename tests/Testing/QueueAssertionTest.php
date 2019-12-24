<?php

namespace Bdf\Queue\Testing;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @group Bdf
 * @group Bdf_Queue
 * @group Bdf_Queue_Testing
 */
class QueueAssertionTest extends TestCase
{
    use QueueAssertion;

    private $container;

    /**
     * {@inheritDoc}
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

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

        $this->initializeQueue('test', 'myQueue');
    }

    /**
     *
     */
    public function test_empty_assertion()
    {
        $this->assertQueueEmpty('myQueue');
        $this->assertQueueNumber(0, 'myQueue');
        $this->assertSame([], $this->getAwaitingMessages('myQueue'));
    }

    /**
     *
     */
    public function test_assertion_job()
    {
        $this->destination()->send(Message::createFromJob('handler@handle', 'data', 'myQueue'));

        $this->assertQueueNumber(1, 'myQueue');
        $this->assertQueueHasJob('handle', 'myQueue');
        $this->assertQueueContains('handler', 'myQueue');
        $this->assertQueueContains('data', 'myQueue');
        $this->assertNotEmpty($this->getAwaitingMessages('myQueue'));
    }

    /**
     *
     */
    public function test_consume()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));

        $this->consume(2, 'test', 'myQueue');

        $this->assertQueueNumber(0, 'myQueue');
        $this->assertSame(2, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_one()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));

        $this->consume(1, 'test', 'myQueue');

        $this->assertQueueNumber(1, 'myQueue');
        $this->assertSame(1, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_many()
    {
        TestQueueAssertionHandler::$count = 0;
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));
        $this->consume(2, 'test', 'myQueue');

        $this->assertQueueNumber(1, 'myQueue');
        $this->assertSame(2, TestQueueAssertionHandler::$count);
    }

    /**
     *
     */
    public function test_consume_with_prefetch()
    {
        TestQueueAssertionHandler::$count = 0;

        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data1', 'myQueue'));
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data2', 'myQueue'));
        $this->destination()->send(Message::createFromJob(TestQueueAssertionHandler::class, 'data3', 'myQueue'));

        $this->consume(2, 'test', 'myQueue', function($extension) {
            return new MessageWatcherReceiver($extension, function() {
                // Assert all the 3 jobs are reserved
                foreach ($this->getAwaitingMessages('myQueue', 'test') as $message) {
                    $this->assertSame(true, $message->internalJob()->metadata['reserved']);
                }
            });
        });

        $this->assertQueueNumber(1, 'myQueue');
        $this->assertQueueContains('data3', 'myQueue');
        $this->assertSame(2, TestQueueAssertionHandler::$count);

        // The job is available
        $message = $this->getAwaitingMessages('myQueue', 'test')[0];
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