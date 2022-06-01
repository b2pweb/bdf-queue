<?php

namespace Bdf\Queue\Util;

/**
 * MultiServer
 */
class MultiServer
{
    /**
     * Prepare the config item
     *
     * Match DSN config to multiple servers config
     * Allow syntax "localhost, 192.168.0.5:4731"
     *
     * @param string $defaultHost  The default host
     * @param int $defaultPort  The default port
     *
     * @return array
     */
    public static function prepareMultiServers($config, $defaultHost, $defaultPort)
    {
        $hosts = [];

        if (!isset($config['host'])) {
            $hosts[$defaultHost] = $defaultPort;
        } else {
            $port = isset($config['port']) ? $config['port'] : $defaultPort;

            foreach (explode(',', $config['host']) as $host) {
                $parts = explode(':', $host);
                $host = trim($host);
                $port = isset($parts[1]) ? trim($parts[1]) : $port;

                $hosts[$host] = $port;
            }
        }

        $config['hosts'] = $hosts;
        unset($config['host'], $config['port']);

        return $config;
    }
}
