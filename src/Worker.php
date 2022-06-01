<?php

namespace Bdf\Queue;

use Bdf\Queue\Consumer\ConsumerInterface;

/**
 * Worker
 */
class Worker
{
    /**
     * The stack of receivers.
     *
     * @var ConsumerInterface
     */
    private $consumer;

    /**
     * The register stop on pcntl signals
     *
     * @var bool
     */
    private $enableSignals;

    /**
     * Create a new queue worker.
     *
     * @param ConsumerInterface $consumer
     * @param bool $enableSignals
     */
    public function __construct(ConsumerInterface $consumer, $enableSignals = true)
    {
        $this->consumer = $consumer;
        $this->enableSignals = $enableSignals;
    }

    /**
     * Run the worker: start consuming queue
     *
     * @param array $options
     *
     * @api
     */
    public function run(array $options = []): void
    {
        $this->setupSignalHandler();
        $this->consumer->consume($options['duration'] ?? 0);
    }

    /**
     * Register signal handlers
     */
    private function setupSignalHandler()
    {
        if (!$this->enableSignals) {
            return;
        }

        $stop = function () {
            $this->consumer->stop();
        };

        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }
}
