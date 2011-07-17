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
    \Dasbit\Irc\PrivMsg;

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
     * Prefix for commands.
     * 
     * @var string
     */
    protected $commandPrefix;
    
    /**
     * Registered plugins.
     * 
     * @var array
     */
    protected $plugins = array();
    
    /**
     * Registered commands.
     * 
     * @var array
     */
    protected $commands = array();
    
    /**
     * Registered hooks.
     * 
     * @var array
     */
    protected $hooks = array();
    
    /**
     * Registered triggers.
     * 
     * @var array
     */
    protected $triggers = array();
    
    /**
     * Registered timeouts.
     * 
     * @var array
     */
    protected $timeouts = array();
       
    /**
     * Load plugins from a directory.
     * 
     * @param  string $pluginsPath 
     * @param  string $databasePath
     * @param  stirng $commandPrefix
     * @return void
     */
    public function __construct($pluginsPath, $databasePath, $commandPrefix)
    {
        $this->commandPrefix = $commandPrefix;
        
        $this->registerPlugin(new Plugins($this, $databasePath), true);
        $this->registerPlugin(new Users($this, $databasePath), true);
        
        if (!is_dir($pluginsPath)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a directory', $pluginsPath));
        }
        
        $iterator = new \DirectoryIterator($pluginsPath);
        
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || !preg_match('(\.php$)', $fileInfo->getFilename())) {
                continue;
            }
            
            $pluginName = '\\Plugin\\' . $fileInfo->getBasename('.php');
            include $pluginsPath . '/' . $fileInfo->getFilename();
            
            $plugin = new $pluginName($this, $databasePath);
            
            if ($plugin instanceof AbstractPlugin) {
                $this->registerPlugin($plugin);
            }
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
     * If $enabled is set to null, it will be auto-discovered by the original
     * setting, or if not found, set to false.
     * 
     * @param  Plugin  $plugin
     * @param  boolean $enabled
     * @return Manager
     */
    public function registerPlugin(AbstractPlugin $plugin, $enabled = null)
    {
        if (isset($this->plugins[$plugin->getName()])) {
            throw new RuntimeException(sprintf('Plugin with name "%s" was already registered', $plugin->getName()));
        }
        
        if ($enabled === null) {
            $enabled = $this->getPlugin('plugins')->isEnabled($plugin->getName());
        }
        
        $this->plugins[$plugin->getName()] = $plugin;
        
        if ($enabled) {
            $plugin->enable();
        }
        
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
     * @return AbstractPlugin
     */
    public function getPlugin($pluginName)
    {
        if (!isset($this->plugins[$pluginName])) {
            return null;
        }
        
        return $this->plugins[$pluginName];
    }
    
    /**
     * Enable a plugin.
     * 
     * @param  string $pluginName 
     * @return void
     */
    public function enablePlugin($pluginName)
    {
        if (isset($this->plugins[$pluginName])) {
            $this->plugins[$pluginName]->enable();
        }
    }
    
    /**
     * Disable a plugin.
     * 
     * @param  string $pluginName 
     * @return void
     */
    public function disablePlugin($pluginName)
    {
        if (isset($this->plugins[$pluginName])) {
            // Remove commands
            foreach ($this->commands as $command => $data) {
                if ($data['pluginName'] === $pluginName) {
                    unset($this->commands[$command]);
                }
            }
            
            // Remove Triggers
            foreach ($this->triggers as $key => $data) {
                if ($data['pluginName'] === $pluginName) {
                    unset($this->triggers[$key]);
                }
            }
            
            // Remove hooks
            foreach ($this->hooks as $hookName => $hooks) {
                foreach ($hooks as $key => $data) {
                    if ($data['pluginName'] === $pluginName) {
                        unset($this->hooks[$hookName][$key]);
                    }
                }
            }
            
            // Remove timeouts
            if (isset($this->timeouts[$pluginName])) {
                foreach ($this->timeouts[$pluginName] as $ident) {
                    $this->client->getReactor()->removeTimeout($ident);
                }
                
                unset($this->timeouts[$pluginName]);
            }
        }
    }
    
    /**
     * Get the client the manager is attached to.
     * 
     * @return Client
     */
    public function getClient()
    {
        if ($this->client === null) {
            throw new RuntimeException('Client was not set');
        }
        
        return $this->client;
    }
    
    /**
     * Register a command.
     * 
     * @param  string $pluginName
     * @param  mixed  $command
     * @param  mixed  $callback
     * @param  string $restrict
     * @return void
     */
    public function registerCommand($pluginName, $command, $callback, $restrict = null)
    {
        if (!isset($this->plugins[$pluginName])) {
            throw new RuntimeException(sprintf('Plugin "%s" was not registered'));
        }
        
        if (is_string($command)) {
            $command = array($command);
        }
               
        foreach ($command as $option) {
            $this->commands[$option] = array(
                'pluginName' => $pluginName,
                'callback'   => $callback,
                'restrict'   => $restrict
            );
        }
    }
       
    /**
     * Register a hook.
     * 
     * @param  string $pluginName
     * @param  string $hook
     * @param  mixed  $callback
     * @return void
     */
    public function registerHook($pluginName, $hook, $callback)
    {
        if (!isset($this->plugins[$pluginName])) {
            throw new RuntimeException(sprintf('Plugin "%s" was not registered'));
        }
        
        if (!isset($this->hooks[$hook])) {
            $this->hooks[$hook] = array();
        }
        
        $this->hooks[$hook][] = array(
            'pluginName' => $pluginName,
            'callback'   => $callback
        );
    }
    
    /**
     * Register a trigger.
     * 
     * @param  string $pluginName
     * @param  string $pattern
     * @param  mixed  $callback
     * @return void
     */
    public function registerTrigger($pluginName, $pattern, $callback)
    {
        if (!isset($this->plugins[$pluginName])) {
            throw new RuntimeException(sprintf('Plugin "%s" was not registered'));
        }
        
        $this->triggers[] = array(
            'pluginName' => $pluginName,
            'pattern'    => $pattern,
            'callback'   => $callback
        );
    }
    
    /**
     * Register a timeout.
     * 
     * @param  string  $pluginName
     * @param  integer $seconds
     * @param  mixed   $callback
     * @return void
     */
    public function registerTimeout($pluginName, $seconds, $callback)
    {
        if (!isset($this->plugins[$pluginName])) {
            throw new RuntimeException(sprintf('Plugin "%s" was not registered'));
        }
        
        if (!isset($this->timeouts[$pluginName])) {
            $this->timeouts[$pluginName] = array();
        }
        
        $manager = $this;
        
        $cleanupCallback = function($index) use ($manager, $pluginName, $callback)
        {
            $manager->timeoutExecuted($pluginName, $index);
            call_user_func($callback);
        };
        
        $ident = $this->getClient()->getReactor()->addTimeout($seconds, $cleanupCallback);
        
        $this->timeouts[$pluginName][] = $ident;
    }
    
    /**
     * Called after a timeout was executed.
     * 
     * @param  string $pluginName
     * @param  string $ident
     * @return void
     */
    public function timeoutExecuted($pluginName, $index)
    {
        if (isset($this->timeouts[$pluginName][$index])) {
            unset($this->timeouts[$pluginName][$index]);
        }
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
        
        foreach ($this->hooks[$hook] as $hookData) {
            call_user_func($hookData['callback'], $hook, $data);
        }
    }
    
    /**
     * Check if a private message contains a command and execute it.
     * 
     * @param  PrivMsg $privMsg
     * @return void
     */
    public function checkMessage(PrivMsg $privMsg)
    {
        $message = $privMsg->getMessage();

        // Check for commands
        if (substr($message, 0, strlen($this->commandPrefix)) === $this->commandPrefix) {
            $command = substr($message, strlen($this->commandPrefix), (strpos($message, ' ') ?: strlen($message)) - strlen($this->commandPrefix));

            if (isset($this->commands[$command])) {
                $privMsg->setMessage(substr($message, strlen($this->commandPrefix) + strlen($command) + 1));
 
                if ($this->commands[$command]['restrict'] === null) {
                    call_user_func($this->commands[$command]['callback'], $privMsg);
                } else {
                    $this->getPlugin('users')->verifyAccess($this->commands[$command]['callback'], $privMsg, $this->commands[$command]['restrict']);
                }
                return;
            }
        }
        
        // Check for triggers
        foreach ($this->triggers as $trigger) {
            if (preg_match('(' . $trigger['pattern'] . ')', $message)) {
                call_user_func($trigger['callback'], $privMsg);
            }
        }
    }
}