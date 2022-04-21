<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader;
use Bdf\Queue\Consumer\Receiver\MessageCountLimiterReceiver;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Destination\ReadableDestinationInterface;
use LogicException;
use Psr\Container\ContainerInterface;

/**
 * Queue helper
 */
class QueueHelper
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * QueueHelper constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Initialize destinations for message reception
     *
     * @param string $destination
     *
     * @return $this
     */
    public function init(string $destination = null): QueueHelper
    {
        if ($destination === null) {
            @trigger_error("Since 1.2: the destination parameter can not be null. Will be removed in 2.0", \E_USER_DEPRECATED);
        }

        if (func_num_args() > 1) {
            @trigger_error("Since 1.2: use destination or 'connectionName::queue' syntax instead of. Will be removed in 2.0", \E_USER_DEPRECATED);

            $destination = "$destination::".func_get_arg(1);
        }

        $this->destination($destination)->declare();

        return $this;
    }

    /**
     * Assert queue number rows
     *
     * @param string $destination
     *
     * @return int
     *
     */
    public function count(string $destination): int
    {
        if (func_num_args() > 1) {
            @trigger_error("Since 1.2: use destination or 'connectionName::queue' syntax instead of. Will be removed in 2.0", \E_USER_DEPRECATED);

            $destination = func_get_arg(1)."::$destination";
        }

        try {
            $destination = $this->destination($destination);
        } catch (\Exception $exception) {
            $destination = $this->destination("::$destination");

            @trigger_error("Since 1.2: the queue parameter is deprecated. Use destination or 'connectionName::queue' syntax instead of. Will be removed in 2.0", \E_USER_DEPRECATED);
        }

        return $destination->count() ?? 0;
    }

    /**
     * Assert queue contains value in raw
     *
     * @param string $expected  The expected string
     * @param string $destination
     *
     * @return bool
     *
     * @throws LogicException If the destination is not readable
     */
    public function contains(string $expected, string $destination): bool
    {
        if (func_num_args() > 2) {
            @trigger_error("Since 1.2: use destination or 'connectionName::queue' syntax instead of. Will be removed in 2.0", \E_USER_DEPRECATED);

            $destination = func_get_arg(2)."::$destination";
        }

        try {
            $destination = $this->destination($destination);
        } catch (\Exception $exception) {
            $destination = $this->destination("::$destination");

            @trigger_error("Since 1.2: the queue parameter is deprecated. Use destination or 'connectionName::queue' syntax instead of. Will be removed in 2.0", \E_USER_DEPRECATED);
        }

        if (!$destination instanceof ReadableDestinationInterface) {
            throw new LogicException(__METHOD__.' works only with peekable connection.');
        }

        $page = 1;
        while ($messages = $destination->peek(20, $page)) {
            foreach ($messages as $message) {
                if (strpos($message->raw(), $expected) !== false) {
                    return true;
                }
            }
            $page++;
        }

        return false;
    }

    /**
     * Get the queue jobs
     *
     * @param int $number
     * @param string $destination
     *
     * @return array
     *
     * @throws LogicException If the destination is not readable
     */
    public function peek(int $number, string $destination): array
    {
        if (func_num_args() > 2) {
            @trigger_error("Since 1.2: use destination or 'connectionName::queue' syntax instead of. Will be removed in 2.0", \E_USER_DEPRECATED);

            $destination = func_get_arg(2)."::$destination";
        }

        try {
            $destination = $this->destination($destination);
        } catch (\Exception $exception) {
            $destination = $this->destination("::$destination");

            @trigger_error("Since 1.2: the queue parameter is deprecated. Use destination or 'connectionName::queue' syntax instead of. Will be removed in 2.0", \E_USER_DEPRECATED);
        }

        if (!$destination instanceof ReadableDestinationInterface) {
            throw new \LogicException(__METHOD__.' works only with peekable connection.');
        }

        return $destination->peek($number);
    }

    /**
     * Consume a number of job from the queue.
     *
     * The work is stopped if the number max of loop is reached or if it had to sleep.
     *
     * @param int $number  Number of loop before stopping the worker
     * @param DestinationInterface|string $destination
     * @param \Closure|null $configurator
     */
    public function consume(int $number = 1, $destination = null, /*\Closure*/ $configurator = null): void
    {
        /** @var ReceiverBuilder $builder */
        $builder = $this->container->get(ReceiverLoader::class)->load(is_string($destination) ? $destination : '');

        if (is_string($configurator)) {
            @trigger_error("Since 1.2: parameter queue is deprecated. Use destination or 'connectionName::queue' syntax. Will be removed in 2.0", \E_USER_DEPRECATED);

            $destination = "$destination::$configurator";
            $configurator = null;
        }

        if (func_num_args() === 4) {
            @trigger_error("Since 1.2: the queue parameter is deprecated. Use destination or 'connectionName::queue' syntax. The configuration will receive a builder instand of extension instance. Will be removed in 2.0", \E_USER_DEPRECATED);

            $configurator = func_get_arg(3);

            // Cannot add receivers here, because it will be added before configurator
            $extension = $configurator($builder->build());
            $extension = new StopWhenEmptyReceiver($extension);
            $extension = new MessageCountLimiterReceiver($extension, $number);
        } else {
            if ($configurator !== null) {
                $configurator($builder);
            }

            $extension = $builder
                ->stopWhenEmpty()
                ->max($number)
                ->build();
        }

        if (!$destination instanceof DestinationInterface) {
            $destination = $this->destination($destination);
        }

        $destination->consumer($extension)->consume(0);
    }

    /**
     * Returns the connection driver
     *
     * @param null|string $connection
     *
     * @return ConnectionDriverInterface
     */
    public function connection(?string $connection)
    {
        return $this->container->get(ConnectionDriverFactoryInterface::class)->create($connection);
    }

    /**
     * Returns the destination manager
     *
     * @return DestinationInterface
     */
    public function destination(string $destination = null)/*: DestinationInterface*/
    {
        if ($destination === null) {
            @trigger_error("Since 1.2: destination manager should be get with destinations() method. Will be removed in 2.0", \E_USER_DEPRECATED);

            return $this->destinations();
        }

        return $this->destinations()->guess($destination);
    }

    /**
     * Returns the destination manager
     *
     * @return DestinationManager
     */
    public function destinations(): DestinationManager
    {
        return $this->container->get(DestinationManager::class);
    }
}