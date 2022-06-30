<?php

namespace Bdf\Queue\Connection\Generic;

/**
 * Name and match an emulated topic queue using regex
 */
final class RegexQueueNamingStrategy implements QueueNamingStrategyInterface
{
    public const WILDCARD = '*';
    public const DEFAULT_GROUP = 'bdf';

    /**
     * @var string
     */
    private $wildcard;

    /**
     * @var string
     */
    private $groupSeparator;

    /**
     * @var string
     */
    private $group;

    /**
     * Cache of matches and regex
     *
     * The first key is the actual queue name without group
     * The second key is the request topic name, mapped with result of the match as boolean
     * If the second key is '_regex', the value is the generated regex pattern, or false if the queue do not contain wildcard
     *
     * @var array<string, array{_regex: string|false}&array<string, bool>>
     */
    private $matchCache = [];

    /**
     * @param string $wildcard Wildcard sequence to use on queue name
     * @param string $groupSeparator Sequence use to separate the topic name with group
     * @param string $group Group use to identify the current consumer
     */
    public function __construct(string $wildcard, string $groupSeparator, string $group)
    {
        $this->wildcard = $wildcard;
        $this->groupSeparator = $groupSeparator;
        $this->group = $group;
    }

    /**
     * {@inheritdoc}
     */
    public function topicNameToQueue(string $topic): string
    {
        if (self::WILDCARD !== $this->wildcard) {
            $topic = str_replace(self::WILDCARD, $this->wildcard, $topic);
        }

        return $this->group . $this->groupSeparator . $topic;
    }

    /**
     * {@inheritdoc}
     */
    public function queueMatchWithTopic(string $queue, string $topic): bool
    {
        if (!str_contains($queue, $this->groupSeparator)) {
            return false;
        }

        [, $queue] = explode($this->groupSeparator, $queue, 2);

        if ($queue === $topic) {
            return true;
        }

        // Match already computed
        if (($match = $this->matchCache[$queue][$topic] ?? null) !== null) {
            /** @var bool - Ignore _regex topic */
            return $match;
        }

        $regex = $this->matchCache[$queue]['_regex'] ?? null;

        // Generate the regex
        if ($regex === null) {
            // Create the regex pattern only if the topic has '*' char.
            if (str_contains($queue, $this->wildcard)) {
                $regex = '/'.str_replace(['.', $this->wildcard, '/'], ['\.', '.*', '\\/'], $queue).'/';
            } else {
                $regex = false;
            }

            // Store regex into cache
            $this->matchCache[$queue]['_regex'] = $regex;
        }

        // Regex has not been generated because queue do not contain wildcard char : no need to continue
        if ($regex === false) {
            return false;
        }

        return $this->matchCache[$queue][$topic] = preg_match($regex, $topic) === 1;
    }
}
