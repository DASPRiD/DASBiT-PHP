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
namespace DasbitTest\Irc;

use Dasbit\Irc\Ctcp;

/**
 * IRC Client
 *
 * @category   DASBiT
 * @package    Dasbit_Irc
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class CtcpTest extends \PHPUnit_Framework_TestCase
{
    protected $ctcp;

    public function setUp()
    {
        $this->ctcp = new Ctcp();
    }

    public function testQuote()
    {
        $result = $this->ctcp->quote("Foo\r\nBar\0Baz\20");
        $this->assertEquals("Foo\20r\20nBar\20" . "0" . "Baz\20\20", $result);
    }

    public function testDequote()
    {
        $result = $this->ctcp->dequote("Foo\20r\20nBar\20" . "0" . "Baz\20\20\20F");
        $this->assertEquals("Foo\r\nBar\0Baz\20F", $result);
    }
}