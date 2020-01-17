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

$command = new Bdf\Queue\Console\Command\InfoCommand(getConnectionsDriverFactory());
$command->run($input, $output);
