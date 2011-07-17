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
namespace Dasbit;

/**
 * Reactor implementation.
 *
 * @category   DASBiT
 * @package    Dasbit_Reactor
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Reactor
{
    /**
     * Reading sockets.
     *
     * @var array
     */
    protected $readers = array(
        'sockets'   => array(),
        'callbacks' => array()
    );
    
    /**
     * Timeouts to execute.
     * 
     * @var array
     */
    protected $timeouts = array();

    /**
     * Run the reactor.
     *
     * @return void
     */
    public function run()
    {
        $writers = null;
        $except  = null;

        while (true) {
            // Check for timeouts
            $minTimeout = null;
            
            foreach ($this->timeouts as $ident => $timeout) {
                $timeLeft = (time() - $timeout['time']);
                
                if ($timeLeft <= 0) {
                    call_user_func($timeout['callback'], $ident);
                    unset($this->timeouts[$ident]);
                } else {
                    $minTimeout = min($minTimeout, $timeLeft);
                }
            }
            
            // Check sockets
            $readers        = array_values($this->readers['sockets']);
            $changedSockets = socket_select($readers, $writers, $except, $minTimeout);

            if ($changedSockets === false) {
                // Error on one of the sockets, ignore.
            } elseif ($changedSockets > 0) {
                foreach ($readers as $socket) {
                    call_user_func($this->readers['callbacks'][(int) $socket]);
                }
            }
        }
    }

    /**
     * Add a reading socket.
     *
     * @param  resource $socket
     * @param  mixed    $callback
     * @return void
     */
    public function addReader($socket, $callback)
    {
        $id = (int) $socket;

        $this->readers['sockets'][$id]   = $socket;
        $this->readers['callbacks'][$id] = $callback;
    }

    /**
     * Remove a reading socket.
     *
     * @param  resource $socket
     * @return void
     */
    public function removeReader($socket)
    {
        $id = (int) $socket;

        if (isset($this->readers['sockets'][$id])) {
            unset($this->readers['sockets'][$id]);
            unset($this->readers['callbacks'][$id]);
        }
    }

    /**
     * Add a timeout callback.
     *
     * @param  integer $seconds
     * @param  mixed   $callback
     * @return string
     */
    public function addTimeout($seconds, $callback)
    {
        $ident = sha1(uniqid());
        
        $this->timeouts[$ident] = array(
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
    public function removeTimeout($ident)
    {
        if (isset($this->timeouts[$ident])) {
            unset($this->timeouts[$ident]);
        }
    }
}
