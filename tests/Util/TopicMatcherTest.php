<?php

namespace Bdf\Queue\Util;

use PHPUnit\Framework\TestCase;

/**
 *
 */
class TopicMatcherTest extends TestCase
{
    public function test_toRegex()
    {
        $this->assertSame('/foo\.bar/', TopicMatcher::toRegex('foo.bar'));
        $this->assertSame('/foo\..*/', TopicMatcher::toRegex('foo.*'));
        $this->assertSame('/\[\(\/\..*/', TopicMatcher::toRegex('[(/.*'));
    }

    public function test_match()
    {
        $this->assertTrue(TopicMatcher::match('foo.bar', 'foo.bar'));
        $this->assertTrue(TopicMatcher::match('foo.*', 'foo.bar'));
        $this->assertTrue(TopicMatcher::match('*', 'foo.bar'));

        $this->assertFalse(TopicMatcher::match('foo.baz', 'foo.bar'));
        $this->assertFalse(TopicMatcher::match('foo.*', 'baz.bar'));
    }
}
