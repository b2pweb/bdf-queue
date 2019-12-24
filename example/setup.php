#!/usr/bin/env php
<?php

/**
 * Declare or delete a queue from a connection
 *
 * Argument:
 *  - destination: The destination to manage
 *
 * Options:
 *  - drop: Delete the queue from connection
 *  - queue: The queue to send the message (if destination is a connection)
 *  - topic: The topic to send the message (if destination is a connection)
 *
 * ex:
 * ./setup.php "destination"
 */

if (file_exists($autoloadFile = __DIR__.'/../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/lib/services.php';
require __DIR__.'/lib/console.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$arguments = $options = [];
parseCommandLine($arguments, $options);

$destinationName = $arguments[0] ?? null;
$destination = createDestination($destinationName);

if ($options['drop'] ?? false) {
    $destination->destroy();

    echo sprintf('The destination "%s" has been deleted.', $destinationName).PHP_EOL;
} else {
    $destination->declare();

    echo sprintf('The destination "%s" has been declared.', $destinationName).PHP_EOL;
}
