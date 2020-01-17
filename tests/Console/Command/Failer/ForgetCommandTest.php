<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJobStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 *
 */
class ForgetCommandTest extends TestCase
{
    /**
     *
     */
    public function test_option_definition()
    {
        $command = new ForgetCommand($this->createMock(FailedJobStorageInterface::class));
        $options = $command->getDefinition()->getArguments();

        $this->assertCount(1, $options);
        $this->assertArrayHasKey('id', $options);
    }
    
    /**
     *
     */
    public function test_forget_id_successfully()
    {
        $id = '123';
        $failer = $this->createMock(FailedJobStorageInterface::class);
        $failer->expects($this->once())->method('forget')->with($id)->will($this->returnValue(true));

        $command = new ForgetCommand($failer);
        $tester = new CommandTester($command);

        $tester->execute(['id' => $id]);

        $this->assertRegExp('/^Failed job deleted successfully/', $tester->getDisplay());
    }

    /**
     * 
     */
    public function test_no_job_found()
    {
        $failer = $this->createMock(FailedJobStorageInterface::class);
        $failer->expects($this->once())->method('forget')->will($this->returnValue(false));

        $command = new ForgetCommand($failer);
        $tester = new CommandTester($command);

        $tester->execute(['id' => 'unknown']);

        $this->assertRegExp('/^No failed job matches the given ID/', $tester->getDisplay());
    }
}