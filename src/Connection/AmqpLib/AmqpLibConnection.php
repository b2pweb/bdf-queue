<?php

namespace Bdf\Queue\Connection\AmqpLib;

use Bdf\Queue\Connection\AmqpLib\Exchange\ExchangeResolverInterface;
use Bdf\Queue\Connection\AmqpLib\Exchange\NamespaceExchangeResolver;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\ManageableTopicInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\SerializerInterface;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * AmqpLibConnection
 */
class AmqpLibConnection implements ConnectionDriverInterface, ManageableQueueInterface, ManageableTopicInterface
{
    use ConnectionNamed;
    use MessageSerializationTrait;

    const FLAG_NOPARAM = 0;

    const FLAG_QUEUE_PASSIVE = 1;
    const FLAG_QUEUE_DURABLE = 2;
    const FLAG_QUEUE_AUTODELETE = 4;
    const FLAG_QUEUE_NOWAIT = 8;
    const FLAG_QUEUE_EXCLUSIVE = 2097152;
    const FLAG_QUEUE_IFUNUSED = 16;
    const FLAG_QUEUE_IFEMPTY = 4194304;

    const FLAG_TOPIC_INTERNAL = 2048;

    const FLAG_CONSUMER_NOACK = 2;

    const FLAG_MESSAGE_MANDATORY = 1;
    const FLAG_MESSAGE_IMMEDIATE = 2;

    /**
     * @var AbstractConnection
     */
    private $connection;

    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var ExchangeResolverInterface
     */
    private $exchangeResolver;

    /**
     * @var array
     */
    private $config;

    /**
     * PhpRedisDriver constructor.
     *
     * @param string $name
     * @param SerializerInterface $serializer
     * @param ExchangeResolverInterface $exchangeResolver
     */
    public function __construct(string $name, SerializerInterface $serializer, ExchangeResolverInterface $exchangeResolver = null)
    {
        $this->name = $name;
        $this->exchangeResolver = $exchangeResolver ?: new NamespaceExchangeResolver();
        $this->setSerializer($serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
        $this->config = $config + [
            'host'     => '127.0.0.1',
            'port'     => 5672,
            'vhost'    => '/',
            'user'     => 'guest',
            'password' => 'guest',
            'sleep_duration' => 200,
            'queue_flags' => self::FLAG_QUEUE_DURABLE,
            'topic_flags' => self::FLAG_NOPARAM,
            'consumer_flags' => self::FLAG_NOPARAM,

            // Prefetch optimisation
            'qos_prefetch_size' => 0,
            'qos_prefetch_count' => 1,
            'qos_global' => false,

            /*
             * AMQP needs queue declaration.
             * This option allows auto declaration when pushing and poping
             */
            'auto_declare' => false,

            // Topic context
            'group' => 'bdf',
        ];

        $this->config['sleep_duration'] *= 1000;
    }

    /**
     * {@inheritdoc}
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Get the amqp connection
     *
     * @return AbstractConnection
     */
    public function connection(): AbstractConnection
    {
        if ($this->connection === null) {
            // create connection with AMQP
            $this->connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );
        }

        return $this->connection;
    }

    /**
     * Set the amqp connection
     *
     * @param AbstractConnection $connection
     */
    public function setAmqpConnection(AbstractConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Gets the amqp channel
     *
     * @return AMQPChannel
     */
    public function channel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->channel = $this->connection()->channel();
            $this->channel->basic_qos(
                $this->config['qos_prefetch_size'],
                $this->config['qos_prefetch_count'],
                $this->config['qos_global']
            );
        }

        return $this->channel;
    }

    /**
     * Set the amqp channel
     *
     * @param AMQPChannel $channel
     */
    public function setAmqpChannel(AMQPChannel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->channel !== null) {
            $this->channel->close();
            $this->channel = null;

            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Check whether the connection is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->channel !== null;
    }

    /**
     * {@inheritdoc}
     *
     * @return AmqpLibQueue
     */
    public function queue(): QueueDriverInterface
    {
        return new AmqpLibQueue($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return AmqpLibTopic
     */
    public function topic(): TopicDriverInterface
    {
        return new AmqpLibTopic($this);
    }

    /**
     * {@inheritdoc}
     */
    public function declareQueue(string $queue): void
    {
        // Exchange should be declared to use ttl queue.
        // The main queue should be bind with a routing key equal to 'x-dead-letter-routing-key'
        $this->channel()->queue_declare(
            $queue,
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_PASSIVE),
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_DURABLE),
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_EXCLUSIVE),
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_AUTODELETE),
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_NOWAIT)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteQueue(string $queue): void
    {
        $this->channel()->queue_delete(
            $queue,
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_IFUNUSED),
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_IFEMPTY),
            (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_NOWAIT)
        );
    }

    /**
     * Declare a delayed queue
     * 
     * @param string        $name
     * @param \DateTime|int $delay
     *
     * @return string  The queue name
     */
    public function declareDelayedQueue($name, $delay = 0)
    {
        if ($delay) {
            list($name) = $this->channel()->queue_declare(
                $name.'_deferred_'.$delay,
                (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_PASSIVE),
                (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_DURABLE),
                (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_EXCLUSIVE),
                (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_AUTODELETE),
                (bool) ($this->config['queue_flags'] & self::FLAG_QUEUE_NOWAIT),
                new AMQPTable([
                    // 'x-dead-letter-exchange' should have same value to exchange name (see basic_publish)
                    'x-dead-letter-exchange'    => '',
                    'x-dead-letter-routing-key' => $name,
                    'x-message-ttl'             => $delay * 1000,
                ])
            );
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function declareTopic(string $name): void
    {
        $this->channel()->exchange_declare(
            $this->exchangeResolver->resolve($name),
            'topic',
            (bool) ($this->config['topic_flags'] & self::FLAG_QUEUE_PASSIVE),
            (bool) ($this->config['topic_flags'] & self::FLAG_QUEUE_DURABLE),
            (bool) ($this->config['topic_flags'] & self::FLAG_QUEUE_AUTODELETE),
            (bool) ($this->config['topic_flags'] & self::FLAG_TOPIC_INTERNAL),
            (bool) ($this->config['topic_flags'] & self::FLAG_QUEUE_NOWAIT)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTopic(string $name): void
    {
        $this->channel()->exchange_delete(
            $this->exchangeResolver->resolve($name),
            (bool) ($this->config['topic_flags'] & self::FLAG_QUEUE_IFUNUSED),
            (bool) ($this->config['topic_flags'] & self::FLAG_QUEUE_NOWAIT)
        );
    }

    /**
     * Bind a list of channels on a topic
     *
     * @param string $topic
     * @param string[] $channels
     *
     * @return string The queue name
     *
     * @todo export strategy for naming queue
     */
    public function bind(string $topic, array $channels)
    {
        $this->declareTopic($topic);

        $exchange = $this->exchangeResolver->resolve($topic);
        $queue = $this->config['group'].'/'.$topic;
        $this->declareQueue($queue);

        foreach ($channels as $channel) {
            $this->channel->queue_bind($queue, $exchange, $channel);
        }

        return $queue;
    }

    /**
     * Unbind a list of channels on a topic
     *
     * @param string $topic
     * @param string[] $channels
     *
     * @return string The queue name
     *
     * @todo export strategy for naming queue
     */
    public function unbind(string $topic, array $channels)
    {
        $exchange = $this->exchangeResolver->resolve($topic);
        $queue = $this->config['group'].'/'.$topic;

        foreach ($channels as $channel) {
            $this->channel->queue_unbind($queue, $exchange, $channel);
        }

        return $queue;
    }

    /**
     * Publish an amqp message
     */
    public function publish(AMQPMessage $amqpMessage, string $topic, string $routing, int $flags = 0): void
    {
        $this->channel()->basic_publish(
            $amqpMessage,
            $this->exchangeResolver->resolve($topic),
            $routing,
            (bool) ($flags & AmqpLibConnection::FLAG_MESSAGE_MANDATORY),
            (bool) ($flags & AmqpLibConnection::FLAG_MESSAGE_IMMEDIATE)
        );
    }

    /**
     * Should the queue be auto declared
     *
     * @return bool
     */
    public function shouldAutoDeclare(): bool
    {
        return $this->config['auto_declare'];
    }

    /**
     * Gets the consumer flags for basic get
     *
     * @return int
     */
    public function consumerFlags(): int
    {
        return $this->config['consumer_flags'];
    }

    /**
     * Gets the sleep duration between 2 basic get
     *
     * @return int
     */
    public function internalSleepDuration(): int
    {
        return $this->config['sleep_duration'];
    }
}
