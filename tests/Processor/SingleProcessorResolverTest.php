<?php

namespace Bdf\Queue\Processor;

use Bdf\Instantiator\Exception\ClassNotExistsException;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Exception\ProcessorNotFoundException;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Processor
 */
class SingleProcessorResolverTest extends TestCase
{
    /**
     *
     */
    public function test_resolve_with_processor()
    {
        $processor = $this->createMock(ProcessorInterface::class);

        $message = new QueuedMessage();
        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);

        $resolver = new SingleProcessorResolver($processor);

        $this->assertSame($processor, $resolver->resolve($envelope));
    }

    /**
     *
     */
    public function test_resolve_with_callable()
    {
        $callable = function(){};

        $message = new QueuedMessage();
        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);

        $resolver = new SingleProcessorResolver($callable);

        $this->assertEquals(new CallbackProcessor($callable), $resolver->resolve($envelope));
    }

    /**
     *
     */
    public function test_resolve_with_string()
    {
        $internal = $this->createMock(InstantiatorInterface::class);
        $internal->expects($this->once())->method('createCallable')->with('test')->willReturn('var_dump');

        $message = new QueuedMessage();
        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);

        $resolver = new SingleProcessorResolver('test', $internal);

        $this->assertEquals(new CallbackProcessor('var_dump'), $resolver->resolve($envelope));
    }
    /**
     *
     */
    public function test_dont_resolve_with_wrong_job()
    {
        $this->expectException(ProcessorNotFoundException::class);

        $internal = $this->createMock(InstantiatorInterface::class);
        $internal->expects($this->once())->method('createCallable')->willThrowException(new ClassNotExistsException(''));

        $message = new QueuedMessage();
        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);

        $resolver = new SingleProcessorResolver('test', $internal);
        $resolver->resolve($envelope);
    }
}
