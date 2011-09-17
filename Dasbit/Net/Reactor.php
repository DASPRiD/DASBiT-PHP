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
 * Reactor implementation.
 *
 * @category   DASBiT
 * @package    Dasbit_Net
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Reactor
{
    /**
     * Registered sockets.
     *
     * @var array
     */
    protected static $sockets = array(
        'sockets'   => array(),
        'callbacks' => array()
    );
    
    /**
     * Timeouts to execute.
     * 
     * @var array
     */
    protected static $timeouts = array();
    
    /**
     * Whether to stop the reactor in the next run.
     * 
     * @var boolean
     */
    protected static $stop = false;

    /**
     * Run the reactor.
     *
     * @return void
     */
    public static function run()
    {
        self::$stop = false;
        
        while (true) {
            if (self::$stop) {
                return;
            }
            
            // Check for timeouts
            $minTimeout = null;
            
            foreach (self::$timeouts as $ident => $timeout) {
                $timeLeft = (time() - $timeout['time']);
                
                if ($timeLeft <= 0) {
                    call_user_func($timeout['callback'], $ident);
                    unset(self::$timeouts[$ident]);
                } else {
                    $minTimeout = min($minTimeout, $timeLeft);
                }
            }
            
            // Check sockets
            $readers = $writers = $except = array_values(self::$sockets['sockets']);
            
            if (!count($readers)) {
                sleep($minTimeout);
                continue;
            }
            
            $changedSockets = @socket_select($readers, $writers, $except, $minTimeout);
            
            if ($changedSockets === false) {
                throw new Exception\SocketException(sprintf('Error while selecting: ', socket_strerror(socket_last_error())));
            } elseif ($changedSockets > 0) {
                foreach ($readers as $socket) {
                    if (isset(self::$sockets['callbacks'][(int) $socket])) {
                        call_user_func(self::$sockets['callbacks'][(int) $socket], 'read');
                    }
                }
                
                foreach ($writers as $socket) {
                    if (isset(self::$sockets['callbacks'][(int) $socket])) {
                        call_user_func(self::$sockets['callbacks'][(int) $socket], 'write');
                    }
                }
                
                foreach ($except as $socket) {
                    if (isset(self::$sockets['callbacks'][(int) $socket])) {
                        call_user_func(self::$sockets['callbacks'][(int) $socket], 'except');
                    }
                }
            }
        }
    }
    
    /**
     * Stop the reactor.
     * 
     * @return void
     */
    public static function stop()
    {
        self::$stop = true;
    }

    /**
     * Add a socket.
     *
     * @param  resource $socket
     * @param  mixed    $callback
     * @return void
     */
    public static function addSocket($socket, $callback)
    {
        if (!is_callable($callback)) {
            throw new Exception\InvalidArgumentException('$callback is no valid callback.');
        }
        
        $id = (int) $socket;

        self::$sockets['sockets'][$id]   = $socket;
        self::$sockets['callbacks'][$id] = $callback;
    }

    /**
     * Remove a socket.
     *
     * @param  resource $socket
     * @return void
     */
    public static function removeSocket($socket)
    {
        $id = (int) $socket;

        if (isset(self::$sockets['sockets'][$id])) {
            unset(self::$sockets['sockets'][$id]);
            unset(self::$sockets['callbacks'][$id]);
        }
    }

    /**
     * Add a timeout callback.
     *
     * @param  integer $seconds
     * @param  mixed   $callback
     * @return string
     */
    public static function addTimeout($seconds, $callback)
    {
        if (!is_callable($callback)) {
            throw new Exception\InvalidArgumentException('$callback is no valid callback.');
        }
        
        $ident = sha1(uniqid());
        
        self::$timeouts[$ident] = array(
            'time'     => (time() + $seconds),
            'callback' => $callback
        );
        
        return $ident;
    }
    
    /**
     * Remove a timeout.
     * 
     * @param  string $ident 
     * @return void
     */
    public static function removeTimeout($ident)
    {
        if (isset(self::$timeouts[$ident])) {
            unset(self::$timeouts[$ident]);
        }
    }
}
