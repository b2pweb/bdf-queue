<?php

namespace Bdf\Queue\Connection\Generic;

use PHPUnit\Framework\TestCase;

class RegexQueueNamingStrategyTest extends TestCase
{
    public function test_topicNameToQueue()
    {
        $strategy = new RegexQueueNamingStrategy('*', '/', 'my_group');

        $this->assertEquals('my_group/foo.bar.*', $strategy->topicNameToQueue('foo.bar.*'));
        $this->assertEquals('my_group/foo.bar', $strategy->topicNameToQueue('foo.bar'));
    }

    public function test_topicNameToQueue_with_custom_wildcard()
    {
        $strategy = new RegexQueueNamingStrategy('__wildcard__', '/', 'my_group');

        $this->assertEquals('my_group/foo.bar.__wildcard__', $strategy->topicNameToQueue('foo.bar.*'));
        $this->assertEquals('my_group/foo.bar', $strategy->topicNameToQueue('foo.bar'));
    }

    public function test_topicNameToQueue_with_empty_group_name()
    {
        $strategy = new RegexQueueNamingStrategy('*', '/', '');

        $this->assertEquals('/foo.bar.*', $strategy->topicNameToQueue('foo.bar.*'));
        $this->assertEquals('/foo.bar', $strategy->topicNameToQueue('foo.bar'));
    }

    public function test_queueMatchWithTopic()
    {
        $strategy = new RegexQueueNamingStrategy('*', '/', 'my_group');

        $this->assertTrue($strategy->queueMatchWithTopic('/foo.bar', 'foo.bar'));
        $this->assertTrue($strategy->queueMatchWithTopic('my_group/foo.bar', 'foo.bar'));
        $this->assertTrue($strategy->queueMatchWithTopic('other_group/foo.bar', 'foo.bar'));
        $this->assertTrue($strategy->queueMatchWithTopic('other_group/foo.*', 'foo.bar'));
        $this->assertTrue($strategy->queueMatchWithTopic('other_group/foo.*', 'foo.baz'));
        $this->assertTrue($strategy->queueMatchWithTopic('other_group/*', 'foo.bar'));

        $this->assertFalse($strategy->queueMatchWithTopic('/foo.bar', 'foo.baz'));
        $this->assertFalse($strategy->queueMatchWithTopic('/foo.*', 'bar.baz'));
        $this->assertFalse($strategy->queueMatchWithTopic('foo.bar', 'foo.bar'));

        // Cache
        $this->assertTrue($strategy->queueMatchWithTopic('/foo.bar', 'foo.bar'));
        $this->assertFalse($strategy->queueMatchWithTopic('/foo.bar', 'foo.baz'));
        $this->assertTrue($strategy->queueMatchWithTopic('other_group/foo.*', 'foo.bar'));
        $this->assertFalse($strategy->queueMatchWithTopic('/foo.*', 'bar.baz'));

        $cache = new \ReflectionProperty(RegexQueueNamingStrategy::class, 'matchCache');
        $cache->setAccessible(true);

        $this->assertSame([
            'foo.*' => [
                '_regex' => '/foo\..*/',
                'foo.bar' => true,
                'foo.baz' => true,
                'bar.baz' => false,
            ],
            '*' => [
                '_regex' => '/.*/',
                'foo.bar' => true,
            ],
            'foo.bar' => [
                '_regex' => false,
            ],
        ], $cache->getValue($strategy));
    }
}
