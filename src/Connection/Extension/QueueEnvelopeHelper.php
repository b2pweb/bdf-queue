<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;

/**
 * @psalm-require-implements \Bdf\Queue\Connection\QueueDriverInterface
 */
trait QueueEnvelopeHelper
{
    /**
     * Create the queue envelope
     *
     * @param QueuedMessage $message
     *
     * @return QueueEnvelope
     */
    public function toQueueEnvelope(QueuedMessage $message)
    {
        return new QueueEnvelope($this, $message);
    }
}
