<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Failer\MemoryFailedJobStorage;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 *
 */
class RetryCommandTest extends TestCase
{
    /** @var Container */
    private $container;
    /** @var DestinationManager */
    private $manager;
    /** @var QueueDriverInterface */
    private $queue;
    private $defaultQueue = 'queue';

    /**
     *
     */
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));
        $this->container->add('queue.connections', [
            'test' => ['driver' => 'memory']
        ]);
        (new QueueServiceProvider())->configure($this->container);

        $this->manager = $this->container->get(DestinationManager::class);
        $this->queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create('test')->queue();
    }

    /**
     *
     */
    public function test_option_definition()
    {
        $command = new RetryCommand(
            $this->createMock(FailedJobStorageInterface::class),
            $this->manager
        );
        $options = $command->getDefinition()->getArguments();

        $this->assertCount(1, $options);
        $this->assertArrayHasKey('id', $options);
    }

    /**
     * 
     */
    public function test_retry_empty()
    {
        $failer = new MemoryFailedJobStorage();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $tester->execute(['id' => '1']);

        $this->assertEquals(0, $this->queue->count($this->defaultQueue));
        $this->assertRegExp('/^No failed job matches the given ID/', $tester->getDisplay());
    }
    
    /**
     * 
     */
    public function test_retry()
    {
        $failer = new MemoryFailedJobStorage();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute(['id' => '1']);
        
        $this->assertEquals(0, count($failer->all()));
        $this->assertEquals(1, $this->queue->count($this->defaultQueue));
        $this->assertRegExp('/^Job #1 has been pushed back onto the queue/', $tester->getDisplay());
    }
    
    /**
     * 
     */
    public function test_retry_with_empty_raw()
    {
        $failer = new MemoryFailedJobStorage();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
        ]));
        
        $tester->execute(['id' => '1']);

        $this->assertEquals(0, count($failer->all()));
        $this->assertEquals(0, $this->queue->count($this->defaultQueue));
        $this->assertRegExp('/^Job #1 has been pushed back onto the queue/', $tester->getDisplay());
    }

    /**
     *
     */
    public function test_retry_all()
    {
        $failer = new MemoryFailedJobStorage();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute(['id' => 'all']);

        $this->assertEquals(0, count($failer->all()));
        $this->assertEquals(2, $this->queue->count($this->defaultQueue));
        $this->assertRegExp('/^Job #1 has been pushed back onto the queue/', $tester->getDisplay());
        $this->assertRegExp('/Job #2 has been pushed back onto the queue/', $tester->getDisplay());
    }
}