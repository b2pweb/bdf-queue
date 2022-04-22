<?php

namespace Bdf\Queue;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\Reader\BufferedReader;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader;
use Bdf\Queue\Consumer\Receiver\MessageCountLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\MessageLoggerReceiver;
use Bdf\Queue\Consumer\Receiver\ProcessorReceiver;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Exception\SerializationException;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Failer\MemoryFailedJobRepository;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\ErrorMessage;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Processor\CallbackProcessor;
use Bdf\Queue\Processor\MapProcessorResolver;
use Bdf\Queue\Testing\MessageStacker;
use Bdf\Queue\Testing\MessageWatcherReceiver;
use Bdf\Queue\Testing\QueueHelper;
use Bdf\Queue\Testing\StackMessagesReceiver;
use Bdf\Queue\Tests\QueueServiceProvider;
use League\Container\Container;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Functionnal
 */
class FunctionnalTest extends TestCase
{
    /** @var Container */
    private $container;
    /** @var DestinationManager */
    private $manager;
    private $defaultQueue = 'test';

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
    public function test_queue_method()
    {
        $message = Message::createFromJob(QueueHandler::class, 'test_queue_method')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);

        $builder = new ReceiverBuilder($this->container);
        $builder
            ->stopWhenEmpty()
            ->max(1)
        ;

        $extension = $builder->build();
        $extension = $watcher = new MessageWatcherReceiver($extension);
        $destination->consumer($extension)->consume(0);

        $this->assertEquals($message->data(), QueueObserver::$data);
        $this->assertEquals(0, $watcher->getLastMessage()->connection()->queue()->count($message->queue()));
    }

    /**
     *
     */
    public function test_queue_with_data_object()
    {
        $message = Message::createFromJob(QueueHandler::class, new QueueMessage('test_queue_with_data_object'))->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);

        $builder = new ReceiverBuilder($this->container);
        $builder
            ->stopWhenEmpty()
            ->max(1)
        ;

        $destination->consumer($builder->build())->consume(0);

        $this->assertEquals($message->data(), QueueObserver::$data);
    }

    /**
     *
     */
    public function test_push_message_on_queue_should_set_the_queue_name_on_the_message()
    {
        $message = Message::create('test_queue_method')->setDestination('test::my-queue');

        $destination = $this->manager->for($message);
        $destination->send($message);

        $builder = new ReceiverBuilder($this->container);
        $builder
            ->stopWhenEmpty()
            ->outlet($stack = new StackMessagesReceiver())
        ;

        $destination->consumer($builder->build())->consume(0);

        $this->assertNotNull($stack->last());
        $this->assertEquals('my-queue', $stack->last()->message()->queue());
    }

    /**
     *
     */
    public function test_receiving_error_message()
    {
        $destination = $this->manager->queue('test');
        $destination->raw('empty');

        $builder = new ReceiverBuilder($this->container);
        $builder
            ->stopWhenEmpty()
            ->max(1)
            ->outlet($stack = new StackMessagesReceiver())
        ;

        $destination->consumer($builder->build())->consume(0);

        $this->assertInstanceOf(ErrorMessage::class, $stack->last()->message());
    }

    /**
     *
     */
    public function test_error_message_throwing_exception()
    {
        $this->expectException(SerializationException::class);

        $destination = $this->manager->queue('test');
        $destination->raw('empty');

        $builder = new ReceiverBuilder($this->container);
        $builder
            ->stopWhenEmpty()
            ->max(1)
            ->handler(function(){})
        ;

        $destination->consumer($builder->build())->consume(0);
    }

    /**
     *
     */
    public function test_pop_an_empty_queue()
    {
        $destination = $this->manager->queue(null);

        $stack = new StackMessagesReceiver();
        $destination->consumer(new StopWhenEmptyReceiver($stack))->consume(0);

        $this->assertNull($stack->last());
    }

    /**
     *
     */
    public function test_pop_serialization_error()
    {
        /** @var ConnectionDriverInterface $connection */
        $connection = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue);
        $queue = $connection->queue();
        $queue->pushRaw('{job, data}', 'queue');
        $envelope = $queue->pop('queue');

        $this->assertInstanceOf(ErrorMessage::class, $envelope->message());
    }

    /**
     *
     */
    public function test_job_deletion()
    {
        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();
        $queue->pushRaw('{job, data}', 'queue');

        $envelope = $queue->pop('queue');
        $this->assertEquals(1, $queue->count('queue'));

        $envelope->acknowledge();
        $this->assertEquals(0, $queue->count('queue'));
    }

    /**
     *
     */
    public function test_worker_consume_job()
    {
        $watcher = new MessageStacker();
        $message = Message::createFromJob(QueueHandler::class, 'test message')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);

        $helper = new QueueHelper($this->container);
        $helper->consume(1, $destination, function(ReceiverBuilder $builder) use($watcher) {
            $builder->watch($watcher);
        });

        $this->assertEquals($message->data(), QueueObserver::$data);
        $this->assertFalse($watcher->last()->isRejected());
        $this->assertTrue($watcher->last()->isDeleted());
    }

    /**
     *
     */
    public function test_worker_consume_basic_message()
    {
        $lastMessage = null;
        $lastJob = null;

        $message = Message::create('Hello world')->setDestination('test::queue');
        $destination = $this->manager->for($message);
        $destination->send($message);

        $this->container->get(ReceiverLoader::class)->register('', function (ReceiverBuilder $builder) use(&$lastMessage, &$lastJob) {
            $builder->outlet(
                new ProcessorReceiver(new MapProcessorResolver([
                    'queue' => new CallbackProcessor(function($message, $envelope) use(&$lastMessage, &$lastJob) {
                        $lastMessage = $message;
                        $lastJob = $envelope;
                    })
                ]))
            );
        });

        $helper = new QueueHelper($this->container);
        $helper->consume(1, $destination);

        $this->assertSame('Hello world', $lastMessage);
        $this->assertFalse($lastJob->isRejected());
        $this->assertTrue($lastJob->isDeleted());
    }

    /**
     *
     */
    public function test_worker_consume_empty_queue()
    {
        $lastJob = false;

        $helper = new QueueHelper($this->container);
        $helper->consume(1, $this->defaultQueue, function(ReceiverBuilder $builder) use(&$lastJob) {
            $builder->watch(function($envelope) use(&$lastJob) {$lastJob = $envelope;});
        });

        $this->assertNull($lastJob);
    }

    /**
     *
     */
    public function test_worker_consume_many_queues()
    {
        $message = Message::createFromJob(QueueHandler::class, 'test message')->setDestination('test::test2');

        $destination = $this->manager->for($message);
        $destination->send($message);

        $helper = new QueueHelper($this->container);
        $helper->consume(1, 'test::test1,test2');

        $this->assertEquals($message->data(), QueueObserver::$data);
    }

    /**
     *
     */
    public function test_release_all_prefetched_jobs()
    {
        $message = Message::createFromJob(QueueHandler::class, 'test message')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);
        $destination->send($message);
        $destination->send($message);

        $helper = new QueueHelper($this->container);
        $helper->consume(1, $destination);

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();
        $this->assertSame(2, $queue->stats()['queues'][0]['jobs in queue']);
    }

    /**
     *
     */
    public function test_worker_fail_job()
    {
        $message = Message::createFromJob(QueueHandler::class.'@error', 'test_queue_failed')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);

        try {
            $helper = new QueueHelper($this->container);
            $helper->consume(1, $destination);

            $this->fail('Worker should raised exception');
        } catch (\Exception $e) {
        }

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();
        $this->assertEquals(0, $queue->count($this->defaultQueue));
    }

    /**
     *
     */
    public function test_worker_retry_job()
    {
        $watcher = new MessageStacker();
        $message = Message::createFromJob(QueueHandler::class.'@error', 'test_queue_failed')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);

        $helper = new QueueHelper($this->container);
        $helper->consume(1, $destination, function(ReceiverBuilder $builder) use($watcher) {
            $builder->retry(3);
            $builder->watch($watcher);
        });

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();

        $this->assertEquals(1, $queue->count($this->defaultQueue));
        $this->assertTrue($watcher->last()->isRejected());
        $this->assertEquals(2, $watcher->last()->message()->attempts());
    }

    /**
     *
     */
    public function test_max_try()
    {
        $watcher = new MessageStacker();
        $message = Message::createFromJob(QueueHandler::class.'@error', 'test_queue_failed')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);

        try {
            $helper = new QueueHelper($this->container);
            $helper->consume(1, $destination, function(ReceiverBuilder $builder) use($watcher) {
                $builder->retry(1, 0);
                $builder->watch($watcher);
            });
        } catch (\Exception $exception) {

        }

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();

        $this->assertEquals(1, $queue->count($this->defaultQueue));
        $this->assertTrue($watcher->last()->isRejected());
        $this->assertEquals(2, $watcher->last()->message()->attempts());

        $watcher->clear();

        try {
            $helper = new QueueHelper($this->container);
            $helper->consume(1, $destination, function (ReceiverBuilder $builder) use ($watcher) {
                $builder->retry(1, 0);
                $builder->watch($watcher);
            });
        } catch (\Exception $exception) {

        }

        $this->assertEquals(0, $queue->count($this->defaultQueue));
        $this->assertTrue($watcher->last()->isRejected());
        $this->assertEquals(2, $watcher->last()->message()->attempts());
    }

    /**
     *
     */
    public function test_disable_max_try()
    {
        $watcher = new MessageStacker();
        $message = Message::createFromJob(QueueHandler::class.'@error', 'test_queue_failed')->setDestination('test::test');
        $message->setMaxTries(-1);

        $destination = $this->manager->for($message);
        $destination->send($message);

        try {
            $helper = new QueueHelper($this->container);
            $helper->consume(1, $destination, function (ReceiverBuilder $builder) use ($watcher) {
                $builder->retry(1, 0);
                $builder->watch($watcher);
            });
        } catch (\Exception $exception) {

        }

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();

        $this->assertTrue($watcher->last()->isRejected());
        $this->assertEquals(1, $watcher->last()->message()->attempts());
        $this->assertEquals(0, $queue->count($this->defaultQueue));
    }

    /**
     *
     */
    public function test_memory_limit_reached()
    {
        $message = Message::createFromJob(QueueHandler::class, 'test_queue')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);
        $destination->send($message);

        $helper = new QueueHelper($this->container);
        $helper->consume(2, $destination, function(ReceiverBuilder $builder) {
            $builder->memory(1);
        });

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();
        $this->assertEquals(1, $queue->count($this->defaultQueue));
    }

    /**
     *
     */
    public function test_memory_limit()
    {
        $watcher = new MessageStacker();
        $message = Message::createFromJob(QueueHandler::class, 'test_queue')->setDestination('test::test');

        $destination = $this->manager->for($message);
        $destination->send($message);

        $helper = new QueueHelper($this->container);
        $helper->consume(1, $destination, function(ReceiverBuilder $builder) use($watcher) {
            $builder->memory(1, function() {return 0;});
            $builder->watch($watcher);
        });

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();
        $this->assertEquals(0, $queue->count($this->defaultQueue));
        $this->assertEquals($message->data(), QueueObserver::$data);
        $this->assertFalse($watcher->last()->isRejected());
    }

    /**
     *
     */
    public function test_reserve()
    {
        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();
        $queue->push(Message::create('foo1', 'queue'));
        $queue->push(Message::create('foo2', 'queue'));
        $queue->push(Message::create('foo3', 'queue'));

        /** @var EnvelopeInterface[] $envelopes */
        $envelopes = $queue->reserve(2, 'queue');

        $this->assertCount(2, $envelopes);
        $this->assertSame('foo1', $envelopes[0]->message()->data());
        $this->assertSame('foo2', $envelopes[1]->message()->data());
        $this->assertSame(1, $queue->stats()['queues'][0]['jobs awaiting']);
        $this->assertSame(2, $queue->stats()['queues'][0]['jobs running']);
    }

    /**
     *
     */
    public function test_pretech_connection_release_all()
    {
        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();
        $queue->push(Message::create('foo1', 'queue'));
        $queue->push(Message::create('foo2', 'queue'));
        $queue->push(Message::create('foo3', 'queue'));

        $reader = new BufferedReader($queue, 'queue', 5);
        $reader->read(1);

        $this->assertSame(3, $queue->stats()['queues'][0]['jobs running']);
        $this->assertSame(0, $queue->stats()['queues'][0]['jobs awaiting']);

        $reader->stop();
        $this->assertSame(1, $queue->stats()['queues'][0]['jobs running']);
        $this->assertSame(2, $queue->stats()['queues'][0]['jobs awaiting']);
    }

    /**
     *
     */
    public function test_failure()
    {
        $failer = new MemoryFailedJobRepository();
        $this->container->add(FailedJobStorageInterface::class, $failer);

        $message = QueuedMessage::createFromJob(QueueHandler::class.'@error', 'test_queue_failed')->setDestination('test::test');
        $message->setAttempts(3);

        $destination = $this->manager->for($message);
        $destination->send($message);

        try {
            $helper = new QueueHelper($this->container);
            $helper->consume(1, $destination, function(ReceiverBuilder $builder) {
                $builder->store();
            });
        } catch (\Exception $exception) {

        }

        $this->assertSame(1, count($failer->all()));
    }

    /**
     *
     */
    public function test_basic_rpc()
    {
        $message = Message::createFromJob(RpcReplyHandler::class, 1);
        $message->setNeedsReply(true);

        $destination = $this->manager->for($message);
        $promise = $destination->send($message);

        $helper = new QueueHelper($this->container);
        $helper->consume(1, $destination);

        $this->assertSame(2, $promise->await()->data());
    }

    /**
     *
     */
    public function test_rpc_received_wrong_job()
    {
        $message = Message::createFromJob(QueueHandler::class, 'test message')->setDestination('test::test');
        $message->setNeedsReply(true);

        $destination = $this->manager->for($message);
        $promise = $destination->send($message);

        /** @var QueueDriverInterface $queue */
        $queue = $this->container->get(ConnectionDriverFactoryInterface::class)->create($this->defaultQueue)->queue();

        $this->assertNull($promise->await());
        $this->assertEquals(1, $queue->count($this->defaultQueue));
    }

    /**
     *
     */
    public function test_debug_receiver()
    {
        $builder = new ReceiverBuilder($this->container);
        $builder
            ->stopWhenEmpty()
            ->log()
            ->max(1)
            ->jobProcessor()
        ;

        $chain = MessageCountLimiterReceiver::class.'->'.MessageLoggerReceiver::class.'->'.StopWhenEmptyReceiver::class.'->'.ProcessorReceiver::class;

        $this->assertEquals($chain, (string)$builder->build());
    }
}

/**
 * Testing classes
 */

class QueueObserver
{
    public static $envelope;
    public static $data;
}

class QueueHandler
{
    protected $name = 'tested';
    
    public function handle($data, $envelope)
    {
        QueueObserver::$envelope  = $envelope->message()->raw();
        QueueObserver::$data = $data;
    }
    
    public function error($data, $envelope)
    {
        throw new \Exception('test error');
    }
}

class QueueMessage
{
    protected $name;
    
    public function __construct($name)
    {
        $this->name = $name;
    }
    
    public function __toString()
    {
        return $this->name;
    }
}

class RpcReplyHandler
{
    public function handle($data, $envelope)
    {
        $envelope->reply($data * 2);
    }
}