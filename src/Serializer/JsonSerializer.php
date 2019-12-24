<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * JsonSerializer
 */
class JsonSerializer implements SerializerInterface
{
    /**
     * {@inheritdoc}
     */
    public function serialize(Message $message)
    {
        return json_encode($message->toQueue());
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($raw, $messageClass = QueuedMessage::class): QueuedMessage
    {
        $data = json_decode($raw, true);

        if (isset($data['queuedAt'])) {
            $data['queuedAt'] = new \DateTimeImmutable($data['queuedAt']['date'], new \DateTimeZone($data['queuedAt']['timezone']));
        }

        return $messageClass::fromQueue($data);
    }
}
