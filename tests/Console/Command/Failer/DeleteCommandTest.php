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
class DeleteCommandTest extends TestCase
{
    /**
     *
     */
    public function test_option_definition()
    {
        $command = new DeleteCommand(
            $this->createMock(FailedJobStorageInterface::class)
        );
        $options = $command->getDefinition()->getArguments();

        $this->assertCount(1, $options);
        $this->assertArrayHasKey('id', $options);
    }

    /**
     * 
     */
    public function test_delete_not_found()
    {
        $failer = new MemoryFailedJobRepository();
        $command = new DeleteCommand($failer);
        $tester = new CommandTester($command);

        $tester->execute(['id' => '1']);

        $this->assertMatchesRegularExpression('/^No failed job matches the given ID/', $tester->getDisplay());
    }
    
    /**
     * 
     */
    public function test_delete()
    {
        $failer = new MemoryFailedJobRepository();
        $command = new DeleteCommand($failer);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute(['id' => '1']);
        
        $this->assertEquals(0, count($failer->all()));
        $this->assertMatchesRegularExpression('/^Failed job deleted successfully/', $tester->getDisplay());
    }

    /**
     *
     */
    public function test_delete_all()
    {
        $failer = new MemoryFailedJobRepository();
        $command = new DeleteCommand($failer);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute([]);

        $this->assertEquals(0, count($failer->all()));
        $this->assertMatchesRegularExpression('/^2 failed jobs deleted successfully/', $tester->getDisplay());
    }

    /**
     * @dataProvider provideOptions
     */
    public function test_delete_with_filter(array $options, array $ids)
    {
        $failer = new MemoryFailedJobRepository();
        $command = new DeleteCommand($failer);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'firstFailedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
            'messageContent' => ['job' => 'showCommand@test'],
            'attempts' => 2,
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'firstFailedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
            'messageContent' => ['job' => 'showCommand@test'],
            'attempts' => 1,
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
            'firstFailedAt' => new \DateTime('2021-12-21 08:00:35'),
            'messageContent' => ['job' => 'showCommand@test'],
            'attempts' => 3,
        ]));

        $tester->execute($options);

        $this->assertEquals(3 - count($ids), count($failer->all()));
        $this->assertMatchesRegularExpression('/^' . count($ids) . ' failed jobs deleted successfully/', $tester->getDisplay());
    }

    /**
     *
     */
    public function test_filter_queue_and_connection()
    {
        $failer = new MemoryFailedJobRepository();
        $command = new DeleteCommand($failer);
        $tester = new CommandTester($command);

        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'firstFailedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'con2',
            'queue' => 'queue',
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'firstFailedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $failer->store(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue2',
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
            'firstFailedAt' => new \DateTime('2021-12-21 08:00:35'),
            'messageContent' => ['job' => 'showCommand@test'],
        ]));

        $tester->execute(['--queue' => 'queue', '--connection' => 'test']);

        $this->assertEquals(2, count($failer->all()));

        $this->assertMatchesRegularExpression('/^1 failed jobs deleted successfully/', $tester->getDisplay());
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
            'firstFailedAt with date' => [['--firstFailedAt' => '2022-01-15 22:14:15'], [2]],
            'firstFailedAt with string' => [['--firstFailedAt' => '2022-01-15 22:14:15'], [2]],
            'firstFailedAt with wildcard' => [['--firstFailedAt' => '2022-01-15*'], [1, 2]],
            'firstFailedAt with operator on value' => [['--firstFailedAt' => '> 2022-01-01'], [1, 2]],
            'attempts' => [['--attempts' => '>= 2'], [1, 3]],
            'none match' => [['--name' => 'Foo', '--error' => '*world*'], []],
        ];
    }
}
