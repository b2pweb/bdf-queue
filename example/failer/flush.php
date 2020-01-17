#!/usr/bin/env php
<?php

/**
 * Flush all of the failed queue jobs
 *
 * ex:
 * ./flush.php
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

$command = new Bdf\Queue\Console\Command\Failer\FlushCommand(getFailerStorage());
$command->run($input, $output);
