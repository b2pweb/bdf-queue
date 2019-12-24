<?php

namespace Bdf\Queue\Connection\Enqueue;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\ManageableTopicInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\SerializerInterface;
use Enqueue\ConnectionFactoryFactory;
use Enqueue\ConnectionFactoryFactoryInterface;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\Context;
use Interop\Queue\Queue;
use Interop\Queue\Topic;

/**
 * Driver for adapt the enqueue connection system to Bdf queue Driver
 *
 * The vendor parameter is required to resolve the real connection.
 *
 * The DSN is in format : enqueue+[vendor]://[username]@[password]:[host]/[path]?[extraParameters]
 *
 * Only the vendor is required, so the minimal DSN is (for redis) : "enqueue+redis:"
 */
class EnqueueConnection implements ConnectionDriverInterface, ManageableQueueInterface, ManageableTopicInterface
{
    use ConnectionNamed;
    use MessageSerializationTrait;

    /**
     * @var array
     */
    private $config;

    /**
     * @var ConnectionFactoryFactoryInterface
     */
    private $factory;

    /**
     * @var Context
     */
    private $context;


    /**
     * EnqueueDriver constructor.
     *
     * @param string $name The connection name
     * @param SerializerInterface $serializer
     * @param ConnectionFactoryFactoryInterface $factory
     */
    public function __construct(string $name, SerializerInterface $serializer, ConnectionFactoryFactoryInterface $factory = null)
    {
        $this->setName($name);
        $this->serializer = $serializer;
        $this->factory = $factory ?: new ConnectionFactoryFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
        if (empty($config['vendor'])) {
            throw new \InvalidArgumentException('Bad configuration : vendor must be provided.');
        }

        $this->config = $config + ['auto_declare' => false];
    }

    /**
     * {@inheritdoc}
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function queue(): QueueDriverInterface
    {
        return new EnqueueQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function topic(): TopicDriverInterface
    {
        return new EnqueueTopic($this);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if (!$this->context) {
            return;
        }

        $this->context->close();
        $this->context = null;
    }

    /**
     * {@inheritdoc}
     */
    public function declareQueue(string $queue): void
    {
        $context = $this->context;

        if ($context instanceof AmqpContext) {
            $context->declareQueue($context->createQueue($queue));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteQueue(string $queue): void
    {
        $context = $this->context;

        // method_exists must be used : some connections (like redis) provide this method
        // but do not implements AmqpContext or any other interface providing createQueue method
        if (method_exists($context, 'deleteQueue')) {
            $context->deleteQueue($context->createQueue($queue));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function declareTopic(string $topic): void
    {
        $context = $this->context();

        if ($context instanceof AmqpContext) {
            $context->declareTopic($context->createTopic($topic));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTopic(string $topic): void
    {
        $context = $this->context();

        if ($context instanceof AmqpContext) {
            $context->deleteTopic($context->createTopic($topic));
        }
    }

    /**
     * Get the queue destination object
     * This method will declare the queue if auto_declared is configured
     *
     * @param string $queue The queue name
     *
     * @return Queue
     */
    public function queueDestination(string $queue): Queue
    {
        $context = $this->context();
        $queue = $context->createQueue($queue);

        if ($this->config['auto_declare'] && $context instanceof AmqpContext) {
            $context->declareQueue($queue);
        }

        return $queue;
    }

    /**
     * Get the topic destination object
     * This method will declare the topic if auto_declared is configured
     *
     * @param string $topic The topic name
     *
     * @return Topic
     */
    public function topicDestination(string $topic): Topic
    {
        $context = $this->context();
        $topic = $context->createTopic($topic);

        if ($topic instanceof AmqpTopic) {
            $topic->setType(AmqpTopic::TYPE_FANOUT);
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        }

        if ($this->config['auto_declare'] && $context instanceof AmqpContext) {
            $context->declareTopic($topic);
        }

        return $topic;
    }

    /**
     * Get the connection context
     *
     * @return Context
     */
    public function context()
    {
        if ($this->context) {
            return $this->context;
        }

        return $this->context = $this->factory->create(self::configToDsn($this->config))->createContext();
    }

    /**
     * Parse the driver configuration to build the Enqueue connection DSN
     *
     * @param array $config
     *
     * @return string
     */
    private static function configToDsn(array $config): string
    {
        $dsn = $config['vendor'].':';
        unset($config['vendor']);

        $userAndPassword = '';

        if (!empty($config['user'])) {
            $userAndPassword = $config['user'];
            unset($config['user']);

            if (!empty($config['password'])) {
                $userAndPassword .= ':'.$config['password'];
                unset($config['password']);
            }
        }

        if (!empty($config['host'])) {
            $host = $config['host'];
            unset($config['host']);

            if (!empty($userAndPassword)) {
                $host = $userAndPassword.'@'.$host;
            }

            $dsn .= '//'.$host;
        }

        if (!empty($config['queue'])) {
            $dsn .= '/'.$config['queue'];
            unset($config['queue']);
        }

        if (!empty($config)) {
            $dsn .= '?'.http_build_query($config);
        }

        return $dsn;
    }
}
