<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Destination\Queue\MultiQueueDestination;
use Bdf\Queue\Destination\Queue\QueueDestination;
use Bdf\Queue\Destination\Topic\MultiTopicDestination;
use Bdf\Queue\Destination\Topic\TopicDestination;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Class DsnDestinationFactoryTest
 */
class DsnDestinationFactoryTest extends TestCase
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
        $container->add('queue.connections', ['test' => 'memory:']);
        (new QueueServiceProvider())->configure($container);

        $this->connectionFactory = $container->get(ResolverConnectionDriverFactory::class);
    }

    /**
     *
     */
    public function test_create_simple_queue()
    {
        $factory = new DsnDestinationFactory($this->connectionFactory);

        $this->assertEquals(new QueueDestination($this->connectionFactory->create('test')->queue(), 'queue-name', 0), $factory->create('queue://test/queue-name'));
    }

    /**
     *
     */
    public function test_create_queue_with_prefetch_option()
    {
        $factory = new DsnDestinationFactory($this->connectionFactory);

        $this->assertEquals(new QueueDestination($this->connectionFactory->create('test')->queue(), 'queue-name', 5), $factory->create('queue://test/queue-name?prefetch=5'));
    }

    /**
     *
     */
    public function test_create_topic()
    {
        $factory = new DsnDestinationFactory($this->connectionFactory);

        $this->assertEquals(new TopicDestination($this->connectionFactory->create('test')->topic(), 'topic-name'), $factory->create('topic://test/topic-name'));
    }

    /**
     *
     */
    public function test_create_multi_queue()
    {
        $factory = new DsnDestinationFactory($this->connectionFactory);

        $this->assertEquals(new MultiQueueDestination($this->connectionFactory->create('test')->queue(), ['q1', 'q2', 'q3']), $factory->create('queues://test/q1,q2,q3'));
    }

    /**
     *
     */
    public function test_create_multi_topic()
    {
        $factory = new DsnDestinationFactory($this->connectionFactory);

        $this->assertEquals(new MultiTopicDestination($this->connectionFactory->create('test')->topic(), ['topic.*']), $factory->create('topics://test/topic.*'));
    }

    /**
     *
     */
    public function test_create_with_invalid_scheme()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The destination type invalid is not supported, for destination invalid://test');

        $factory = new DsnDestinationFactory($this->connectionFactory);

        $factory->create('invalid://test');
    }

    /**
     *
     */
    public function test_register()
    {
        $expected = $this->createMock(DestinationInterface::class);
        $factory = new DsnDestinationFactory($this->connectionFactory);

        $factory->register('test', function () use($expected) { return $expected; });

        $this->assertSame($expected, $factory->create('test:'));
    }

    /**
     *
     */
    public function test_getting_destination_names()
    {
        $factory = new DsnDestinationFactory($this->connectionFactory);
        $factory->register('test', function() {});

        $this->assertSame([], $factory->destinationNames());
    }
}
