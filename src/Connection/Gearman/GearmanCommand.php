<?php

namespace Bdf\Queue\Connection\Gearman;

use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Exception\ServerNotAvailableException;

/**
 * GearmanCommand
 */
class GearmanCommand
{
    use ConnectionBearer;

    public const STATUS = 'STATUS';
    public const WORKERS = 'WORKERS';

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
     * Execute gearman command on active servers
     *
     * @param string $command   The gearman command
     *
     * @return string[][]
     *
     * @throws ServerNotAvailableException If no servers has been found
     * @throws ConnectionFailedException Can't connect to the server
     * @throws ServerException If the server return an error
     */
    public function command($command)
    {
        $result = [];

        foreach ($this->connection->getActiveHost() as $host => $port) {
            $result[$host.':'.$port] = $this->executeCommand($host, $port, $command);
        }

        return $result;
    }

    /**
     * Execute gearman command
     *
     * @param string $host
     * @param int $port
     * @param string $command   The gearman command
     *
     * @return string[]
     *
     * @throws ConnectionFailedException If the connection to the server failed
     * @throws ServerException If the server return an error
     */
    private function executeCommand($host, $port, $command)
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);

        if ($socket === false) {
            throw new ConnectionFailedException($errstr, $errno);
        }

        $buffer = '';
        fputs($socket, $command . PHP_EOL);

        while (!feof($socket)) {
            $l = fgets($socket, 128);

            if ($l === false || $l === '.' . PHP_EOL) {
                break;
            }

            if ($buffer === '' && strpos($l, 'ERR') === 0) {
                throw new ServerException($l);
            }

            $buffer .= $l;
        }

        fclose($socket);

        $result = explode(PHP_EOL, $buffer);
        sort($result);

        return $result;
    }
}
