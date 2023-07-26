<?php

namespace Bdf\Queue\Connection\RdKafka;

use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RdKafka\KafkaConsumer;
use RdKafka\Producer as KafkaProducer;

require_once __DIR__.'/Resources/kafka.php';

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Kafka
 */
class RdKafkaConnectionTest extends TestCase
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
    public function test_setters_getters()
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => null,
            'commitAsync' => false,
            'offset' => null,
            'partition' => RD_KAFKA_PARTITION_UA,
            'group' => '2',
            'global' => ['metadata.broker.list' => '127.0.0.1'],
            'producer' => ['log_level' => 0],
            'consumer' => [],
            'partitioner' => null,
            'flush_timeout' => 100,
            'poll_timeout' => 0,
            'dr_msg_cb' => null,
            'error_cb' => null,
            'rebalance_cb' => null,
            'stats_cb' => null,

        ];

        $this->driver->setConfig([]);
        $this->assertEquals($config, $this->driver->config());
        $this->assertSame($this->kafkaProducer, $this->driver->producer());
        $this->assertSame($this->kafkaConsumer, $this->driver->queueConsumer('queue'));
        $this->assertSame($this->kafkaConsumer, $this->driver->topicConsumer(['queue']));
    }

    /**
     *
     */
    public function test_declare_queue()
    {
        // Just instantiate a consumer: hard to test
        $this->assertNull($this->driver->declareQueue('queue'));
    }

    /**
     *
     */
    public function test_declare_topic()
    {
        // Just instantiate a consumer: hard to test
        $this->assertNull($this->driver->declareTopic('queue'));
    }

    /**
     *
     */
    public function test_delete_queue_not_supported()
    {
        $this->assertNull($this->driver->deleteQueue('queue'));
    }

    /**
     *
     */
    public function test_delete_topic_not_supported()
    {
        $this->assertNull($this->driver->deleteTopic('queue'));
    }

    /**
     *
     */
    public function test_close()
    {
        $this->kafkaConsumer->expects($this->once())->method('unsubscribe');

        $this->driver->close();
        // close once
        $this->driver->close();
    }
}
