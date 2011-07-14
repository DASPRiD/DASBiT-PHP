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
namespace DasbitTest\Plugin;

use Dasbit\Plugin\Acl;

/**
 * ACL test.
 *
 * @category   DASBiT
 * @package    DasbitTest_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class AclTest extends \PHPUnit_Framework_TestCase
{
    protected $acl;

    public function setUp()
    {
        $this->acl = new Acl('');
    }

    public function testMasterAcl()
    {
        $this->acl->modify('*.*');
        
        $this->assertTrue($this->acl->isAllowed('foo', 'bar'));
    }
    
    public function testMasterDenyAcl()
    {
        $this->acl->modify('*.* -foo.bar');
        
        $this->assertFalse($this->acl->isAllowed('foo', 'bar'));
    }

    public function testToString()
    {
        $this->acl->modify('foo.* -foo.baz');
        
        $this->assertEquals('foo.* -foo.baz', $this->acl->__toString());
    }
    
    public function testToStringStripped()
    {
        $this->acl->modify('foo.bar -foo.baz');
        
        $this->assertEquals('foo.bar', $this->acl->__toString());
    }
}