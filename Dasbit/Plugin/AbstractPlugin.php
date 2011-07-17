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
     * Enable the plugin.
     * 
     * In this method you should register commands, hooks, triggers and
     * timeouts. If you need to do initial stuff, overwrite the __construct(),
     * as this method may be called several times.
     * 
     * @return void
     */
    abstract public function enable();
    
    /**
     * Disable the plugin.
     * 
     * You don't have to overwrite this method to remove commands and such, as
     * they are automatically removed by plugin manager. This method is
     * intended to remove running sockets and so forth.
     * 
     * @return void
     */
    public function disable()
    {
    }
    
    /**
     * Register a command.
     * 
     * @param  mixed  $command
     * @param  string $method 
     * @param  string $restrict
     * @return Plugin
     */
    protected function registerCommand($command, $method, $restrict = null)
    {
        $this->manager->registerCommand($this->getName(), $command, array($this, $method), $restrict);
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
        $this->manager->registerHook($this->getName(), $hook, array($this, $method));
        return $this;
    }
    
    /**
     * Register a trigger.
     * 
     * @param  string $trigger
     * @param  string $method
     * @return Plugin
     */
    protected function registerTrigger($pattern, $method)
    {
        $this->manager->registerTrigger($this->getName(), $pattern, array($this, $method));
        return $this;
    }
}