<?php

namespace Bdf\Queue\Consumer;

use Bdf\Queue\Consumer\Reader\QueueReaderInterface;

/**
 * QueueConsumer
 */
class QueueConsumer implements ConsumerInterface
{
    /**
     * @var QueueReaderInterface
     */
    protected $reader;

    /**
     * The stack of receivers.
     *
     * @var ReceiverInterface
     */
    private $receiver;

    /**
     * Run the daemon loop
     *
     * @var bool
     */
    private $running = true;

    /**
     * Create a new queue consumer.
     *
     * @param QueueReaderInterface $reader
     * @param ReceiverInterface $receiver
     */
    public function __construct(QueueReaderInterface $reader, ReceiverInterface $receiver)
    {
        $this->reader = $reader;
        $this->receiver = $receiver;
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration): void
    {
        $this->receiver->start($this);

        // Loop until stop is called
        while (true === $this->running) {
            $envelope = $this->reader->read($duration);

            if ($envelope !== null) {
                $this->receiver->receive($envelope, $this);
            } else {
                $this->receiver->receiveTimeout($this);
            }

            pcntl_signal_dispatch();
        }

        $this->reader->stop();
        $this->receiver->terminate();
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        if ($this->running) {
            $this->running = false;

            $this->receiver->receiveStop();
        }
    }
}
