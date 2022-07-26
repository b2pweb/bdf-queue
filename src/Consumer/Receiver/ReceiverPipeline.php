<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;

/**
 * Pipeline of receivers
 *
 * This class handle receiver stack by passing it-self at next argument of receivers methods
 * Receivers are executed in order : the first receiver of the stack will be executed first, and the last one will be executed last
 * So the last receiver should be the `ProcessorReceiver`
 */
final class ReceiverPipeline implements NextInterface
{
    /**
     * @var ConsumerInterface
     */
    private $consumer;

    /**
     * Stack of receivers
     *
     * @var list<ReceiverInterface>
     */
    private $receivers;

    /**
     * Current receiver to execute
     *
     * @var int
     */
    private $current = 0;

    /**
     * @param list<ReceiverInterface> $receivers Stack of receivers
     */
    public function __construct(array $receivers)
    {
        $this->receivers = $receivers;
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration): void
    {
        $this->consumer->consume($duration);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->consumer->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function connection(): ConnectionDriverInterface
    {
        return $this->consumer->connection();
    }

    /**
     * {@inheritdoc}
     */
    public function start(ConsumerInterface $consumer): void
    {
        $receiver = $this->receivers[$this->current] ?? null;

        if ($receiver) {
            $receiver->start($this->next($consumer));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $receiver = $this->receivers[$this->current] ?? null;

        if ($receiver) {
            $receiver->receive($message, $this->next($consumer));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $receiver = $this->receivers[$this->current] ?? null;

        if ($receiver) {
            $receiver->receiveTimeout($this->next($consumer));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(ConsumerInterface $consumer): void
    {
        $receiver = $this->receivers[$this->current] ?? null;

        if ($receiver) {
            $receiver->receiveStop($this->next($consumer));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(ConsumerInterface $consumer): void
    {
        $receiver = $this->receivers[$this->current] ?? null;

        if ($receiver) {
            $receiver->terminate($this->next($consumer));
        }
    }

    public function __toString(): string
    {
        return implode(
            '->',
            array_map(
                function (ReceiverInterface $receiver): string {
                    return method_exists($receiver, '__toString') ? (string) $receiver : get_class($receiver);
                },
                $this->receivers
            )
        );
    }

    /**
     * Move cursor to next receiver
     *
     * Note: this method do not modify the state if the current instance, but return a new one
     *
     * @return self New instance
     */
    private function next(ConsumerInterface $consumer): self
    {
        $next = clone $this;
        ++$next->current;
        $next->consumer = $this->consumer ?? $consumer;

        return $next;
    }
}
