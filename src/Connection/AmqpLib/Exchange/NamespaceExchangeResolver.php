<?php

namespace Bdf\Queue\Connection\AmqpLib\Exchange;

/**
 * Exchange resolver strategy
 */
class NamespaceExchangeResolver implements ExchangeResolverInterface
{
    private $separator;

    /**
     * NamespaceExchangeResolver constructor.
     *
     * @param string $separator
     */
    public function __construct(string $separator = '.')
    {
        $this->separator = $separator;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $topic): string
    {
        return strstr($topic, $this->separator, true) ?: $topic;
    }
}
