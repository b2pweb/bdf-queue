<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Message\TopicEnvelope;

/**
 * @psalm-require-implements \Bdf\Queue\Connection\TopicDriverInterface
 */
trait TopicEnvelopeHelper
{
    /**
     * Create the topic envelope
     *
     * @param QueuedMessage $message
     *
     * @return TopicEnvelope
     */
    public function toTopicEnvelope(QueuedMessage $message)
    {
        return new TopicEnvelope($this, $message);
    }
}
