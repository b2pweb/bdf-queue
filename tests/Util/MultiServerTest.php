<?php

namespace Bdf\Queue\Util;

use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Util
 */
class MultiServerTest extends TestCase
{
    /**
     *
     */
    public function test_empty_config()
    {
        $config = [
            'hosts' => [
                'localhost' => 80
            ]
        ];

        $this->assertSame($config, MultiServer::prepareMultiServers([], 'localhost', 80));
    }

    /**
     *
     */
    public function test_one_server_config()
    {
        $config = [
            'hosts' => [
                '127.0.0.1' => 80
            ]
        ];

        $this->assertSame($config, MultiServer::prepareMultiServers(['host' => '127.0.0.1'], 'localhost', 80));
    }

    /**
     *
     */
    public function test_multi_server_config()
    {
        $config = [
            'hosts' => [
                '127.0.0.1' => 80,
                'localhost' => 80,
            ]
        ];

        $this->assertSame($config, MultiServer::prepareMultiServers(['host' => '127.0.0.1,localhost'], 'localhost', 80));
    }
}
