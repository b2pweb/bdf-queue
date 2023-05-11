<?php

namespace Bdf\Queue\Connection\RdKafka;

use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RdKafka\KafkaConsumer;
use RdKafka\Message as RdKafkaMessage;
use RdKafka\Producer as KafkaProducer;
use RdKafka\ProducerTopic;

require_once __DIR__.'/Resources/kafka.php';

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Kafka
 */
class RdKafkaDriverTest extends TestCase
{
    /** @var RdKafkaConnection */
    private $driver;
    /** @var KafkaConsumer|MockObject */
    private $kafkaConsumer;
    /** @var KafkaProducer|MockObject */
    private $kafkaProducer;

    /**
     * 
     */
    public function setUp(): void
    {
        $this->kafkaProducer = $this->createMock(KafkaProducer::class);
        $this->kafkaConsumer = $this->createMock(KafkaConsumer::class);

        $this->driver = new RdKafkaConnection('foo', new JsonSerializer());
        $this->driver->setProducer($this->kafkaProducer);
        $this->driver->setConsumers(['queue' => $this->kafkaConsumer]);
        $this->driver->setConfig([]);
    }

    /**
     *
     */
    public function test_push()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');

        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->with(RD_KAFKA_PARTITION_UA, 0, $this->stringContains('{"job":"test","data":"foo","queuedAt":{"date":'), null);

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->queue()->push($message);
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_push_error($expected, $internal)
    {
        $this->expectException($expected);
        $message = Message::createFromJob('test', 'foo', 'queue');

        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->willThrowException($internal);

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->queue()->push($message);
    }

    public function provideExceptions()
    {
        return [
            [ConnectionLostException::class, new \RdKafka\Exception('', RD_KAFKA_RESP_ERR__TRANSPORT)],
            [ConnectionLostException::class, new \RdKafka\Exception('', RD_KAFKA_RESP_ERR_NETWORK_EXCEPTION)],
            [ServerException::class, new \RdKafka\Exception()],
        ];
    }

    /**
     *
     */
    public function test_push_raw()
    {
        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->with(RD_KAFKA_PARTITION_UA, 0, 'message');

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->queue()->pushRaw('message', 'queue');
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_push_raw_error($expected, $internal)
    {
        $this->expectException($expected);
        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->willThrowException($internal);

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->queue()->pushRaw('message', 'queue');
    }

    /**
     *
     */
    public function test_pop_empty()
    {
        $this->kafkaConsumer->expects($this->once())->method('consume')->with(3000);

        $this->assertNull($this->driver->queue()->pop('queue', 3));
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_pop_error($expected, $internal)
    {
        $this->expectException($expected);
        $this->kafkaConsumer->expects($this->once())->method('consume')->willThrowException($internal);

        $this->driver->queue()->pop('queue', 3);
    }

    /**
     *
     */
    public function test_pop()
    {
        $kafkaMessage = $this->createMock(RdKafkaMessage::class);
        $kafkaMessage->payload = '{"data":"foo"}';
        $kafkaMessage->partition = 2;
        $kafkaMessage->key = 'key';
        $kafkaMessage->topic_name = 'queue';
        $this->kafkaConsumer->expects($this->once())->method('consume')->with(3000)->willReturn($kafkaMessage);

        $message = $this->driver->queue()->pop('queue', 3)->message();

        $this->assertInstanceOf(QueuedMessage::class, $message);
        $this->assertSame('foo', $message->data());
        $this->assertSame('{"data":"foo"}', $message->raw());
        $this->assertSame('queue', $message->queue());
        $this->assertSame(2, $message->header('partition'));
        $this->assertSame('key', $message->header('key'));
        $this->assertSame($kafkaMessage, $message->internalJob());
    }

    /**
     *
     */
    public function test_acknowledge()
    {
        $kafkaMessage = $this->createMock(RdKafkaMessage::class);
        $this->kafkaConsumer->expects($this->once())->method('commit')->with($kafkaMessage);

        $message = new QueuedMessage();
        $message->setQueue('queue');
        $message->setInternalJob($kafkaMessage);

        $this->driver->queue()->acknowledge($message);
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_acknowledge_error($expected, $internal)
    {
        $this->expectException($expected);
        $kafkaMessage = $this->createMock(RdKafkaMessage::class);
        $this->kafkaConsumer->expects($this->once())->method('commit')->willThrowException($internal);

        $message = new QueuedMessage();
        $message->setQueue('queue');
        $message->setInternalJob($kafkaMessage);

        $this->driver->queue()->acknowledge($message);
    }

    /**
     *
     */
    public function test_acknowledge_async()
    {
        $kafkaMessage = $this->createMock(RdKafkaMessage::class);
        $this->kafkaConsumer->expects($this->once())->method('commitAsync')->with($kafkaMessage);

        $message = new QueuedMessage();
        $message->setQueue('queue');
        $message->setInternalJob($kafkaMessage);

        $this->driver->setConfig(['commitAsync' => true]);
        $this->driver->queue()->acknowledge($message);
    }

    /**
     *
     */
    public function test_release()
    {
        $kafkaMessage = $this->createMock(RdKafkaMessage::class);
        $this->kafkaConsumer->expects($this->once())->method('commit')->with($kafkaMessage);
        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce');
        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $message = new QueuedMessage();
        $message->setQueue('queue');
        $message->setInternalJob($kafkaMessage);

        $this->driver->queue()->release($message);
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_release_error($expected, $internal)
    {
        $this->expectException($expected);
        $kafkaMessage = $this->createMock(RdKafkaMessage::class);
        $this->kafkaConsumer->expects($this->once())->method('commit')->willThrowException($internal);

        $message = new QueuedMessage();
        $message->setQueue('queue');
        $message->setInternalJob($kafkaMessage);

        $this->driver->queue()->release($message);
    }

    /**
     *
     */
    public function test_count()
    {
        $this->assertSame(0, $this->driver->queue()->count('queue'));
    }

    /**
     *
     */
    public function test_stats()
    {
        $this->assertSame([], $this->driver->queue()->stats());
    }

    /**
     *
     */
    public function test_publish()
    {
        $message = Message::createForTopic('queue', 'foo');

        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->with(
            RD_KAFKA_PARTITION_UA,
            0,
            $this->stringContains('{"data":"foo","queuedAt":{"date":'),
            null
        );

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->topic()->publish($message);
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_publish_error($expected, $internal)
    {
        $this->expectException($expected);
        $message = Message::createForTopic('queue', 'foo');

        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->willThrowException($internal);

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->topic()->publish($message);
    }

    /**
     *
     */
    public function test_publish_raw()
    {
        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->with(RD_KAFKA_PARTITION_UA, 0, 'message');

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->topic()->publishRaw('queue', 'message');
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_publish_raw_error($expected, $internal)
    {
        $this->expectException($expected);
        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects($this->once())->method('produce')->willThrowException($internal);

        $this->kafkaProducer->expects($this->once())->method('newTopic')->with('queue')->willReturn($topic);

        $this->driver->topic()->publishRaw('queue', 'message');
    }

    /**
     *
     */
    public function test_consume_empty()
    {
        $this->kafkaConsumer->expects($this->once())->method('consume')->with(3000);

        $topic = $this->driver->topic();
        $topic->subscribe(['queue'], function() {});

        $this->assertSame(0, $topic->consume(3));
    }

    /**
     *
     */
    public function test_consume()
    {
        /** @var QueuedMessage $received */
        $received = null;
        $kafkaMessage = $this->createMock(RdKafkaMessage::class);
        $kafkaMessage->payload = '{"data":"foo"}';
        $kafkaMessage->partition = 2;
        $kafkaMessage->key = 'key';
        $kafkaMessage->topic_name = 'queue';
        $this->kafkaConsumer->expects($this->once())->method('consume')->with(3000)->willReturn($kafkaMessage);

        $topic = $this->driver->topic();
        $topic->subscribe(['queue'], function(EnvelopeInterface $envelope) use(&$received) {
            $received = $envelope->message();
        });

        $this->assertSame(1, $topic->consume(3));
        $this->assertSame($kafkaMessage, $received->internalJob());
    }

    /**
     * @dataProvider provideExceptions
     */
    public function test_consume_error($expected, $internal)
    {
        $this->expectException($expected);
        $this->kafkaConsumer->expects($this->once())->method('consume')->willThrowException($internal);

        $topic = $this->driver->topic();
        $topic->subscribe(['queue'], function() {});

        $this->assertSame(0, $topic->consume(3));
    }
}
