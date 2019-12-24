<?php

namespace Bdf\Queue\Failer;

use Bdf\Queue\Message\QueuedMessage;

/**
 * Store failed job message information
 *
 * @internal Used internally by queue failer
 */
class FailedJob
{
    /**
     * The internal identifier
     *
     * @var mixed
     */
    public $id;

    /**
     * The message name
     *
     * @var string
     */
    public $name;

    /**
     * The queue connection name
     *
     * @var string
     */
    public $connection;

    /**
     * The queue name
     *
     * @var string
     */
    public $queue;

    /**
     * The queued message content
     *
     * @var array
     * @see Message::toQueue()
     */
    public $messageContent;

    /**
     * The queue message class
     *
     * @var string
     */
    public $messageClass = QueuedMessage::class;

    /**
     * The failure error
     *
     * @var string
     */
    public $error;

    /**
     * The failure date
     *
     * @var \DateTime
     */
    public $failedAt;

    /**
     * FailedJob constructor.
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->failedAt = new \DateTime();

        foreach ($values as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->$attribute = $value;
            }
        }
    }

    /**
     * Create a failed job
     *
     * @param QueuedMessage $message
     * @param \Throwable|null $exception
     *
     * @return FailedJob
     */
    public static function create(QueuedMessage $message, \Throwable $exception = null)
    {
        // Reset the attemps before the message is stored
        $message = clone $message;
        $message->resetAttempts();

        $failed = new static;
        $failed->name = $message->name();
        $failed->connection = $message->connection();
        $failed->queue = $message->queue();
        $failed->messageContent = $message->toQueue();
        $failed->messageClass = get_class($message);
        $failed->error = $exception ? $exception->getMessage() : null;
        $failed->failedAt = new \DateTime();

        return $failed;
    }

    /**
     * Create the queue message
     *
     * @return null|QueuedMessage
     */
    public function toMessage()
    {
        if (!$this->messageContent) {
            return null;
        }

        /** @var QueuedMessage $message */
        $message = $this->messageClass::fromQueue($this->messageContent);
        $message->setConnection($this->connection);
        $message->setQueue($this->queue);

        return $message;
    }
}
