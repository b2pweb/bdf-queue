<?php

namespace Bdf\Queue\Connection\Gearman;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Serializer\JsonSerializer;
use GearmanClient;
use GearmanJob;
use GearmanWorker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

include_once __DIR__.'/Fixtures/GearmanClass.php';

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Gearman
 */
class GearmanQueueTest extends TestCase
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
    public function test_push()
    {
        $message = Message::createFromJob('test', 'foo', 'queue', 1);

        $this->client->expects($this->once())->method('doBackground')->with('queue', $this->stringContains('{"job":"test","data":"foo"'));
        $this->client->expects($this->once())->method('returnCode')->willReturn(GEARMAN_SUCCESS);

        $this->driver->queue()->push($message);
    }

    /**
     *
     */
    public function test_push_ok()
    {
        $this->client->expects($this->once())->method('doBackground')->with('queue', 'test');
        $this->client->expects($this->once())->method('returnCode')->willReturn(GEARMAN_SUCCESS);

        $this->driver->queue()->pushRaw('test', 'queue');
    }

    /**
     *
     */
    public function test_push_fail()
    {
        $this->expectException(\RuntimeException::class);

        $this->client->expects($this->once())->method('doBackground')->with('queue', 'test');
        $this->client->expects($this->once())->method('returnCode')->willReturn(1);

        $this->driver->queue()->pushRaw('test', 'queue');
    }

    /**
     *
     */
    public function test_pop()
    {
        $this->worker->expects($this->once())->method('setTimeout')->with(1000);
        $this->worker->expects($this->once())->method('work');

        $this->assertSame(null, $this->driver->queue()->pop('queue', 1));
    }

    /**
     *
     */
    public function test_acknowledge_not_supported()
    {
        $this->assertNull($this->driver->queue()->acknowledge(new QueuedMessage()));
    }

    /**
     *
     */
    public function test_release()
    {
        $this->client->expects($this->once())->method('doBackground')->with('queue', $this->stringContains('{"job":"test","data":"foo"'));
        $this->client->expects($this->once())->method('returnCode')->willReturn(GEARMAN_SUCCESS);

        $this->driver->queue()->release(QueuedMessage::createFromJob('test', 'foo', 'queue', 1));
    }
}
