<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * Class ClassNameBinderTest
 */
class ClassNameBinderTest extends TestCase
{
    /**
     *
     */
    public function test_bind_not_an_array()
    {
        $binder = new ClassNameBinder();

        $message = new Message($event = new ClassNameBinderEvent('data'));
        $message->setName(ClassNameBinderEvent::class);

        $this->assertFalse($binder->bind($message));
        $this->assertSame($event, $message->data());
    }

    /**
     *
     */
    public function test_bind_success()
    {
        $binder = new ClassNameBinder();

        $message = new Message(['payload' => 'data']);
        $message->setName(ClassNameBinderEvent::class);

        $this->assertTrue($binder->bind($message));
        $this->assertEquals(new ClassNameBinderEvent('data'), $message->data());
    }

    /**
     *
     */
    public function test_bind_validation_failed()
    {
        $binder = new ClassNameBinder(null, function (...$parameters) use(&$validationParameters) { $validationParameters = $parameters; return false; });

        $message = new Message(['payload' => 'data']);
        $message->setName(ClassNameBinderEvent::class);

        $this->assertFalse($binder->bind($message));
        $this->assertEquals(['payload' => 'data'], $message->data());
        $this->assertEquals([ClassNameBinderEvent::class, ['payload' => 'data']], $validationParameters);
    }

    /**
     *
     */
    public function test_bind_validation_success()
    {
        $binder = new ClassNameBinder(null, function (...$parameters) use(&$validationParameters) { $validationParameters = $parameters; return true; });

        $message = new Message(['payload' => 'data']);
        $message->setName(ClassNameBinderEvent::class);

        $this->assertTrue($binder->bind($message));
        $this->assertEquals(new ClassNameBinderEvent('data'), $message->data());
        $this->assertEquals([ClassNameBinderEvent::class, ['payload' => 'data']], $validationParameters);
    }
}

class ClassNameBinderEvent
{
    public $payload;

    /**
     * ClassNameBinderEvent constructor.
     *
     * @param $payload
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }
}
