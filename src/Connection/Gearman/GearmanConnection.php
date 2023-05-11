<?php

namespace Bdf\Queue\Connection\Gearman;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\Generic\GenericTopic;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Exception\ServerNotAvailableException;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Serializer\SerializerInterface;
use Bdf\Queue\Util\MultiServer;
use GearmanClient;
use GearmanException;
use GearmanJob;
use GearmanWorker;

/**
 * GearmanConnection
 */
class GearmanConnection implements ConnectionDriverInterface
{
    use ConnectionNamed;
    use MessageSerializationTrait;

    /**
     * @var GearmanWorker[]
     */
    private $workers = [];

    /**
     * @var GearmanClient
     */
    private $client;

    /**
     * The collected messages
     *
     * @var array
     */
    private $messages = [];

    /**
     * @var array
     */
    private $config;

    /**
     * GearmanConnection constructor.
     *
     * @param string $name
     * @param SerializerInterface $serializer
     */
    public function __construct(string $name, SerializerInterface $serializer)
    {
        $this->name = $name;
        $this->setSerializer($serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
        $this->config = MultiServer::prepareMultiServers($config, '127.0.0.1', 4730) + [
            'client-timeout' => null, //milliseconds
        ];
    }

    /**
     * Gets the global config
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Get the gearman client
     *
     * @return GearmanClient
     * @throws ServerNotAvailableException When no server is available
     */
    public function client()
    {
        if ($this->client === null) {
            $this->client = new GearmanClient();

            try {
                // Set the first server available
                foreach ($this->getActiveHost() as $host => $port) {
                    $this->client->addServer($host, $port);
                    break;
                }
            } catch (GearmanException $e) {
                throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
            }

            if (!empty($this->config['client-timeout'])) {
                $this->client->setTimeout($this->config['client-timeout']);
            }
        }

        return $this->client;
    }

    /**
     * Set the gearman client
     *
     * @param GearmanClient $client
     */
    public function setClient(GearmanClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get the gearman worker
     *
     * @param string $queue  The queue name
     *
     * @return GearmanWorker
     */
    public function worker($queue)
    {
        if (!isset($this->workers[$queue])) {
            $this->workers[$queue] = new GearmanWorker();

            try {
                // Set all host available
                foreach ($this->getActiveHost() as $host => $port) {
                    $this->workers[$queue]->addServer($host, $port);
                }
            } catch (GearmanException $e) {
                throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
            }

            $this->workers[$queue]->addFunction($queue, function (GearmanJob $job) {
                $this->messages[$job->functionName()] = $this->toQueuedMessage($job->workload(), $job->functionName());
            });
        }

        return $this->workers[$queue];
    }

    /**
     * Set the gearman client
     *
     * @param GearmanWorker[<string>] $workers
     */
    public function setWorkers(array $workers)
    {
        $this->workers = $workers;
    }

    /**
     * Gets the last collected message from the queue
     *
     * @param string $queue
     *
     * @return null|QueuedMessage
     */
    public function getCollectedMessage($queue): ?QueuedMessage
    {
        $message = $this->messages[$queue] ?? null;
        unset($this->messages[$queue]);

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->client !== null) {
            $this->client->clearCallbacks();
            $this->client = null;
        }

        foreach ($this->workers as $worker) {
            $worker->unregisterAll();
        }

        $this->workers = [];
    }

    /**
     * {@inheritdoc}
     *
     * @return GearmanQueue
     */
    public function queue(): QueueDriverInterface
    {
        return new GearmanQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function topic(): TopicDriverInterface
    {
        return new GenericTopic($this);
    }

    /**
     * Get command helper to send command to gearman
     *
     * @param string $command
     *
     * @return string[][]
     *
     * @throws ServerNotAvailableException If no servers has been found
     * @throws ConnectionFailedException When the connection cannot be established
     * @throws ServerException When the server return an error
     */
    public function command(string $command): array
    {
        return (new GearmanCommand($this))->command($command);
    }

    /**
     * Get the active IP of gearmand server
     *
     * @return array
     *
     * @throws ServerNotAvailableException If no servers has been found
     */
    public function getActiveHost()
    {
        $valid = [];
        $exception = null;

        foreach ($this->config['hosts'] as $host => $port) {
            try {
                $gearman = new GearmanClient();
                $gearman->addServer($host, $port);

                $valid[$host] = $port;
            } catch (GearmanException $gearmanException) {
                $exception = $gearmanException;
            }
        }

        if (empty($valid)) {
            throw new ServerNotAvailableException('Gearman server not found', 0, $exception);
        }

        return $valid;
    }
}
