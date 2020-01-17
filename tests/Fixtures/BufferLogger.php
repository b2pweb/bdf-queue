<?php

namespace Bdf\Queue\Tests;

use Psr\Log\LoggerInterface;

class BufferLogger implements LoggerInterface
{
    use \Psr\Log\LoggerTrait;

    private $buffer = [];

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = array())
    {
        $this->buffer[] = [
            'level' => $level,
            'message' => $message,
        ];
    }

    public function flush(): array
    {
        $buffer = $this->buffer;
        $this->buffer = [];

        return $buffer;
    }
}
