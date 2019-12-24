<?php

namespace Bdf\Queue\Message;

/**
 * Allows method to interact with the message.
 */
interface InteractEnvelopeInterface extends EnvelopeInterface
{
    /**
     * Reject the message and send it back to the queue.
     * The attempts will be incremented and the job is rejected if necessary.
     *
     * @param int $delay In seconds
     */
    public function retry(int $delay = 0): void;

    /**
     * Reply to a rpc client. The "correlationId" and "replyTo" headers must be set.
     *
     * @param mixed|Message $message The message or the data to send back.
     */
    public function reply($message): void;
}
