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
namespace Dasbit\Irc;

/**
 * IRC Client
 *
 * @category   DASBiT
 * @package    Dasbit_Irc
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Client
{
    /**
     * Reactor instance
     * 
     * @var Dasbit\Reactor
     */
    protected $reactor;

    /**
     * Instantiate a new IRC client
     *
     * @param Dasbit\Reactor $reactor
     * @return void
     */
    public function __construct(Dasbit\Reactor $reactor)
    {
        $this->reactor = $reactor;
    }

    /**
     * Connect to the server
     *
     * @return void
     */
    public function connect()
    {

    }
}