<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 * @group Bdf_Queue_Consumer_JobLogger
 */
class JobLoggerReceiverTest extends TestCase
{
    protected $envelope;
    protected $logger;
    protected $buffer;
    /** @var ReceiverInterface|MockObject */
    protected $extension;
    /** @var ConsumerInterface|MockObject */
    protected $consumer;
    /**
     * @var NextInterface|MockObject
     */
    private $next;

    /**
     * 
     */
    protected function setUp(): void
    {
        $this->buffer = new TestHandler();
        $this->buffer->setFormatter(new LineFormatter('[%datetime%] %level_name%: %message% %context% %extra%\n', 'Y-m-d H:i:s'));
        $this->logger = new Logger('test', [$this->buffer]);

        $message = new QueuedMessage();
        $message->setConnection('test-connection');
        $message->setJob('test-job');
        $message->setQueue('test-queue');
        $message->setRaw('{"name":"foo"}');

        $this->envelope = new QueueEnvelope((new NullConnection(''))->queue(), $message);

        $this->extension = $this->createMock(ReceiverInterface::class);
        $this->consumer = $this->createMock(ConsumerInterface::class);
        $this->next = $this->createMock(NextInterface::class);
    }
    
    /**
     * 
     */
    public function test_next_call()
    {
        $this->next->expects($this->once())->method('receive');

        $extension = new MessageLoggerReceiver($this->logger);
        $extension->receive($this->envelope, $this->next);
    }

    /**
     *
     */
    public function test_next_call_legacy()
    {
        $this->extension->expects($this->once())->method('receive');

        $extension = new MessageLoggerReceiver($this->extension, $this->logger);
        $extension->receive($this->envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_start()
    {
        $this->next->expects($this->once())->method('start')->with($this->next);

        $extension = new MessageLoggerReceiver($this->logger);
        $extension->start($this->next);
    }

    /**
     *
     */
    public function test_start_legacy()
    {
        $this->extension->expects($this->once())->method('start')->with($this->consumer);

        $extension = new MessageLoggerReceiver($this->extension, $this->logger);
        $extension->start($this->consumer);
    }

    /**
     *
     */
    public function test_log()
    {
        $extension = new MessageLoggerReceiver($this->logger);
        $extension->receive($this->envelope, $this->next);

        $output = array_filter($this->buffer->getRecords());
        $this->assertCount(3, $output);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] INFO: \[test-connection::test-queue\] "test-job" starting/';
        $this->assertMatchesRegularExpression($regex, $output[0]['formatted']);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] DEBUG: {"name":"foo"}/';
        $this->assertMatchesRegularExpression($regex, $output[1]['formatted']);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] INFO: \[test-connection::test-queue\] "test-job" succeed/';
        $this->assertMatchesRegularExpression($regex, $output[2]['formatted']);
    }

    /**
     *
     */
    public function test_log_legacy()
    {
        $extension = new MessageLoggerReceiver($this->extension, $this->logger);
        $extension->receive($this->envelope, $this->consumer);

        $output = array_filter($this->buffer->getRecords());
        $this->assertCount(3, $output);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] INFO: \[test-connection::test-queue\] "test-job" starting/';
        $this->assertMatchesRegularExpression($regex, $output[0]['formatted']);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] DEBUG: {"name":"foo"}/';
        $this->assertMatchesRegularExpression($regex, $output[1]['formatted']);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] INFO: \[test-connection::test-queue\] "test-job" succeed/';
        $this->assertMatchesRegularExpression($regex, $output[2]['formatted']);
    }

    /**
     * 
     */
    public function test_exception_is_thrown()
    {
        $this->expectExceptionMessage('error');

        $this->next->expects($this->once())->method('receive')->willThrowException(new \Exception('error'));

        $extension = new MessageLoggerReceiver($this->logger);
        $extension->receive($this->envelope, $this->next);
    }

    /**
     *
     */
    public function test_exception_is_thrown_legacy()
    {
        $this->expectExceptionMessage('error');

        $this->extension->expects($this->once())->method('receive')->willThrowException(new \Exception('error'));

        $extension = new MessageLoggerReceiver($this->extension, $this->logger);
        $extension->receive($this->envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_exception()
    {
        $this->next->expects($this->once())->method('receive')->willThrowException(new \Exception('error'));

        $extension = new MessageLoggerReceiver($this->logger);
        try {
            $extension->receive($this->envelope, $this->next);
        } catch (\Exception $exception) {

        }

        $output = array_filter($this->buffer->getRecords());
        $this->assertCount(3, $output);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] CRITICAL: \[test-connection::test-queue\] "test-job" failed/';
        $this->assertMatchesRegularExpression($regex, $output[2]['formatted']);
    }

    /**
     *
     */
    public function test_exception_legacy()
    {
        $this->extension->expects($this->once())->method('receive')->willThrowException(new \Exception('error'));

        $extension = new MessageLoggerReceiver($this->extension, $this->logger);
        try {
            $extension->receive($this->envelope, $this->consumer);
        } catch (\Exception $exception) {

        }

        $output = array_filter($this->buffer->getRecords());
        $this->assertCount(3, $output);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] CRITICAL: \[test-connection::test-queue\] "test-job" failed/';
        $this->assertMatchesRegularExpression($regex, $output[2]['formatted']);
    }

    /**
     *
     */
    public function test_on_stopping()
    {
        $this->next->expects($this->once())->method('receiveStop')->with($this->next);

        $extension = new MessageLoggerReceiver($this->logger);
        $extension->receiveStop($this->next);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] INFO: stopping worker/';

        $this->assertMatchesRegularExpression($regex, $this->buffer->getRecords()[0]['formatted']);
    }

    /**
     *
     */
    public function test_on_stopping_legacy()
    {
        $this->extension->expects($this->once())->method('receiveStop')->with($this->consumer);

        $extension = new MessageLoggerReceiver($this->extension, $this->logger);
        $extension->receiveStop($this->consumer);

        $regex = '/^\[' . str_replace('/', '\\/', date('Y-m-d H:i')) . ':[0-9]{2}\] INFO: stopping worker/';

        $this->assertMatchesRegularExpression($regex, $this->buffer->getRecords()[0]['formatted']);
    }
}
