<?php

namespace Bdf\Queue\Connection\Memory;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Driver
 * @group Bdf_Queue_Driver_Memory
 */
class MemoryQueueTest extends TestCase
{
    /**
     * @var MemoryQueue
     */
    protected $driver;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->driver = (new MemoryConnection('foo', new JsonSerializer()))->queue();
    }

    /**
     * 
     */
    public function test_push_one_data()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');
        $this->driver->push($message);

        $message = $this->driver->pop('queue', 0)->message();
        
        $this->assertInstanceOf(QueuedMessage::class, $message);
        $this->assertSame('foo', $message->data());
        $this->assertStringContainsString('{"job":"test","data":"foo","queuedAt":{"date"', $message->raw());
        $this->assertSame('queue', $message->queue());
        $this->assertSame('queue', $message->internalJob()->queue);
        $this->assertStringContainsString('{"job":"test","data":"foo","queuedAt":{"date"', $message->internalJob()->raw);
    }

    /**
     *
     */
    public function test_stats()
    {
        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        $this->driver->pushRaw('{"data":"foo"}', 'queue', 10);
        $this->driver->pushRaw('{"data":"foo"}', 'queue');

        // reserved one
        $this->driver->pop('queue', 0);

        $stats = $this->driver->stats()['queues'][0];

        $this->assertSame('queue', $stats['queue']);
        $this->assertSame(3, $stats['jobs in queue']);
        $this->assertSame(1, $stats['jobs awaiting']);
        $this->assertSame(1, $stats['jobs running']);
        $this->assertSame(1, $stats['jobs delayed']);
    }

    /**
     * 
     */
    public function test_pop_reserve_job()
    {
        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        
        $this->driver->pop('queue', 0);
        
        $stats = $this->driver->stats()['queues'][0];
        
        $this->assertSame(2, $stats['jobs in queue']);
        $this->assertSame(1, $stats['jobs running']);
    }

    /**
     *
     */
    public function test_reserve()
    {
        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        $this->driver->pushRaw('{"data":"foo"}', 'queue');

        $this->driver->reserve(2, 'queue', 0);

        $stats = $this->driver->stats()['queues'][0];

        $this->assertSame(3, $stats['jobs in queue']);
        $this->assertSame(2, $stats['jobs running']);
    }

    /**
     * 
     */
    public function test_pop_none()
    {
        $this->assertNull($this->driver->pop('queue', 0));
    }
    
    /**
     * 
     */
    public function test_push_several_data()
    {
        $this->driver->pushRaw('{"data":"foo1"}', 'queue');
        $this->driver->pushRaw('{"data":"foo2"}', 'queue');
        
        $message = $this->driver->pop('queue', 0)->message();
        $this->assertSame('foo1', $message->data());

        $message = $this->driver->pop('queue', 0)->message();
        $this->assertSame('foo2', $message->data());
    }

    /**
     * 
     */
    public function test_acknowledge_job()
    {
        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        
        $message = $this->driver->pop('queue', 0)->message();

        $this->driver->acknowledge($message);
        
        $stats = $this->driver->stats()['queues'][0];
        
        $this->assertSame(1, $stats['jobs in queue']);
        $this->assertSame(0, $stats['jobs running']);
    }

    /**
     *
     */
    public function test_release_message()
    {
        $this->driver->pushRaw('{"data":"foo"}', 'queue');

        $message = $this->driver->pop('queue', 0)->message();

        $this->driver->release($message);

        $stats = $this->driver->stats()['queues'][0];

        $this->assertSame(1, $stats['jobs awaiting']);
        $this->assertSame(0, $stats['jobs delayed']);
    }

    /**
     *
     */
    public function test_release_message_with_delay()
    {
        $this->driver->pushRaw('{"data":"foo"}', 'queue');

        $message = $this->driver->pop('queue', 0)->message();
        $message->setDelay(20);

        $this->driver->release($message);

        $stats = $this->driver->stats()['queues'][0];
        $this->assertSame(0, $stats['jobs awaiting']);
        $this->assertSame(1, $stats['jobs delayed']);
    }


    /**
     * 
     */
    public function test_delayed_job()
    {
        $this->driver->pushRaw('{"data":"foo"}', 'queue', 1);
        $message = $this->driver->pop('queue', 0);
        $this->assertNull($message);
        
        sleep(1);
        
        $message = $this->driver->pop('queue', 0)->message();
        $this->assertSame('foo', $message->data());
    }

    /**
     *
     */
    public function test_count()
    {
        $this->assertSame(0, $this->driver->count('queue'));

        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        $this->assertSame(1, $this->driver->count('queue'));

        $this->driver->pushRaw('{"data":"foo"}', 'queue');
        $this->assertSame(2, $this->driver->count('queue'));
    }

    /**
     *
     */
    public function test_peek()
    {
        $this->driver->pushRaw('{"data":"foo1"}', 'queue');
        $this->driver->pushRaw('{"data":"foo2"}', 'queue');

        $messages = $this->driver->peek('queue', 1);

        $this->assertCount(1, $messages);
        $this->assertSame('foo1', $messages[0]->data());

        $message = $this->driver->pop('queue', 0)->message();
        $this->assertSame('foo1', $message->data());


        $messages = $this->driver->peek('queue', 1, 2);

        $this->assertCount(1, $messages);
        $this->assertSame('foo2', $messages[0]->data());
    }
}