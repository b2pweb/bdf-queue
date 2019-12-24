<?php

namespace Bdf\Queue\Message;

use Bdf\Queue\Exception\SerializationException;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Message
 */
class MessageTest extends TestCase
{
    /**
     * 
     */
    public function test_default_value()
    {
        $message = new Message();

        $this->assertNull($message->name());
        $this->assertNull($message->job());
        $this->assertNull($message->data());
        $this->assertNull($message->queue());
        $this->assertNull($message->connection());
        $this->assertNull($message->destination());
        $this->assertNull($message->maxTries());
        $this->assertSame(0, $message->delay());
        $this->assertNull($message->noStore());
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->queuedAt());
    }

    /**
     *
     */
    public function test_name()
    {
        $message = new Message();

        $this->assertSame(null, $message->name());

        $message->setJob('job');
        $this->assertSame('job', $message->name());

        $message->setName('foo');
        $this->assertSame('foo', $message->name());
    }

    /**
     *
     */
    public function test_job()
    {
        $message = new Message();

        $message->setJob('job');
        $this->assertSame('job', $message->job());

        $message->setJob(['class', 'method']);
        $this->assertSame('class@method', $message->job());

        $message->setJob([$this, 'method']);
        $this->assertSame(__CLASS__.'@method', $message->job());

    }

    /**
     *
     */
    public function test_accessors()
    {
        $message = new Message();

        $message->setData('payload');
        $this->assertSame('payload', $message->data());

        $message->setMaxTries('1');
        $this->assertSame(1, $message->maxTries());

        $message->setDelay('1');
        $this->assertSame(1, $message->delay());

        $message->disableStore();
        $this->assertSame(true, $message->noStore());

        $message->setQueue('queue');
        $this->assertSame('queue', $message->queue());

        $message->setConnection('connection');
        $this->assertSame('connection', $message->connection());
        $this->assertSame('connection', $message->destination());

        $message->setDestination('connection2');
        $this->assertSame('connection2', $message->connection());
        $this->assertSame('connection2', $message->destination());

        $date = new \DateTimeImmutable();
        $message->setQueuedAt($date);
        $this->assertSame($date, $message->queuedAt());

        $message->setHeaders(['foo' => 'bar']);
        $this->assertSame(['foo' => 'bar'], $message->headers());
        $this->assertSame('bar', $message->header('foo'));
        $this->assertSame('null', $message->header('bar', 'null'));
    }

    /**
     *
     */
    public function test_headers()
    {
        $message = new Message();
        $message->addHeader('foo', 'bar');

        $this->assertSame('bar', $message->header('foo'));
    }

    /**
     *
     */
    public function test_to_queue()
    {
        $message = Message::createFromJob('job', 'data');
        $queued = $message->toQueue();

        $this->assertCount(3, $queued);
        $this->assertSame('job', $queued['job']);
        $this->assertSame('data', $queued['data']);
        $this->assertNotEmpty($queued['queuedAt']);
    }

    /**
     *
     */
    public function test_to_queue_with_headers()
    {
        $message = Message::createFromJob('job', 'data');
        $message->setMaxTries(1);
        $message->setDelay(1);
        $message->disableStore();
        $message->addHeader('foo', 'bar');

        $queued = $message->toQueue();

        $this->assertCount(6, $queued);
        $this->assertSame('job', $queued['job']);
        $this->assertSame('data', $queued['data']);
        $this->assertSame(1, $queued['maxTries']);
        $this->assertSame(true, $queued['noStore']);
        $this->assertSame('bar', $queued['headers']['foo']);
        $this->assertNotEmpty($queued['queuedAt']);
    }

    /**
     *
     */
    public function test_from_queue()
    {
        $now = new \DateTimeImmutable();

        $message = Message::createFromJob('job', 'data');
        $message->setQueuedAt($now);

        $data = [
            'job' => 'job',
            'data' => 'data',
            'queuedAt' => $now,
        ];

        $this->assertEquals($message, Message::fromQueue($data));
    }

    /**
     *
     */
    public function test_from_queue_with_headers()
    {
        $now = new \DateTimeImmutable();

        $message = Message::createFromJob('job', 'data');
        $message->setMaxTries(1);
        $message->disableStore();
        $message->setQueuedAt($now);
        $message->addHeader('foo', 'bar');

        $data = [
            'job' => 'job',
            'data' => 'data',
            'queuedAt' => $now,
            'maxTries' => 1,
            'noStore' => true,
            'headers' => ['foo' => 'bar'],
        ];

        $this->assertEquals($message, Message::fromQueue($data));
    }

    /**
     *
     */
    public function test_denormalize_error()
    {
        $this->expectException(SerializationException::class);

        Message::fromQueue(null);
    }

    /**
     *
     */
    public function test_denormalize()
    {
        $message = Message::fromQueue([
            'job' => 'idContainer@test',
            'data' => '',
        ]);

        $this->assertSame('idContainer@test', $message->job());
        $this->assertSame('', $message->data());
    }

    /**
     *
     */
    public function test_topic_in_header()
    {
        $message = new Message();
        $message->addHeader('topic', 'foo');

        $this->assertSame('foo', $message->topic());
    }

    /**
     *
     */
    public function test_source()
    {
        $message = new Message();

        $message->setQueue('foo');
        $this->assertSame('foo', $message->source());

        $message->setTopic('bar');
        $this->assertSame('bar', $message->source());
    }
}
