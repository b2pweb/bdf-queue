<?php

namespace Bdf\Queue\Message;

/**
 * Message with error during deserialization
 *
 * @internal
 */
class ErrorMessage extends QueuedMessage
{
    /**
     * @var \Throwable
     */
    private $exception;

    /**
     * ErrorMessage constructor.
     *
     * @param \Throwable $exception
     */
    public function __construct(\Throwable $exception)
    {
        parent::__construct();

        $this->exception = $exception;

        // Disable retry
        $this->setMaxTries(-1);
        $this->setJob('ErrorMessage');
        $this->disableStore();
    }

    /**
     * Gets the thrown exception
     *
     * @return \Throwable
     */
    public function exception()
    {
        return $this->exception;
    }
}
