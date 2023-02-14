<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Destination\Queue\MultiQueueDestination;
use Bdf\Queue\Destination\Queue\QueueDestination;
use Bdf\Queue\Destination\Topic\MultiTopicDestination;
use Bdf\Queue\Destination\Topic\ReadableTopicDestination;
use Bdf\Queue\Destination\Topic\TopicDestination;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;

class DestinationFactoryTest extends TestCase
{
    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $connectionFactory;

    /**
     *
     */
    protected function setUp(): void
    {
        $container = new Container();
        $container->add('queue.connections', [
            'test' => 'memory:',
            'other' => 'gearman://127.0.0.1',
        ]);
        (new QueueServiceProvider())->configure($container);

        $this->connectionFactory = $container->get(ResolverConnectionDriverFactory::class);
    }

    /**
     *
     */
    public function test_create_simple_queue()
    {
        $factory = new DestinationFactory($this->connectionFactory);

        $this->assertEquals(new QueueDestination($this->connectionFactory->create('test')->queue(), 'queue-name', 0), $factory->create('queue://test/queue-name'));
    }

    /**
     *
     */
    public function test_create_queue_with_prefetch_option()
    {
        $factory = new DestinationFactory($this->connectionFactory);

        $this->assertEquals(new QueueDestination($this->connectionFactory->create('test')->queue(), 'queue-name', 5), $factory->create('queue://test/queue-name?prefetch=5'));
    }

    /**
     *
     */
    public function test_create_topic()
    {
        $factory = new DestinationFactory($this->connectionFactory);

        $this->assertEquals(new ReadableTopicDestination($this->connectionFactory->create('test')->topic(), 'topic-name'), $factory->create('topic://test/topic-name'));
        $this->assertEquals(new TopicDestination($this->connectionFactory->create('other')->topic(), 'topic-name'), $factory->create('topic://other/topic-name'));
    }

    /**
     *
     */
    public function test_create_multi_queue()
    {
        $factory = new DestinationFactory($this->connectionFactory);

        $this->assertEquals(new MultiQueueDestination($this->connectionFactory->create('test')->queue(), ['q1', 'q2', 'q3']), $factory->create('queues://test/q1,q2,q3'));
    }

    /**
     *
     */
    public function test_create_multi_topic()
    {
        $factory = new DestinationFactory($this->connectionFactory);

        $this->assertEquals(new MultiTopicDestination($this->connectionFactory->create('test')->topic(), ['topic.*']), $factory->create('topics://test/topic.*'));
    }

    /**
     *
     */
    public function test_create_with_invalid_scheme()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The destination type invalid is not supported, for destination invalid://test');

        $factory = new DestinationFactory($this->connectionFactory);

        $factory->create('invalid://test');
    }

    /**
     *
     */
    public function test_register()
    {
        $expected = $this->createMock(DestinationInterface::class);
        $factory = new DestinationFactory($this->connectionFactory);

        $factory->register('test', function () use($expected) { return $expected; });

        $this->assertSame($expected, $factory->create('test:'));
    }

    /**
     *
     */
    public function test_create_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The destination not_found is not configured');

        $factory = new DestinationFactory($this->connectionFactory, [], false);

        $factory->create('not_found');
    }

    /**
     *
     */
    public function test_create_success()
    {
        $factory = new DestinationFactory($this->connectionFactory, ['dest' => 'queue://test/my-queue']);

        $destination = $factory->create('dest');

        $this->assertEquals($destination, new QueueDestination($this->connectionFactory->create('test')->queue(), 'my-queue', 0));
        $this->assertSame($destination, $factory->create('dest'));
    }

    public function test_create_aggregate()
    {
        $factory = new DestinationFactory($this->connectionFactory, [
            'foo' => 'queue://test/my-queue',
            'bar' => 'topic://test/my-topic',
            'baz' => 'queue://test/other',
        ]);

        $destination = $factory->create('aggregate://foo,bar,baz');

        $this->assertEquals(new AggregateDestination([
            $factory->create('foo'),
            $factory->create('bar'),
            $factory->create('baz'),
        ]), $destination);
    }

    public function test_create_aggregate_with_wildcard()
    {
        $factory = new DestinationFactory($this->connectionFactory, [
            'foo' => 'queue://test/my-queue',
            'bar' => 'topic://test/my-topic',
            'baz' => 'queue://test/other',
            'aaa' => 'queue://test/aaa',
        ]);

        $destination = $factory->create('aggregate://*o*,b*');

        $this->assertEquals(new AggregateDestination([
            $factory->create('foo'),
            $factory->create('bar'),
            $factory->create('baz'),
        ]), $destination);
    }

    /**
     *
     */
    public function test_getting_destination_names()
    {
        $factory = new DestinationFactory($this->connectionFactory, [
            'test1' => 'queue://test/my-queue',
            'test2' => 'queue://test/your-queue',
        ]);

        $this->assertSame(['test1', 'test2'], $factory->destinationNames());
    }
}
