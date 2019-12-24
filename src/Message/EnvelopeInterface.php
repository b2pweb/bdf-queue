<?php

namespace Bdf\Queue\Message;

use Bdf\Queue\Connection\ConnectionDriverInterface;

/**
 * Job is layer allows user to interact with message and queue connection
 */
interface EnvelopeInterface
{
    /**
     * Get the queued message
     */
    public function message(): QueuedMessage;

    /**
     * Get the connection that pop the message.
     *
     * @return ConnectionDriverInterface
     */
    public function connection();
//    public function connection(): ConnectionDriverInterface;

    /**
     * Informs the message broker the message was processed successfully.
     */
    public function acknowledge(): void;

    /**
     * Reject the message.
     * Send a non ack to the message broker.
     * The "requeue" flags allows the message to be released. It will go back to the queue without attempt incrementation.
     */
    public function reject(bool $requeue = false): void;

    /**
     * Determine if the job has been deleted.
     * This allows user to determine if he can interact with the message.
     */
    public function isDeleted(): bool;

    /**
     * Determine if the job has been rejected.
     */
    public function isRejected(): bool;
}
