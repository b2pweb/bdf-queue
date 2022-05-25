<?php

namespace Bdf\Queue\Message\Extension;

/**
 * EnvelopeState
 */
trait EnvelopeState
{
    /**
     * Indicates if the job has been deleted.
     *
     * @var bool
     */
    private $deleted = false;

    /**
     * Indicates if the job has been rejected.
     *
     * @var bool
     */
    private $rejected = false;

    /**
     * {@inheritdoc}
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function isRejected(): bool
    {
        return $this->rejected;
    }
}
