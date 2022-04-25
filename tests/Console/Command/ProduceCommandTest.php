<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Console
 */
class ProduceCommandTest extends TestCase
{
    /** @var Container */
    private $container;
    /** @var DestinationManager */
    private $manager;
    /** @var MemoryConnection */
    private $connection;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));
        $this->container->add('queue.connections', [
            'test' => ['driver' => 'memory', 'prefetch' => 5]
        ]);
        $this->container->add('queue.destinations', [
            'foo' => 'queue://test/queue-foo',
            'bar' => 'topic://test/topic-bar',
        ]);
        (new QueueServiceProvider())->configure($this->container);

        $this->manager = $this->container->get(DestinationManager::class);
        $this->connection = $this->container->get(ConnectionDriverFactoryInterface::class)->create('test');
    }

    /**
     *
     */
    public function test_produce_to_destination()
    {
        $queue = $this->connection->queue();

        $command = new ProduceCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            'message'    => 'foo',
            '--payload'  => true,
        ]);

        $this->assertStringContainsString('Message has been sent in queue.', $tester->getDisplay());
        $this->assertEquals(1, $queue->count('queue-foo'));

        $message = json_decode($queue->pop('queue-foo', 0)->message()->raw(), true);
        $this->assertEquals('foo', $message['data']);
    }

    /**
     *
     */
    public function test_produce_to_topic()
    {
        $last = null;
        $topic = $this->connection->topic();
        $topic->subscribe(['topic-bar'], function($message) use(&$last) {
            $last = $message;
        });

        $command = new ProduceCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'test',
            'message'    => 'foo',
            '--topic'    => 'topic-bar',
            '--payload'  => true,
        ]);

        $this->assertStringContainsString('Message has been published in topic.', $tester->getDisplay());

        $topic->consume(0);

        $message = json_decode($last->message()->raw(), true);
        $this->assertEquals('foo', $message['data']);
    }

    /**
     *
     */
    public function test_produce_to_queue()
    {
        $queue = $this->connection->queue();

        $command = new ProduceCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'test',
            'message'    => 'foo',
            '--queue'    => 'queue-foo',
            '--payload'  => true,
        ]);

        $this->assertStringContainsString('Message has been sent in queue.', $tester->getDisplay());
        $this->assertEquals(1, $queue->count('queue-foo'));

        $message = json_decode($queue->pop('queue-foo', 0)->message()->raw(), true);
        $this->assertEquals('foo', $message['data']);
    }

    /**
     *
     */
    public function test_produce_raw_message()
    {
        $queue = $this->connection->queue();

        $command = new ProduceCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            'message'    => 'foo',
            '--payload'  => false,
        ]);

        $this->assertStringContainsString('Message has been sent.', $tester->getDisplay());
        $this->assertEquals(1, $queue->count('queue-foo'));
        $this->assertEquals('foo', $queue->pop('queue-foo', 0)->message()->raw());
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function test_complete(array $input, array $expectedSuggestions)
    {
        if (!class_exists(CommandCompletionTester:: class)) {
            $this->markTestSkipped();
        }

        $command = new ProduceCommand($this->manager);
        $application = new Application();
        $application->add($command);

        $tester = new CommandCompletionTester($application->get('queue:produce'));
        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'namespace' => [
            [''],
            ['foo', 'bar', 'test'],
        ];

        yield 'namespace started' => [
            ['t'],
            ['foo', 'bar', 'test'],
        ];
    }
}
