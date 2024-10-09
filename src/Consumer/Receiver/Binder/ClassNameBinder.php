<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Exception\SerializationException;
use Bdf\Queue\Message\Message;
use Bdf\Serializer\SerializerBuilder;
use Bdf\Serializer\SerializerInterface;

/**
 * Binder using the serializer, and the message name as class name
 */
final class ClassNameBinder implements BinderInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var callable
     */
    private $validator;


    /**
     * ClassNameBinder constructor.
     *
     * @param SerializerInterface|null $serializer The serializer
     * @param callable|null $validator Validator for the class name or data. Take the class and data as parameters, and returns a boolean (true if valid)
     */
    public function __construct(?SerializerInterface $serializer = null, ?callable $validator = null)
    {
        $this->serializer = $serializer ?: (new SerializerBuilder())->build();
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public function bind(Message $message): bool
    {
        $name = $message->name();
        $data = $message->data();

        if (!is_array($data) || !class_exists($name)) {
            return false;
        }

        if ($this->validator && !($this->validator)($name, $data)) {
            return false;
        }

        try {
            $message->setData($this->serializer->fromArray($data, $name));
        } catch (SerializationException $e) {
            return false;
        }

        return true;
    }
}
