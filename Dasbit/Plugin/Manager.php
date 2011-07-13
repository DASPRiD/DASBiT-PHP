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

use \Dasbit\Irc\Client,
    \Dasbit\Irc\Command;

/**
 * Plugin manager.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Manager
{
    /**
     * Client the manager is attached to.
     * 
     * @var Client
     */
    protected $client;
    
    /**
     * Registered plugins.
     * 
     * @var array
     */
    protected $plugins = array();
       
    /**
     * Load plugins from a directory.
     * 
     * @param  string $pluginsPath 
     * @param  string $databasePath
     * @return void
     */
    public function __construct($pluginsPath, $databasePath)
    {       
        $this->registerPlugin(new Core($this, $databasePath . '/core.db'), true);
        
        if (!is_dir($pluginsPath)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a directory', $pluginsPath));
        }
        
        $iterator = new \DirectoryIterator($pluginsPath);
        
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }
            
            $pluginName = '\\Plugin\\' . $fileInfo->getBasename('.php');
            include $pluginsPath . '/' . $fileInfo->getFilename();
            $this->registerPlugin(new $pluginName($databasePath));
        }
    }
    
    /**
     * Set the client after attaching the manager to it.
     * 
     * @param  Client $client 
     * @return void
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }
    
    /**
     * Register a plugin.
     * 
     * If $active is set to null, it will be auto-discovered by the original
     * setting, or if not found, set to false.
     * 
     * @param  Plugin  $plugin
     * @param  boolean $active
     * @return Manager
     */
    public function registerPlugin(Plugin $plugin, $active = null)
    {
        if (isset($this->plugins[$plugin->getName()])) {
            throw new RuntimeException(sprintf('Plugin with name "%s" was already registered', $plugin->getName()));
        }
        
        if ($active === null) {
            $active = $this->getPlugin('core')->isPluginActive($plugin->getName());
        }
        
        $this->plugins[$plugin->getName()] = array(
            'plugin'   => $plugin,
            'active'   => $active,
            'commands' => array(),
            'hooks'    => array()
        );
        
        return $this;
    }
    
    /**
     * Check if a specific plugin was registered.
     * 
     * @param  string $pluginName
     * @return boolean
     */
    public function hasPlugin($pluginName)
    {
        return isset($this->plugins[$pluginName]);
    }
    
    /**
     * Get a specific plugin.
     * 
     * @param  string $pluginName 
     * @return Plugin
     */
    public function getPlugin($pluginName)
    {
        if (!isset($this->plugins[$pluginName])) {
            return null;
        }
        
        return $this->plugins[$pluginName];
    }
    
    /**
     * Get the client the manager is attached to.
     * 
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }
    
    /**
     * Register a command.
     * 
     * @param  mixed $command
     * @param  mixed $callback
     * @return void
     */
    public function registerCommand($command, $callback)
    {
        if (is_string($command)) {
            $command = array($command);
        }
        
        foreach ($command as $option) {
            $this->commands[$option] = $callback;
        }
    }
    
    /**
     * Register a timeout.
     * 
     * @param  integer $seconds
     * @param  mixed   $callback
     * @return void
     */
    public function registerTimeout($seconds, $callback)
    {
        $this->getClient()->getReactor()->addTimeout($seconds, $callback);
    }
    
    /**
     * Register a hook.
     * 
     * @param  string $hook
     * @param  mixed  $callback
     * @return void
     */
    public function registerHook($hook, $callback)
    {
        if (!isset($this->hooks[$hook])) {
            $this->hooks[$hook] = array();
        }
        
        $this->hooks[$hook][] = $callback;
    }
    
    /**
     * Trigger a hook.
     * 
     * @param  string $hook
     * @param  mixed  $data 
     * @return void
     */
    public function triggerHook($hook, $data = null)
    {
        if (!isset($this->hooks[$hook])) {
            return;
        }
        
        foreach ($this->hooks[$hook] as $callback) {
            call_user_func($callback, $hook, $data);
        }
    }
}