<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Queue\Connection\AmqpLib\AmqpLibConnection;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 *
 */
class BindCommandTest extends TestCase
{
    /**
     *
     */
    public function test_simple_bind()
    {
        $connection = $this->createMock(AmqpLibConnection::class);
        $connection->expects($this->once())->method('bind')
            ->with('bar', ['bar.*']);

        $factory = $this->createMock(ConnectionDriverFactoryInterface::class);
        $factory->expects($this->any())->method('create')->willReturn($connection);

        $command = new BindCommand($factory);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            'topic' => 'bar',
            'channels' => ['bar.*'],
        ]);

        $this->assertRegExp('/^Channels bar.* have been binded to topic bar/', $tester->getDisplay());
    }

    /**
     *
     */
    public function test_error()
    {
        $connection = $this->createMock(ConnectionDriverInterface::class);

        $factory = $this->createMock(ConnectionDriverFactoryInterface::class);
        $factory->expects($this->any())->method('create')->willReturn($connection);

        $command = new BindCommand($factory);
        $tester = new CommandTester($command);

        $tester->execute([
            'connection' => 'foo',
            'topic' => 'bar',
            'channels' => ['bar.*'],
        ]);

        $this->assertRegExp('/^The connection "foo" does not manage binding route/', $tester->getDisplay());
    }
}
