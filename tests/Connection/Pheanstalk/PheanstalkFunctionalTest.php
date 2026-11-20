<?php

namespace Connection\Pheanstalk;

use Bdf\Queue\Connection\Pheanstalk\PheanstalkConnection;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\TestCase;

use function pcntl_fork;
use function usleep;
use function var_dump;

class PheanstalkFunctionalTest extends TestCase
{
    private PheanstalkConnection $connection;
    private string $queueName;

    protected function setUp(): void
    {
        $host = getenv('PHEANSTALK_HOST') ?: '127.0.0.1';
        $port = getenv('PHEANSTALK_PORT') ?: 11300;

        if (!@fsockopen($host, $port, $errno, $errstr, 1)) {
            $this->markTestSkipped('Pheanstalk server is not available');
        }

        $this->connection = new PheanstalkConnection('test', new JsonSerializer());
        $this->connection->setConfig([
            'host' => $host,
            'port' => $port,
        ]);

        $this->queueName = 'test_queue_' . bin2hex(random_bytes(5));
    }

    public function test_queuePushPop()
    {
        $queue = $this->connection->queue();

        $this->assertEquals(0, $queue->count($this->queueName));
        $queue->push(
            (new Message(['foo' => 'bar']))
                ->setQueue($this->queueName)
        );
        $this->assertEquals(1, $queue->count($this->queueName));

        $message = $queue->pop($this->queueName);
        $this->assertEquals($this->queueName, $message->message()->queue());
        $this->assertSame(['foo' => 'bar'], $message->message()->data());
        $this->assertFalse($message->isRejected());
        $this->assertFalse($message->isDeleted());

        $message->acknowledge();

        $this->assertEquals(0, $queue->count($this->queueName));
        $this->assertNull($queue->pop($this->queueName, 1));
    }

    public function test_queueRelease()
    {
        $queue = $this->connection->queue();
        $this->assertSame(0, $queue->count($this->queueName));
        $queue->push(
            (new Message(['foo' => 'bar']))
                ->setQueue($this->queueName)
        );
        $this->assertSame(1, $queue->count($this->queueName));

        $message = $queue->pop($this->queueName);
        $this->assertSame(['foo' => 'bar'], $message->message()->data());
        $this->assertFalse($message->isRejected());
        $this->assertFalse($message->isDeleted());

        $queue->release($message->message());
        $this->assertSame(1, $queue->count($this->queueName));
        $this->assertEquals($message, $queue->pop($this->queueName));
        $this->assertFalse($message->isRejected());
        $this->assertFalse($message->isDeleted());

        $message->acknowledge();
    }

    public function test_queueStats()
    {
        $queue = $this->connection->queue();
        $stats = $queue->stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('queues', $stats);
        $this->assertArrayHasKey('workers', $stats);
    }

    public function test_topic()
    {
        $messages = [];

        $topic = $this->connection->topic();
        $topic->subscribe(['topic.test'], function (QueueEnvelope $envelope) use (&$messages) {
            $messages[] = $envelope->message()->data();
            $envelope->acknowledge();
        });

        if (pcntl_fork() === 0) {
            usleep(50000);
            $topic->publish(
                (new Message(['foo' => 'bar']))
                    ->setTopic('topic.test')
            );
            exit;
        }

        $topic->consume(2);
        $this->assertEquals([['foo' => 'bar']], $messages);
    }
}
