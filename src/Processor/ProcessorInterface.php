<?php

namespace Bdf\Queue\Processor;

use Bdf\Queue\Message\EnvelopeInterface;

/**
 * ProcessorInterface
 */
interface ProcessorInterface
{
    /**
     * Process the message
     *
     * @param EnvelopeInterface $envelope
     */
    public function process(EnvelopeInterface $envelope);
}
