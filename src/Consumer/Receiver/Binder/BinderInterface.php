<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Message\Message;

/**
 * Transform incoming messages from topic or queue to an inner internal object
 *
 * @see BinderReceiver
 */
interface BinderInterface
{
    /**
     * Bind message payload to an event object
     * The binder should call Message::setData() with the binding
     *
     * Note: If bind is successful (returns true), the message should be modified,
     *       if not (returns false), the message should not be modified
     *
     * @param Message $message
     *
     * @return bool true if the binder has successfully bind the data, or false if bind is not supported
     */
    public function bind(Message $message): bool;
}
