## Queue

This package provides 2 layers for abstraction of message broker.
 - A connection layer
 - A destination layer

[![Build Status](https://travis-ci.org/b2pweb/bdf-queue.svg?branch=master)](https://travis-ci.org/b2pweb/bdf-queue)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/b2pweb/bdf-queue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/b2pweb/bdf-queue/?branch=master)
[![Packagist Version](https://img.shields.io/packagist/v/b2pweb/bdf-queue.svg)](https://packagist.org/packages/b2pweb/bdf-queue)
[![Total Downloads](https://img.shields.io/packagist/dt/b2pweb/bdf-queue.svg)](https://packagist.org/packages/b2pweb/bdf-queue)

#### Supports

|Message Broker   | Library           | Driver name |
|-----------------|-------------------|---------- |
|Beanstalk        | Pheanstalk        | pheanstalk |
|Db               | Doctrine          | doctrine+(*) |
|Enqueue          | php-enqueue       | enqueue+(*) |
|Gearman          | Pecl Gearman      | gearman |
|Kafka            | RdKafka           | rdkafka |
|Memory           |                   | memory |
|Null             |                   | null |
|RabbitMQ         | Amqp lib          | amqp-lib |
|Redis (Ext)      | PhpRedis          | redis+phpredis |
|Redis            | PRedis            | redis+predis |


### Usage Instructions

#### Produce messages

First, create a new destination manager instance.

```PHP
<?php

use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Connection\Pheanstalk\PheanstalkConnection;
use Bdf\Queue\Destination\ConfigurationDestinationFactory;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Destination\DsnDestinationFactory;
use Bdf\Queue\Serializer\JsonSerializer;

// Declare connections
$driverFactory = new ResolverConnectionDriverFactory([
    'foo' => [
        'driver' => 'pheanstalk',
        'host' => 'localhost',
        'port' => '11300',
        'additionalOption' => 'value',
    ]
    // OR use DSN 'foo' => 'pheanstalk://localhost:11300?additionalOption=value'
]);

// Declare drivers
$driverFactory->addDriverResolver('pheanstalk', function($config) {
    //echo $config['connection'] displays "foo"
    return new PheanstalkConnection($config['connection'], new JsonSerializer());
});

// Declare destination
$destinationFactory = new DsnDestinationFactory($driverFactory);

// You can also declare your custom destination that defined type of transport (queue, multi queues, topic, ...),
// the connection to use, and the name of the queue(s) / topic(s) to use.
// This example will use the queue driver of the "foo" connection defined above. And send / consume message on the queue named "default".
$destinationFactory = new ConfigurationDestinationFactory(
    ['my_destination' => 'queue://foo/default'],
    $destinationFactory
);

// Create the manager
$manager = new DestinationManager($driverFactory, $destinationFactory);
```

Push a basic message into the queue.
The consume should defined handler to process the message.

```PHP
<?php

use Bdf\Queue\Message\Message;

$message = Message::create('Hello world');
$message->setDestination('my_destination');
// or use a lower level setting the connection and queue.
$message = Message::create('Hello world', 'queue');
$message->setConnection('foo');

/** @var Bdf\Queue\Destination\DestinationManager $manager */
$manager->send($message);
```

Useful for monolithic application that needs to differ a process.
Push a message job into the queue. The consumer will evaluate the job string and run the processor.
In this use case the producer and the receiver share the same model.

```PHP
<?php
$message = \Bdf\Queue\Message\Message::createFromJob(Mailer::class.'@send', ['body' => 'my content']);
$message->setDestination('my_destination');

/** @var Bdf\Queue\Destination\DestinationManager $manager */
$manager->send($message);
```

#### Available type for dsn destination

The class `Bdf\Queue\Destination\DsnDestinationFactory` provides default type of destination:

|Name           | Exemple                                          | Definition     | 
|---------------|--------------------------------------------------|----------------|
|queue          | queue://connection_name/queue_name               | Publish and consume a single queue      |
|queues         | queues://connection_name/queue1,queue2           | Only consume multi queues      |
|topic          | topic://connection_name/topic                    | Publish and consume a topic. Pattern with wildcard are allowed for consumer use case only (ex: topic.*) |
|topics         | topics://connection_name/topic1,topic2           | Only consume multi topics      |

You can declare your own type:

```PHP
<?php

use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;

/** @var ResolverConnectionDriverFactory $driverFactory */

$destinationFactory = new Bdf\Queue\Destination\DsnDestinationFactory($driverFactory);
$destinationFactory->register('my_own_type', function(ConnectionDriverInterface $connection, DsnRequest $dsn) {
    // ...
});

// use dsn as "my_own_type://connection/queue_or_topic_name?option="
```

#### Consume messages

The consumer layer provides many tools for message handling.
The default stack of objects that will receive the message is:

`consumer (ConsumerInterface) -> receivers (ReceiverInterface) -> processor (ProcessorInterface) -> handler (callable)`

- `consumer` has the strategy for reading the message from queue / topic. It also manage a graceful shutdown.
- `receivers` is the stack of middlewares interacts with the envelope.
- `processor` resolves the handler arguments. You can plug here your business logic and remove the handler layer.
By default processor injects 2 arguments in handlers: the message data and the envelope.
- `handler` manages the business logic. Handler allows an interface less mode.

An example to consume a simple message:

```PHP
<?php

use Bdf\Queue\Consumer\Receiver\ProcessorReceiver;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Processor\CallbackProcessor;
use Bdf\Queue\Processor\MapProcessorResolver;

// Create your processor and declare in a map:
$myProcessor = new CallbackProcessor(function($data) {
    echo $data;
});
$processorResolver = new MapProcessorResolver(['foo' => $myProcessor]);

/** @var DestinationManager $manager */
$manager->create('queue://foo')->consumer(new ProcessorReceiver($processorResolver))->consume(0);
```

Consume a job message:

```PHP
<?php

use Bdf\Instantiator\Instantiator;
use Bdf\Queue\Consumer\Receiver\ProcessorReceiver;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Processor\JobHintProcessorResolver;

/** @var Instantiator $instantiator */

// The job should be provided from message to get the processor
$processorResolver = new JobHintProcessorResolver($instantiator);

/** @var DestinationManager $manager */
$manager->create('queue://foo')->consumer(new ProcessorReceiver($processorResolver))->consume(0);
```

#### Create a handler

```PHP
<?php

/** @var Bdf\Queue\Destination\DestinationManager $manager */

class MyHandler
{
    public function handle($data, \Bdf\Queue\Message\EnvelopeInterface $envelope)
    {
        echo $data; // Display 'foo'
        
        // Ack the message. Default behavior. The ack is sent before the call by the consumer.
        $envelope->acknowledge();
        
        // Reject the message. It will be no more available. The message is rejected if and exception is thrown.
        $envelope->reject();
        
        // Reject the message and send it back to the queue
        $envelope->reject(true);
    }
}

$message = \Bdf\Queue\Message\Message::createFromJob(MyHandler::class, 'foo', 'queue');
$manager->send($message);
```

Use the synthax `"Class@method"` to determine the callable (By default the method is "handle")
or register your handlers on a specific destination with the receiver builder:

```PHP
<?php

use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader;
use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */
/** @var Bdf\Queue\Destination\DestinationManager $manager */

$container->set(ReceiverLoader::class, function (ContainerInterface $container) {
    return new ReceiverLoader(
        $container,
        [
            'destination_name or connection_name' => function(ReceiverBuilder $builder) {
                /** @var \Bdf\Queue\Processor\ProcessorInterface $myProcessor */
                /** @var \Bdf\Queue\Consumer\ReceiverInterface $myReceiver */

                // Register your unique handler for the destination or connection. 
                // all message will be handled by this handler.
                $builder->handler(MyHandler::class);
                
                // Or register your unique processor
                $builder->processor($myProcessor);
                
                // Or register the job bearer resolver as processor. The procesor will resolve the job
                // from the Message::$job attribute value.
                $builder->jobProcessor();
                
                // Or register your own processor or handler by queue in case you consume a connection.
                // By default the key of the map is the queue name. You can provide your own key provider 
                // with the second parameter.
                $builder->mapProcessor([
                    'queue1' => $myProcessor,
                    'queue2' => MyHandler::class,
                ]);
                
                // Or register your final own receiver
                $builder->outlet($myReceiver);
                
                // Or register your own receiver in the stack
                $builder->add($myReceiver);
                
                // You can add more defined middlewares here
                // $builder->retry(2);
            }
        ]
    );
});

$receiver = $container->get(ReceiverLoader::class)->load('destination_name or connection_name')->build();

$manager->create('queue://foo')->consumer($receiver)->consume(0);
```

#### Run the consumer in console

```bash
$ example/consumer.php "connection name OR destination name"

```

##### Create worker extensions

The consumer use a stack of receivers to extend the reception of messages. 
See the interface `Bdf\Queue\Consumer\ReceiverInterface` and the trait `Bdf\Queue\Consumer\DelegateHelper`.

```PHP
<?php
class MyExtension implements \Bdf\Queue\Consumer\ReceiverInterface
{
    use \Bdf\Queue\Consumer\DelegateHelper;
    
    private $options;

    /**
     * MyExtension constructor.
     */
    public function __construct(\Bdf\Queue\Consumer\ReceiverInterface $delegate, array $options)
    {
        $this->delegate = $delegate;
        $this->options = $options;
    }
    
    /**
     * {@inheritdoc}
     */
    public function receiving($message, \Bdf\Queue\Consumer\ConsumerInterface $consumer): void
    {
        // Do something when receiving message
        if ($message->queue() === 'foo') {
            return;        
        }

        // Call the next receiver
        $this->delegate->receive($message, $consumer);
    }
}
```

You can use the `Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoader::add()` to register your receiver in the stack
```PHP
<?php
$options = ['foo' => 'bar'];

/** @var \Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder $builder */
$builder->add(MyExtension::class, [$options]);
```

#### Customize the string payload

The class `Bdf\Queue\Serializer\SerializerInterface` manage the payload content sent to the message broker.
By default metadata are added to the json as:
- PHP Type: to help consumer to deserialize complex entities.
- Message info: The attempt number for retry, The sending date, ...
    
A basic payload looks like:
```json
{
  "name": "Foo",
  "data": "Hello World",
  "date": "2019-12-23T16:02:03+01:00"
}
```

You can customize the string with your own implementation of the serializer interface.

Try the hello world example (configure the message broker in `example/config/connections.php`):
```bash
$ example/producer.php foo '{"name":"Foo", "data":"Hello World"}' --raw
$ example/consumer.php foo
```

#### RPC client

```PHP
<?php

use Bdf\Queue\Message\InteractEnvelopeInterface;
use Bdf\Queue\Message\Message;

class RpcReplyHandler
{
    public function doSomethingUseful(int $number, InteractEnvelopeInterface $envelope)
    {
        // Send bask: 1 x 2 to client
        $envelope->reply($number * 2);

        // Or retry in 10sec
        $envelope->retry(10);
    }
}

$message = Message::createFromJob(RpcReplyHandler::class.'@doSomethingUseful', 1, 'queue');
$message->setConnection('foo');

/** @var Bdf\Queue\Destination\DestinationManager $manager */
$promise = $manager->send($message);

// Consume the foo connection

// Receive data from the reply queue. If the header "replyTo" is not set, 
// the response will be sent to "queue_reply"
echo $promise->await(500)->data(); // Display 2
```


#### Additionnal options for connection

| Option              | Type        | Supports                       | Description  |
|---------------------|-------------|--------------------------------|--------------|
| `prefetch`          | int         |                                | Load a number of message in memory. Faster for some broker that supports reservation |
| `serializer`        | string      |                                | Load a serializer for this connection. Used only by driver that needs serializer. |
| `ttr`               | int         | pheanstalk                     | Time to run in seconds. Can also be defined in message header. Default `60`. |
| `client-timeout`    | int         | pheanstalk, gearman            | Timeout of client in milliseconds. Disable by default. |
| `commitAsync`       | bool        | rdkafka                        | Enable asynchrone ack. Default `true`. |
| `offset`            | int         | rdkafka                        | Position to start consumer. Default `null`. |
| `global`            | array       | rdkafka                        | Kafka config for global settings. |
| `dr_msg_cb`         | string      | rdkafka                        | Kafka config for global settings. |
| `error_cb`          | string      | rdkafka                        | Kafka config for global settings. |
| `rebalance_cb`      | string      | rdkafka                        | Kafka config for global settings. |
| `topic`             | array       | rdkafka                        | Kafka config for topic settings. |
| `partitioner`       | string      | rdkafka                        | Kafka partitioner for topic settings. |
| `sleep_duration`    | int         | amqp-lib                       | The internal sleep in milliseconds between two pop. Default `200`. |
| `queue_flags`       | int         | amqp-lib                       | The flag for queue declaration. See AmqpDriver constants. |
| `consumer_flags`    | int         | amqp-lib                       | The flag for consumer. See AmqpDriver constants. |
| `auto_declare`      | bool        | amqp-lib                       | Auto declare the queue when pushing or poping. Use queue setup command otherwise. |
| `timeout`           | int         | redis                          | The connection timeout in seconds. Default `0`. |
| `prefix`            | string      | redis                          | The key prefix. Default `queues:`. |
| `auto_declare`      | bool        | redis                          | Auto declare the queue when pushing or poping. Use queue setup command otherwise. |


#### Additionnal options for message

| Option              | Type        | Supports                       | Description  |
|---------------------|-------------|--------------------------------|--------------|
| `priority`          | int         | pheanstalk                     | Priority message. Default `1024`. |
| `ttr`               | int         | pheanstalk                     | Time to run in seconds. Default `60`. |
| `key`               | string      | rdkafka                        |  |
| `partition`         | int         | rdkafka                        | The number of the partition. |
| `flags`             | int         | amqp-lib                       | The flags for message. See driver constants. |


### Serialization

#### Benchmarks

simple job / closure job

|Serializer       | Serializer     | +Compress       | Bdf JSON      | +Compress     | Bdf binary    | 
|-----------------|----------------|-----------------|---------------|---------------|---------------|
|Size             | 141 / 377      | 105 / 244       | 109 / 407     | 76 / 247      | 98 / 355      |
|Serialize time   | 0.0014 / 6.8   | 0.016 / 7       | 0.011 / 7     | 0.026 / 7     | 0.011 / 7     |
|Unserialize time | 0.007 / 0.0025 | 0.0082 / 0.0068 | 0.024 / 0.015 | 0.024 / 0.019 | 0.019 / 0.011 |

#### Analysis

- For the best execution time, regardless of size, use the default `Serializer`
- For the smaller size, regardless of time, use `BdfSerializer` with `CompressedSerializer`
- For the best compromise, use `Serializer` with `CompressedSerializer`
    - Always smaller than pure `BdfSerializer` (JSON or Binary)
    - Faster on **unserialize**, slightly slower on **serialize**
    - Around **twice faster** than compressed bdf, but **only ~40% larger** on simple job
    

## License

Distributed under the terms of the MIT license.
