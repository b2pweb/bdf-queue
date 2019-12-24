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
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Worker;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

if (file_exists($autoloadFile = __DIR__.'/../vendor/autoload.php') || file_exists($autoloadFile = __DIR__.'/../../../autoload.php')) {
    require $autoloadFile;
}
require __DIR__.'/lib/services.php';
require __DIR__.'/lib/console.php';
require __DIR__.'/lib/psr.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$arguments = $options = [];

parseCommandLine($arguments, $options);
$destinationName = $arguments[0] ?? null;

$destination = createDestination($destinationName, $options['queue'] ?? null, $options['topic'] ?? null);

$worker = new Worker($destination->consumer(createExtension($destinationName, $options)));
$worker->run(['duration' => $options['duration'] ?? 3]);


function createExtension(string $destination, array $options = []): ReceiverInterface
{
    $container = getContainer();

    $builder = (new ReceiverLoader($container, require __DIR__.'/config/receivers.php'))->load($destination);
    $builder->log($container->get(LoggerInterface::class));

    if ($options['middleware'] ?? false) {
        foreach ($options['middleware'] ?? [] as $middleware) {
            $id = 'queue.middlewares.'.$middleware;
            if (!$container->has($id)) {
                echo 'Try to add an unknown middleware "'.$middleware.'"'.PHP_EOL;
            } else {
                $builder->add($id);
            }
        }
    }

    if ($options['retry'] ?? false) {
        $builder->retry($options['retry'], $options['delay'] ?? 10);
    }

    if ($options['save'] ?? false) {
        $builder->store();
    }

    if ($options['limit'] ?? false) {
        $builder->limit($options['limit'], $options['duration'] ?? 3);
    }

    // Set the no failure here because the Stop...Receiver does not manage exception
    $builder->noFailure();

    if ($options['stopWhenEmpty'] ?? false) {
        $builder->stopWhenEmpty();
    }

    if ($options['max'] ?? 0 > 0) {
        $builder->max($options['max']);
    }

    $memory = convertToBytes($options['memory'] ?? '128M');
    if ($memory > 0) {
        $builder->memory($memory);
    }

    return $builder->build();
}

function getContainer(): ContainerInterface
{
    $container = new Container([
        LoggerInterface::class => new Logger(),
    ]);
    $container->set(InstantiatorInterface::class, new Instantiator($container));

    return $container;
}