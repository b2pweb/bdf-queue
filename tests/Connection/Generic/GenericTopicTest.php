<?php

namespace Bdf\Queue\Connection\Generic;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\Message;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class GenericTopicTest extends TestCase
{
    /**
     * @var ConnectionDriverInterface|MockObject
     */
    private $driver;

    /**
     *
     */
    public function setUp(): void
    {
        $this->driver = $this->createMock(ConnectionDriverInterface::class);
    }

    /**
     *
     */
    public function test_basic_publish()
    {
        $topic = new GenericTopic($this->driver);
        $queue = $this->createMock(QueueDriverInterface::class);

        $this->driver->expects($this->once())->method('queue')->willReturn($queue);
        $queue->expects($this->once())->method('stats')->willReturn([
            'queues' => [
                ['queue' => 'group1/foo'],
                ['queue' => 'group1/my-topic'],
            ]
        ]);

        $queue->expects($this->once())
            ->method('push')
            ->with($this->callback(function($message) {
                return $message->queue() === 'group1/my-topic';
            }))
        ;

        $topic->publish((new Message('foo'))->setTopic('my-topic'));
    }

    /**
     *
     */
    public function test_broadcast()
    {
        $topic = new GenericTopic($this->driver);
        $queue = $this->createMock(QueueDriverInterface::class);

        $this->driver->expects($this->once())->method('queue')->willReturn($queue);
        $queue->expects($this->once())->method('stats')->willReturn([
            'queues' => [
                ['queue' => 'group1/foo'],
                ['queue' => 'group1/my-topic'],
                ['queue' => 'group2/my-*'],
            ]
        ]);

        $queue->expects($this->at(1))
            ->method('push')
            ->with($this->callback(function($message) {
                return $message->queue() === 'group1/my-topic';
            }))
        ;

        $queue->expects($this->at(2))
            ->method('push')
            ->with($this->callback(function($message) {
                return $message->queue() === 'group2/my-*';
            }))
        ;

        $topic->publish((new Message('foo'))->setTopic('my-topic'));
    }

    /**
     *
     */
    public function test_group_option()
    {
        $topic = new GenericTopic($this->driver, ['group_separator' => '@']);
        $queue = $this->createMock(QueueDriverInterface::class);

        $this->driver->expects($this->once())->method('queue')->willReturn($queue);
        $queue->expects($this->once())->method('stats')->willReturn([
            'queues' => [
                ['queue' => 'group1/my-topic'],
                ['queue' => 'group1@my-topic'],
            ]
        ]);

        $queue->expects($this->once())
            ->method('push')
            ->with($this->callback(function($message) {
                return $message->queue() === 'group1@my-topic';
            }))
        ;

        $topic->publish((new Message('foo'))->setTopic('my-topic'));
    }

    /**
     *
     */
    public function test_wildcard_option()
    {
        $topic = new GenericTopic($this->driver, ['wildcard' => '#']);
        $queue = $this->createMock(QueueDriverInterface::class);

        $this->driver->expects($this->once())->method('queue')->willReturn($queue);
        $queue->expects($this->once())->method('stats')->willReturn([
            'queues' => [
                ['queue' => 'group1/foo'],
                ['queue' => 'group1/my-#'],
                ['queue' => 'group2/my-*'],
            ]
        ]);

        $queue->expects($this->once())
            ->method('push')
            ->with($this->callback(function($message) {
                return $message->queue() === 'group1/my-#';
            }))
        ;

        $topic->publish((new Message('foo'))->setTopic('my-topic'));
    }

    /**
     *
     */
    public function test_subscribe()
    {
        $this->driver->expects($this->once())->method('config')->willReturn(['group' => 'test']);
        $topic = new GenericTopic($this->driver, ['wildcard' => '#', 'group_separator' => '@']);

        $topic->subscribe(['my-*'], function(){});

        $this->assertSame(['test@my-#'], $topic->getSubscribedTopics());
    }

    /**
     *
     */
    public function test_consume_empty()
    {
        $this->driver->expects($this->once())->method('config')->willReturn(['group' => 'test']);
        $topic = new GenericTopic($this->driver, ['wildcard' => '#', 'group_separator' => '@']);

        $queue = $this->createMock(QueueDriverInterface::class);
        $this->driver->expects($this->once())->method('queue')->willReturn($queue);

        $topic->subscribe(['my-*'], function(){});

        $queue->expects($this->once())->method('pop')->willReturn(null);
        $this->assertSame(0, $topic->consume());
    }
}
