<?php

namespace Bdf\Queue\Message;

use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Message
 */
class ErrorMessageTest extends TestCase
{
    /**
     *
     */
    public function test_constructor()
    {
        $exception = new \Exception('error');
        $message = new ErrorMessage($exception);

        $this->assertSame('ErrorMessage', $message->job());
        $this->assertSame(-1, $message->maxTries());
        $this->assertSame($exception, $message->exception());
    }
}
