#!/usr/bin/env php
<?php

/**
 * Retry a failed queue job
 *
 * Argument:
 *  - id: The ID of the failed job. Type "all" to retry all job
 *
 * ex:
 * ./retry.php "1"
 */

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

if (file_exists($autoloadFile = __DIR__.'/../../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/../lib/services.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$input = new ArgvInput();
$output = new ConsoleOutput();

$command = new Bdf\Queue\Console\Command\Failer\RetryCommand(getFailerStorage(), getDestinationManager());
$command->run($input, $output);
