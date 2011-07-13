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
 * Abstract plugin.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
abstract class AbstractPlugin
{
    /**
     * Mananger the plugin is attached to.
     * 
     * @var Manager
     */
    protected $manager;
    
    /**
     * Database adapter.
     * 
     * @var \Dasbit\Database
     */
    protected $db;
    
    /**
     * Database schema.
     * 
     * If null, no database will be available.
     * 
     * @var array
     */
    protected $dbSchema;
    
    /**
     * Instantiate the plugin.
     * 
     * @param  Manager $manager
     * @param  string  $databasePath
     * @return void
     */
    public function __construct(Manager $manager, $databasePath)
    {
        $this->manager = $manager;
        
        if ($this->dbSchema !== null) {           
            $this->db = new \Dasbit\Database($databasePath . '/' . $this->getName() . '.db', $this->dbSchema);
        }
        
        $this->init();
    }
    
    /**
     * Get the name of the plugin.
     * 
     * @return string
     */
    public function getName()
    {
        return strtolower(array_pop(explode('\\', get_class($this))));
    }
    
    /**
     * Initiate the plugin.
     * 
     * @return void
     */
    abstract protected function init();
    
    /**
     * Register a command.
     * 
     * @param  mixed  $command
     * @param  string $method 
     * @return Plugin
     */
    protected function registerCommand($command, $method)
    {
        $this->manager->registerCommand($command, array($this, $method));
        return $this;
    }
    
    /**
     * Register a timeout.
     * 
     * @param  integer $seconds
     * @param  string  $method 
     * @return Plugin
     */
    protected function registerTimeout($seconds, $method)
    {
        $this->manager->registerTimeout($seconds, array($this, $method));
        return $this;
    }
    
    /**
     * Register a hook.
     * 
     * @param  string $hook
     * @param  string $method
     * @return Plugin
     */
    protected function registerHook($hook, $method)
    {
        $this->manager->registerHook($hook, array($this, $method));
        return $this;
    }
}