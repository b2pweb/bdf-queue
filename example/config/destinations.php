<?php

return [
    'foo' => 'queue://beanstalk/foo',
    'bar' => 'queue://doctrine/bar?prefetch=5',
    'b2pweb.foo' => 'topic://beanstalk/b2pweb.foo',
    'b2pweb' => 'topic://beanstalk/b2pweb.*',
];
