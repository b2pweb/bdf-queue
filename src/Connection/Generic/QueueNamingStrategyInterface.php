<?php

namespace Bdf\Queue\Connection\Generic;

/**
 * Strategy for name and match emulated topic queues
 */
interface QueueNamingStrategyInterface
{
    /**
     * Transform a topic name to the queue name associated for the current receiver group
     * This method is used for read from emulated topic
     *
     * @param string $topic Requested topic
     *
     * @return string
     */
    public function topicNameToQueue(string $topic): string;

    /**
     * Check if the given queue match with the requested topic
     *
     * Queues which are not emulated topic are always ignored
     * The group name is ignored, this method will match for all emulated topic queues
     *
     * This method is used for write to all emulated topic
     *
     * @param string $queue Actual queue name
     * @param string $topic Requested topic
     *
     * @return bool
     */
    public function queueMatchWithTopic(string $queue, string $topic): bool;
}
