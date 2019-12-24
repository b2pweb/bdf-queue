<?php

class Logger implements \Psr\Log\LoggerInterface
{
    use \Psr\Log\LoggerTrait;

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = array())
    {
        echo "$level: $message".PHP_EOL;
    }
}

class Container implements \Psr\Container\ContainerInterface
{
    private $service = [];

    public function __construct(array $service = [])
    {
        $this->service = $service;
    }

    /**
     * @param string $id
     * @param mixed $service
     */
    public function set($id, $service): void
    {
        $this->service[$id] = $service;
    }

    /**
     * @inheritDoc
     */
    public function get($id)
    {
        return $this->service[$id];
    }

    /**
     * @inheritDoc
     */
    public function has($id)
    {
        return isset($this->service[$id]);
    }
}
