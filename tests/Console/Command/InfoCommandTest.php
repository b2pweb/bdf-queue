<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Message\Message;
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
class InfoCommandTest extends TestCase
{
    /** @var Container */
    private $container;
    /** @var DestinationManager */
    private $manager;

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
        (new QueueServiceProvider())->configure($this->container);

        $this->manager = $this->container->get(DestinationManager::class);
    }
    
    /**
     * 
     */
    public function test_info()
    {
        $this->manager->send(Message::create([], 'test1')->setConnection('test'));
        $this->manager->send(Message::create([], 'test1')->setConnection('test'));
        $this->manager->send(Message::create([], 'test2')->setConnection('test'));
        $this->manager->send(Message::create([], 'test2', 10)->setConnection('test'));

        $command = new InfoCommand($this->container->get(ConnectionDriverFactoryInterface::class));
        $tester = new CommandTester($command);
        $tester->execute(['connection' => ['test']]);

        $expected = <<<EOF
Server: test
------ Report: queues
┌───────┬───────────────┬───────────────┬──────────────┬──────────────┐
│ queue │ jobs in queue │ jobs awaiting │ jobs running │ jobs delayed │
├───────┼───────────────┼───────────────┼──────────────┼──────────────┤
│ test1 │ 2             │ 2             │ 0            │ 0            │
│ test2 │ 2             │ 1             │ 0            │ 1            │
└───────┴───────────────┴───────────────┴──────────────┴──────────────┘

EOF;

        $this->assertSame($expected, $tester->getDisplay());
    }

    /**
     *
     */
    public function test_no_info()
    {
        $command = new InfoCommand($this->container->get(ConnectionDriverFactoryInterface::class));
        $tester = new CommandTester($command);
        $tester->execute(['connection' => ['test']]);

        $expected = <<<EOF
Server: test
------ Report: queues
No result found

EOF;

        $this->assertSame($expected, $tester->getDisplay());
    }

    /**
     *
     */
    public function test_wrong_filter()
    {
        $command = new InfoCommand($this->container->get(ConnectionDriverFactoryInterface::class));
        $tester = new CommandTester($command);
        $tester->execute(['connection' => ['test'], '--filter' => 'unknown']);

        $expected = <<<EOF
Server: test
------ Report: queues
No result found

EOF;

        $this->assertSame($expected, $tester->getDisplay());
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function test_complete(array $input, array $expectedSuggestions)
    {
        if (!class_exists(CommandCompletionTester:: class)) {
            $this->markTestSkipped();
        }

        $command = new InfoCommand($this->container->get(ConnectionDriverFactoryInterface::class));
        $application = new Application();
        $application->add($command);

        $tester = new CommandCompletionTester($application->get('queue:info'));
        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'namespace' => [
            [''],
            ['test'],
        ];

        yield 'namespace started' => [
            ['t'],
            ['test'],
        ];

        yield 'filter option' => [
            ['--filter'],
            ['queues', 'workers'],
        ];
    }

    /**
     *
     */
    public function test_json_format()
    {
        $this->manager->send(Message::create([], 'test1')->setConnection('test'));
        $this->manager->send(Message::create([], 'test1')->setConnection('test'));
        $this->manager->send(Message::create([], 'test2')->setConnection('test'));
        $this->manager->send(Message::create([], 'test2', 10)->setConnection('test'));

        $command = new InfoCommand($this->container->get(ConnectionDriverFactoryInterface::class));
        $tester = new CommandTester($command);
        $tester->execute(['connection' => ['test'], '--format' => 'json']);

        $expected = ['queues' => [
            ['queue' => 'test1', 'jobs in queue' => 2, 'jobs awaiting' => 2, 'jobs running' => 0, 'jobs delayed' => 0],
            ['queue' => 'test2', 'jobs in queue' => 2, 'jobs awaiting' => 1, 'jobs running' => 0, 'jobs delayed' => 1],
        ]];

        $this->assertSame($expected, json_decode($tester->getDisplay(), true));
    }
}