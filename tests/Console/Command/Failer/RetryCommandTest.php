<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobCriteria;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Failer\MemoryFailedJobRepository;
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
        $failer = new MemoryFailedJobRepository();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $tester->execute(['id' => '1']);

        $this->assertEquals(0, $this->queue->count($this->defaultQueue));
        $this->assertMatchesRegularExpression('/^No failed job matches the given ID/', $tester->getDisplay());
    }
    
    /**
     * 
     */
    public function test_retry()
    {
        $failer = new MemoryFailedJobRepository();
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
        $this->assertMatchesRegularExpression('/^Job #1 has been pushed back onto the queue/', $tester->getDisplay());
    }
    
    /**
     * 
     */
    public function test_retry_with_empty_raw()
    {
        $failer = new MemoryFailedJobRepository();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
        ]));
        
        $tester->execute(['id' => '1']);

        $this->assertEquals(0, count($failer->all()));
        $this->assertEquals(0, $this->queue->count($this->defaultQueue));
        $this->assertMatchesRegularExpression('/^Job #1 has been pushed back onto the queue/', $tester->getDisplay());
    }

    /**
     *
     */
    public function test_retry_all()
    {
        $failer = new MemoryFailedJobRepository();
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
        $this->assertMatchesRegularExpression('/^Job #1 has been pushed back onto the queue/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/Job #2 has been pushed back onto the queue/', $tester->getDisplay());
    }

    /**
     * @dataProvider provideOptions
     */
    public function test_retry_with_filter(array $options, array $ids)
    {
        $failer = new MemoryFailedJobRepository();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute($options);

        $this->assertEquals(3 - count($ids), count($failer->all()));
        $this->assertEquals(count($ids), $this->queue->count($this->defaultQueue));

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/Job #' . $id . ' has been pushed back onto the queue/', $tester->getDisplay());
        }
    }

    /**
     *
     */
    public function test_filter_queue_and_connection()
    {
        $failer = new MemoryFailedJobRepository();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'con2',
            'queue' => $this->defaultQueue,
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue2',
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute(['--queue' => $this->defaultQueue, '--connection' => 'test']);

        $this->assertEquals(2, count($failer->all()));
        $this->assertEquals(1, $this->queue->count($this->defaultQueue));

        $this->assertMatchesRegularExpression('/^Job #1 has been pushed back onto the queue/', $tester->getDisplay());
    }

    public function provideOptions()
    {
        return [
            'empty' => [[], [1, 2, 3]],
            'name' => [['--name' => 'Foo'], [1]],
            'name wildcard' => [['--name' => 'Ba*'], [2, 3]],
            'name case insensitive' => [['--name' => 'foo'], [1]],
            'error' => [['--error' => 'my error'], [1]],
            'error wildcard' => [['--error' => 'my*error'], [1, 2]],
            'error wildcard contains' => [['--error' => '*or*'], [1, 2, 3]],
            'failedAt with date' => [['--failedAt' => '2022-01-15 22:14:15'], [2]],
            'failedAt with string' => [['--failedAt' => '2022-01-15 22:14:15'], [2]],
            'failedAt with wildcard' => [['--failedAt' => '2022-01-15*'], [1, 2]],
            'failedAt with operator on value' => [['--failedAt' => '> 2022-01-01'], [1, 2]],
            'none match' => [['--name' => 'Foo', '--error' => '*world*'], []],
        ];
    }

    /**
     *
     */
    public function test_retry_attempts()
    {
        $failer = new MemoryFailedJobRepository();
        $command = new RetryCommand($failer, $this->manager);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => $this->defaultQueue,
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute(['id' => '1']);

        $envelope = $this->queue->pop($this->defaultQueue);
        $this->assertEquals(1, $envelope->message()->header('failer-attempts'));
        $this->assertNotNull($envelope->message()->header('failer-failed-at'));
    }
}
