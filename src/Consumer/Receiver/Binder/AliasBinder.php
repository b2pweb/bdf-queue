<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Exception\SerializationException;
use Bdf\Queue\Message\Message;
use Bdf\Serializer\SerializerBuilder;
use Bdf\Serializer\SerializerInterface;

/**
 * Binder using the serializer and class name mapping for creates the event object
 */
final class AliasBinder implements BinderInterface
{
    /**
     * @var string[]
     */
    private $mapping;

    /**
     * @var SerializerInterface
     */
    private $serializer;


    /**
     * AliasBinder constructor.
     *
     * @param string[] $mapping The class name mapping with the event name as key and the event class name as value
     * @param SerializerInterface $serializer The serializer
     */
    public function __construct(array $mapping, SerializerInterface $serializer = null)
    {
        $this->mapping = $mapping;
        $this->serializer = $serializer ?: (new SerializerBuilder())->build();
    }

    /**
     * {@inheritdoc}
     */
    public function bind(Message $message): bool
    {
        $name = $message->name();
        $data = $message->data();

        if (!is_array($data) || !isset($this->mapping[$name])) {
            return false;
        }

        try {
            $message->setData($this->serializer->fromArray($data, $this->mapping[$name]));
        } catch (SerializationException $e) {
            return false;
        }

        return true;
    }
}
