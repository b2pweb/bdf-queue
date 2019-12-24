<?php

namespace Bdf\Queue\Processor;

use Bdf\Instantiator\Exception\ClassNotExistsException;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Exception\ProcessorNotFoundException;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\ErrorMessage;

/**
 * JobHintProcessorResolver
 */
class JobHintProcessorResolver implements ProcessorResolverInterface
{
    /**
     * The job instantiator
     *
     * @var InstantiatorInterface
     */
    private $instantiator;

    /**
     * JobHintProcessorResolver
     *
     * @param InstantiatorInterface $instantiator
     */
    public function __construct(InstantiatorInterface $instantiator)
    {
        $this->instantiator = $instantiator;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(EnvelopeInterface $envelope): ProcessorInterface
    {
        $message = $envelope->message();

        // Already resolved
        if ($message instanceof ErrorMessage) {
            return new CallbackProcessor(function() use($message) {
                throw $message->exception();
            });
        }

        $job = $message->job();

        if ($job === null) {
            throw new ProcessorNotFoundException('Cannot resolve processor: no processor path given for message '.$message->name());
        }

        try {
            return new CallbackProcessor($this->instantiator->createCallable($job, 'handle'));
        } catch (ClassNotExistsException $exception) {
            throw new ProcessorNotFoundException('Cannot resolve processor with path '.$job, 0, $exception);
        }
    }
}
