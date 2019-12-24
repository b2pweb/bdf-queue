<?php

namespace Bdf\Queue\Message;

use Bdf\Queue\Destination\Promise\PromiseInterface;
use Bdf\Queue\Exception\SerializationException;
use Bdf\Queue\Processor\ProcessorResolverInterface;

/**
 * Message to push in queue
 */
class Message
{
    /**
     * The message name.
     *
     * By default the name is the job name.
     * It could be something custom more verbose.
     *
     * @var string
     */
    private $name;

    /**
     * The job name
     * It should refers to a method or a function
     *
     * @var string
     * @see ProcessorResolverInterface Resolve the job name to real job handler
     */
    private $job;

    /**
     * The payload data
     *
     * @var mixed
     */
    private $data;

    /**
     * Max number of retry if job fails
     *
     * @var int
     */
    private $maxTries;

    /**
     * The message delay in seconds
     *
     * @var int
     */
    private $delay = 0;

    /**
     * Choose to no store the job if it fails
     *
     * @var null|true
     */
    private $noStore;

    /**
     * The queue name where the message goes to or from
     *
     * @var string
     */
    private $queue;

    /**
     * The queue connection name.
     *
     * @var string
     */
    private $connection;

    /**
     * The custom headers / driver options
     *
     * @var array
     */
    private $headers = [];

    //------ internal

    /**
     * The date when the payload has been queued
     *
     * @var \DateTimeImmutable
     */
    private $queuedAt;

    /**
     * Does a reply is requested for the given message
     *
     * @var bool
     */
    private $needsReply = false;

    /**
     * Message constructor.
     *
     * @param mixed $data  The payload of the message
     */
    public function __construct($data = null)
    {
        $this->data = $data;
        $this->queuedAt = new \DateTimeImmutable();
    }

    /**
     * Create a message
     *
     * @param mixed $data The payload
     * @param string|null $queue The queue name
     * @param int $delay The message delay in seconds
     *
     * @return static
     */
    public static function create($data, $queue = null, $delay = 0)
    {
        $message = new static($data);
        $message->queue = $queue;
        $message->delay = $delay;

        return $message;
    }

    /**
     * Create a message with job hint
     *
     * @param string|array $job The job name
     * @param mixed $data The payload
     * @param string|null $queue The queue name
     * @param int $delay The message delay in seconds
     *
     * @return static
     */
    public static function createFromJob($job, $data, $queue = null, $delay = 0)
    {
        $message = static::create($data, $queue, $delay);
        $message->job = $job;

        return $message;
    }

    /**
     * Create a message for topic
     *
     * @param string $topic
     * @param mixed $data The payload
     * @param string|null $name The message name
     *
     * @return static
     */
    public static function createForTopic($topic, $data, $name = null)
    {
        $message = static::create($data);
        $message->name = $name;
        $message->setTopic($topic);

        return $message;
    }

    /**
     * Create envelope from queue payload
     *
     * @param array $data
     *
     * @return self
     */
    public static function fromQueue($data): self
    {
        if (!is_array($data)) {
            throw new SerializationException('Unexpected value given during denormalization "'.print_r($data, true).'"');
        }

        $message = new static();

        if (isset($data['job'])) {
            $message->job = $data['job'];
        }
        if (isset($data['data'])) {
            $message->data = $data['data'];
        }
        if (isset($data['queuedAt'])) {
            $message->queuedAt = $data['queuedAt'];
        }
        if (isset($data['name'])) {
            $message->name = $data['name'];
        }
        if (isset($data['maxTries'])) {
            $message->maxTries = $data['maxTries'];
        }
        if (isset($data['noStore'])) {
            $message->noStore = $data['noStore'];
        }
        if (isset($data['headers'])) {
            $message->headers = $data['headers'];
        }

        return $message;
    }

    /**
     * Envelope to queue payload
     *
     * @return array
     */
    public function toQueue(): array
    {
        $payload = [];

        if ($this->job !== null) {
            // TODO remove this method call when job will be private
            $payload['job'] = $this->formatJobName($this->job);
        }

        $payload['data'] = $this->data;
        $payload['queuedAt'] = $this->queuedAt;

        if ($this->name !== null) {
            $payload['name'] = $this->name;
        }

        if ($this->maxTries !== null) {
            $payload['maxTries'] = $this->maxTries;
        }

        if ($this->noStore !== null) {
            $payload['noStore'] = $this->noStore;
        }

        if ($this->headers) {
            $payload['headers'] = $this->headers;
        }

        return $payload;
    }

    /**
     * Set the message name
     * If not set, the job name will be used as name
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the message name
     *
     * @return string
     */
    public function name()
    {
        return $this->name ?: $this->job;
    }

    /**
     * Set the job handler
     * The given value should be a valid callable job
     *
     * @param string|array $job
     *
     * @return $this
     */
    public function setJob($job)
    {
        $this->job = $this->formatJobName($job);

        return $this;
    }

    /**
     * Stringify the job name
     *
     * @param string|array $job
     *
     * @return string
     *
     * @todo This should be done by the TargetResolver
     */
    private function formatJobName($job)
    {
        if (!is_array($job)) {
            return $job;
        }

        if (is_object($job[0])) {
            $job[0] = get_class($job[0]);
        }

        return $job[0].'@'.$job[1];
    }

    /**
     * Get the job handler name
     *
     * @return string
     */
    public function job()
    {
        return $this->job;
    }

    /**
     * Set the message payload
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get the message payload
     *
     * @return mixed
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Change the message max retry count
     * If set to zero, default retry count is used
     *
     * @param int $value
     *
     * @return $this
     */
    public function setMaxTries($value)
    {
        $this->maxTries = (int)$value;

        return $this;
    }

    /**
     * Get max number of retry
     *
     * @return int
     */
    public function maxTries()
    {
        return $this->maxTries;
    }

    /**
     * Delay the execution of the job
     * If the delay is zero, the job will be execute as soon as possible
     *
     * @param int $delay Delay in seconds
     *
     * @return $this
     */
    public function setDelay($delay)
    {
        $this->delay = (int)$delay;

        return $this;
    }

    /**
     * @return int
     */
    public function delay(): int
    {
        return $this->delay;
    }

    /**
     * Disable storing job when failed
     *
     * @param bool $flag
     *
     * @return $this
     *
     * @see JobStoreSubscriber
     */
    public function disableStore($flag = true)
    {
        $this->noStore = $flag;

        return $this;
    }

    /**
     * Does the job should be saved when failed to execute ?
     * If the return value is true, the failed job should not be stored
     *
     * @return null|true
     */
    public function noStore()
    {
        return $this->noStore;
    }

    /**
     * Set the handled queue name
     *
     * @param string $queue
     *
     * @return $this
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Get the handled queue name
     *
     * @return string
     */
    public function queue()
    {
        return $this->queue;
    }

    /**
     * Set the topic to publish the message
     *
     * @param string $topic
     *
     * @return $this
     */
    public function setTopic(string $topic)
    {
        return $this->addHeader('topic', $topic);
    }

    /**
     * Get the destination topic name
     *
     * @return string
     */
    public function topic(): ?string
    {
        return $this->header('topic');
    }

    /**
     * Get the source name of the message
     *
     * The topic name will be check first otherwise the queue name will be returned
     *
     * @return string
     */
    public function source(): ?string
    {
        return $this->header('topic', $this->queue);
    }

    /**
     * Set the queue connection name
     *
     * @param string $connection
     *
     * @return $this
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set the message destination
     *
     * @param string $destination
     *
     * @return $this
     */
    public function setDestination($destination)
    {
        return $this->setConnection($destination);
    }

    /**
     * Get the queue connection name
     *
     * @todo rename connectionOrDestination ?
     *
     * @return string
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * Get the destination name
     *
     * Note: This is actually an alias of `$this->connection()`
     *
     * @return string
     */
    public function destination()
    {
        return $this->connection;
    }

    /**
     * @param \DateTimeImmutable|\DateTimeInterface $date
     *
     * @return $this
     */
    public function setQueuedAt(\DateTimeInterface $date)
    {
        $this->queuedAt = $date;

        return $this;
    }

    /**
     * @return \DateTimeImmutable|\DateTimeInterface
     */
    public function queuedAt()
    {
        return $this->queuedAt;
    }

    /**
     * Change the message driver options
     *
     * @param array $headers
     *
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Set a single option value
     *
     * @param string $key The option name
     * @param mixed $value The option value
     *
     * @return $this
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Get a option value
     *
     * @param string $key The option name
     * @param null|mixed $default Default value, is option is not provided
     *
     * @return mixed
     */
    public function header($key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all provided options on the message
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Check if the message request for a response
     *
     * @return bool
     *
     * @see PromiseInterface
     */
    public function needsReply(): bool
    {
        return $this->needsReply || !empty($this->headers['replyTo']) || !empty($this->headers['correlationId']);
    }

    /**
     * Defines if the message needs a reply or not
     *
     * @param bool $needsReply true for enable reply
     *
     * @return $this
     *
     * @see PromiseInterface
     */
    public function setNeedsReply(bool $needsReply = true): Message
    {
        $this->needsReply = $needsReply;

        return $this;
    }
}
