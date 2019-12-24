<?php

namespace Bdf\Queue\Destination\Queue;

use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * Promise for a queue
 */
final class QueuePromise implements PromiseInterface
{
    /**
     * @var QueueDriverInterface
     */
    private $driver;

	/**
	 * @var string
	 */
	private $replyTo;

    /**
     * @var null|QueuedMessage
     */
    private $message;

    /**
     * @var string
     */
    private $correlationId;

    /**
     * @param QueueDriverInterface $driver The queue connection
     * @param string $replyTo The reply queue
     * @param string $correlationId The matching correlation id
     */
    public function __construct(QueueDriverInterface $driver, string $replyTo, string $correlationId)
    {
        $this->driver = $driver;
        $this->replyTo = $replyTo;
        $this->correlationId = $correlationId;
    }

    /**
     * {@inheritdoc}
     */
    public function await(int $timeout = 0): ?QueuedMessage
    {
        if (null === $this->message) {
            $this->message = $this->pop($timeout);
        }

        return $this->message;
    }

    /**
     * @param int $timeout
     *
     * @return QueuedMessage
     */
    private function pop(int $timeout)
    {
        // TODO manage no timeout when $timeout = -1 ?
        $endTime = microtime(true) + ($timeout / 1000);

        do {
            $envelope = $this->driver->pop($this->replyTo, $timeout);

            if (null !== $envelope) {
                if ($envelope->message()->header('correlationId') === $this->correlationId) {
                    $envelope->acknowledge();

                    return $envelope->message();
                }

                $envelope->reject(true);
            }
        } while (microtime(true) < $endTime);

        // TODO Throw timeout exception ?
        return null;
    }

    /**
     * Prepare the message before sending for the promise
     *
     * @param Message $message
     *
     * @return Message
     */
    public static function prepareMessage(Message $message): Message
    {
        if (!$message->header('correlationId')) {
            $message->addHeader('correlationId', self::generateCorrelationId());
        }

        if (!$message->header('replyTo')) {
            $message->addHeader('replyTo', $message->queue().'_reply');
        }

        return $message;
    }

    /**
     * Creates the promise for the given message
     *
     * @param QueueDriverInterface $driver
     * @param Message $message
     *
     * @return QueuePromise
     */
    public static function fromMessage(QueueDriverInterface $driver, Message $message): QueuePromise
    {
        return new QueuePromise($driver, $message->header('replyTo'), $message->header('correlationId'));
    }

    /**
     * Generate the correlation id.
     *
     * @return string
     */
    public static function generateCorrelationId()
    {
        return base64_encode(random_bytes(12));
    }
}
