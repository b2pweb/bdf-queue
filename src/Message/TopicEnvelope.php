<?php

namespace Bdf\Queue\Message;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\Extension\EnvelopeState;

/**
 * TopicEnvelopeEnvelope
 */
class TopicEnvelope implements EnvelopeInterface
{
    use EnvelopeState;

    /**
     * The driver topic connection
     *
     * @var TopicDriverInterface
     */
    private $connection;

    /**
     * The message of the job.
     *
     * @var QueuedMessage
     */
    private $message;

    /**
     * Envelope constructor.
     *
     * @param TopicDriverInterface $connection
     * @param QueuedMessage $message
     */
    public function __construct(TopicDriverInterface $connection, QueuedMessage $message)
    {
        $this->connection = $connection;
        $this->message = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function message(): QueuedMessage
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function connection(): ConnectionDriverInterface
    {
        return $this->connection->connection();
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(): void
    {
        $this->deleted = true;
    }

    /**
     * {@inheritdoc}
     */
    public function reject(bool $requeue = false): void
    {
        $this->rejected = true;
        $this->deleted = true;
    }
}
