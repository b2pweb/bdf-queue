<?php

namespace Bdf\Queue\Destination\Topic;

use BadMethodCallException;
use Bdf\Queue\Connection\CountableDriverInterface;
use Bdf\Queue\Connection\PeekableDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Destination\ReadableDestinationInterface;
use Bdf\Queue\Message\Message;

/**
 * Topic destination with direct read capabilities like count and peek messages
 */
final class ReadableTopicDestination implements DestinationInterface, ReadableDestinationInterface
{
    /**
     * @var TopicDriverInterface
     */
    private $driver;

    /**
     * The topic name
     *
     * @var string
     */
    private $topic;

    /**
     * @var TopicDestination|null
     */
    private $destination;

    /**
     * @param TopicDriverInterface $driver
     * @param string $topic Topic name, or pattern for read-only topic
     */
    public function __construct(TopicDriverInterface $driver, string $topic)
    {
        $this->driver = $driver;
        $this->topic = $topic;
    }

    /**
     * {@inheritdoc}
     */
    public function consumer(ReceiverInterface $receiver): ConsumerInterface
    {
        return $this->destination()->consumer($receiver);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message): PromiseInterface
    {
        return $this->destination()->send($message);
    }

    /**
     * {@inheritdoc}
     */
    public function raw($payload, array $options = []): void
    {
        $this->destination()->raw($payload, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function declare(): void
    {
        $this->destination()->declare();
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $this->destination()->destroy();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        $topic = $this->driver->connection()->topic();

        if (!$topic instanceof CountableDriverInterface) {
            throw new BadMethodCallException(__METHOD__.' works only with countable connection.');
        }

        return $topic->count($this->topic);
    }

    /**
     * {@inheritdoc}
     */
    public function peek(int $rowCount = 20, int $page = 1): array
    {
        $topic = $this->driver->connection()->topic();

        if (!$topic instanceof PeekableDriverInterface) {
            throw new BadMethodCallException(__METHOD__.' works only with peekable connection.');
        }

        return $topic->peek($this->topic, $rowCount, $page);
    }

    /**
     * Get or create the internal destination instance
     */
    private function destination(): DestinationInterface
    {
        if ($this->destination) {
            return $this->destination;
        }

        return $this->destination = new TopicDestination($this->driver, $this->topic);
    }
}
