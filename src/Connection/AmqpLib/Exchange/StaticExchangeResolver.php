<?php

namespace Bdf\Queue\Connection\AmqpLib\Exchange;

/**
 * Exchange resolver strategy
 */
class StaticExchangeResolver implements ExchangeResolverInterface
{
    private $name;

    /**
     * StaticExchangeResolver constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $topic): string
    {
        return $this->name;
    }
}