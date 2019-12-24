<?php

namespace Bdf\Queue\Connection;

/**
 * ManageableQueueInterface
 *
 * Some drive needs to be setup before running.
 * This interface expose the creation and deletion of queues.
 */
interface ManageableQueueInterface
{
    /**
     * Declare a queue
     *
     * @param string $queue
     */
    public function declareQueue(string $queue): void;

    /**
     * Declare a queue
     *
     * @param string $queue
     */
    public function deleteQueue(string $queue): void;
}
