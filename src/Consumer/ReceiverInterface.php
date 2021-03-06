<?php

namespace Bdf\Queue\Consumer;

/**
 *
 */
interface ReceiverInterface
{
    /**
     * Receive a message from the consumer.
     *
     * @param object $message
     */
    public function receive($message, ConsumerInterface $consumer): void;

    /**
     * Receive a timeout from the consumer.
     * The consumer does not receive message for a period.
     */
    public function receiveTimeout(ConsumerInterface $consumer): void;

    /**
     * Receiving stop event from consumer.
     * The consumer want to stop the reception.
     */
    public function receiveStop(): void;

    /**
     * Ends the middleware.
     * The last event before the consumer ends.
     */
    public function terminate(): void;
}
