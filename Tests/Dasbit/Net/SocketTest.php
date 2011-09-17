<?php
/**
 * DASBiT
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE
 *
 * @category   DASBiT
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */

/**
 * @namespace
 */
namespace DasbitTest\Net;

use Dasbit\Net\Socket,
    Dasbit\Net\Reactor;

/**
 * socket test.
 *
 * @category   DASBiT
 * @package    DasbitTest_Net
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class SocketTest extends \PHPUnit_Framework_TestCase
{
    public $server;
    public $port;

    public function setUp()
    {
        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); 
        
        socket_bind($this->server, '127.0.0.1', 0);
        socket_listen($this->server);
        
        $address = null;
        socket_getsockname($this->server, $address, $this->port);
    }
    
    public function tearDown()
    {
        socket_close($this->server);
        $this->server = null;
    }

    public function testSimpleConnection()
    {
        $test     = $this;
        $client   = null;
        $received = false;
        
        $socket = new Socket();
        $socket->onConnect(function() use ($socket, $test, &$client) {
            $client = socket_accept($test->server);           
            $socket->write('foobar');
            
            $readers = array($client);
            $writers = $except = null;
            
            $test->assertEquals(1, socket_select($readers, $writers, $except, 1));
            $test->assertEquals('foobar', socket_read($client, 6));
            
            socket_write($client, 'baz');
        })->onRead(function($data) use ($test, &$client, &$received) {
            $test->assertEquals('baz', $data);
            $received = true;
            
            socket_close($client);
        })->onDisconnect(function() use ($test, &$received){
            if (!$received) {
                $test->fail('Data not received');
            }
            
            Reactor::stop();
        });

        $socket->connect('127.0.0.1', $this->port);
        Reactor::run();
    }
}