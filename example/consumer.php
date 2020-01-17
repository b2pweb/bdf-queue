#!/usr/bin/env php
<?php

/**
 * Consume messages from a destination (queue/topic)
 *
 * Argument:
 *  - destination: The destination from read message
 *
 * Options:
 *  - duration: Number of seconds to wait until a job is available
 *  - retry: Number of retries for failed jobs
 *  - delay: Amount of time to delay failed jobs before retry
 *  - limit: Limit number of jobs in one loop
 *  - save: Save failed job
 *  - max: The max number of jobs
 *  - stopWhenEmpty: Stop the worker if the queues are empty
 *  - queue: The queue to send the message (if destination is a connection)
 *  - topic: The topic to send the message (if destination is a connection)
 *
 * ex:
 * ./consumer.php "destination" --retry=2
 */

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

if (file_exists($autoloadFile = __DIR__.'/../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/lib/services.php';
require __DIR__.'/lib/console.php';
require __DIR__.'/lib/psr.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$container = new Container();
$container->set(LoggerInterface::class, new Logger());
$container->set(InstantiatorInterface::class, new Instantiator($container));

$input = new ArgvInput();
$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG);
$loader = new ReceiverLoader($container, require __DIR__.'/config/receivers.php');

$command = new Bdf\Queue\Console\Command\ConsumeCommand(getDestinationManager(), $loader);
$command->run($input, $output);
