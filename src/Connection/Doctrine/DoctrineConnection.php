<?php

namespace Bdf\Queue\Connection\Doctrine;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionNamed;
use Bdf\Queue\Connection\ManageableQueueInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Exception\MethodNotImplementedException;
use Bdf\Queue\Message\MessageSerializationTrait;
use Bdf\Queue\Serializer\SerializerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Driver using Prime DBAL
 */
class DoctrineConnection implements ConnectionDriverInterface, ManageableQueueInterface
{
    use ConnectionNamed;
    use MessageSerializationTrait;

    /**
     * The doctrine connection
     *
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $config;


    /**
     * Create a new connector instance.
     *
     * @param string $name The connection name
     * @param SerializerInterface $serializer
     */
    public function __construct(string $name, SerializerInterface $serializer)
    {
        $this->setName($name);
        $this->setSerializer($serializer);
    }

    /**
     * Get the doctrine connection
     * 
     * @return Connection
     */
    public function connection(): Connection
    {
        if ($this->connection === null) {
            $this->connection = DriverManager::getConnection($this->config);
            $this->connection->connect();
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
        $this->config = $config + [
            'user' => 'root',
            'host' => '127.0.0.1',
            'table' => 'doctrine_queue',
            'vendor' => 'pdo_mysql'
        ];

        $this->config['driver'] = str_replace('+', '_', $this->config['vendor']);
    }

    /**
     * {@inheritdoc}
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function declareQueue(string $queue): void
    {
        $this->schema();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteQueue(string $queue): void
    {
        // No queue deletion: this will delete all the queues.
        // Acceptable in the context of setup.
        $this->dropSchema();
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Get the table name
     *
     * @return string
     */
    public function table(): string
    {
        return $this->config['table'];
    }

    /**
     * Create Schema
     */
    public function schema()
    {
        $schema = $this->connection()->createSchemaManager();

        if ($schema->tablesExist([$this->table()])) {
            return;
        }

        $table = new Table($this->table());
        $table->addColumn('id', Types::GUID, ['length' => 16, 'fixed' => true]);
        $table->addColumn('queue', Types::STRING, ['length' => 90]);
        $table->addColumn('raw', Types::TEXT);
        $table->addColumn('reserved', Types::BOOLEAN);
        $table->addColumn('reserved_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('available_at', Types::DATETIME_MUTABLE);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE);
        $table->addIndex(['queue', 'reserved']);
        $table->setPrimaryKey(['id']);

        $schema->createTable($table);
    }

    /**
     * Drop Schema
     */
    public function dropSchema()
    {
        $schema = $this->connection()->createSchemaManager();

        if ($schema->tablesExist([$this->table()])) {
            $schema->dropTable($this->table());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function queue(): QueueDriverInterface
    {
        return new DoctrineQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function topic(): TopicDriverInterface
    {
        throw new MethodNotImplementedException('Topic is not implemented on Doctrine driver');
    }
}
