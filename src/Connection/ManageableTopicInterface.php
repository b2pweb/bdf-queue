<?php

namespace Bdf\Queue\Connection;

/**
 * ManageableTopicInterface
 *
 * Some drive needs to be setup before running.
 * This interface expose the creation and deletion of topics.
 */
interface ManageableTopicInterface
{
    /**
     * Declare a topic
     *
     * @param string $topic The topic name
     */
    public function declareTopic(string $topic): void;

    /**
     * Declare a topic
     *
     * @param string $topic The topic name
     */
    public function deleteTopic(string $topic): void;
}
