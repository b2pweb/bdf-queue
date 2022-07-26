<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;

/**
 * Decorate consumer and receiver instances, use as parameter of `ReceiverInterface` methods
 *
 * Implementations of this interface must handle stack of receiver, and call next receiver on each method call
 * It should be called by passing itself as parameter : `$next->receive($message, $next);`
 */
interface NextInterface extends ConsumerInterface, ReceiverInterface
{
}
