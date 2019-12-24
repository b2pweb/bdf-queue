<?php

namespace Bdf\Queue\Consumer\Receiver\Builder;

use Bdf\Instantiator\Instantiator;
use Bdf\Instantiator\InstantiatorInterface;
use League\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Class ReceiverLoaderTest
 */
class ReceiverLoaderTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(InstantiatorInterface::class, new Instantiator($this->container));
    }

    /**
     *
     */
    public function test_load_config_not_found()
    {
        $loader = new ReceiverLoader($this->container, []);

        $this->assertEquals(new ReceiverBuilder($this->container), $loader->load('not_found'));
    }

    /**
     *
     */
    public function test_load_with_config()
    {
        $loader = new ReceiverLoader($this->container, [
            'test' => function (ReceiverBuilder $builder) {
                $builder->limit(10);
            }
        ]);

        $this->assertEquals((new ReceiverBuilder($this->container))->limit(10), $loader->load('test'));
    }
}
