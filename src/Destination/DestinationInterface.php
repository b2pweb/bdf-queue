<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Message\Message;

/**
 * Handle read and write operations on a distant destination
 *
 * @todo destination name ?
 */
interface DestinationInterface
{
    /**
     * Get the destination consumer
     *
     * @param ReceiverInterface $receiver The receiver for handle messages
     *
     * @return ConsumerInterface
     *
     * @throws \BadMethodCallException On write-only destination
     */
    public function consumer(ReceiverInterface $receiver): ConsumerInterface;

    /**
     * Send the message to the destination
     * Setter the queue or topic name is not required : the destination will set the value by it-self
     * For delay the sending, Message::setDelay() should be called, but the method will not failed if not supported (ex: for topic)
     *
     * @param Message $message Message to send
     *
     * @return PromiseInterface
     *
     * @throws \BadMethodCallException On read-only destination
     */
    public function send(Message $message): PromiseInterface;

    /**
     * Sending raw message to the destination
     * The payload will not be transformed by the serializer
     *
     * Note: The payload should corresponds with the serialized content format
     *
     * @param mixed $payload The raw message payload
     * @param array $options The sending options (destination specific)
     *
     * @return void
     */
    public function raw($payload, array $options = []): void;

    /**
     * Declare the destination
     * If the driver do not supports declare, this method is a noop
     */
    public function declare(): void;

    /**
     * Destroy the destination
     * If the driver do not supports declare, this method is a noop
     *
     * Note: all pending data will be removed
     */
    public function destroy(): void;
}
