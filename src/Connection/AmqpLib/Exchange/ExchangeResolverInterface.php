<?php

namespace Bdf\Queue\Connection\AmqpLib\Exchange;

/**
 * Exchange resolver strategy
 */
interface ExchangeResolverInterface
{
    /**
     * Resolve the exchange name from the topic name
     *
     * @param string $topic
     *
     * @return string
     */
    public function resolve(string $topic): string;
}