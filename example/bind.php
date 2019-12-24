#!/usr/bin/env php
<?php

/**
 * Bind a channel or pattern to a topic
 *
 * Argument:
 *  - destination: The destination to manage
 *  - topic: The topic to bind
 *  - channels: The routing keys to bind on the topic
 *
 * ex:
 * ./bind.php "destination" "topic" "channel" "channel"
 */

use Bdf\Queue\Connection\AmqpLib\AmqpLibConnection;

if (file_exists($autoloadFile = __DIR__.'/../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/lib/services.php';
require __DIR__.'/lib/console.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$arguments = $options = [];

parseCommandLine($arguments, $options);
$connectionName = array_shift($arguments);
$topic = array_shift($arguments);
$channels = $arguments;

$connection = getConnectionsDriverFactory()->create($connectionName);

if (!$connection instanceof AmqpLibConnection) {
    die('The connection "'.$connectionName.'" does not manage binding route'.PHP_EOL);
}

$connection->bind($topic, $channels);

echo sprintf(
    'Channels %s have been binded to topic %s',
    implode(', ', $channels),
    $topic
).PHP_EOL;
