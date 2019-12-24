<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Exception\SerializationException;
use Bdf\Queue\Message\Message;
use Bdf\Serializer\Normalizer\ClosureNormalizer;
use Bdf\Serializer\SerializerBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf
 * @group Bdf_Queue
 * @group Bdf_Queue_Serializer
 * @group Bdf_Queue_Serializer_BdfSerializer
 */
class BdfSerializerTest extends TestCase
{
    /**
     * @var BdfSerializer
     */
    protected $serializer;

    protected function setUp(): void
    {
        $serializer = (new SerializerBuilder())->build();
        $serializer->getLoader()
            ->addNormalizer(new ClosureNormalizer());

        $this->serializer = new BdfSerializer($serializer);
    }

    /**
     *
     */
    public function test_serialize_callable()
    {
        $now = date(\DateTime::ATOM);

        $message = Message::createFromJob([$this, 'test'], new User());
        $serialized = $this->serializer->serialize($message);

        $this->assertSame('{"job":"Bdf\\\\Queue\\\\Serializer\\\\BdfSerializerTest@test","data":{"@type":"Bdf\\\\Queue\\\\Serializer\\\\User","data":[]},"queuedAt":{"@type":"DateTimeImmutable","data":"'.$now.'"}}', $serialized);
    }

    /**
     *
     */
    public function test_serialize_string()
    {
        $now = date(\DateTime::ATOM);

        $message = Message::createFromJob('MyJob@method', new User());
        $serialized = $this->serializer->serialize($message);

        $this->assertEquals('{"job":"MyJob@method","data":{"@type":"Bdf\\\\Queue\\\\Serializer\\\\User","data":[]},"queuedAt":{"@type":"DateTimeImmutable","data":"'.$now.'"}}', $serialized);
    }

    /**
     *
     */
    public function test_serialize_array()
    {
        $now = date(\DateTime::ATOM);

        $message = Message::createFromJob(['MyJob', 'method'], new User());
        $serialized = $this->serializer->serialize($message);

        $this->assertEquals('{"job":"MyJob@method","data":{"@type":"Bdf\\\\Queue\\\\Serializer\\\\User","data":[]},"queuedAt":{"@type":"DateTimeImmutable","data":"'.$now.'"}}', $serialized);
    }

    /**
     *
     */
    public function test_serialize_unserialize()
    {
        $message = Message::createFromJob(['MyJob', 'method'], new User());
        $serialized = $this->serializer->serialize($message);
        $message = $this->serializer->unserialize($serialized);

        $this->assertSame('MyJob@method', $message->job());
        $this->assertEquals(new User(), $message->data());
    }

    /**
     *
     */
    public function test_serializer_json_options()
    {
        $now = date(\DateTime::ATOM);

        $message = Message::createFromJob('MyJob@method', '/tmp');
        $serialized = $this->serializer->serialize($message);

        $json = '{"job":"MyJob@method","data":"/tmp","queuedAt":{"@type":"DateTimeImmutable","data":"'.$now.'"}}';
        $this->assertEquals($json, $serialized);
    }

    /**
     *
     */
    public function test_serialize_unserialize_dateTime()
    {
        $message = Message::create('data');
        $message->setQueuedAt(new \DateTimeImmutable('2019-02-15 15:58'));

        $serialized = $this->serializer->serialize($message);
        $unserialized = $this->serializer->unserialize($serialized);

        $this->assertEquals(new \DateTimeImmutable('2019-02-15 15:58'), $unserialized->queuedAt());
    }

    /**
     *
     */
    public function test_unserialized_simple_json()
    {
        $message = $this->serializer->unserialize('{"data":"foo"}');

        $this->assertSame('foo', $message->data());
        $this->assertSame(null, $message->job());
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->queuedAt());
    }

    /**
     *
     */
    public function test_unserialized_unconcerned_json()
    {
        $message = $this->serializer->unserialize('"foo"');

        $this->assertSame(null, $message->data());
        $this->assertSame(null, $message->job());
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->queuedAt());
    }

    /**
     *
     */
    public function test_unserialized_invalid_string()
    {
        $this->expectException(SerializationException::class);

        $this->serializer->unserialize('foo');
    }
}

if (!class_exists(User::class)) {
    class User
    {
        private $id;
        private $name;

        public function __construct($id = null, $name = null)
        {
            $this->id   = $id;
            $this->name = $name;
        }
    }
}