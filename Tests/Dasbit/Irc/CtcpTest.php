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

    public function testPackMultipleMessages()
    {
        $expected = "Say hi to Ron\020n\t/actor\001USERINFO\001Say hi to Lara\001\001Say hi to Max";
        $result   = $this->ctcp->packMessage(array(
            "Say hi to Ron\n\t/actor",
            array(
                'tag'  => 'USERINFO',
                'data' => null
            ),
            'Say hi to Lara',
            'Say hi to Max'
        ));

        $this->assertEquals($expected, $result);
    }

    public function testUnpackMultipleMessages()
    {
        $result   = $this->ctcp->unpackMessage("Say hi to Ron\020n\t/actor\001USERINFO\001Say hi to Lara\001\001Say hi to Max");
        $expected = array(
            "Say hi to Ron\n\t/actor",
            array(
                'tag'  => 'USERINFO',
                'data' => null
            ),
            'Say hi to Lara',
            'Say hi to Max'
        );

        $this->assertEquals($expected, $result);
    }
}