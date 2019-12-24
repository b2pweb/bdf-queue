<?php

namespace Bdf\Queue\Destination\Promise;

use PHPUnit\Framework\TestCase;

/**
 * Class NullPromiseTest
 */
class NullPromiseTest extends TestCase
{
    /**
     *
     */
    public function test_instance()
    {
        $this->assertInstanceOf(NullPromise::class, NullPromise::instance());
        $this->assertSame(NullPromise::instance(), NullPromise::instance());
    }

    /**
     *
     */
    public function test_await()
    {
        $this->assertNull((new NullPromise())->await());
    }
}
