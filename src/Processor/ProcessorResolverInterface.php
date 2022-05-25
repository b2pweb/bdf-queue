<?php

namespace Bdf\Queue\Processor;

use Bdf\Queue\Exception\ProcessorNotFoundException;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * ProcessorResolverInterface
 */
interface ProcessorResolverInterface
{
    /**
     * Create the processor for this job
     *
     * @param EnvelopeInterface $envelope
     *
     * @return ProcessorInterface
     *
     * @throws ProcessorNotFoundException
     */
    public function resolve(EnvelopeInterface $envelope): ProcessorInterface;
}
