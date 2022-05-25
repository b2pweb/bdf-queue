<?php

namespace Bdf\Queue\Consumer;

use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * Consumer for topic
 * The consumer can be use for listen on multiple channels
 *
 * @see TopicDriverInterface
 */
class TopicConsumer implements ConsumerInterface
{
    /**
     * The topic object.
     *
     * @var TopicDriverInterface
     */
    private $topic;

    /**
     * The stack of receivers.
     *
     * @var ReceiverInterface
     */
    private $receiver;

    /**
     * The channels to consume.
     *
     * @var string[]
     */
    private $channels;

    /**
     * Run the daemon loop
     *
     * @var bool
     */
    private $running = true;

    /**
     * Does the listener has subscribe to the topic ?
     *
     * @var bool
     */
    private $subscribed = false;


    /**
     * Create a new topic consumer.
     *
     * @param TopicDriverInterface $topic
     * @param ReceiverInterface $receiver
     * @param string[] $channels
     */
    public function __construct(TopicDriverInterface $topic, ReceiverInterface $receiver, array $channels)
    {
        $this->topic = $topic;
        $this->receiver = $receiver;
        $this->channels = $channels;
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration): void
    {
        $this->subscribe();
        $this->loop($duration);
        $this->terminate();
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

    /**
     * Subscribe to channels on the topic
     * Subscribe only once : next calls are ignored
     *
     * @internal Use for testing purpose
     */
    public function subscribe(): void
    {
        if ($this->subscribed) {
            return;
        }

        // Subscribe to channels: send envelope to receiver
        $this->topic->subscribe($this->channels, function (EnvelopeInterface $envelope) {
            $this->receiver->receive($envelope, $this);

            pcntl_signal_dispatch();

            if (!$this->running) {
                $this->topic->connection()->close();
            }
        });

        $this->subscribed = true;
    }

    /**
     * Loop while consumer is running, and consume messages
     *
     * @param int $duration
     */
    private function loop(int $duration): void
    {
        while ($this->running) {
            if ($this->topic->consume($duration) === 0) {
                $this->receiver->receiveTimeout($this);
            }

            pcntl_signal_dispatch();
        }
    }

    /**
     * Terminate the consummation and close the connection
     */
    private function terminate(): void
    {
        $this->topic->connection()->close();
        $this->receiver->terminate();
    }
}
