<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Message\TopicEnvelope;

/**
 * Trait EnvelopeHelper
 */
trait EnvelopeHelper
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