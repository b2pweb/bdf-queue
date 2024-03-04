<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJobStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 *
 */
class FlushCommandTest extends TestCase
{
    /**
     *
     */
    public function test_flush()
    {
        $failer = $this->createMock(FailedJobStorageInterface::class);
        $failer->expects($this->once())->method('flush');

        $command = new FlushCommand($failer);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertMatchesRegularExpression('/^All failed jobs deleted successfully/', $tester->getDisplay());
    }
}
