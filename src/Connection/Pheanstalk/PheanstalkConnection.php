<?php

namespace Bdf\Queue\Connection\Pheanstalk;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\Generic\GenericTopic;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Exception\ServerNotAvailableException;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\SerializerInterface;
use Bdf\Queue\Util\MultiServer;
use Pheanstalk\Connection;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

/**
 * PheanstalkConnection
 */
class PheanstalkConnection implements ConnectionDriverInterface
{
    use ConnectionNamed;
    use MessageSerializationTrait;

    /**
     * @var PheanstalkInterface
     */
    private $pheanstalk;

    /**
     * @var array
     */
    private $config;

    /**
     * PheanstalkConnection constructor.
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
        $this->config = MultiServer::prepareMultiServers($config, '127.0.0.1', PheanstalkInterface::DEFAULT_PORT) + [
            'ttr'            => PheanstalkInterface::DEFAULT_TTR,
            'client-timeout' => null,
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
     * Gets the ttr of the connection
     */
    public function timeToRun(): ?int
    {
        return $this->config['ttr'];
    }

    /**
     * Get the pheanstalk connection
     *
     * @return PheanstalkInterface
     * @throws ServerNotAvailableException If no servers has been found
     */
    public function pheanstalk(): PheanstalkInterface
    {
        if ($this->pheanstalk === null) {
            // Set the first available server
            // Pheanstalk manage a lazy connection. We can instantiate the client here.
            foreach ($this->getActiveHost() as $host => $port) {
                $this->pheanstalk = new Pheanstalk($host, $port, $this->config['client-timeout']);
                break;
            }
        }

        return $this->pheanstalk;
    }

    /**
     * Set the pheanstalk connection
     *
     * @param PheanstalkInterface $pheanstalk
     */
    public function setPheanstalk(PheanstalkInterface $pheanstalk)
    {
        $this->pheanstalk = $pheanstalk;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->pheanstalk !== null) {
            $this->pheanstalk->getConnection()->disconnect();
            $this->pheanstalk = null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return PheanstalkQueue
     */
    public function queue(): QueueDriverInterface
    {
        return new PheanstalkQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function topic(): TopicDriverInterface
    {
        return new GenericTopic($this, ['wildcard' => 'wildcard']);
    }

    /**
     * Get the active IP of beanstalk server
     *
     * @return array
     *
     * @throws ServerNotAvailableException If no servers has been found
     */
    public function getActiveHost()
    {
        $valid = [];

        foreach ($this->config['hosts'] as $host => $port) {
            if ((new Connection($host, $port))->isServiceListening()) {
                $valid[$host] = $port;
            }
        }

        if (empty($valid)) {
            throw new ServerNotAvailableException('Beanstalk server not found');
        }

        return $valid;
    }
}
