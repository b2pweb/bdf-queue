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

$command = new Bdf\Queue\Console\Command\SetupCommand(getDestinationManager());
$command->run($input, $output);
