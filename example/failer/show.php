#!/usr/bin/env php
<?php

/**
 * List all of the failed queue jobs
 *
 * Argument:
 *  - id: The ID of the failed job (Optional)
 *
 * ex:
 * ./show.php "1"
 */

use Bdf\Queue\Failer\FailedJob;if (file_exists($autoloadFile = __DIR__.'/../../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
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

if ($id) {
    $job = $failer->find($id);

    if ($job === null) {
        die('No failed job "'.$id.'"'.PHP_EOL);
    }

    var_dump((array)$job);
} else {
    $jobs = array_map('parseFailedJob', $failer->all());

    if (count($jobs) === 0) {
        die('No failed jobs'.PHP_EOL);
    }

    displayTable(['ID', 'Connection', 'Queue', 'Job', 'Error', 'Failed At'], $jobs);
}


/**
 * Parse the failed job row.
 *
 * @param FailedJob $job
 *
 * @return array
 */
function parseFailedJob($job)
{
    if (!empty($job->failedAt)) {
        $job->failedAt = $job->failedAt->format('H:i:s d/m/Y');
    }

    return [
        'id' => $job->id,
        'connection' => $job->connection,
        'queue' => $job->queue,
        'name' => $job->name,
        'error' => $job->error,
        'failedAt' => $job->failedAt,
    ];
}
