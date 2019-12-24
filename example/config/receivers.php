<?php

use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;

return [
    'foo' => function(ReceiverBuilder $builder) {
        $builder->handler(function($data) {
            var_dump($data);
        });
    }
];
