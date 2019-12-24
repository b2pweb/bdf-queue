<?php

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\MemoryFailedJobStorage;
use Bdf\Queue\Message\QueuedMessage;

$message = QueuedMessage::create("Foo");
$message->setDestination('foo');

$failer = new MemoryFailedJobStorage();
$failer->store(FailedJob::create($message));

return $failer;