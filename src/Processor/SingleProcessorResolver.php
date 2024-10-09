<?php

namespace Bdf\Queue\Processor;

use Bdf\Instantiator\Exception\ClassNotExistsException;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Exception\ProcessorNotFoundException;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * SingleProcessorResolver
 */
class SingleProcessorResolver implements ProcessorResolverInterface
{
    /**
     * The final processor
     *
     * @var ProcessorInterface
     */
    private $endPoint;

    /**
     * SingleProcessorResolver
     *
     * @param string|callable|ProcessorInterface $endPoint
     * @param InstantiatorInterface|null $instantiator = null
     */
    public function __construct($endPoint, ?InstantiatorInterface $instantiator = null)
    {
        $this->endPoint = $this->createProcessor($endPoint, $instantiator);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(EnvelopeInterface $envelope): ProcessorInterface
    {
        return $this->endPoint;
    }

    /**
     * Create the single processor from a string callable
     *
     * @param string|callable|ProcessorInterface $endPoint
     *
     * @return ProcessorInterface
     */
    private function createProcessor($endPoint, ?InstantiatorInterface $instantiator): ProcessorInterface
    {
        if ($endPoint instanceof ProcessorInterface) {
            return $endPoint;
        }

        if (is_string($endPoint) && !is_callable($endPoint)) {
            try {
                $endPoint = $instantiator->createCallable($endPoint, 'handle');
            } catch (ClassNotExistsException $exception) {
                throw new ProcessorNotFoundException('Cannot resolve processor with path '.$endPoint, 0, $exception);
            }
        }

        return new CallbackProcessor($endPoint);
    }
}
