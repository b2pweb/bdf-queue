<?php

namespace Bdf\Queue\Processor;

use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Exception\ProcessorNotFoundException;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Processor
 */
class MapProcessorResolverTest extends TestCase
{
    /**
     *
     */
    public function test_resolve()
    {
        $expected = $this->createMock(ProcessorInterface::class);

        $message = new QueuedMessage();
        $message->setQueue('foo');
        $resolver = new MapProcessorResolver(['foo' => $expected]);

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);
        $processor = $resolver->resolve($envelope);

        $this->assertSame($expected, $processor);
    }

    /**
     *
     */
    public function test_resolve_with_key_builder()
    {
        $expected = $this->createMock(ProcessorInterface::class);

        $message = new QueuedMessage();
        $message->setQueue('foo');
        $resolver = new MapProcessorResolver(['bar' => $expected], null, function() {
            return 'bar';
        });

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);
        $processor = $resolver->resolve($envelope);

        $this->assertSame($expected, $processor);
    }

    /**
     *
     */
    public function test_resolve_with_delegate()
    {
        $message = new QueuedMessage();
        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);

        $expected = $this->createMock(ProcessorInterface::class);
        $delegateResolver = $this->createMock(ProcessorResolverInterface::class);
        $delegateResolver->expects($this->once())->method('resolve')->with($envelope)->willReturn($expected);

        $resolver = new MapProcessorResolver([], $delegateResolver);

        $processor = $resolver->resolve($envelope);

        $this->assertSame($expected, $processor);
    }

    /**
     *
     */
    public function test_resolve_job_pattern()
    {
        $message = new QueuedMessage();
        $message->setQueue('foo');
        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);

        $delegateResolver = $this->createMock(ProcessorResolverInterface::class);
        $delegateResolver->expects($this->once())->method('resolve')->with($envelope);

        $resolver = new MapProcessorResolver(['foo' => 'var_dump'], $delegateResolver);
        $resolver->resolve($envelope);

        $this->assertSame('var_dump', $message->job());
    }

    /**
     *
     */
    public function test_not_found()
    {
        $this->expectException(ProcessorNotFoundException::class);

        $message = new QueuedMessage();
        $resolver = new MapProcessorResolver([]);

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);
        $resolver->resolve($envelope);
    }
}
