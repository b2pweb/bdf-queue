<?php

namespace Bdf\Queue\Consumer\Reader;

use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * Class MultiQueueReaderTest
 */
class MultiQueueReaderTest extends TestCase
{
    /**
     * @var MultiQueueReader
     */
    private $reader;

    /**
     * @var QueueDriverInterface
     */
    private $driver;

    protected function setUp(): void
    {
        $this->driver = (new MemoryConnection())->queue();
        $this->reader = new MultiQueueReader($this->driver, ['q1', 'q2', 'q3']);
    }

    /**
     *
     */
    public function test_read_from_empty_queue_should_returns_null()
    {
        $this->assertNull($this->reader->read());
    }

    /**
     *
     */
    public function test_read_success()
    {
        $this->driver->push((new Message('foo'))->setQueue('q1'));
        $this->driver->push((new Message('bar'))->setQueue('q3'));

        $this->assertEquals('foo', $this->reader->read(0)->message()->data());
        $this->assertEquals('bar', $this->reader->read(0)->message()->data());
    }

    /**
     *
     */
    public function test_stop_should_close_connection()
    {
        $connection = new class extends MemoryConnection {
            public $closed = false;
            public function close(): void { $this->closed = true; }
        };

        $reader = new QueueReader($connection->queue(), 'my-queue');
        $reader->stop();

        $this->assertTrue($connection->closed);
    }
}
