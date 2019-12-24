<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * Class AliasBinderTest
 */
class AliasBinderTest extends TestCase
{
    /**
     *
     */
    public function test_bind_not_mapped()
    {
        $binder = new AliasBinder([]);

        $message = new Message('data');
        $message->setName('MyAliasEvent');

        $this->assertFalse($binder->bind($message));
        $this->assertSame('data', $message->data());
    }

    /**
     *
     */
    public function test_bind_not_an_array()
    {
        $binder = new AliasBinder(['MyAliasEvent' => MyAliasEvent::class]);

        $message = new Message($event = new MyAliasEvent('data'));
        $message->setName('MyAliasEvent');

        $this->assertFalse($binder->bind($message));
        $this->assertSame($event, $message->data());
    }

    /**
     *
     */
    public function test_bind_success()
    {
        $binder = new AliasBinder(['MyAliasEvent' => MyAliasEvent::class]);

        $message = new Message(['payload' => 'data']);
        $message->setName('MyAliasEvent');

        $this->assertTrue($binder->bind($message));
        $this->assertEquals(new MyAliasEvent('data'), $message->data());
    }
}

class MyAliasEvent
{
    public $payload;

    /**
     * MyAliasEvent constructor.
     *
     * @param $payload
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }
}
