<?php

namespace Bdf\Queue\Connection\Memory;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableQueueDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\QueueEnvelopeHelper;
use Bdf\Queue\Connection\PeekableQueueDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\ReservableQueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * MemoryQueue
 */
class MemoryQueue implements QueueDriverInterface, ReservableQueueDriverInterface, PeekableQueueDriverInterface, CountableQueueDriverInterface
{
    use ConnectionBearer;
    use QueueEnvelopeHelper;

    /**
     * MemoryQueue constructor.
     *
     * @param MemoryConnection $connection
     */
    public function __construct(MemoryConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function push(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        $this->pushRaw($this->connection->serializer()->serialize($message), $message->queue(), $message->delay());
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
        if ($delay > 0) {
            $delay += time();
        }

        $this->connection->declareQueue($queue);

        $metadata = ['reserved' => false, 'delay' => $delay];
        $object = (object)['raw' => $raw, 'queue' => $queue, 'metadata' => $metadata];

        $this->connection->storage()->queues[$queue]->attach($object, $metadata);
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        $this->connection->declareQueue($queue);

        $found = null;
        $storage = $this->connection->storage()->queues[$queue];

        foreach ($storage as $message) {
            $metadata = $storage[$message];
            
            if (!$metadata['reserved'] && $metadata['delay'] <= time()) {
                $message->metadata = $metadata;
                $message->metadata['reserved'] = true;
                $storage[$message] = $message->metadata;
                $found = $message;
                break;
            }
        }
        
        if ($found === null) {
            return null;
        }
        
        return $this->toQueueEnvelope(
            $this->connection->toQueuedMessage($found->raw, $queue, $found)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function reserve(int $number, string $queue, int $duration = ConnectionDriverInterface::DURATION): array
    {
        $envelopes = [];

        // The operator will modify $number before the assertion
        while ($number-- > 0) {
            $envelope = $this->pop($queue, $duration);

            if ($envelope === null) {
                break;
            }

            $envelopes[] = $envelope;
        }

        return $envelopes;
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        $this->connection->storage()->queues[$message->queue()]
            ->detach($message->internalJob());
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        $queue = $this->connection->storage()->queues[$message->queue()];

        if (!isset($queue[$message->internalJob()])) {
            return;
        }

        $message->internalJob()->metadata = $queue[$message->internalJob()];
        $message->internalJob()->metadata['reserved'] = false;

        if ($message->delay() > 0) {
            $message->internalJob()->metadata['delay'] = time() + $message->delay();
        }

        $queue[$message->internalJob()] = $message->internalJob()->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $queueName): int
    {
        $storage = $this->connection->storage()->queues[$queueName] ?? null;

        return $storage ? $storage->count() : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function peek(string $queueName, int $rowCount = 20, int $page = 1): array
    {
        if (!isset($this->connection->storage()->queues[$queueName])) {
            return [];
        }

        $queues = $this->connection->storage()->queues[$queueName];
        $iterator = new \LimitIterator($queues, $rowCount * ($page - 1), $rowCount);
        $messages = [];

        foreach ($iterator as $data) {
            $messages[] = $this->connection->toQueuedMessage($data->raw, $queueName, $data);
        }

        return $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        $stats   = [];

        foreach ($this->connection->storage()->queues as $name => $queue) {
            $reserved = 0;
            $delayed  = 0;
            
            foreach ($queue as $envelope) {
                $metadata = $queue[$envelope];
                
                $reserved += (int)$metadata['reserved'];
                $delayed  += $metadata['delay'] ? 1 : 0;
            }
            
            $stats[] = [
                'queue'             => $name,
                'jobs in queue'     => $queue->count(),
                'jobs awaiting'     => $queue->count() - $reserved - $delayed,
                'jobs running'      => $reserved,
                'jobs delayed'      => $delayed,
            ];
        }
        
        return ['queues' => $stats];
    }
}
