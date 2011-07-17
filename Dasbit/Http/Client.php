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
namespace Dasbit\Http;

/**
 * HTTP Client.
 *
 * @category   DASBiT
 * @package    Dasbit_Http
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Client
{
    /**
     * Reactor instance.
     * 
     * @var Reactor
     */
    protected $reactor;
    
    /**
     * Client socket.
     *
     * @var resource
     */
    protected $socket;
    
    /**
     * Callback to call after response was received.
     * 
     * @var mixed
     */
    protected $callback;
    
    /**
     * Response buffer.
     * 
     * @var string
     */
    protected $buffer;
    
    /**
     * Create a new HTTP client.
     * 
     * @param  Reactor $reactor
     * @return void
     */
    public function __construct($reactor)
    {
        $this->reactor = $reactor;
    }
    
    /**
     * Request a URL.
     * 
     * If $postVariables is set to null, a GET request is made, else it will be
     * a POST request. If the $url is not valid, false is returned.
     * 
     * @param  mixed  $callback
     * @param  string $url
     * @param  array  $postVariables
     * @return boolean
     */
    public function request($callback, $url, array $postVariables = null)
    {
        $components = $this->parseUrl($url);
        
        if ($components === null || $components['scheme'] === 'https') {
            return false;
        }
        
        $this->callback = $callback;

        $address = gethostbyname($components['host']);

        if (ip2long($address) === false || ($address === gethostbyaddr($address)
            && preg_match("#.*\.[a-zA-Z]{2,3}$#", $host) === 0) )
        {
           return false;
        }
        
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, 0);

        if ($this->socket === false) {
            return false;
        }

        $connected = @socket_connect($this->socket, $address, $components['port']);
        
        if (!$connected) {
            return false;
        }
        
        $this->reactor->addReader($this->socket, array($this, 'receiveData'));
        
        $data = "GET " . $components['path'] . "?" . $components['query'] . " HTTP/1.1\r\n"
              . "Host: " . $components['host'] . "\r\n"
              . "Connection: Close\r\n\r\n";

        socket_write($this->socket, $data);
        
        return true;
    }
    
    /**
     * Data received from socket.
     * 
     * @return void
     */
    public function receiveData()
    {
        while (false !== ($data = socket_read($this->socket, 512))) {
            if ($data === '') {
                $this->reactor->removeReader($this->socket);
                
                call_user_func($this->callback, Response::fromString($this->buffer));
                return;
            }
            
            $this->buffer .= $data;
        }
    }
    
    /**
     * Parse a URL.
     * 
     * @param  string $url 
     * @return array
     */
    protected function parseUrl($url)
    {
        $components = parse_url($url);
        
        if ($components === false) {
            return null;
        }
                
        if (!isset($components['scheme'])) {
            $components['scheme'] = 'http';
        } elseif (!in_array($components['scheme'], array('http', 'https'))) {
            return null;
        }
        
        if (!isset($components['host'])) {
            return null;
        }
        
        if (!isset($components['port'])) {
            if ($components['scheme'] === 'http') {
                $components['port'] = 80;
            } else {
                $components['port'] = 443;
            }
        }
        
        if (!isset($components['user'])) {
            $components['user'] = null;
        }
        
        if (!isset($components['pass'])) {
            $components['pass'] = null;
        }
        
        if (!isset($components['path'])) {
            $components['path'] = '/';
        }
        
        if (!isset($components['query'])) {
            $components['query'] = '';
        }
        
        return $components;
    }
}