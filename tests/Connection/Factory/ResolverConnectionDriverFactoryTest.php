<?php

namespace Bdf\Queue\Connection\Factory;

use Bdf\Queue\Connection\Null\NullConnection;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class ResolverConnectionDriverFactoryTest extends TestCase
{
    /**
     * 
     */
    public function test_constructor()
    {
        $expected = [
            'host'    => 'localhost',
            'driver'  => 'test',
            'queue'   => 'test',
            'connection' => 'test'
        ];
        $config = [
            'test'  => $expected,
        ];
        
        $factory = new ResolverConnectionDriverFactory($config);
        
        $this->assertEquals($expected, $factory->getConfig('test'));
    }

    /**
     *
     */
    public function test_constructor_with_dsn()
    {
        $expected = [
            'host'    => 'localhost',
            'driver'  => 'test',
            'queue'   => 'test',
            'connection' => 'test'
        ];
        $config = [
            'test'  => 'test://localhost/test',
        ];

        $factory = new ResolverConnectionDriverFactory($config);

        $this->assertEquals($expected, $factory->getConfig('test'));
    }
    
    /**
     * 
     */
    public function test_get_unknown_connection()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('No queue config found for unknow');

        $factory = new ResolverConnectionDriverFactory();
        $factory->getConfig('unknow');
    }

    /**
     * 
     */
    public function test_getConfig_complex_dsn()
    {
        $factory = new ResolverConnectionDriverFactory([
            'test1' => 'test://localhost:1552/test?foo=bar',
            'test2' => 'test://localhost:1553/test/?foo=bar',
        ]);

        $expected = [
            'driver' => 'test',
            'host' => 'localhost',
            'port' => '1552',
            'queue' => 'test',
            'foo' => 'bar',
            'connection' => 'test1'
        ];
        $this->assertEquals($expected, $factory->getConfig('test1'));
        
        $expected = [
            'driver' => 'test',
            'host' => 'localhost',
            'port' => '1553',
            'queue' => 'test',
            'foo' => 'bar',
            'connection' => 'test2'
        ];
        $this->assertEquals($expected, $factory->getConfig('test2'));
    }

    /**
     *
     */
    public function test_basic_create()
    {
        $factory = new ResolverConnectionDriverFactory(['test' => 'test://localhost:1552/test']);
        $factory->addDriverResolver('test', function() { return new NullConnection('');});

        $this->assertInstanceOf(NullConnection::class, $factory->create('test'));
    }

    /**
     *
     */
    public function test_with_vendor()
    {
        $factory = new ResolverConnectionDriverFactory(['test1' => 'test+part1+part2:']);

        $expected = [
            'driver' => 'test',
            'vendor' => 'part1+part2',
            'queue' => 'test1',
            'connection' => 'test1'
        ];
        $this->assertEquals($expected, $factory->getConfig('test1'));
    }

    /**
     *
     */
    public function test_defaultConnection_not_configured_should_use_the_first_configured_connection()
    {
        $factory = new ResolverConnectionDriverFactory([
            'test' => 'test://localhost:1552/test',
            'test2' => 'test://localhost:1552/test',
        ]);
        $factory->addDriverResolver('test', function($config) { return new NullConnection($config['connection']);});

        $this->assertEquals('test', $factory->defaultConnectionName());
        $this->assertEquals('test', $factory->defaultConnection()->getName());
    }

    /**
     *
     */
    public function test_defaultConnection_configured()
    {
        $factory = new ResolverConnectionDriverFactory([
            'test' => 'test://localhost:1552/test',
            'test2' => 'test://localhost:1552/test',
        ], 'test2');
        $factory->addDriverResolver('test', function($config) { return new NullConnection($config['connection']);});

        $this->assertEquals('test2', $factory->defaultConnectionName());
        $this->assertEquals('test2', $factory->defaultConnection()->getName());
    }

    /**
     *
     */
    public function test_callable_resolver()
    {
        $factory = new ResolverConnectionDriverFactory(['test' => 'test://localhost:1552/test']);
        $factory->addDriverResolver('test', [$this, 'createNullConnection']);

        $this->assertInstanceOf(NullConnection::class, $factory->create('test'));
    }

    public static function createNullConnection($config)
    {
        return new NullConnection($config['connection']);
    }

    /**
     *
     */
    public function test_defaultConnection_not_found()
    {
        $this->expectException(\LogicException::class);

        $factory = new ResolverConnectionDriverFactory([]);
        $factory->defaultConnection();
    }

}
