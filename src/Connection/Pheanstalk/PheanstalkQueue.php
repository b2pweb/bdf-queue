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
use Pheanstalk\Exception\ServerException as BaseServerException;
use Pheanstalk\Exception\SocketException;
use Pheanstalk\Job as PheanstalkJob;
use Pheanstalk\Pheanstalk;

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

        try {
            $pheanstalk->useTube($message->queue())->put(
                $this->connection->serializer()->serialize($message),
                $message->header('priority', Pheanstalk::DEFAULT_PRIORITY),
                $message->delay(),
                $message->header('ttr', $this->connection->timeToRun())
            );
        } catch (SocketException $e) {
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

        try {
            $pheanstalk->useTube($queue)->put(
                $raw,
                Pheanstalk::DEFAULT_PRIORITY,
                $delay,
                $this->connection->timeToRun()
            );
        } catch (SocketException $e) {
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

        try {
            $job = $pheanstalk->watchOnly($queue)->reserve($duration);
        } catch (SocketException $e) {
            throw new ConnectionLostException($e->getMessage(), $e->getCode(), $e);
        } catch (BaseServerException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        } catch (ClientException $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }

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
        try {
            $this->connection->pheanstalk()->delete($message->internalJob());
        } catch (SocketException $e) {
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
        } catch (SocketException $e) {
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
        try {
            return $this->connection->pheanstalk()->statsTube($name)['current-jobs-ready'];
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

            try {
                $queuesInfo = array_merge($queuesInfo, $this->queuesInfo($pheanstalk));
                $workersInfo = array_merge($workersInfo, $this->workersInfo($pheanstalk));
            } catch (SocketException $e) {
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
    private function queuesInfo($pheanstalk)
    {
        $status = [];

        foreach ($pheanstalk->listTubes() as $tube) {
            try {
                /** @var \Pheanstalk\Response\ArrayResponse $stats */
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
