<?php

namespace Bdf\Queue\Connection\Doctrine;

use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ServerException;
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
    public function test_connection_invalid()
    {
        $this->expectException(ConnectionFailedException::class);
        $connection = new DoctrineConnection('name', new JsonSerializer());
        $connection->setConfig(['table' => 'job', 'vendor' => 'pdo_mysql', 'host' => 'ivalid']);
        $this->assertInstanceOf(Connection::class, $connection->connection());
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

    public function test_declareQueue_error()
    {
        $this->expectException(ServerException::class);
        $this->connection->setConfig(['table' => '', 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->declareQueue('foo');
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
