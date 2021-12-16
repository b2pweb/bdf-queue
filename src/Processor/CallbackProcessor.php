<?php

namespace Bdf\Queue\Processor;

use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\ErrorMessage;

/**
 * Processor using a callback for process the job
 */
class CallbackProcessor implements ProcessorInterface
{
    /**
     * @var callable
     */
    private $callback;

    /**
     * CallbackProcessor constructor.
     *
     * @param callable $callback The callback to use. Takes the data as first parameter, and the job as second.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function process(EnvelopeInterface $envelope)
    {
        // Already resolved
        if ($envelope->message() instanceof ErrorMessage) {
            throw $envelope->message()->exception();
        }

        ($this->callback)($envelope->message()->data(), $envelope);
    }
}
