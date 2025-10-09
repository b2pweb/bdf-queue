<?php

namespace Bdf\Queue\Connection\Pheanstalk;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableQueueDriverInterface;
use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\Extension\QueueEnvelopeHelper;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Exception;
use Pheanstalk\Exception\ClientException;
use Pheanstalk\Exception\ConnectionException as PheanstalkConnectionException;
use Pheanstalk\Exception\ServerException as BaseServerException;
use Pheanstalk\Exception\SocketException;
use Pheanstalk\Job as PheanstalkJob;
use Pheanstalk\Pheanstalk;

use Pheanstalk\Values\Job as Pheanstalk5Job;
use Pheanstalk\Values\TubeName;

use Pheanstalk\Values\TubeStats;

use function class_exists;
use function method_exists;

/**
 * PheanstalkDriver
 */
class PheanstalkQueue implements QueueDriverInterface, CountableQueueDriverInterface
{
    use ConnectionBearer;
    use QueueEnvelopeHelper;

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
        $queue = $message->queue();

        if (class_exists(TubeName::class)) {
            // Support for Pheanstalk 5
            $queue = new TubeName($queue);
        }

        try {
            $pheanstalk->useTube($queue);

            $pheanstalk->put(
                $this->connection->serializer()->serialize($message),
                $message->header('priority', Pheanstalk::DEFAULT_PRIORITY),
                $message->delay(),
                $message->header('ttr', $this->connection->timeToRun())
            );
        } catch (SocketException|PheanstalkConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        } catch (ClientException $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
        $pheanstalk = $this->connection->pheanstalk();

        if (class_exists(TubeName::class)) {
            // Support for Pheanstalk 5
            $queue = new TubeName($queue);
        }

        try {
            $pheanstalk->useTube($queue);

            $pheanstalk->put(
                $raw,
                Pheanstalk::DEFAULT_PRIORITY,
                $delay,
                $this->connection->timeToRun()
            );
        } catch (SocketException|PheanstalkConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        } catch (ClientException $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        $pheanstalk = $this->connection->pheanstalk();

        if (class_exists(TubeName::class)) {
            // Support for Pheanstalk 5
            $queue = new TubeName($queue);
        }

        try {
            if (method_exists($pheanstalk, 'watchOnly')) {
                // Pheanstalk < 5
                $pheanstalk->watchOnly($queue);
            } else {
                // Pheanstalk 5
                $pheanstalk->watch($queue);

                foreach ($pheanstalk->listTubesWatched() as $tube) {
                    if ($tube != $queue) {
                        $pheanstalk->ignore($tube);
                    }
                }
            }

            if (method_exists($pheanstalk, 'reserveWithTimeout')) {
                $job = $pheanstalk->reserveWithTimeout($duration);
            } else {
                // Support for Pheanstalk 3
                $job = $pheanstalk->reserve($duration);
            }
        } catch (SocketException|PheanstalkConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        } catch (ClientException $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$job instanceof PheanstalkJob && !$job instanceof Pheanstalk5Job) {
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
        try {
            $this->connection->pheanstalk()->delete($message->internalJob());
        } catch (SocketException|PheanstalkConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        } catch (ClientException $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        try {
            $this->connection->pheanstalk()->release(
                $message->internalJob(),
                $message->header('priority', Pheanstalk::DEFAULT_PRIORITY),
                $message->delay()
            );
        } catch (SocketException|PheanstalkConnectionException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        } catch (ClientException $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $name): int
    {
        if (class_exists(TubeName::class)) {
            // Support for Pheanstalk 5
            $name = new TubeName($name);
        }

        try {
            $stats = $this->connection->pheanstalk()->statsTube($name);

            if ($stats instanceof TubeStats) {
                return $stats->currentJobsReady;
            } else {
                return $stats['current-jobs-ready'];
            }
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
            $pheanstalk = method_exists(Pheanstalk::class, 'create')
                ? Pheanstalk::create($host, (int) $port)
                : new Pheanstalk($host, $port)
            ;

            try {
                $queuesInfo = array_merge($queuesInfo, $this->queuesInfo($pheanstalk, $host, $port));
                $workersInfo = array_merge($workersInfo, $this->workersInfo($pheanstalk, $host, $port));
            } catch (SocketException|PheanstalkConnectionException $e) {
                throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
            } catch (BaseServerException $e) {
                throw new ServerException($e->getMessage(), $e->getCode(), $e);
            } catch (ClientException $e) {
                throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
            }
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
    private function queuesInfo($pheanstalk, string $host, int $port): array
    {
        $status = [];

        foreach ($pheanstalk->listTubes() as $tube) {
            try {
                /** @var \Pheanstalk\Response\ArrayResponse|TubeStats $stats */
                $stats = $pheanstalk->statsTube($tube);

                if ($stats instanceof TubeStats) {
                    // Pheanstalk 5
                    $status[] = [
                        'host'              => $host.':'.$port,
                        'queue'             => $stats->name->value,
                        'jobs in queue'     => $stats->currentJobsReady,
                        'jobs running'      => $stats->currentJobsReserved,
                        'jobs delayed'      => $stats->currentJobsDelayed,
                        //                    'jobs buried'       => $stats['current-jobs-buried'],
                        'total jobs'        => $stats->totalJobs,
                        //                    'workers using'     => $stats['current-using'],
                        'workers waiting'   => $stats->currentWaiting,
                        'workers watching'  => $stats->currentWatching - 1, // remove the monitoring
                    ];
                } else {
                    $status[] = [
                        'host'              => $host.':'.$port,
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
                }
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
    private function workersInfo($pheanstalk, string $host, int $port): array
    {
        $jobs = [];

        foreach ($pheanstalk->listTubes() as $tube) {
            $job = [
                'host'              => $host.':'.$port,
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
            } catch (\Throwable $exception) {
                // tube not found or unserialization issue
            }

            try {
                if ($delayed = $pheanstalk->peekDelayed($tube)) {
                    $message = $this->connection->serializer()->unserialize($delayed->getData());

                    $job['job delayed id']   = $delayed->getId();
                    $job['job delayed data'] = $message ? $message->name() : '';
                }
            } catch (\Throwable $exception) {
                // tube not found or unserialization issue
            }

            $jobs[] = $job;
        }

        return $jobs;
    }
}
