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
namespace Dasbit\Plugin;

/**
 * ACL.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Acl
{
    /**
     * Resources.
     * 
     * @var array
     */
    protected $resources = array();
    
    /**
     * Create a new ACL object.
     * 
     * @param  string $aclString
     * @return void
     */
    public function __construct($aclString = null)
    {
        if ($aclString !== null) {
            $this->modify($aclString);
        }
    }
    
    /**
     * Modify ACL.
     * 
     * @param  string $aclString
     * @return void
     */
    public function modify($aclString)
    {
        $mods = array_filter(array_map('trim', explode(' ', $aclString)));
        
        foreach ($mods as $mod) {
            $mode = 'allow';
            
            if ($mod[0] === '-') {
                $mode = 'deny';
                $mod  = substr($mod, 1);
            } elseif ($mod[0] === '+') {
                $mod  = substr($mod, 1);
            }
            
            if (strpos($mod, '.') === false) {
                $resource  = $mod;
                $privilege = '*';
            } else {
                list($resource, $privilege) = explode('.', $mod);
            }
            
            if ($privilege === '*' && $mode === 'deny') {
                continue;
            }
            
            if (!isset($this->resources[$resource])) {
                $this->resources[$resource] = array();
            }
            
            $this->resources[$resource][$privilege] = ($mode === 'allow');
        }
        
        // Cleanup
        if (!isset($this->resources['*']['*'])) {
            foreach ($this->resources as $resource => $privileges) {
                if (!isset($privileges['*'])) {
                    foreach ($privileges as $privilege => $mode) {
                        if (!$mode) {
                            unset($this->resources[$resource][$privilege]);
                        }
                    }
                }
            }
        } else {
            foreach ($this->resources as $resource => $privileges) {
                foreach ($privileges as $privilege => $mode) {
                    if ($resource === '*' && $privilege === '*') {
                        continue;
                    } elseif ($mode) {
                        unset($this->resources[$resource][$privilege]);
                    }
                }
            }            
        }
    }
    
    /**
     * Check if access to a given resource is allowed.
     * 
     * @param  string $resource
     * @param  string $privilege 
     * @return boolean
     */
    public function isAllowed($resource, $privilege)
    {
        $allowed = false;
        
        if (isset($this->resources['*'])) {
            if (isset($this->resources['*']['*'])) {
                $allowed = true;
            }
            
            if (isset($this->resources['*'][$privilege])) {
                $allowed = $this->resources['*'][$privilege];
            }
        }
        
        if (isset($this->resources[$resource])) {
            if (isset($this->resources[$resource]['*'])) {
                $allowed = true;
            }
            
            if (isset($this->resources[$resource][$privilege])) {
                $allowed = $this->resources[$resource][$privilege];
            }            
        }
        
        return $allowed;
    }
    
    /**
     * Convert the ACL to a string.
     * 
     * @return string
     */
    public function __toString()
    {
        $mods = array();
        
        foreach ($this->resources as $resource => $privileges) {
            foreach ($privileges as $privilege => $mode) {
                $mods[] = ($mode ? '' : '-') . $resource . '.' . $privilege;
            }
        }
        
        return implode(' ', $mods);
    }
}