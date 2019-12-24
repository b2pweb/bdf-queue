<?php

namespace Bdf\Queue\Message;

use Bdf\Queue\Serializer\SerializerInterface;

/**
 * Provide message deserialization for Driver
 */
trait MessageSerializationTrait
{
    /**
     * The message serializer
     *
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Create the queued message from serialized string
     *
     * @todo Que faire pour les topics ?
     *
     * @param string $raw
     * @param string $queue
     * @param mixed|null $internalJob
     *
     * @return QueuedMessage|ErrorMessage
     */
    public function toQueuedMessage(string $raw, string $queue, $internalJob = null): QueuedMessage
    {
        try {
            /** @var QueuedMessage $message */
            $message = $this->serializer->unserialize($raw, QueuedMessage::class);
        } catch (\Throwable $exception) {
            // Exception occured during unserialization:
            // We have to fail this message and release it.
            $message = new ErrorMessage($exception);
        }

        $message->setRaw($raw);
        $message->setQueue($queue);
        $message->setInternalJob($internalJob);

        if (isset($this->name)) {
            $message->setConnection($this->name);
        }

        return $message;
    }

    /**
     * Set the queue serializer
     *
     * @param SerializerInterface $serializer
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Get the queue serializer
     *
     * @return SerializerInterface
     */
    public function serializer(): SerializerInterface
    {
        return $this->serializer;
    }
}