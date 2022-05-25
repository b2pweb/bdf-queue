<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Exception\SerializationException;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Serializer\BinarySerializerInterface;
use Bdf\Serializer\Context\NormalizationContext;
use Bdf\Serializer\SerializerBuilder;
use Bdf\Serializer\SerializerInterface as BaseSerializer;

/**
 * Serializer using the Bdf Serializer
 *
 * Compared to @see Serializer (with json)
 * - With simple job (i.e. not closure), the serialized size is ~40% smaller
 * - With closure job (or with complex data), the serialized size is ~10% larger
 * - Serialization is 10 times slower
 * - Deserialization is 4 times slower
 *
 * With @see BinarySerializerInterface ('binary' format)
 * - No impact on performances
 * - Serialized size decreased by 10-15%
 * - Cannot be compressed with @see CompressedSerializer
 */
class BdfSerializer implements SerializerInterface
{
    /**
     * @var BaseSerializer
     */
    private $serializer;

    /**
     * @var string
     */
    private $format;

    /**
     * @var array
     */
    private $options;

    /**
     * BdfSerializer constructor.
     *
     * @param BaseSerializer|null $serializer
     * @param string $format
     * @param array $options
     */
    public function __construct(BaseSerializer $serializer = null, $format = 'json', array $options = [])
    {
        $this->serializer = $serializer ?: (new SerializerBuilder())->build();
        $this->format = $format;

        $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(Message $message)
    {
        /** @var string */
        return $this->serializer->serialize($message->toQueue(), $this->format, $this->options);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $raw
     * @param class-string<M> $messageClass
     * @return M
     *
     * @template M as QueuedMessage
     */
    public function unserialize($raw, $messageClass = QueuedMessage::class): QueuedMessage
    {
        // Instantiate message before hydration by the serializer
        // allows to instantiate internal object from message that will not be present
        // in the serialized message.
        // Careful: a valid json with different structure will not return error.
        /** @var M|null $message */
        $message = $this->serializer->deserialize($raw, new $messageClass(), $this->format, $this->options);

        if ($message !== null) {
            return $message;
        }

        throw new SerializationException('Cannot unserialize string "'.$raw.'"');
    }

    /**
     * Set the serializer options
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options + [
            NormalizationContext::META_TYPE => true,
            NormalizationContext::REMOVE_DEFAULT_VALUE => true,
            // Add json option even if the format is not json
            'json_options' => JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE,
        ];
    }
}
