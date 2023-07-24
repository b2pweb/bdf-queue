<?php

namespace Bdf\Queue\Connection\RdKafka;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\ManageableTopicInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\SerializerInterface;
use RdKafka\Conf as KafkaConf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer as KafkaProducer;
use RdKafka\TopicPartition;

/**
 * RdKafkaConnection
 *
 * @see https://github.com/edenhill/librdkafka
 */
class RdKafkaConnection implements ConnectionDriverInterface, ManageableQueueInterface, ManageableTopicInterface
{
    use ConnectionNamed;
    use MessageSerializationTrait;

    /**
     * @var KafkaProducer
     */
    private $producer;

    /**
     * One consumer by queue
     *
     * @var KafkaConsumer[]
     */
    private $consumers;

    /**
     * @var array
     */
    private $config;

    /**
     * RdKafkaConnection constructor.
     *
     * @param string $name
     * @param SerializerInterface $serializer
     * @param string $default
     */
    public function __construct(string $name, SerializerInterface $serializer)
    {
        $this->name = $name;
        $this->setSerializer($serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
        $this->config = [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? null,
            'global' => $config['global'] ?? [],
            'topic' => $config['topic'] ?? [],
            'commitAsync' => (bool)($config['commitAsync'] ?? false),
            'offset' => $config['offset'] ?? null,
            'partitioner' => $config['partitioner'] ?? null,
            'partition' => (int)($config['partition'] ?? RD_KAFKA_PARTITION_UA),
            'group' => $config['group'] ?? '2',
            'shutdown_timeout' => (int)($config['shutdown_timeout'] ?? -1),
            'dr_msg_cb' => $config['dr_msg_cb'] ?? null,
            'error_cb' => $config['error_cb'] ?? null,
            'rebalance_cb' => $config['rebalance_cb'] ?? null,
            'stats_cb' => $config['stats_cb'] ?? null,
        ];

        $this->config['global']['metadata.broker.list'] = $this->config['host'];

        if (isset($this->config['port'])) {
            $this->config['global']['metadata.broker.list'] .= ':'.$this->config['port'];
        }
    }

    /**
     * Gets the global config
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Create the kafka config
     *
     * @param string|null $group
     *
     * @return KafkaConf
     */
    private function createKafkaConf($group = null)
    {
        $kafkaConf = new KafkaConf();

        foreach ($this->config['topic'] as $key => $value) {
            $kafkaConf->set($key, $value);
        }

        foreach ($this->config['global'] as $key => $value) {
            $kafkaConf->set($key, $value);
        }

        if (isset($this->config['partitioner'])) {
            $kafkaConf->set('partitioner', $this->config['partitioner']);
        }

        if (isset($this->config['dr_msg_cb'])) {
            $kafkaConf->setDrMsgCb($this->config['dr_msg_cb']);
        }

        if (isset($this->config['error_cb'])) {
            $kafkaConf->setErrorCb($this->config['error_cb']);
        }

        if (isset($this->config['rebalance_cb'])) {
            $kafkaConf->setRebalanceCb($this->config['rebalance_cb']);
        }

        if (isset($this->config['stats_cb'])) {
            $kafkaConf->setStatsCb($this->config['stats_cb']);
        }

        if ($group !== null) {
            $kafkaConf->set('group.id', $group);
        }

        return $kafkaConf;
    }

    /**
     * Get the kafka producer
     *
     * @return KafkaProducer
     */
    public function producer()
    {
        if ($this->producer === null) {
            $this->producer = new KafkaProducer($this->createKafkaConf());

            // RdKafkaProducer can store messages internally that need to be delivered before PHP shuts down.
            // Not calling flush can lead to message lost.
            register_shutdown_function([$this, 'flush'], $this->config['shutdown_timeout']);
        }

        return $this->producer;
    }

    /**
     * Set the Kafka producer
     *
     * @param KafkaProducer $producer
     */
    public function setProducer(KafkaProducer $producer)
    {
        $this->producer = $producer;
    }

    /**
     * Get the kafka consumer for queue context
     *
     * @param string $queue The queue name
     *
     * @return KafkaConsumer
     *
     * @throws \RdKafka\Exception
     */
    public function queueConsumer($queue)
    {
        if (!isset($this->consumers[$queue])) {
            // Use queue for queue consumer
            $this->consumers[$queue] = new KafkaConsumer($this->createKafkaConf($queue));

            if ($this->config['offset'] === null) {
                $this->consumers[$queue]->subscribe([$queue]);
            } else {
                $this->consumers[$queue]->assign([
                    new TopicPartition($queue, $this->config['partition'], $this->config['offset']),
                ]);
            }
        }

        return $this->consumers[$queue];
    }

    /**
     * Get the kafka consumer for topic context
     *
     * @param string[] $topics The topic names
     *
     * @return KafkaConsumer
     *
     * @throws \RdKafka\Exception
     */
    public function topicConsumer(array $topics)
    {
        $key = implode('-', $topics);

        if (!isset($this->consumers[$key])) {
            // Use group for topic consumer
            $this->consumers[$key] = new KafkaConsumer($this->createKafkaConf($this->config['group']));
            $this->consumers[$key]->subscribe($this->createTopicName($topics));
        }

        return $this->consumers[$key];
    }

    /**
     * Set the Kafka consumers
     *
     * @param KafkaConsumer[] $consumers
     */
    public function setConsumers(array $consumers)
    {
        $this->consumers = $consumers;
    }

    /**
     * Create the topic name for kafka. Allows pattern.
     *
     * @return string[]
     */
    private function createTopicName($topics)
    {
        $patterns = [];

        foreach ($topics as $topic) {
            if (strpos($topic, '*') !== false) {
                $patterns[] = '^'.$topic;
            } else {
                $patterns[] = $topic;
            }
        }

        return $patterns;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        foreach ($this->consumers as $consumer) {
            $consumer->unsubscribe();
        }

        $this->consumers = [];

        $this->flush($this->config['shutdown_timeout']);
    }

    /**
     * FLush all queued and in-flight requests.
     */
    public function flush(int $timeout): void
    {
        // Compatibility with phprdkafka 4.0.
        if ($this->producer !== null && method_exists($this->producer, 'flush')) {
            $this->producer->flush($timeout);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return RdKafkaDriver
     */
    public function queue(): QueueDriverInterface
    {
        return new RdKafkaDriver($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return RdKafkaDriver
     */
    public function topic(): TopicDriverInterface
    {
        return new RdKafkaDriver($this);
    }

    /**
     * {@inheritdoc}
     */
    public function declareQueue(string $queue): void
    {
        // Create consumer is enough to create queue.
        // Queues have to be created before consumers start.
        // Otherwise the n first messages will be lost.
        $this->queueConsumer($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteQueue(string $queue): void
    {
        // Kafka does not support deletion
    }

    /**
     * {@inheritdoc}
     */
    public function declareTopic(string $topic): void
    {
        $this->topicConsumer([$topic]);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTopic(string $topic): void
    {
        // Kafka does not support deletion
    }

    /**
     * Get the commit async info
     *
     * @return bool
     */
    public function commitAsync(): bool
    {
        return $this->config['commitAsync'];
    }
}
