<?php

namespace Bdf\Queue\Connection\Pheanstalk;

use Bdf\Queue\Connection\Generic\GenericTopic;
use Bdf\Queue\Serializer\JsonSerializer;
use Pheanstalk\Connection;
use Pheanstalk\PheanstalkInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('disconnect');
        $this->pheanstalk->expects($this->once())->method('getConnection')->willReturn($connection);

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
        $this->assertInstanceOf(GenericTopic::class, $this->connection->topic());
    }
}
