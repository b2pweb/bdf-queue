<?php

namespace Bdf\Queue\Connection\Pheanstalk;

use Bdf\Queue\Connection\Generic\GenericTopic;
use Bdf\Queue\Serializer\JsonSerializer;
use Pheanstalk\Connection;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function class_exists;
use function interface_exists;
use function method_exists;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Pheanstalk
 */
class PheanstalkConnectionTest extends TestCase
{
    /**
     * @var PheanstalkConnection
     */
    private $connection;
    /**
     * @var PheanstalkInterface|MockObject
     */
    private $pheanstalk;
    /**

    /**
     * 
     */
    public function setUp(): void
    {
        if (interface_exists(PheanstalkSubscriberInterface::class)) {
            $this->markTestSkipped('Pheanstalk >= 5 is not supported');
        }

        class_exists(PheanstalkConnection::class); // Autoload Pheanstalk classes to ensure that interface alias is defined
        $this->pheanstalk = $this->createMock(PheanstalkInterface::class);

        $this->connection = new PheanstalkConnection('foo', new JsonSerializer());
        $this->connection->setPheanstalk($this->pheanstalk);
    }

    /**
     *
     */
    public function test_set_config()
    {
        $this->connection->setConfig([]);
        $expected = [
            'hosts' => ['127.0.0.1' => 11300],
            'ttr' => 60,
            'client-timeout' => null,
        ];

        $this->assertSame($expected, $this->connection->config());
        $this->assertSame($expected['ttr'], $this->connection->timeToRun());
    }

    /**
     *
     */
    public function test_set_get_pheanstalk()
    {
        $pheanstalk = $this->createMock(PheanstalkInterface::class);

        $this->connection->setPheanstalk($pheanstalk);

        $this->assertSame($pheanstalk, $this->connection->pheanstalk());
    }

    /**
     *
     */
    public function test_close()
    {
        $this->expectNotToPerformAssertions();

        $this->connection->close();
        // close once
        $this->connection->close();
    }

    /**
     *
     */
    public function test_queue()
    {
        $this->assertInstanceOf(PheanstalkQueue::class, $this->connection->queue());
    }

    /**
     *
     */
    public function test_topic()
    {
        $this->connection->setConfig([]);
        $this->assertInstanceOf(GenericTopic::class, $this->connection->topic());
    }
}
