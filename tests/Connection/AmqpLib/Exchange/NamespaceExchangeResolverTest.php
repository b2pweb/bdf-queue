<?php

namespace Bdf\Queue\Connection\AmqpLib\Exchange;

use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Amqp
 */
class NamespaceExchangeResolverTest extends TestCase
{
    /**
     *
     */
    public function test_resolve()
    {
        $resolver = new NamespaceExchangeResolver('#');

        $this->assertSame('foo', $resolver->resolve('foo#bar'));
        $this->assertSame('foo.bar', $resolver->resolve('foo.bar'));
    }
}
