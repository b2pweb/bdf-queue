<?php

namespace Bdf\Queue\Connection\RdKafka;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\ManageableTopicInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\SerializerInterface;
use Exception;
use RdKafka\Conf as KafkaConf;
use RdKafka\Exception as KafkaException;
use RdKafka\KafkaConsumer;
use RdKafka\Producer as KafkaProducer;
use RdKafka\TopicConf;
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
        $this->config = $config + [
            'host' => '127.0.0.1',
            'global' => [],
            'topic' => [],
            'commitAsync' => false,
            'offset' => null,
            'partition' => RD_KAFKA_PARTITION_UA,
            'group' => '2',
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
        $topicConf = new TopicConf();
        foreach ($this->config['topic'] as $key => $value) {
            $topicConf->set($key, $value);
        }

        if (isset($this->config['partitioner'])) {
            $topicConf->setPartitioner($this->config['partitioner']);
        }

        $kafkaConf = new KafkaConf();
        $kafkaConf->setDefaultTopicConf($topicConf);

        foreach ($this->config['global'] as $key => $value) {
            $kafkaConf->set($key, $value);
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
     * @throws ConnectionFailedException When the connection configuration is invalid
     * @throws ConnectionLostException When the connection is lost
     * @throws ServerException When the server return an error
     * @throws ConnectionException Generic error
     */
    public function queueConsumer($queue)
    {
        if (!isset($this->consumers[$queue])) {
            // Use queue for queue consumer
            $this->consumers[$queue] = $this->createConsumer($queue);

            try {
                if ($this->config['offset'] === null) {
                    $this->consumers[$queue]->subscribe([$queue]);
                } else {
                    $this->consumers[$queue]->assign([
                        new TopicPartition($queue, $this->config['partition'], $this->config['offset']),
                    ]);
                }
            } catch (KafkaException $e) {
                $this->handleException($e);
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
     * @throws ConnectionFailedException When the connection configuration is invalid
     * @throws ConnectionLostException When the connection is lost
     * @throws ServerException When the server return an error
     * @throws ConnectionException Generic error
     */
    public function topicConsumer(array $topics)
    {
        $key = implode('-', $topics);

        if (!isset($this->consumers[$key])) {
            // Use group for topic consumer
            $this->consumers[$key] = $this->createConsumer($this->config['group']);

            try {
                $this->consumers[$key]->subscribe($this->createTopicName($topics));
            } catch (KafkaException $e) {
                $this->handleException($e);
            }
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
            try {
                $consumer->unsubscribe();
            } catch (KafkaException $e) {
                // Ignore
            }
        }

        $this->consumers = [];
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

    /**
     * @param string|null $group The group id
     * @return KafkaConsumer
     *
     * @throws ConnectionFailedException When the configuration is invalid
     */
    private function createConsumer(?string $group): KafkaConsumer
    {
        try {
            return new KafkaConsumer($this->createKafkaConf($group));
        } catch (Exception $e) {
            throw new ConnectionFailedException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Transform the kafka exception to one of bdf queue exception
     */
    private function handleException(KafkaException $e): void
    {
        switch ($e->getCode()) {
            case RD_KAFKA_RESP_ERR__TRANSPORT:
            case RD_KAFKA_RESP_ERR_NETWORK_EXCEPTION:
                throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);

            default:
                throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
