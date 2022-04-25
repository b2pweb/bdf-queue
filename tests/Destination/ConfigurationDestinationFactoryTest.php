<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigurationDestinationFactoryTest
 */
class ConfigurationDestinationFactoryTest extends TestCase
{
    /**
     * @var DsnDestinationFactory
     */
    private $innerFactory;

    protected function setUp(): void
    {

        $connectionFactory = (new ResolverConnectionDriverFactory(['test' => 'test://host/default-queue']));
        $connectionFactory->addDriverResolver('test', function () { return new MemoryConnection(); });

        $this->innerFactory = new DsnDestinationFactory($connectionFactory);
    }

    /**
     *
     */
    public function test_create_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The destination not_found is not configured');

        $factory = new ConfigurationDestinationFactory([], $this->innerFactory);

        $factory->create('not_found');
    }

    /**
     *
     */
    public function test_create_success()
    {
        $factory = new ConfigurationDestinationFactory(['dest' => 'queue://test/my-queue'], $this->innerFactory);

        $destination = $factory->create('dest');
        $this->assertEquals($destination, $this->innerFactory->create('queue://test/my-queue'));
    }

    /**
     *
     */
    public function test_getting_destination_names()
    {
        $factory = new ConfigurationDestinationFactory([
            'test1' => 'queue://test/my-queue',
            'test2' => 'queue://test/your-queue',
        ], $this->innerFactory);

        $this->assertSame(['test1', 'test2'], $factory->destinationNames());
    }
}
