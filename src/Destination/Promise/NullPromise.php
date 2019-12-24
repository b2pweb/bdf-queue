<?php

namespace Bdf\Queue\Destination\Promise;

use Bdf\Queue\Message\QueuedMessage;

/**
 * Null object for promise
 * This object will always return null, without wait
 */
final class NullPromise implements PromiseInterface
{
    /**
     * @var NullPromise
     */
    private static $instance;

    /**
     * {@inheritdoc}
     */
    public function await(int $timeout = 0): ?QueuedMessage
    {
        return null;
    }

    /**
     * Get the NullPromise instance
     *
     * @return NullPromise
     */
    public static function instance(): NullPromise
    {
        if (self::$instance === null) {
            return self::$instance = new NullPromise();
        }

        return self::$instance;
    }
}
