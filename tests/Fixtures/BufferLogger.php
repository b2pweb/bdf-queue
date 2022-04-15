<?php

namespace Bdf\Queue\Tests;

use Psr\Log\LoggerInterface;
use Stringable;

class BufferLogger implements LoggerInterface
{
    use \Psr\Log\LoggerTrait;

    private $buffer = [];

    /**
     * @inheritDoc
     */
    public function log($level, /*Stringable|string*/ $message, array $context = array()): void
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
