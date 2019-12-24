<?php

namespace Bdf\Queue\Connection\Enqueue;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\EnvelopeHelper;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\Message;
use Enqueue\Consumption\FallbackSubscriptionConsumer;
use Interop\Queue\Consumer;
use Interop\Queue\Exception\SubscriptionConsumerNotSupportedException;
use Interop\Queue\Message as EnqueueMessage;
use Interop\Queue\SubscriptionConsumer;

/**
 * Topic driver for enqueue
 */
class EnqueueTopic implements TopicDriverInterface
{
    use EnvelopeHelper;

    /**
     * @var EnqueueConnection
     */
    private $connection;

    /**
     * @var SubscriptionConsumer
     */
    private $consumer;

    /**
     * @var int
     */
    private $consumedMessages = 0;


    /**
     * EnqueueTopic constructor.
     *
     * @param EnqueueConnection $connection
     */
    public function __construct(EnqueueConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function connection(): ConnectionDriverInterface
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        $this->publishRaw($message->topic(), $this->connection->serializer()->serialize($message));
    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $topic, $payload): void
    {
        $context = $this->connection->context();

        $context->createProducer()->send($this->connection->topicDestination($topic), $context->createMessage($payload));
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int
    {
        // The duration for enqueue is in milliseconds
        // The "wait forever" is 0
        // Use -1 for "no wait" (hacker's way, but it works)
        if ($duration === -1) {
            $duration = 0;
        } elseif ($duration === 0) {
            $duration = -1;
        } else {
            $duration *= 1000;
        }

        $this->consumedMessages = 0;

        $this->consumer()->consume($duration);

        return $this->consumedMessages;
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(array $topics, callable $callback): void
    {
        $consumer = $this->consumer();
        $context = $this->connection->context();

        foreach ($topics as $topic) {
            $destination = $this->connection->topicDestination($topic);

            $consumer->subscribe(
                $context->createConsumer($destination),
                function (EnqueueMessage $message, Consumer $consumer) use ($callback, $topic) {
                    ++$this->consumedMessages;

                    // Use topic or queue envelop ?
                    $callback($this->toTopicEnvelope(
                        $this->connection->toQueuedMessage($message->getBody(), $topic, $message)
                    ));

                    $consumer->acknowledge($message);
                }
            );
        }
    }

    /**
     * @return SubscriptionConsumer
     */
    private function consumer(): SubscriptionConsumer
    {
        if ($this->consumer) {
            return $this->consumer;
        }

        try {
            $this->consumer = $this->connection->context()->createSubscriptionConsumer();
        } catch (SubscriptionConsumerNotSupportedException $e) {
            $this->consumer = new FallbackSubscriptionConsumer();
        }

        return $this->consumer;
    }
}
