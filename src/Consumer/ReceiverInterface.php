<?php

namespace Bdf\Queue\Consumer;

use Bdf\Queue\Consumer\Receiver\NextInterface;

/**
 *
 */
interface ReceiverInterface
{
    /**
     * Start the middleware when the consumer starts.
     *
     * @param ConsumerInterface|NextInterface $consumer Next receiver
     */
    public function start(ConsumerInterface $consumer): void;

    /**
     * Receive a message from the consumer.
     *
     * @param object $message
     * @param ConsumerInterface|NextInterface $consumer Next receiver
     */
    public function receive($message, ConsumerInterface $consumer): void;

    /**
     * Receive a timeout from the consumer.
     * The consumer does not receive message for a period.
     *
     * @param ConsumerInterface|NextInterface $consumer Next receiver
     */
    public function receiveTimeout(ConsumerInterface $consumer): void;

    /**
     * Receiving stop event from consumer.
     * The consumer want to stop the reception.
     *
     * @param ConsumerInterface|NextInterface $consumer Next receiver
     */
    public function receiveStop(ConsumerInterface $consumer): void;

    /**
     * Ends the middleware.
     * The last event before the consumer ends.
     *
     * @param ConsumerInterface|NextInterface $consumer Next receiver
     */
    public function terminate(ConsumerInterface $consumer): void;
}
