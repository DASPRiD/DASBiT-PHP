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
 * CLI interface.
 *
 * @category   DASBiT
 * @package    Dasbit_Cli
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Cli
{
    /**
     * Stream to write data to.
     * 
     * @var resource
     */
    protected $stream;

    /**
     * Open STDOUT for writing.
     *
     * @return void
     */
    public function __construct()
    {
        $this->stream = fopen('php://stdout', 'w');
    }

    /**
     * Closing writing stream.
     *
     * @return void
     */
    public function __destruct()
    {
        fclose($this->stream);
    }

    /**
     * Output data coming from the server.
     *
     * @param  string $string
     * @return void
     */
    public function serverOutput($string)
    {
        fwrite($this->stream, rtrim($string, "\r\n") . "\n");
    }

    /**
     * Output data coming from the client.
     *
     * @param  string $string
     * @return void
     */
    public function clientOutput($string)
    {
        $string = "\33[1;34m"
                . $string
                . "\33[0m";

        fwrite($this->stream, rtrim($string, "\r\n") . "\n");
    }
}