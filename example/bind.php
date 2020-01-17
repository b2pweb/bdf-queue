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

$command = new Bdf\Queue\Console\Command\BindCommand(getConnectionsDriverFactory());
$command->run($input, $output);
