<?php

namespace Bdf\Queue\Destination;

use BadMethodCallException;
use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Message\Message;

use function array_values;
use function explode;
use function preg_match;
use function preg_quote;
use function str_replace;
use function trim;

/**
 * Aggregate multiple destinations
 * This destination will send the message to all destinations
 *
 * Note: this destination does not support read and send with reply
 */
final class AggregateDestination implements DestinationInterface
{
    /**
     * @var list<DestinationInterface>
     */
    private $destinations;

    /**
     * @param list<DestinationInterface> $destinations
     */
    public function __construct(array $destinations)
    {
        $this->destinations = $destinations;
    }

    /**
     * {@inheritdoc}
     */
    public function consumer(ReceiverInterface $receiver): ConsumerInterface
    {
        throw new BadMethodCallException('Aggregate destination does not support consumer yet.');
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message): PromiseInterface
    {
        if ($message->needsReply()) {
            throw new BadMethodCallException('Aggregate destination does not support reply option.');
        }

        foreach ($this->destinations as $destination) {
            $destination->send(clone $message);
        }

        return NullPromise::instance();
    }

    /**
     * {@inheritdoc}
     */
    public function raw($payload, array $options = []): void
    {
        foreach ($this->destinations as $destination) {
            $destination->raw($payload, $options);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function declare(): void
    {
        foreach ($this->destinations as $destination) {
            $destination->declare();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        foreach ($this->destinations as $destination) {
            $destination->destroy();
        }
    }

    /**
     * Create an aggregate destination from a DSN
     *
     * The DSN should be in the form "[scheme]://dest1,dest2,dest3"
     * A destination can be any destination name (queue or topic), or a pattern with wildcard "*"
     *
     * @param DestinationFactoryInterface $factory
     * @param DsnRequest $dsn
     *
     * @return static
     */
    public static function createByDsn(DestinationFactoryInterface $factory, DsnRequest $dsn): self
    {
        $destinations = [];

        foreach (explode(',', $dsn->getHost()) as $destination) {
            $destination = trim($destination);

            if ($destination === '' || isset($destinations[$destination])) {
                continue;
            }

            if (strpos($destination, '*') === false) {
                $destinations[$destination] = $factory->create($destination);
            } else {
                $pattern = str_replace('\*', '.*', preg_quote($destination, '#'));

                foreach ($factory->destinationNames() as $name) {
                    if (preg_match('#^'.$pattern.'$#', $name)) {
                        $destinations[$name] = $factory->create($name);
                    }
                }
            }
        }

        return new self(array_values($destinations));
    }
}
