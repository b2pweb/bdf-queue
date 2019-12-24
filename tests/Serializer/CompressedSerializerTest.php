<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf
 * @group Bdf_Queue
 * @group Bdf_Queue_Serializer
 * @group Bdf_Queue_Serializer_CompressedSerializer
 */
class CompressedSerializerTest extends TestCase
{
    /**
     * @var SerializerInterface
     */
    protected $base;

    /**
     * @var CompressedSerializer
     */
    protected $compressed;


    /**
     *
     */
    protected function setUp(): void
    {
        $this->base = new Serializer();
        $this->compressed = new CompressedSerializer($this->base);
    }

    /**
     * @dataProvider serializationProvider
     */
    public function test_serialize_unserialize($job, $data = null)
    {
        $message = Message::createFromJob($job, '');

        $ser = $this->base->serialize($message);
        $compressed = $this->compressed->serialize($message);

        // Compressed should be smaller than base
        $this->assertTrue(strlen($ser) > strlen($compressed));

        $uncompress = $this->compressed->unserialize($compressed);

        $this->assertEquals($this->base->unserialize($ser), $uncompress);
    }

    public function test_serialize_unserialize_dateTime()
    {
        $message = Message::create('data');
        $message->setQueuedAt(new \DateTimeImmutable('2019-02-15 15:58'));

        $serialized = $this->compressed->serialize($message);
        $unserialized = $this->compressed->unserialize($serialized);

        $this->assertEquals(new \DateTimeImmutable('2019-02-15 15:58'), $unserialized->queuedAt());
    }

    /**
     * @return array
     */
    public function serializationProvider()
    {
        $user = new User();

        return [
            'object'  => [
                [$this, 'test'], $user
            ],
            'array'   => [
                [__CLASS__, 'test'], $user
            ],
            'string'  => [
                __CLASS__.'@test', $user
            ],
        ];
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