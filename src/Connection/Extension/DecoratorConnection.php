<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\QueueConnectionInterface;

/**
 * DecoratorConnection
 *
 * Add the delegated connection for decorators. See {@see \Bdf\Queue\Connection\DecoratorConnectionInterface}
 */
trait DecoratorConnection
{
    /**
     * The delegated connection
     *
     * @var QueueConnectionInterface
     */
    private $delegate;

    /**
     * {@inheritdoc}
     */
    public function getDelegate()
    {
        return $this->delegate;
    }
}