<?php

namespace Bdf\Queue\Consumer\Reader;

use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\ReservableQueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * Class BufferedReaderTest
 */
class BufferedReaderTest extends TestCase
{
    /**
     *
     */
    public function test_read_from_empty_queue_should_returns_null()
    {
        $reader = new BufferedReader((new MemoryConnection())->queue(), 'my-queue', 10);
        $this->assertNull($reader->read());
    }

    /**
     *
     */
    public function test_read_unit()
    {
        $driver = $this->createMock(ReservableQueueDriverInterface::class);
        $reader = new BufferedReader($driver, 'my-queue', 2);

        $messages = [
            $this->createMock(EnvelopeInterface::class),
            $this->createMock(EnvelopeInterface::class),
        ];

        $driver->expects($this->at(0))
            ->method('reserve')
            ->with(2, 'my-queue', 0)
            ->willReturn($messages)
        ;

        $driver->expects($this->at(1))
            ->method('reserve')
            ->with(2, 'my-queue', 0)
            ->willReturn([])
        ;

        $this->assertSame($messages[0], $reader->read(0));
        $this->assertSame($messages[1], $reader->read(0));
        $this->assertNull($reader->read(0));
    }

    /**
     *
     */
    public function test_read_functional()
    {
        $reader = new BufferedReader($driver = (new MemoryConnection())->queue(), 'my-queue', 2);

        $driver->push((new Message('foo'))->setQueue('my-queue'));
        $driver->push((new Message('bar'))->setQueue('my-queue'));

        $this->assertEquals('foo', $reader->read(0)->message()->data());

        $this->assertEmpty($driver->pop('my-queue')); // messages are reserved

        $this->assertEquals('bar', $reader->read(0)->message()->data());
    }

    /**
     *
     */
    public function test_stop_should_close_connection_and_release_messages()
    {
        $connection = new class extends MemoryConnection {
            public $closed = false;
            public function close(): void { $this->closed = true; }
        };

        $reader = new BufferedReader($driver = $connection->queue(), 'my-queue', 2);

        $driver->push((new Message('foo'))->setQueue('my-queue'));
        $driver->push((new Message('bar'))->setQueue('my-queue'));

        $this->assertEquals('foo', $reader->read(0)->message()->data());
        $this->assertEmpty($driver->pop('my-queue')); // messages are reserved

        $reader->stop();
        $this->assertTrue($connection->closed);
        $this->assertEquals('bar', $driver->pop('my-queue')->message()->data());
    }
}
