<?php

namespace Bdf\Queue\Connection\Gearman;

use Bdf\Queue\Connection\Extension\ConnectionBearer;

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
     */
    private function executeCommand($host, $port, $command)
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);

        $buffer = '';
        fputs($socket, $command . PHP_EOL);

        while (!feof($socket)) {
            $l = fgets($socket, 128);

            if ($l === '.' . PHP_EOL) {
                break;
            }

            $buffer .= $l;
        }

        fclose($socket);

        $result = explode(PHP_EOL, $buffer);
        sort($result);

        return $result;
    }
}
