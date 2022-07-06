<?php

namespace Bdf\Queue\Connection\Extension;

use Bdf\Queue\Util\TopicMatcher;

/**
 * Subscriber
 */
trait Subscriber
{
    /**
     * All the subscribers by pattern
     *
     * @var array
     */
    private $subscribers = [];

    /**
     * All regex from special patterns
     *
     * @var array
     */
    private $regex = [];

    /**
     * Register callback on topics
     *
     * @param array $topics
     * @param callable $callback
     */
    public function subscribe(array $topics, callable $callback): void
    {
        foreach ($topics as $pattern) {
            $this->subscribers[$pattern][] = $callback;

            // Create the regex pattern if the topic has '*' char.
            if (strpos($pattern, '*') !== false) {
                $this->regex[$pattern] = TopicMatcher::toRegex($pattern);
            }
        }
    }

    /**
     * Gets the subscribers of a topic.
     * Manage pattern or topic name.
     *
     * @param string $topicName
     *
     * @return callable[]
     */
    public function getSubscribers($topicName)
    {
        if (isset($this->subscribers[$topicName])) {
            return $this->subscribers[$topicName];
        }

        $found = [];

        // Manage pattern
        foreach ($this->regex as $pattern => $regex) {
            if (preg_match($regex, $topicName)) {
                $found = array_merge($found, $this->subscribers[$pattern]);
            }
        }

        return $found;
    }

    /**
     * Gets the registered topics
     *
     * @return string[]
     */
    public function getSubscribedTopics()
    {
        return array_keys($this->subscribers);
    }
}
