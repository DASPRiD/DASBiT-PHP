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
            $this->registerPlugin(new $pluginName($this, $databasePath));
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
        
        $this->plugins[$plugin->getName()] = array(
            'plugin'  => $plugin,
            'enabled' => $enabled
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
     * @return AbstractPlugin
     */
    public function getPlugin($pluginName)
    {
        if (!isset($this->plugins[$pluginName])) {
            return null;
        }
        
        return $this->plugins[$pluginName]['plugin'];
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
            $this->plugins[$pluginName]['enabled'] = true;
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
            $this->plugins[$pluginName]['enabled'] = false;
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
        $this->triggers[] = array(
            'pattern'    => $pattern,
            'pluginName' => $pluginName,
            'callback'   => $callback
        );
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
            if ($this->plugins[$hookData['pluginName']]['enabled']) {
                call_user_func($hookData['callback'], $hook, $data);
            }
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
            $command  = substr($message, strlen($this->commandPrefix), (strpos($message, ' ') ?: strlen($message)) - strlen($this->commandPrefix));

            if (isset($this->commands[$command]) && $this->plugins[$this->commands[$command]['pluginName']]['enabled']) {
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
            if ($this->plugins[$trigger['pluginName']]['enabled']) {
                if (preg_match('(' . $trigger['pattern'] . ')', $message)) {
                    call_user_func($trigger['callback'], $privMsg);
                }
            }
        }
    }
}