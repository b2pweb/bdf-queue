<?php

namespace Connection\Pheanstalk;

use Bdf\Queue\Connection\Pheanstalk\PheanstalkConnection;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Message\TopicEnvelope;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\TestCase;

use function pcntl_fork;
use function usleep;

class PheanstalkFunctionalTest extends TestCase
{
    private PheanstalkConnection $connection;

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
    }

    public function test_queuePushPop()
    {
        $queue = $this->connection->queue();
        $queue->push(
            (new Message(['foo' => 'bar']))
                ->setQueue('test')
        );

        $message = $queue->pop('test');
        $this->assertEquals('test', $message->message()->destination());
        $this->assertSame(['foo' => 'bar'], $message->message()->data());
        $this->assertFalse($message->isRejected());
        $this->assertFalse($message->isDeleted());

        $message->acknowledge();

        $this->assertNull($queue->pop('test'));
    }

    public function test_queueRelease()
    {
        $queue = $this->connection->queue();
        $queue->push(
            (new Message(['foo' => 'bar']))
                ->setQueue('test')
        );

        $message = $queue->pop('test');
        $this->assertSame(['foo' => 'bar'], $message->message()->data());
        $this->assertFalse($message->isRejected());
        $this->assertFalse($message->isDeleted());

        $queue->release($message->message());
        $this->assertEquals($message, $queue->pop('test'));
        $this->assertFalse($message->isRejected());
        $this->assertFalse($message->isDeleted());

        $message->acknowledge();
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
