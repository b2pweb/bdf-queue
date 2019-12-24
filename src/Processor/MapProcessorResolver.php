<?php

namespace Bdf\Queue\Processor;

use Bdf\Queue\Exception\ProcessorNotFoundException;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * MapProcessorResolver
 */
class MapProcessorResolver implements ProcessorResolverInterface
{
    /**
     * The map of processor
     *
     * @var array $map
     */
    private $map;

    /**
     * The next processor
     *
     * @var ProcessorResolverInterface
     */
    private $delegate;

    /**
     * The key builder
     * Build the key from a given job. Should accept a JobInterface on its first parameter.
     *
     * <code>
     * function (EnvelopeInterface $envelope) {
     *     return $envelope->message()->connection().'::'.$envelope->message()->queue();
     * }
     * </code>
     *
     * @var callable
     */
    private $keyBuilder;

    /**
     * MapProcessorResolver
     * 
     * @param array $map
     * @param ProcessorResolverInterface $delegate
     * @param callable $keyBuilder
     */
    public function __construct(array $map, ProcessorResolverInterface $delegate = null, callable $keyBuilder = null)
    {
        $this->map = $map;
        $this->delegate = $delegate;

        $this->keyBuilder = $keyBuilder ?: function(EnvelopeInterface $envelope) {
            return $envelope->message()->queue();
        };
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(EnvelopeInterface $envelope): ProcessorInterface
    {
        $key = ($this->keyBuilder)($envelope);

        if (isset($this->map[$key])) {
            if ($this->map[$key] instanceof ProcessorInterface) {
                return $this->map[$key];
            }

            // String value will be considered as job pattern
            $envelope->message()->setJob($this->map[$key]);
        }

        if ($this->delegate !== null) {
            return $this->delegate->resolve($envelope);
        }

        throw new ProcessorNotFoundException('Cannot resolve processor with map key '.$key);
    }
}
