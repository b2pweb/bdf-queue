<?php

namespace Bdf\Queue\Connection\AmqpLib;

use Bdf\Instantiator\Instantiator;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder;
use Bdf\Queue\Destination\ConfigurationDestinationFactory;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Destination\DsnDestinationFactory;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Processor\CallbackProcessor;
use Bdf\Queue\Processor\MapProcessorResolver;
use Bdf\Queue\Serializer\JsonSerializer;
use League\Container\Container;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class AmqpLibTopicTest extends TestCase
{
    /**
     * @return void
     */
    public function test_functional()
    {
        $driverFactory = new ResolverConnectionDriverFactory(['foo' => 'amqp://127.0.0.1?auto_declare=1']);

        $driverFactory->addDriverResolver('amqp', function($config) {
            return new AmqpLibConnection($config['connection'], new JsonSerializer());
        });

        $destinationFactory = new DsnDestinationFactory($driverFactory);
        $destinationFactory = new ConfigurationDestinationFactory(['my_destination' => 'topic://foo/default'], $destinationFactory);

        $manager = new DestinationManager($driverFactory, $destinationFactory);

        $destination = $manager->create('my_destination');

        try {
            $destination->declare();
        } catch (ConnectionFailedException $e) {
            if (stripos($e->getMessage(), 'Unable to connect to') !== false) {
                $this->markTestSkipped('RabbitMQ not started');
            }

            throw $e;
        }

        $builder = new ReceiverBuilder(
            $container = new Container(),
            new Instantiator($container)
        );

        $messages = [];

        $builder
            ->stopWhenEmpty()
            ->handler(function($data, EnvelopeInterface $envelope) use(&$messages) {
                $envelope->acknowledge();
                $messages[] = $data;
            })
        ;


        $destination->consumer($builder->build())->consume(1);
        sleep(1);

        $message = Message::create('Hello world');
        $message->setDestination('my_destination');
        $destination->send($message);

        $destination->consumer($builder->build())->consume(1);
        $this->assertSame(['Hello world'], $messages);
    }
}
