<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * Compress serialized data for reduce queue payload
 *
 * - Useless with binary serialization
 * - Compress by 40% serialized data
 * - On serialize, Adds 0.015s of overhead on compression with x1000 iterations
 *      - Double BdfSerializer time
 *      - 15x Serializer time
 * - On unserialize, Adds 0.001s of overhead with x1000 iterations
 *      - < 10% slower on Serializer
 *      - Insignificant with BdfSerializer
 *
 * @uses gzdeflate()
 */
class CompressedSerializer implements SerializerInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;


    /**
     * CompressedSerializer constructor.
     *
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(Message $message)
    {
        return gzdeflate($this->serializer->serialize($message));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($raw, $messageClass = QueuedMessage::class): QueuedMessage
    {
        return $this->serializer->unserialize(gzinflate($raw), $messageClass);
    }
}
