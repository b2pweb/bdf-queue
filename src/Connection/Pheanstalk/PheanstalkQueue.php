<?php

namespace Bdf\Queue\Connection\Pheanstalk;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\EnvelopeHelper;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Exception;
use Pheanstalk\Job as PheanstalkJob;
use Pheanstalk\Pheanstalk;

/**
 * PheanstalkDriver
 */
class PheanstalkQueue implements QueueDriverInterface
{
    use ConnectionBearer;
    use EnvelopeHelper;

    /**
     * PheanstalkQueue constructor.
     *
     * @param PheanstalkConnection $connection
     */
    public function __construct(PheanstalkConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function push(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());
        $pheanstalk = $this->connection->pheanstalk();

        $pheanstalk->useTube($message->queue())->put(
            $this->connection->serializer()->serialize($message),
            $message->header('priority', Pheanstalk::DEFAULT_PRIORITY),
            $message->delay(),
            $message->header('ttr', $this->connection->timeToRun())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
        $pheanstalk = $this->connection->pheanstalk();

        $pheanstalk->useTube($queue)->put(
            $raw, Pheanstalk::DEFAULT_PRIORITY, $delay, $this->connection->timeToRun()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        $pheanstalk = $this->connection->pheanstalk();

        $job = $pheanstalk->watchOnly($queue)->reserve($duration);

        if (!$job instanceof PheanstalkJob) {
            return null;
        }

        return $this->toQueueEnvelope(
            $this->connection->toQueuedMessage($job->getData(), $queue, $job)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        $this->connection->pheanstalk()->delete($message->internalJob());
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        $this->connection->pheanstalk()->release(
            $message->internalJob(),
            $message->header('priority', Pheanstalk::DEFAULT_PRIORITY),
            $message->delay()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $queue): ?int
    {
        try {
            return $this->connection->pheanstalk()->statsTube($queue)['current-jobs-ready'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        $queuesInfo = [];
        $workersInfo = [];

        foreach ($this->connection->getActiveHost() as $host => $port) {
            $pheanstalk = new Pheanstalk($host, $port);

            $queuesInfo = array_merge($queuesInfo, $this->queuesInfo($pheanstalk));
            $workersInfo = array_merge($workersInfo, $this->workersInfo($pheanstalk));
        }

        return [
            'queues'  => $queuesInfo,
            'workers' => $workersInfo,
        ];
    }
    
    /**
     * Get queues infos
     *
     * @param Pheanstalk $pheanstalk
     *
     * @return array
     */
    private function queuesInfo($pheanstalk)
    {
        $status = [];

        foreach ($pheanstalk->listTubes() as $tube) {
            try {
                $stats = $pheanstalk->statsTube($tube);

                $status[] = [
                    'host'              => $pheanstalk->getConnection()->getHost().':'.$pheanstalk->getConnection()->getPort(),
                    'queue'             => $stats['name'],
                    'jobs in queue'     => $stats['current-jobs-ready'],
                    'jobs running'      => $stats['current-jobs-reserved'],
                    'jobs delayed'      => $stats['current-jobs-delayed'],
//                    'jobs buried'       => $stats['current-jobs-buried'],
                    'total jobs'        => $stats['total-jobs'],
//                    'workers using'     => $stats['current-using'],
                    'workers waiting'   => $stats['current-waiting'],
                    'workers watching'  => --$stats['current-watching'], // remove the monitoring
                ];
            } catch (Exception $e) {
                // tube not found
            }
        }

        return $status;
    }

    /**
     * Get workers infos
     *
     * @param Pheanstalk $pheanstalk
     *
     * @return array
     */
    private function workersInfo($pheanstalk)
    {
        $jobs = [];

        foreach ($pheanstalk->listTubes() as $tube) {
            $job = [
                'host'              => $pheanstalk->getConnection()->getHost().':'.$pheanstalk->getConnection()->getPort(),
                'queue'             => $tube,
                'job ready id'      => '',
                'job ready data'    => '',
                'job delayed id'    => '',
                'job delayed data'  => '',
            ];

            try {
                if ($ready = $pheanstalk->peekReady($tube)) {
                    $message = $this->connection->serializer()->unserialize($ready->getData());

                    $job['job ready id']   = $ready->getId();
                    $job['job ready data'] = $message ? $message->name() : '';
                }
            } catch (Exception $e) {
                // tube not found
            }

            try {
                if ($delayed = $pheanstalk->peekDelayed($tube)) {
                    $message = $this->connection->serializer()->unserialize($delayed->getData());

                    $job['job delayed id']   = $delayed->getId();
                    $job['job delayed data'] = $message ? $message->name() : '';
                }
            } catch (Exception $e) {
                // tube not found
            }

            $jobs[] = $job;
        }

        return $jobs;
    }
}
