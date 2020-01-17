<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Failer\MemoryFailedJobStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 *
 */
class ShowCommandTest extends TestCase
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
    public function test_show_empty()
    {
        $failer = new MemoryFailedJobStorage();
        $command = new ShowCommand($failer);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertRegExp('/^No failed jobs/', $tester->getDisplay());
    }
    
    /**
     * 
     */
    public function test_show()
    {
        $failer = new MemoryFailedJobStorage();
        $failer->store(new FailedJob([
            'name' => 'showCommand@test',
            'connection' => 'queue-connection',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $command = new ShowCommand($failer);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $display = $tester->getDisplay();
        
        $this->assertRegExp('/ queue-connection /', $display);
        $this->assertRegExp('/ queue /', $display);
        $this->assertRegExp('/ showCommand@test /', $display);
    }

    /**
     *
     */
    public function test_show_id()
    {
        $failer = new MemoryFailedJobStorage();
        $failer->store(new FailedJob([
            'name' => 'showCommand@test',
            'connection' => 'queue-connection',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
        ]));
        $command = new ShowCommand($failer);
        $tester = new CommandTester($command);

        $tester->execute(['id' => '1']);

        $expected = <<<EOF
id.............. 1
name............ showCommand@test
connection...... queue-connection
queue........... queue
error........... 
EOF;
        $this->assertStringContainsString($expected, $tester->getDisplay());

        $expected = <<<EOF
message class... Bdf\Queue\Message\QueuedMessage

message content:
array:1 [
  "job" => "showCommand@test"
]
EOF;
        $this->assertStringContainsString($expected, $tester->getDisplay());
        $this->assertRegExp('/failed at....... [0-9\: \/]+/', $tester->getDisplay());
    }
}