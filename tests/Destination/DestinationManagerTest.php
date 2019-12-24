<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Connection\Factory\CachedConnectionDriverFactory;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Destination\Queue\MultiQueueDestination;
use Bdf\Queue\Destination\Queue\QueueDestination;
use Bdf\Queue\Destination\Queue\QueuePromise;
use Bdf\Queue\Destination\Topic\TopicDestination;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use PHPUnit\Framework\TestCase;

/**
 * Class DestinationFactoryTest
 */
class DestinationManagerTest extends TestCase
{
    /**
     * @var DestinationManager
     */
    private $manager;

    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $connectionFactory;

    protected function setUp(): void
    {
        $this->connectionFactory = new ResolverConnectionDriverFactory([
            'test' => 'test://host/default-queue',
            'other' => 'test://host/my-queue',
            'prefetched' => 'test:?prefetch=10',
        ]);
        $this->connectionFactory->addDriverResolver('test', function () { return new MemoryConnection(); });
        $this->connectionFactory = new CachedConnectionDriverFactory($this->connectionFactory);
        $this->manager = new DestinationManager(
            $this->connectionFactory,
            new ConfigurationDestinationFactory([
                'd1' => 'queue://other/q1'
            ], new DsnDestinationFactory($this->connectionFactory))
        );
    }

    /**
     *
     */
    public function test_topic()
    {
        $this->assertEquals(
            new TopicDestination($this->connectionFactory->create('test')->topic(), 'my-topic'),
            $this->manager->topic('test', 'my-topic')
        );
    }

    /**
     *
     */
    public function test_queue_default()
    {
        $this->assertEquals(
            new QueueDestination($this->connectionFactory->create('test')->queue(), 'default-queue', 0),
            $this->manager->queue(null)
        );
    }

    /**
     *
     */
    public function test_queue_with_connection()
    {
        $this->assertEquals(
            new QueueDestination($this->connectionFactory->create('other')->queue(), 'my-queue', 0),
            $this->manager->queue('other')
        );
    }

    /**
     *
     */
    public function test_queue_with_queue_name()
    {
        $this->assertEquals(
            new QueueDestination($this->connectionFactory->create('other')->queue(), 'foo', 0),
            $this->manager->queue('other', 'foo')
        );
    }

    /**
     *
     */
    public function test_multi_queue()
    {
        $this->assertEquals(
            new MultiQueueDestination($this->connectionFactory->create('other')->queue(), ['foo', 'bar']),
            $this->manager->queue('other', ['foo', 'bar'])
        );
    }

    /**
     *
     */
    public function test_queue_with_prefetch()
    {
        $this->assertEquals(
            new QueueDestination($this->connectionFactory->create('prefetched')->queue(), 'foo', 10),
            $this->manager->queue('prefetched', 'foo')
        );
    }

    /**
     * @todo delete when default connection is removed
     */
    public function test_for_without_destination()
    {
        $this->assertEquals(
            $this->manager->queue('test', 'default-queue'),
            $this->manager->for(new Message())
        );
    }

    /**
     * @todo delete when default queue is removed
     */
    public function test_for_with_connection()
    {
        $this->assertEquals(
            $this->manager->queue('other', 'my-queue'),
            $this->manager->for((new Message())->setConnection('other'))
        );
    }

    /**
     *
     */
    public function test_for_with_queue()
    {
        $this->assertEquals(
            $this->manager->queue('other', 'foo'),
            $this->manager->for((new Message())->setConnection('other')->setQueue('foo'))
        );
    }

    /**
     *
     */
    public function test_for_with_topic()
    {
        $this->assertEquals(
            $this->manager->topic('other', 'foo'),
            $this->manager->for((new Message())->setConnection('other')->setTopic('foo'))
        );
    }

    /**
     *
     */
    public function test_for_with_destination()
    {
        $this->assertEquals(
            $this->manager->queue('other', 'q1'),
            $this->manager->for((new Message())->setConnection('d1'))
        );
    }

    /**
     *
     */
    public function test_send()
    {
        $this->assertInstanceOf(NullPromise::class, $this->manager->send((new Message('Hello World !'))->setConnection('other')->setQueue('foo')));

        $this->assertEquals('Hello World !', $this->connectionFactory->create('other')->queue()->pop('foo')->message()->data());
    }

    /**
     *
     */
    public function test_send_with_with_reply()
    {
        $promise = $this->manager->send(
            $message = (new Message('Hello World !'))
                ->setConnection('other')
                ->setQueue('foo')
                ->setNeedsReply()
        );

        $this->assertInstanceOf(QueuePromise::class, $promise);

        $this->manager->send(
            (new Message('My response'))
                ->setConnection('other')
                ->setQueue('foo_reply')
                ->addHeader('correlationId', $message->header('correlationId'))
        );

        $this->assertEquals('My response', $promise->await()->data());
    }

    /**
     *
     */
    public function test_call()
    {
        // Send the reply before for ensure that promise will get it on call
        $this->manager->send(
            (new Message('My response'))
                ->setConnection('other')
                ->setQueue('foo_reply')
                ->addHeader('correlationId', '123')
        );

        $response = $this->manager->call(
            $message = (new Message('Hello World !'))
                ->setConnection('other')
                ->setQueue('foo')
                ->addHeader('correlationId', '123')
        );

        $this->assertInstanceOf(QueuedMessage::class, $response);
        $this->assertEquals('My response', $response->data());
    }

    /**
     *
     */
    public function test_call_without_response()
    {
        $this->assertNull($this->manager->call($message = (new Message('Hello World !'))->setConnection('other')->setQueue('foo')));
    }
}
