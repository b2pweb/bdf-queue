<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 *
 */
class SetupCommandTest extends TestCase
{
    /** @var Container */
    private $container;
    /** @var DestinationManager */
    private $manager;
    /** @var MemoryConnection */
    private $connection;
    private $defaultQueue = 'queue';

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
            'foo' => 'queue://test/bar'
        ]);
        (new QueueServiceProvider())->configure($this->container);

        $this->manager = $this->container->get(DestinationManager::class);
        $this->connection = $this->container->get(ConnectionDriverFactoryInterface::class)->create('test');
    }

    /**
     *
     */
    public function test_declare_queue()
    {
        $command = new SetupCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'test',
            '--queue'    => $this->defaultQueue,
        ]);

        $this->assertRegExp('/^The destination "test" has been declared/', $tester->getDisplay());
        $this->assertArrayHasKey($this->defaultQueue, $this->connection->storage()->queues);
    }

    /**
     *
     */
    public function test_declare_with_destination()
    {
        $command = new SetupCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
        ]);

        $this->assertRegExp('/^The destination "foo" has been declared/', $tester->getDisplay());
        $this->assertArrayHasKey('bar', $this->connection->storage()->queues);
    }

    /**
     *
     */
    public function test_create_with_topic()
    {
        $command = new SetupCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'test',
            '--topic'    => $this->defaultQueue,
        ]);

        $this->assertRegExp('/^The destination "test" has been declared/', $tester->getDisplay());
        // Could not test topic declaration: assert topic name has not been declare as queue
        $this->assertArrayNotHasKey($this->defaultQueue, $this->connection->storage()->queues);
    }

    /**
     *
     */
    public function test_drop_queue()
    {
        $this->connection->storage()->queues['bar'] = [];

        $command = new SetupCommand($this->manager);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--drop' => true,
        ]);

        $this->assertRegExp('/^The destination "foo" has been deleted/', $tester->getDisplay());
        $this->assertArrayNotHasKey('bar', $this->connection->storage()->queues);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function test_complete(array $input, array $expectedSuggestions)
    {
        if (!class_exists(CommandCompletionTester:: class)) {
            $this->markTestSkipped();
        }

        $command = new SetupCommand($this->manager);
        $application = new Application();
        $application->add($command);

        $tester = new CommandCompletionTester($application->get('queue:setup'));
        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'namespace' => [
            [''],
            ['foo', 'test'],
        ];

        yield 'namespace started' => [
            ['f'],
            ['foo', 'test'],
        ];
    }
}
