<?php

namespace Bdf\Queue\Connection\Doctrine;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\QueueEnvelopeHelper;
use Bdf\Queue\Connection\PeekableQueueDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Connection\ReservableQueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Ramsey\Uuid\Uuid;

/**
 * Queue driver for PrimeConnection
 */
class DoctrineQueue implements QueueDriverInterface, ReservableQueueDriverInterface, PeekableQueueDriverInterface
{
    use QueueEnvelopeHelper;

    /**
     * @var DoctrineConnection
     */
    private $connection;


    /**
     * PrimeQueue constructor.
     *
     * @param DoctrineConnection $connection
     */
    public function __construct(DoctrineConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function connection(): ConnectionDriverInterface
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function push(Message $message): void
    {
        $message->setQueuedAt(new \DateTimeImmutable());

        $this->pushRaw($this->connection->serializer()->serialize($message), $message->queue(), $message->delay());
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
        $types = [
            'id'            => Types::GUID,
            'queue'         => Types::STRING,
            'raw'           => Types::STRING,
            'reserved'      => Types::BOOLEAN,
            'reserved_at'   => Types::DATETIME_MUTABLE,
            'available_at'  => Types::DATETIME_MUTABLE,
            'created_at'    => Types::DATETIME_MUTABLE,
        ];

        $this->connection->connection()->insert(
            $this->connection->table(),
            $this->entity($delay, $queue, $raw),
            $types
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        $messages = $this->reserve(1, $queue, $duration);

        return $messages[0] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function reserve(int $number, string $queue, int $duration = ConnectionDriverInterface::DURATION): array
    {
        $doctrine = $this->connection->connection();

        // The query builder of Doctrine does not manage the lock for update.
        $sql = 'SELECT * FROM '.$doctrine->quoteIdentifier($this->connection->table()).'
              WHERE queue = :queue AND reserved = :reserved AND available_at <= :available_at
              ORDER BY available_at, created_at LIMIT '.((int)$number).' '.$doctrine->getDatabasePlatform()->getForUpdateSql();

        $dbJobs = $doctrine->transactional(function() use($sql, $queue, $doctrine) {
            $dbJobs = $doctrine->executeQuery(
                $sql,
                [
                    'queue' => $queue,
                    'reserved' => false,
                    'available_at' => new DateTime(),
                ],
                [
                    'queue' => Types::STRING,
                    'reserved' => Types::BOOLEAN,
                    'available_at' => Types::DATETIME_MUTABLE,
                ]
            )->fetchAllAssociative();

            if (!$dbJobs) {
                return [];
            }

            $ids = [];
            foreach ($dbJobs as $job) {
                $ids[] = $job['id'];
            }

            $updateQuery = $doctrine->createQueryBuilder()
                ->update($this->connection->table())
                ->set('reserved', ':reserved')
                ->set('reserved_at', ':reserved_at')
                ->andWhere('id IN (:ids)')
                ->setParameter('reserved', true, Types::BOOLEAN)
                ->setParameter('reserved_at', new DateTime(), Types::DATETIME_MUTABLE)
                ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

            // Doctrine 3 compatibility
            if (method_exists($updateQuery, 'executeStatement')) {
                $updateQuery->executeStatement();
            } else {
                $updateQuery->execute();
            }

            return $dbJobs;
        });

        // Message instantiation: this creation is done after le transaction
        // This allows the lock to be removed asap
        $envelope = [];
        foreach ($dbJobs as $job) {
            $job = $this->postProcessor($job);

            $envelope[] = $this->toQueueEnvelope($this->connection->toQueuedMessage($job['raw'], $job['queue'], $job));
        }

        // Force sleep if no result found
        if (!isset($envelope[0])) {
            sleep($duration);
        }

        return $envelope;
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
        $this->connection->connection()->delete(
            $this->connection->table(),
            ['id' => $message->internalJob()['id']]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
        $update = $this->connection->connection()->createQueryBuilder()
            ->update($this->connection->table())
            ->set('reserved', ':reserved')
            ->andWhere('id = :id')
            ->setParameter('id', $message->internalJob()['id'])
            ->setParameter('reserved', false, Types::BOOLEAN)
        ;

        if ($message->delay() > 0) {
            $update
                ->set('available_at', ':available_at')
                ->setParameter('available_at', new DateTime("+{$message->delay()} seconds"), Types::DATETIME_MUTABLE);
        }

        // Doctrine 3 compatibility
        if (method_exists($update, 'executeStatement')) {
            $update->executeStatement();
        } else {
            $update->execute();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $queue): ?int
    {
        $query = $this->connection->connection()->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->connection->table())
            ->andWhere('queue = :queue')
            ->setParameter('queue', $queue);

        $result = method_exists($query, 'executeQuery') ? $query->executeQuery() : $query->execute();

        return $result->fetchOne();
    }

    /**
     * {@inheritdoc}
     */
    public function peek(string $queueName, int $rowCount = 20, int $page = 1): array
    {
        $query = $this->connection->connection()->createQueryBuilder()
            ->select('*')
            ->from($this->connection->table())
            ->andWhere('queue = :queue')
            ->orderBy('available_at, created_at')
            ->setParameter('queue', $queueName)
            ->setFirstResult($rowCount * ($page - 1))
            ->setMaxResults($rowCount);

        $result = method_exists($query, 'executeQuery') ? $query->executeQuery() : $query->execute();
        $dbJobs = $result->fetchAllAssociative();

        $messages = [];
        foreach ($dbJobs as $job) {
            $job = $this->postProcessor($job);

            $messages[] = $this->connection->toQueuedMessage($job['raw'], $job['queue'], $job);
        }

        return $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        $stats = [];
        $queueReserved = [];
        $queueDelayed = [];

        // Get running job by queue and group result by queue
        $query = $this->connection->connection()->createQueryBuilder()
            ->select('queue, COUNT(*) as nb')
            ->from($this->connection->table())
            ->andWhere('reserved = :reserved')
            ->groupBy('queue')
            ->setParameter('reserved', true, Types::BOOLEAN);

        $result = method_exists($query, 'executeQuery') ? $query->executeQuery() : $query->execute();
        $result = $result->fetchAllAssociative();

        foreach ($result as $row) {
            $queueReserved[$row['queue']] = (int)$row['nb'];
        }

        // Get delayed job by queue and group result by queue
        $query = $this->connection->connection()->createQueryBuilder()
            ->select('queue, COUNT(*) as nb')
            ->from($this->connection->table())
            ->andWhere('available_at > :available_at')
            ->groupBy('queue')
            ->setParameter('available_at', new DateTime(), Types::DATETIME_MUTABLE);

        $result = method_exists($query, 'executeQuery') ? $query->executeQuery() : $query->execute();
        $result = $result->fetchAllAssociative();

        foreach ($result as $row) {
            $queueDelayed[$row['queue']] = (int)$row['nb'];
        }
        
        // Get all job by queue
        $query = $this->connection->connection()->createQueryBuilder()
            ->select('queue, COUNT(*) as nb')
            ->from($this->connection->table());

        $result = method_exists($query, 'executeQuery') ? $query->executeQuery() : $query->execute();
        $result = $result->fetchAllAssociative();

        // build stats result.
        foreach ($result as $row) {
            $queue = $row['queue'];
            
            if (!isset($queueReserved[$queue])) {
                $queueReserved[$queue] = 0;
            }
            if (!isset($queueDelayed[$queue])) {
                $queueDelayed[$queue] = 0;
            }

            $stats[] = [
                'queue'         => $row['queue'], 
                'jobs in queue' => (int)$row['nb'],
                'jobs awaiting' => $row['nb'] - $queueReserved[$queue] - $queueDelayed[$queue],
                'jobs running'  => $queueReserved[$queue],
                'jobs delayed'  => $queueDelayed[$queue],
            ];
        }
        
        return ['queues' => $stats];
    }

    /**
     * Internal method. Format the db row on post process
     * 
     * @param array $row
     * 
     * @return array
     */
    public function postProcessor($row)
    {
        $connection = $this->connection->connection();

        $row['created_at'] = $connection->convertToPHPValue($row['created_at'], Types::DATETIME_MUTABLE);
        $row['available_at'] = $connection->convertToPHPValue($row['available_at'], Types::DATETIME_MUTABLE);
        $row['reserved_at'] = $connection->convertToPHPValue($row['reserved_at'], Types::DATETIME_MUTABLE);
        
        return $row;
    }

    /**
     * Push a data to the connection with a given delay.
     *
     * @param int $delay
     * @param string $queue
     * @param string $raw
     *
     * @return array
     */
    private function entity($delay, $queue, $raw)
    {
        $available = new DateTime();

        if ($delay > 0) {
            $available->modify($delay.' second');
        }

        return [
            'id'            => Uuid::uuid4(),
            'queue'         => $queue,
            'raw'           => $raw,
            'reserved'      => false,
            'reserved_at'   => null,
            'available_at'  => $available,
            'created_at'    => new DateTime(),
        ];
    }
}
