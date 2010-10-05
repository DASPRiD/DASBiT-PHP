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
 * Reactor implementation
 *
 * @category   DASBiT
 * @package    Dasbit_Reactor
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Reactor
{
    /**
     * Reading sockets
     *
     * @var array
     */
    protected $readers = array();

    /**
     * Run the reactor
     *
     * @return void
     */
    public function run()
    {
        while (true) {
            $readers = array_keys($this->readers);
            $writers = null;
            $except  = null;

            $changedSockets = socket_select($readers, $writers, $except, 1);

            if ($changedSockets === false) {
                // @todo What to do? :)
            } elseif ($changedSockets > 0) {
                foreach ($readers as $socket) {
                    call_user_func($this->readers[$socket]);
                }
            }
        }
    }

    /**
     * Add a reading socket
     *
     * @param  resource $socket
     * @param  callback $callback
     * @return void
     */
    public function addReader($socket, $callback)
    {
        $this->readers[] = array($socket, $callback);
    }

    /**
     * Remove a reading socket
     *
     * @param  resource $socket
     * @return void
     */
    public function removeReader($socket)
    {
        if (isset($this->readers[$socket])) {
            unset($this->readers[$socket]);
        }
    }
}