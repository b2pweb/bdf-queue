<?php

namespace Bdf\Queue\Message;

/**
 * Message from queue
 * Should be allowed to be pushed in queue
 */
class QueuedMessage extends Message
{
    /**
     * The current number of attempts to invoke the job
     *
     * @var int
     */
    private $attempts = 1;

    /**
     * The serialized payload
     *
     * @var string
     */
    private $raw;

    /**
     * The message broker job object.
     *
     * Internal: provides by the message broker in the consumer.
     *
     * @var mixed
     */
    private $internalJob;

    /**
     * {@inheritdoc}
     */
    public function toQueue(): array
    {
        $payload = parent::toQueue();

        if ($this->attempts > 1) {
            $payload['attempts'] = $this->attempts;
        }

        return $payload;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromQueue($data): Message
    {
        $message = parent::fromQueue($data);

        if (isset($data['attempts'])) {
            $message->attempts = $data['attempts'];
        }

        return $message;
    }

    /**
     * Reset the number of retries
     * Used when storing a failed job
     *
     * @return $this
     */
    public function resetAttempts()
    {
        $this->attempts = 1;

        return $this;
    }

    /**
     * Notify the message that execution is retried
     *
     * @return $this
     */
    public function incrementAttempts()
    {
        $this->attempts++;

        return $this;
    }

    /**
     * Set the current number of attempts
     *
     * @param int $number
     *
     * @return $this
     */
    public function setAttempts(int $number)
    {
        $this->attempts = $number;

        return $this;
    }

    /**
     * Get current number of execution attempts
     * This value starts at 1 and should be lower than Message::maxTries()
     *
     * @return int
     */
    public function attempts()
    {
        return $this->attempts;
    }

    /**
     * Set the raw message payload (before deserialization)
     *
     * @param string $raw
     *
     * @return $this
     */
    public function setRaw(string $raw)
    {
        $this->raw = $raw;

        return $this;
    }

    /**
     * Get the raw message payload (before deserialization)
     *
     * @return string
     */
    public function raw()
    {
        return $this->raw;
    }

    /**
     * Set the driver job information
     *
     * @param mixed $job
     *
     * @return $this
     */
    public function setInternalJob($job)
    {
        $this->internalJob = $job;

        return $this;
    }

    /**
     * Retrieve driver job information
     *
     * @return mixed
     */
    public function internalJob()
    {
        return $this->internalJob;
    }
}
