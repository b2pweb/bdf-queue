#!/usr/bin/env php
<?php

/**
 * Delete a failed queue job
 *
 * Argument:
 *  - id: The ID of the failed job
 *
 * ex:
 * ./forget.php "1"
 */

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

if (getFailerStorage()->forget($id)) {
    echo 'Failed job deleted successfully.'.PHP_EOL;
} else {
    echo 'No failed job matches the given ID.'.PHP_EOL;
}
