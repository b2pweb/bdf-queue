#!/usr/bin/env php
<?php

/**
 * Push a serialized message onto queue
 *
 * Argument:
 *  - destination: The destination to send to the message
 *  - message: The message payload
 *
 * Options:
 *  - delay: Delay the message
 *  - raw: Message will be sent as raw message
 *  - queue: The queue to send the message (if destination is a connection)
 *  - topic: The topic to send the message (if destination is a connection)
 *
 * ex:
 * ./producer.php "destination" "message" --delay=2
 */

use Bdf\Queue\Message\Message;

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
$message = $arguments[1] ?? null;

$destination = createDestination($destinationName, $options['queue'] ?? null, $options['topic'] ?? null);

if ($options['raw'] ?? false) {
    $destination->raw($message, ['delay' => $options['delay'] ?? 0]);
} else {
    $destination->send(Message::create($message, $options['queue'] ?? null, $options['delay'] ?? 0));
}

echo 'Message has been sent in "'.$destinationName.'".'.PHP_EOL;
