<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoaderInterface;
use Bdf\Queue\Consumer\TopicConsumer;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Destination\ReadableDestinationInterface;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\TopicEnvelope;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\IsEqual;
use Psr\Container\ContainerInterface;

/**
 * Helper for perform verifications on topics
 */
class TopicHelper
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $defaultDestination;

    /**
     * @var TopicConsumer[]
     */
    private $consumers = [];

    /**
     * @var TopicEnvelope[][]
     */
    private $received = [];


    /**
     * TopicAsserter constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Initialize topic destinations for message reception
     *
     * @param string ...$destinations The destination names
     *
     * @return $this
     */
    public function init(string... $destinations): TopicHelper
    {
        foreach ($destinations as $destination) {
            $this->consumer($destination);
        }

        return $this;
    }

    /**
     * Get a destination
     *
     * @param string|null $destination The destination name. Can be in format "connectionName::topicName"
     *
     * @return DestinationInterface
     */
    public function destination(string $destination = null): DestinationInterface
    {
        if ($destination === null) {
            $destination = $this->defaultDestination;
        }

        if (strpos($destination, '::') !== false) {
            return $this->manager()->topic(...explode('::', $destination, 2));
        }

        return $this->manager()->create($destination);
    }

    /**
     * Consume a topic
     *
     * @param string|null $destination The destination name. Can be in format "connectionName::topicName"
     * @param bool $reinit Does the consumer should be reinitialised after consummation ? Set to true if you wants to re-call consume() on the topic
     *
     * @return $this
     *
     * @see TopicHelper::consumeAll() For consume all intialized topics
     */
    public function consume(string $destination = null, bool $reinit = false): TopicHelper
    {
        $destination = $destination ?: $this->defaultDestination;
        $this->consumer($destination)->consume(0);
        unset($this->consumers[$destination]);

        if ($reinit) {
            $this->init($destination);
        }

        return $this;
    }

    /**
     * Consume all initialized consumers
     *
     * @param bool $reinit Does the consumers should be reinitialized ?
     *
     * @return $this
     *
     * @see TopicHelper::consume() For consume a single topic
     */
    public function consumeAll(bool $reinit = false): TopicHelper
    {
        foreach ($this->consumers as $consumer) {
            $consumer->consume(0);
        }

        if ($reinit) {
            $destinations = array_keys($this->consumers);
            $this->consumers = [];
            $this->init(...$destinations);
        } else {
            $this->consumers = [];
        }

        return $this;
    }

    /**
     * Get all received messages
     *
     * Note: consume() must be called before to collect messages
     *
     * @param string|null $destination The destination name. Can be in format "connectionName::topicName"
     *
     * @return TopicEnvelope[]
     */
    public function messages(string $destination = null): array
    {
        return $this->received[$destination ?: $this->defaultDestination] ?? [];
    }

    /**
     * Peek sent messages from a topic
     * Unlike `TopicHelper::messages()` consume message is not required for retrieve, and messages are not removed from topic
     *
     * @param string|null $destination The destination name. Can be in format "connectionName::topicName"
     *
     * @return QueuedMessage[]
     */
    public function peek(string $destination = null, int $count = 20, int $page = 1): array
    {
        $destination = $this->destination($destination);

        if (!$destination instanceof ReadableDestinationInterface) {
            throw new \BadMethodCallException('The destination do not supports peeking messages');
        }

        return $destination->peek($count, $page);
    }

    /**
     * Check if the received messages contains the expected message
     *
     * <code>
     * $asserter->contains(function (TopicEnvelope $envelope) {
     *     $envelope->message()->data(); // Manual matching
     * });
     *
     * $asserter->contains((new QueuedMessage('data'))->setConnection(...)); // Check for the message
     * $asserter->contains('my data'); // Check for the message payload
     * $asserter->contains(new IsInstanceOf(MyEvent::class)); // Phpunit constraints can be used
     * </code>
     *
     * @param mixed $expected The excepted message or data
     * @param string|null $destination The destination name. Can be in format "connectionName::topicName"
     *
     * @return bool
     */
    public function contains($expected, string $destination = null): bool
    {
        $messages = $this->messages($destination);

        if (empty($messages)) {
            return false;
        }

        if (is_callable($expected)) {
            return !empty(array_filter($messages, $expected));
        }

        if (!$expected instanceof Constraint) {
            $expected = new IsEqual($expected);
        }

        foreach ($messages as $message) {
            if ($expected->evaluate($message->message(), '', true) || $expected->evaluate($message->message()->data(), '', true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the default destination to use when no destination is given to methods
     *
     * @param string $defaultDestination The destination name. Can be in format "connectionName::topicName"
     *
     * @return $this
     */
    public function setDefaultDestination(string $defaultDestination): TopicHelper
    {
        $this->defaultDestination = $defaultDestination;

        return $this;
    }

    /**
     * Clear the consumers and received messages
     *
     * @return $this
     */
    public function clear(): TopicHelper
    {
        $this->consumers = [];
        $this->received = [];

        return $this;
    }

    /**
     * Get and initialize the consumer
     *
     * @param string|null $destination
     *
     * @return TopicConsumer
     */
    private function consumer(string $destination = null): TopicConsumer
    {
        $destination = $destination ?: $this->defaultDestination;

        if (isset($this->consumers[$destination])) {
            return $this->consumers[$destination];
        }

        /** @var ReceiverBuilder $builder */
        $builder = $this->container->get(ReceiverLoaderInterface::class)->load($destination);

        $builder
            ->stopWhenEmpty()
            ->watch(function ($message) use ($destination) {
                if ($message !== null) {
                    $this->received[$destination][] = $message;
                }
            })
        ;

        $consumer = $this->destination($destination)->consumer($builder->build());

        if (!$consumer instanceof TopicConsumer) {
            throw new \LogicException('Invalid consumer : the destination must be a topic');
        }

        $consumer->subscribe();

        return $this->consumers[$destination] = $consumer;
    }

    private function manager(): DestinationManager
    {
        return $this->container->get(DestinationManager::class);
    }
}
