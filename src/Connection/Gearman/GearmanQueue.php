<?php

namespace Bdf\Queue\Connection\Gearman;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableQueueDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\QueueEnvelopeHelper;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use RuntimeException;

/**
 * GearmanDriver
 *
 * @package Bdf\Queue\Driver
 */
class GearmanQueue implements QueueDriverInterface, CountableQueueDriverInterface
{
    use ConnectionBearer;
    use QueueEnvelopeHelper;

    /**
     * GearmanQueue constructor.
     *
     * @param GearmanConnection $connection
     */
    public function __construct(GearmanConnection $connection)
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
        $client = $this->connection->client();
        $client->doBackground($queue, $raw);

        if ($client->returnCode() != GEARMAN_SUCCESS) {
            throw new RuntimeException($client->error(), $client->getErrno());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        $worker = $this->connection->worker($queue);
        $worker->setTimeout($duration * 1000);
        $worker->work();

        $message = $this->connection->getCollectedMessage($queue);

        return $message ? $this->toQueueEnvelope($message) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        // Gearman does not manage ack
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
    public function count(string $name): int
    {
        $queues = $this->queuesInfo();

        foreach ($queues as $info) {
            if ($info['queue'] === $name) {
                return $info['jobs in queue'];
            }
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        return [
            'queues'  => $this->queuesInfo(),
            'workers' => $this->workersInfo(),
        ];
    }

    /**
     * Get queues info
     *
     * @return array
     */
    private function queuesInfo()
    {
        $result = [];

        foreach ($this->connection->command(GearmanCommand::STATUS) as $host => $buffer) {
            foreach ($buffer as $part) {
                $tmp = explode("\t", $part);

                // Gearman returns a first empty line.
                // To skip that line and trying to be more compatible,
                // we just test the second part of the split.
                // We display only line that contains char "\t".
                if (isset($tmp[1])) {
                    $result[] = [
                        'Server'                => $host,
                        'queue'                 => $tmp[0],
                        'jobs in queue'         => $tmp[1] ?? 0,
                        'jobs runnning'         => $tmp[2] ?? 0,
                        'registered workers'    => $tmp[3] ?? 0,
                    ];
                }
            }

            // Jump line only if somthing has been set
            if ($result) {
                $result[] = [
                    'Server' => '',
                    'queue' => '',
                    'jobs in queue' => '',
                    'jobs runnning' => '',
                    'registered workers' => '',
                ];
            }
        }

        // Pop the last jump line
        array_pop($result);

        return $result;
    }

    /**
     * Get workers info
     *
     * @return array
     */
    private function workersInfo()
    {
        $result = [];

        foreach ($this->connection->command(GearmanCommand::WORKERS) as $host => $buffer) {
            foreach ($buffer as $part) {
                if (preg_match('/^([\d]+)\ ([:\.%\w\d]+) ([-\w\d]+) : (.*)$/', $part, $tmp)) {
                    $result[] = [
                        'Server'            => $host,
                        'File Descriptor'   => $tmp[1],
                        'Peer IP'           => $tmp[2],
                        'Client ID'         => $tmp[3],
                        'registered ID'     => $tmp[4],
                    ];
                }
            }

            // Jump line only if somthing has been set
            if ($result) {
                $result[] = [
                    'Server'            => '',
                    'File Descriptor'   => '',
                    'Peer IP'           => '',
                    'Client ID'         => '',
                    'registered ID'     => '',
                ];
            }
        }

        // Pop the last jump line
        array_pop($result);

        return $result;
    }
}
