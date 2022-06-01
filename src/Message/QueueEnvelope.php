<?php

namespace Bdf\Queue\Message;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\Extension\EnvelopeState;

/**
 * Envelope
 */
class QueueEnvelope implements InteractEnvelopeInterface
{
    use EnvelopeState;

    /**
     * The driver queue connection
     *
     * @var QueueDriverInterface
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
     * @param QueueDriverInterface $connection
     * @param QueuedMessage $message
     */
    public function __construct(QueueDriverInterface $connection, QueuedMessage $message)
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
        if ($this->deleted) {
            return;
        }

        $this->deleted = true;

        $this->connection->acknowledge($this->message);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(bool $requeue = false): void
    {
        $this->rejected = true;

        if ($this->deleted) {
            return;
        }

        $this->deleted = true;

        if ($requeue) {
            $this->connection->release($this->message);
        } else {
            // TODO nack
            $this->connection->acknowledge($this->message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retry(int $delay = 0): void
    {
        if (!$this->deleted) {
            $this->reject();
        }

        $this->message->incrementAttempts();
        $this->message->setDelay($delay);

        $this->connection->push($this->message);
    }

    /**
     * {@inheritdoc}
     */
    public function reply($message): void
    {
        if ($this->deleted) {
            return;
        }

        $correlationId = $this->message->header('correlationId');
        $replyTo = $this->message->header('replyTo');

        if (!$correlationId || !$replyTo) {
            return;
        }

        if (!$message instanceof Message) {
            $message = new Message($message);
        }

        $message->addHeader('correlationId', $correlationId);
        $message->setQueue($replyTo);

        $this->connection->push($message);

        $this->acknowledge();
    }
}
