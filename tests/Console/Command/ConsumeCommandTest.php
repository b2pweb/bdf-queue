<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Tests\BufferLogger;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Console
 */
class ConsumeCommandTest extends TestCase
{
    /** @var Container */
    private $container;
    /** @var DestinationManager */
    private $manager;
    /** @var MemoryConnection */
    private $connection;
    /** @var BufferLogger */
    private $logger;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->logger = new BufferLogger();
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));
        $this->container->add(LoggerInterface::class, $this->logger);
        $this->container->add('queue.connections', [
            'test' => ['driver' => 'memory', 'prefetch' => 5]
        ]);
        $this->container->add('queue.destinations', [
            'foo' => 'queue://test/bar'
        ]);
        (new QueueServiceProvider())->configure($this->container);

        $this->manager = $this->container->get(DestinationManager::class);
        $this->connection = $this->container->get(ConnectionDriverFactoryInterface::class)->create('test');

        QueueObserver::$data = null;
    }

    /**
     *
     */
    public function test_consume_with_connection_name()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], 'foo', 'bar')->setConnection('test');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'test',
            '--queue' => 'bar',
            '--max' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('[test::bar] "Bdf\Queue\Console\Command\QueueObserver@handleOk" starting', $logs[0]['message']);
        $this->assertStringContainsString('[test::bar] "Bdf\Queue\Console\Command\QueueObserver@handleOk" succeed', $logs[2]['message']);
    }
    
    /**
     * 
     */
    public function test_consume_queues()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], 'foo', 'test2');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'test',
            '--max' => 1,
            '--memory' => 0,
            '--queue' => 'test1,test2',
            '--duration' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('[test::test2] "Bdf\Queue\Console\Command\QueueObserver@handleOk" succeed', $logs[2]['message']);
    }

    /**
     *
     */
    public function test_consume_with_destination()
    {
        $this->manager->send((new Message('foo'))->setDestination('foo')->setJob([QueueObserver::class, 'handleOk']));

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--max' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('[test::bar] "Bdf\Queue\Console\Command\QueueObserver@handleOk" succeed', $logs[2]['message']);
    }

    /**
     * 
     */
    public function test_fail_consume()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleFail'], '')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--max' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertNotEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('[test::bar] "Bdf\Queue\Console\Command\QueueObserver@handleFail" failed', $logs[2]['message']);
    }

    /**
     *
     */
    public function test_memory_option()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], '')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--max' => 1,
            '--memory' => '2Ko',
        ]);

        $logs = $this->logger->flush();

        $this->assertEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('Receiver stopped due to memory limit of {limit} exceeded', end($logs)['message']);
    }

    /**
     *
     */
    public function test_max_option()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], '')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--max' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('Receiver stopped due to maximum count of {count} exceeded', end($logs)['message']);
    }

    /**
     *
     */
    public function test_expire_option()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], '')->setDestination('foo');
        $this->manager->send($message);
        $message = Message::createFromJob([QueueObserver::class, 'wait'], '')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--expire' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('Receiver stopped due to time limit of {timeLimit}s reached', end($logs)['message']);
    }

    /**
     *
     */
    public function test_stop_when_empty_option()
    {
        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--stopWhenEmpty' => true,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertStringContainsString('The worker will stop for no consuming job', end($logs)['message']);
    }

    /**
     *
     */
    public function test_limit_option()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], '')->setDestination('foo');
        $this->manager->send($message);
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--limit' => 1,
            '--max' => 1,
            '--memory' => 0,
            '--duration' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertStringContainsString('The worker has reached its rate limit of 1', $logs[3]['message']);
    }

    /**
     *
     */
    public function test_retry_option()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleFail'], '')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--retry' => 1,
            '--max' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertStringContainsString('Sending the job "'.QueueObserver::class.'@handleFail" back to queue', $logs[3]['message']);
    }

    /**
     *
     */
    public function test_save_option()
    {
        $this->container->add(FailedJobStorageInterface::class, $this->createMock(FailedJobStorageInterface::class));

        $message = Message::createFromJob([QueueObserver::class, 'handleFail'], '')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--save' => true,
            '--max' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertStringContainsString('Storing the job "'.QueueObserver::class.'@handleFail"', $logs[3]['message']);
    }

    /**
     *
     */
    public function test_middleware_option()
    {
        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], '')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--middleware' => ['bench'],
            '--max' => 1,
            '--memory' => 0,
        ]);

        $logs = $this->logger->flush();

        $this->assertStringContainsString('Benchmark results', end($logs)['message']);
    }

    /**
     *
     */
    public function test_unknown_middleware()
    {
        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            '--middleware' => ['unkwown'],
            '--stopWhenEmpty' => true,
            '--memory' => 0,
        ]);

        $this->assertStringContainsString('Try to add an unknown middleware "unkwown"', $tester->getDisplay());
    }

    /**
     *
     */
    public function test_with_configured_receiver()
    {
        $this->container->add('queue.consumer.config', __DIR__.'/../../Fixtures/receivers.php');

        $message = Message::createFromJob([QueueObserver::class, 'handleOk'], 'foo')->setDestination('foo');
        $this->manager->send($message);

        $command = new ConsumeCommand($this->manager, $this->container->get(ReceiverLoader::class));
        $tester = new CommandTester($command);

        $tester->execute(['connection' => 'foo']);

        $logs = $this->logger->flush();

        $this->assertEquals('triggered', QueueObserver::$data);
        $this->assertStringContainsString('[test::bar] "Bdf\Queue\Console\Command\QueueObserver@handleOk" starting', $logs[0]['message']);
        $this->assertStringContainsString('The worker will stop for no consuming job', end($logs)['message']);
    }
}

class QueueObserver
{
    public static $job;
    public static $data;
    public static $sleep = 1;

    public function handleOk()
    {
        QueueObserver::$data = 'triggered';
    }

    public function handleFail()
    {
        throw new \Exception('foo');
    }

    public function wait()
    {
        sleep(self::$sleep);
    }
}