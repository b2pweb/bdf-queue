#!/usr/bin/env php
<?php

/**
 * Flush all of the failed queue jobs
 *
 * ex:
 * ./flush.php
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

getFailerStorage()->flush();

echo 'All failed jobs deleted successfully.'.PHP_EOL;
