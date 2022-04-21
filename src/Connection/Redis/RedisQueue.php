<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableQueueDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\QueueEnvelopeHelper;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * PhpRedisQueue
 */
class RedisQueue implements QueueDriverInterface, CountableQueueDriverInterface
{
    use ConnectionBearer;
    use QueueEnvelopeHelper;

    /**
     * PhpRedisQueue constructor.
     *
     * @param RedisConnection $connection
     */
    public function __construct(RedisConnection $connection)
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
        $redis = $this->connection->connect();

        if ($this->connection->shouldAutoDeclare()) {
            $this->connection->declareQueue($queue);
        }

        if ($delay > 0) {
            $redis->zAdd($this->connection->queuePrefix().$queue.':delayed', time() + $delay, $raw);
        } else {
            $redis->rPush($this->connection->queuePrefix().$queue, $raw);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        $redis = $this->connection->connect();

        if ($this->connection->shouldAutoDeclare()) {
            $this->connection->declareQueue($queue);
        }

        $this->popDelayedQueue($redis, $queue);

        $job = $redis->blPop([$this->connection->queuePrefix().$queue], $duration);

        if (!$job) {
            return null;
        }

        return $this->toQueueEnvelope($this->connection->toQueuedMessage($job[1], $queue));
    }

    /**
     * @param RedisInterface $redis
     * @param string $queue
     */
    private function popDelayedQueue($redis, $queue)
    {
        /**
         * lua script from laravel to manage delayed queues. Inspire from REDIS cookbook.
         *
         * Get all of the expired jobs.
         * If we have values in the array, we will remove them from the first queue
         * and add them onto the destination queue in chunks of 100, which moves
         * all of the appropriate jobs onto the destination queue very safely.
         */
        $lua = <<<'LUA'
local val = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1])
if(next(val) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, #val - 1)
    for i = 1, #val, 100 do
        redis.call('rpush', KEYS[2], unpack(val, i, math.min(i+99, #val)))
    end
end
return val
LUA;
        $queue = $this->connection->queuePrefix().$queue;

        $redis->evaluate($lua, [$queue.':delayed', $queue], [time()]);
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        $this->push($message);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $queueName): int
    {
        $redis = $this->connection->connect();

        return $redis->lLen($this->connection->queuePrefix().$queueName);
    }

    /**
     * Cound delayed jobs
     *
     * @param string $queue
     *
     * @return int
     */
    public function countDelayed($queue)
    {
        $redis = $this->connection->connect();

        return $redis->zCard($this->connection->queuePrefix().$queue.':delayed');
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        $redis = $this->connection->connect();

        $queues = [];

        foreach ($redis->sMembers(RedisConnection::QUEUE_KEY) as $queue) {
            $queues[] = [
                'queue'         => $queue,
                'jobs in queue' => $this->count($queue),
                'delayed'       => $this->countDelayed($queue),
            ];
        }

        return ['queues' => $queues];
    }
}
