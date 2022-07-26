<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LoggerProxyTest extends TestCase
{
    /**
     * @dataProvider provideMethods
     */
    public function test_delegation($method)
    {
        $inner = $this->createMock(LoggerInterface::class);
        $proxy = new LoggerProxy($inner);

        $inner->expects($this->once())->method($method)->with('foo', ['bar']);
        $proxy->$method('foo', ['bar']);
    }

    public function test_setLogger()
    {
        $l1 = $this->createMock(LoggerInterface::class);
        $l2 = $this->createMock(LoggerInterface::class);
        $proxy = new LoggerProxy($l1);

        $l1->expects($this->once())->method('info')->with('foo');
        $proxy->info('foo');

        $l2->expects($this->once())->method('info')->with('foo');
        $proxy->setLogger($l2);
        $proxy->info('foo');
    }

    public function test_log()
    {
        $inner = $this->createMock(LoggerInterface::class);
        $proxy = new LoggerProxy($inner);

        $inner->expects($this->once())->method('log')->with(LogLevel::ALERT, 'foo', ['bar']);
        $proxy->log(LogLevel::ALERT, 'foo', ['bar']);
    }

    public function provideMethods()
    {
        return [
            ['emergency'],
            ['alert'],
            ['critical'],
            ['error'],
            ['warning'],
            ['notice'],
            ['info'],
            ['debug'],
        ];
    }
}
