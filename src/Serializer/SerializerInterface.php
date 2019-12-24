<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * SerializerInterface
 */
interface SerializerInterface
{
    /**
     * Create a string from the given message.
     *
     * @param Message $message
     * 
     * @return string
     */
    public function serialize(Message $message);

    /**
     * Decode data from queue
     * 
     * @param string $raw
     * @param string $messageClass  The class of the message to deserialize
     *
     * @return QueuedMessage
     */
    public function unserialize($raw, $messageClass = QueuedMessage::class): QueuedMessage;
}
