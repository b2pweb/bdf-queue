<?php

return [
    'foo' => function (\Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder $builder) {
        $builder
            ->max(5)
            ->stopWhenEmpty()
            ->jobProcessor()
        ;
    }
];
