#!/usr/bin/env php
<?php

/**
 * Display queue info
 *
 * Argument:
 *  - connection (array): The connection to monitor
 *
 * Options:
 *  - filter: Filter display [queues,workers]
 *
 * ex:
 * ./info.php "connection1" "connection2" --filter=[queues,workers]
 */

use Bdf\Queue\Connection\ConnectionDriverInterface;

if (file_exists($autoloadFile = __DIR__.'/../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/lib/services.php';
require __DIR__.'/lib/console.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$arguments = $options = [];

parseCommandLine($arguments, $options);
$filter = $options['filter'] ?? null;
$connections = [];

$factory = getConnectionsDriverFactory();
foreach ($arguments as $connection) {
    $connections[] = $factory->create($connection);
}

/** @var ConnectionDriverInterface $connection */
foreach ($connections as $connection) {
    echo 'Server: '.$connection->getName().PHP_EOL;
    $reports = $connection->queue()->stats();

    if (empty($reports)) {
        echo 'Reports are not available for this connection.'.PHP_EOL;
        continue;
    }

    foreach ($reports as $report => $stats) {
        if ($filter !== null && $filter !== $report) {
            continue;
        }

        echo sprintf('------ Report: %s', $report).PHP_EOL;

        if (empty($stats)) {
            echo 'No result found.'.PHP_EOL;
        } else {
            displayTable(isset($stats[0]) ? array_keys($stats[0]) : [], $stats);
        }

        echo PHP_EOL;
    }
}
