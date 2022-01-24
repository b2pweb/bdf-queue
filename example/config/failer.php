<?php

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\MemoryFailedJobRepository;
use Bdf\Queue\Message\QueuedMessage;

$message = QueuedMessage::create("Foo");
$message->setDestination('foo');

$failer = new MemoryFailedJobRepository();
$failer->store(FailedJob::create($message));

return $failer;