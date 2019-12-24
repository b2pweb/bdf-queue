<?php

namespace Bdf\Queue\Processor;

use Bdf\Instantiator\Exception\ClassNotExistsException;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Exception\ProcessorNotFoundException;
use Bdf\Queue\Message\ErrorMessage;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Processor
 */
class JobHintProcessorResolverTest extends TestCase
{
    /**
     *
     */
    public function test_resolve()
    {
        $internal = $this->createMock(InstantiatorInterface::class);
        $internal->expects($this->once())->method('createCallable')->with('test')->willReturn('var_dump');

        $message = new QueuedMessage();
        $message->setJob('test');
        $resolver = new JobHintProcessorResolver($internal);

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);
        $processor = $resolver->resolve($envelope);

        $this->assertEquals(new CallbackProcessor('var_dump'), $processor);
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
        $message->setJob('test');

        $resolver = new JobHintProcessorResolver($internal);

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);
        $resolver->resolve($envelope);
    }

    /**
     *
     */
    public function test_dont_resolve_without_job()
    {
        $this->expectException(ProcessorNotFoundException::class);

        $internal = $this->createMock(InstantiatorInterface::class);
        $internal->expects($this->never())->method('createCallable');

        $message = new QueuedMessage();

        $resolver = new JobHintProcessorResolver($internal);

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);
        $resolver->resolve($envelope);
    }

    /**
     *
     */
    public function test_error_message()
    {
        $this->expectExceptionMessage('foo');

        $internal = $this->createMock(InstantiatorInterface::class);
        $internal->expects($this->never())->method('createCallable');

        $message = new ErrorMessage(new \Exception('foo'));

        $resolver = new JobHintProcessorResolver($internal);

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), $message);
        $processor = $resolver->resolve($envelope);
        $processor->process($envelope);
    }
}
