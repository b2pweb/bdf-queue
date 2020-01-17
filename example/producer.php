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
 *  - payload: Send message as raw payload
 *  - queue: The queue to send the message (if destination is a connection)
 *  - topic: The topic to send the message (if destination is a connection)
 *
 * ex:
 * ./producer.php "destination" "message" --delay=2
 */

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

if (file_exists($autoloadFile = __DIR__.'/../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/lib/services.php';
require __DIR__.'/lib/console.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$input = new ArgvInput();
$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG);

$command = new Bdf\Queue\Console\Command\ProduceCommand(getDestinationManager());
$command->run($input, $output);
