<?php

namespace Bdf\Queue\Connection\AmqpLib\Exchange;

use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Amqp
 */
class StaticExchangeResolverTest extends TestCase
{
    /**
     *
     */
    public function test_resolve()
    {
        $resolver = new StaticExchangeResolver('foo');

        $this->assertSame('foo', $resolver->resolve('bar'));
    }
}
