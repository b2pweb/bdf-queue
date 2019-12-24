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

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobStorageInterface;

if (file_exists($autoloadFile = __DIR__.'/../../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/../lib/services.php';
require __DIR__.'/../lib/console.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$arguments = $options = [];
parseCommandLine($arguments, $options);
$id = array_shift($arguments);

$failer = getFailerStorage();
$manager = getDestinationManager();

foreach (getFailedJobs($failer, $id) as $job) {
    if ($message = $job->toMessage()) {
        $manager->send($message);
    }

    $failer->forget($job->id);

    echo sprintf('Job #%s has been pushed back onto the queue.', $job->id).PHP_EOL;
}

/**
 * @param FailedJobStorageInterface $failer
 * @param string $id
 *
 * @return FailedJob[]|iterable
 */
function getFailedJobs(FailedJobStorageInterface $failer, string $id)
{
    if ($id === 'all') {
        return $failer->all();
    }

    $job = $failer->find($id);

    if ($job === null) {
        die('No failed job matches the given ID.'.PHP_EOL);
    }

    return [$job];
}