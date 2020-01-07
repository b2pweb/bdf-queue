<?php

namespace Bdf\Queue\Connection\Doctrine;

use Bdf\Queue\Exception\MethodNotImplementedException;
use Bdf\Queue\Serializer\JsonSerializer;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class DoctrineConnectionTest extends TestCase
{
    /**
     * @var DoctrineConnection
     */
    protected $connection;
    
    /**
     * 
     */
    protected function setUp(): void
    {
        $this->connection = new DoctrineConnection('name', new JsonSerializer());
        $this->connection->setConfig(['table' => 'job', 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->schema();
    }
    
    /**
     * 
     */
    protected function tearDown(): void
    {
        $this->connection->dropSchema();
    }
    
    /**
     * 
     */
    public function test_connections()
    {
        $this->assertInstanceOf(Connection::class, $this->connection->connection());
    }
    
    /**
     * 
     */
    public function test_unused_methods()
    {
        $this->assertNull($this->connection->setConfig(['table' => 'job', 'vendor' => 'pdo_sqlite', 'memory' => true]));
        $this->assertNull($this->connection->close());
    }

    /**
     *
     */
    public function test_queue()
    {
        $this->assertInstanceOf(DoctrineQueue::class, $this->connection->queue());
    }

    /**
     *
     */
    public function test_topic()
    {
        $this->expectException(MethodNotImplementedException::class);

        $this->connection->topic();
    }
}
