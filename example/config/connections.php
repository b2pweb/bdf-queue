<?php

return [
    'beanstalk' => "pheanstalk://localhost?group=bdf",
    'gearman' => "gearman://localhost?group=bdf",
    'rabbit' => "amqp-lib://localhost?user=&password=&auto_declare=1",
    'redis' => "redis+phpredis://localhost",
    'kafka' => "rdkafka://localhost:32773",
];
