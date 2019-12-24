<?php

namespace Bdf\Queue\Connection\Gearman;

use Bdf\Queue\Connection\Generic\GenericTopic;
use Bdf\Queue\Serializer\JsonSerializer;
use GearmanClient;
use GearmanWorker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

include_once __DIR__.'/Fixtures/GearmanClass.php';

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Gearman
 */
class GearmanConnectionTest extends TestCase
{
    /**
     * @var GearmanConnection
     */
    private $driver;
    /**
     * @var GearmanWorker|MockObject
     */
    private $worker;
    /**
     * @var GearmanClient|MockObject
     */
    private $client;

    /**
     * 
     */
    public function setUp(): void
    {
        $this->worker = $this->createMock(GearmanWorker::class);
        $this->client = $this->createMock(GearmanClient::class);

        $this->driver = new GearmanConnection('foo', new JsonSerializer());
        $this->driver->setClient($this->client);
        $this->driver->setWorkers(['queue' => $this->worker]);
    }

    /**
     * 
     */
    public function test_set_config()
    {
        $this->driver->setConfig([]);

        $config = ['hosts' => ['127.0.0.1' => 4730], 'client-timeout' => null];

        $this->assertSame($config, $this->driver->config());
    }

    /**
     *
     */
    public function test_close()
    {
        $this->client->expects($this->once())->method('clearCallbacks');
        $this->worker->expects($this->once())->method('unregisterAll');

        $this->driver->close();
        // close once
        $this->driver->close();
    }

    /**
     *
     */
    public function test_queue()
    {
        $this->assertInstanceOf(GearmanQueue::class, $this->driver->queue());
    }

    /**
     *
     */
    public function test_topic()
    {
        $this->assertInstanceOf(GenericTopic::class, $this->driver->topic());
    }
}
