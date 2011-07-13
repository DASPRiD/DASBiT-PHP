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
abstract class Plugin
{
    /**
     * Client the plugin is attached to.
     * 
     * @var \Dasbit\Irc\Client
     */
    protected $client;
    
    /**
     * Database adapter.
     * 
     * @var \Dasbit\Db
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
     * @param  \Dasbit\Irc\Client $client
     * @return void
     */
    public function __construct(\Dasbit\Irc\Client $client)
    {
        $this->client = $client;
        
        if ($this->dbSchema !== null) {           
            $this->db = new \Dasbit\Db($this->getName(), $this->dbSchema);
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
        return array_pop(explode('\\', get_class($this)));
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
        $this->client->registerCommand($command, array($this, $method));
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
        $this->client->registerTimeout($seconds, array($this, $method));
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
        $this->client->registerHook($hook, array($this, $method));
        return $this;
    }
}