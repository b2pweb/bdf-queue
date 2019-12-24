<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Connection\Factory\CachedConnectionDriverFactory;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class GuessDestinationTest extends TestCase
{
    /**
     * @var DestinationManager
     */
    private $manager;

    protected function setUp(): void
    {
        $connectionFactory = new ResolverConnectionDriverFactory([
            'test' => 'test://host/default-queue',
            'other' => 'test://host/my-queue',
            'prefetched' => 'test:?prefetch=10',
        ]);
        $connectionFactory->addDriverResolver('test', function () { return new MemoryConnection(); });
        $connectionFactory = new CachedConnectionDriverFactory($connectionFactory);
        $this->manager = new DestinationManager(
            $connectionFactory,
            new ConfigurationDestinationFactory([
                'd1' => 'queue://other/q1'
            ], new DsnDestinationFactory($connectionFactory))
        );
    }

    /**
     *
     */
    public function test_create_default()
    {
        $this->assertEquals(
            $this->manager->queue('test', 'default-queue'),
            $this->manager->guess('')
        );
    }

    /**
     *
     */
    public function test_with_connection_name()
    {
        $this->assertEquals(
            $this->manager->queue('other', 'my-queue'),
            $this->manager->guess('other')
        );
    }

    /**
     *
     */
    public function test_with_destination_name()
    {
        $this->assertEquals(
            $this->manager->create('d1'),
            $this->manager->guess('d1')
        );
    }

    /**
     *
     */
    public function test_with_connection_and_queue_name()
    {
        $this->assertEquals(
            $this->manager->queue('test', 'other-queue'),
            $this->manager->guess('test::other-queue')
        );
    }
}
