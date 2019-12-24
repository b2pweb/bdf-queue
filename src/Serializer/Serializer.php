<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * Serializer
 */
class Serializer implements SerializerInterface
{
    /**
     * {@inheritdoc}
     */
    public function serialize(Message $message)
    {
        return serialize($message->toQueue());
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($raw, $messageClass = QueuedMessage::class): QueuedMessage
    {
        return $messageClass::fromQueue(unserialize($raw));
    }
}
