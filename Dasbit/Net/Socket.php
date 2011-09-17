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
namespace Dasbit\Net;

use \Dasbit\Exception;

/**
 * Socket implementation.
 *
 * @category   DASBiT
 * @package    Dasbit_Net
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Socket
{
    const EINTR        = 4;
    const EBADF        = 9;
    const EAGAIN       = 11;
    const EWOULDBLOCK  = 11;
    const EINVAL       = 22;
    const EPIPE        = 32;
    const ECONNABORTED = 103;
    const ECONNRESET   = 104;
    const EISCONN      = 106;
    const ENOTCONN     = 107;
    const ESHUTDOWN    = 112;
    const EALREADY     = 114;
    const EINPROGRESS  = 115;
    
    /**
     * Disconnected constants.
     * 
     * @var array
     */
    protected static $disconnected = array(
        self::ECONNRESET, self::ENOTCONN, self::ESHUTDOWN, self::ECONNABORTED,
        self::EPIPE, self::EBADF
    );

    /**
     * Socket.
     * 
     * @var resource
     */
    protected $socket;
    
    /**
     * Protocol family.
     * 
     * @var integer
     */
    protected $family;
    
    /**
     * Whether the socket is connected.
     * 
     * @var boolean
     */
    protected $connected = false;
    
    /**
     * Connect callback.
     * 
     * @var mixed
     */
    protected $connectCallback;

    /**
     * Disconnect callback.
     * 
     * @var mixed
     */
    protected $disconnectCallback;
    
    /**
     * Data callback.
     * 
     * @var mixed
     */
    protected $readCallback;
    
    /**
     * Create a new socket.
     * 
     * @param  integer $family
     * @return void
     */
    public function __construct($family = AF_INET)
    {
        if ($family === AF_INET6) {
            throw new Exception\SocketException('INET6 protocol family is not supported');
        }
        
        $this->socket = @socket_create($family, SOCK_STREAM, SOL_TCP);
        $this->family = $family;
        
        if ($this->socket === false) {
            throw new Exception\SocketException('Could not create socket');
        }
        
        @socket_set_nonblock($this->socket);
    }
    
    /**
     * Remove socket on destruction.
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }
    
    /**
     * Open a connection.
     * 
     * @param  string  $address
     * @param  integer $port
     * @return void
     */
    public function connect($address, $port = 0)
    {
        $socket = $this;
        Reactor::addSocket($this->socket, function($event) use ($socket) {
            if ($event === 'read') {
                $socket->handleReadEvent();
            } elseif ($event === 'write') {
                $socket->handleWriteEvent();
            } elseif ($event === 'except') {
                $socket->handleExceptEvent();
            }
        });
        
        if ($this->family === AF_INET) {
            // Get IP from hostname
            $address = gethostbyname($address);

            if ($address === gethostbyname($address) && ip2long($address) === false) {
                throw new Exception\SocketException(sprintf('%s is not a valid host', $host));
            } elseif (!is_integer($port)) {
                throw new Exception\SocketException('Port must be an integer');
            }
        } elseif ($this->family === AF_UNIX) {
            $port = 0;
        }

        $this->connected = false;
        $result          = @socket_connect($this->socket, $address, $port);
            
        if ($result === false) {
            $error = socket_last_error($this->socket);
            
            if (in_array($error, array(self::EINPROGRESS, self::EALREADY, self::EWOULDBLOCK))) {
                return;
            } elseif ($error !== self::EISCONN) {
                throw new Exception\SocketException(sprintf('Error while connecting: ', socket_strerror($error)));
            }
        }
        
        $this->handleConnectEvent();
    }
    
    /**
     * Write data to the socket.
     * 
     * @param  string $data
     * @return integer
     */
    public function write($data)
    {
        $result = socket_write($this->socket, $data);

        if ($result === false) {
            $error = socket_last_error($this->socket);

            if ($errror === self::EWOULDBLOCK) {
                return 0;
            } elseif (in_array($error, self::$disconnected)) {
                $this->handleClose();
                return 0;
            } else {
                throw new Exception\SocketException(sprintf('Error while writing: ', socket_strerror($error)));
            }
        }

        return $result;
    }
    
    /**
     * Read data from the socket.
     * 
     * @return string
     */
    protected function read()
    {
        $buffer = '';

        while (false !== ($data = @socket_read($this->socket, 512))) {
            if ($data === '') {
                $this->handleClose();
                return null;
            }
            
            $buffer .= $data;
        }
        
        return $buffer;
    }
    
    /**
     * Close the socket.
     * 
     * @return void
     */
    public function close()
    {
        Reactor::removeSocket($this->socket);
    
        if ($this->connected) {
            $this->connected = false;
            socket_close($this->socket);
        }
    }
    
    /**
     * Handle a connect event.
     * 
     * @return void
     */
    protected function handleConnectEvent()
    {
        $error = socket_get_option($this->socket, SOL_SOCKET, SO_ERROR);
        
        if ($error !== 0) {
            throw new Exception\SocketException(sprintf('Connect event error: ', socket_strerror($error)));
        }
        
        $this->handleConnect();
        $this->connected = true;
    }
    
    /**
     * Handle a read event.
     * 
     * @return void
     */
    public function handleReadEvent()
    {
        if (!$this->connected) {
            $this->handleConnectEvent();
        }
        
        $this->handleRead();
    }
    
    /**
     * Handle a write event.
     * 
     * @return void
     */
    public function handleWriteEvent()
    {
        if (!$this->connected) {
            $error = socket_get_option($this->socket, SOL_SOCKET, SO_ERROR);
            
            if ($error !== 0) {
                throw new Exception\SocketException(sprintf('Write event error: ', socket_strerror($error)));
            }
            
            $this->handleConnectEvent();
        }
    }
    
    /**
     * Handle an except event.
     * 
     * @return void
     */
    public function handleExceptEvent()
    {
        $error = socket_get_option($this->socket, SOL_SOCKET, SO_ERROR);
            
        if ($error !== 0) {
            $this->handleClose();
        }
    }
    
    /**
     * Handle a read.
     * 
     * @return void
     */
    protected function handleRead()
    {
        $data = $this->read();
        
        if ($data !== null && $this->readCallback !== null) {
            call_user_func($this->readCallback, $data);
        }
    }

    /**
     * Handle a connect.
     * 
     * @return void
     */
    protected function handleConnect()
    {
        if ($this->connectCallback !== null) {
            call_user_func($this->connectCallback);
        }
    }
    
    /**
     * Handle a close.
     * 
     * @return void
     */
    protected function handleClose()
    {
        $this->close();
        
        if ($this->disconnectCallback !== null) {
            call_user_func($this->disconnectCallback);
        }
    }
    
    /**
     * Set a connect callback.
     * 
     * @param  mixed $callback
     * @return self 
     */
    public function onConnect($callback)
    {
        if (!is_callable($callback)) {
            throw new Exception\InvalidArgumentException('$callback is no valid callback.');
        }
        
        $this->connectCallback = $callback;
        
        return $this;
    }
    
    /**
     * Set a disconnect callback.
     * 
     * @param  mixed $callback
     * @return self 
     */
    public function onDisconnect($callback)
    {
        if (!is_callable($callback)) {
            throw new Exception\InvalidArgumentException('$callback is no valid callback.');
        }
        
        $this->disconnectCallback = $callback;
        
        return $this;
    }
    
    /**
     * Set a read callback.
     * 
     * @param  mixed $callback
     * @return self 
     */
    public function onRead($callback)
    {
        if (!is_callable($callback)) {
            throw new Exception\InvalidArgumentException('$callback is no valid callback.');
        }
        
        $this->readCallback = $callback;
        
        return $this;
    }
    
    /**
     * Check whether the socket is connected.
     * 
     * @return boolean
     */
    public function isConnected()
    {
        return $this->connected;
    }
}
