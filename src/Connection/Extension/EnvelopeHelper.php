<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Message\TopicEnvelope;

/**
 * Trait EnvelopeHelper
 *
 * @psalm-require-implements \Bdf\Queue\Connection\QueueDriverInterface
 * @psalm-require-implements \Bdf\Queue\Connection\TopicDriverInterface
 */
trait EnvelopeHelper
{
    use QueueEnvelopeHelper;
    use TopicEnvelopeHelper;
}
