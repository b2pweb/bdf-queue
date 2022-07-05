<?php

namespace Bdf\Queue\Util;

/**
 * Utility class for handle matching of topic name with wildcard
 */
final class TopicMatcher
{
    /**
     * Convert a wildcard topic name to a regex
     *
     * @param string $pattern Topic pattern
     *
     * @return string
     */
    public static function toRegex(string $pattern): string
    {
        return '/' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '/';
    }

    /**
     * Check if a topic match with a topic pattern
     * If the pattern has no wildcard, a simple string equality will be performed
     *
     * @param string $pattern Topic pattern with wildcard
     * @param string $topicName Topic name to test
     *
     * @return bool true if the topic match with pattern
     */
    public static function match(string $pattern, string $topicName): bool
    {
        if (!str_contains($pattern, '*')) {
            return $pattern === $topicName;
        }

        return preg_match(self::toRegex($pattern), $topicName) === 1;
    }
}
