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

use \Dasbit\Irc\Command;

/**
 * Core plugin.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Core extends Plugin
{
    /**
     * $dbSchema: defined by Plugin.
     * 
     * @see Plugin::$dbSchema
     * @var array
     */
    protected $dbSchema = array(
        'plugins' => array(
            'plugin_id'     => 'INTEGER PRIMARY KEY',
            'plugin_name'   => 'TEXT',
            'plugin_active' => 'INTEGER'
        )
    );

    /**
     * init(): defined by Plugin.
     * 
     * @see    Plugin::init()
     * @return void
     */
    protected function init()
    {
       
        $this->registerCommand('plugin activate', 'activate')
             ->registerCommand('plugin deactivate', 'deactivate');
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
            $row = $this->db->fetchOne(sprintf("
                SELECT plugin_active
                FROM plugins
                WHERE plugin_name = %s
            ", $this->db->quote($plugin->getName())));
            
            if ($row === false) {
                $active = false;
            } else {
                $active = (bool) $row['plugin_active'];
            }
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
     * Activate a plugin.
     * 
     * @param  Command $command 
     * @return void
     */
    public function activate(Command $command)
    {
        $pluginName = strtolower($command->getWord(0));
        
        if ($pluginName === 'core') {
            // Error: Core plugin not activatable.
        } elseif (!$this->manager->hasPlugin($pluginName)) {
            // Error: Plugin does not exist.
        }
        
        $this->switchPluginActive($command, $pluginName, true);
        
        // Reply: Plugin activated.
        
    }
    
    /**
     * Deactivate a plugin.
     * 
     * @param  Command $command
     * @return void
     */
    public function deactivate(Command $command)
    {
        $pluginName = strtolower($command->getWord(0));
        
        if ($pluginName === 'core') {
            // Error: Core plugin not deactivatable.
        } elseif (!$this->manager->hasPlugin($pluginName)) {
            // Error: Plugin does not exist.
        }
        
        $this->switchPluginActive($command, $pluginName, false);
        
        // Reply: Plugin deactivated.
    }
    
    /**
     * Switch the active status of a plugin.
     * 
     * @param  boolean $active 
     * @return void
     */
    protected function switchPluginActive(Command $command, $pluginName, $active)
    {
        $row = $this->db->fetchOne(sprintf("
            SELECT plugin_active
            FROM plugins
            WHERE plugin_name = %s
        ", $this->db->quote($pluginName)));
        
        if ($row === false) {
            $this->db->insert('plugins', array(
                'plugin_name'   => $pluginName,
                'plugin_active' => ($active ? 1 : 0)
            ));
        } else {
            $this->db->update('plugins', array(
                'plugin_name'   => $pluginName,
                'plugin_active' => ($active ? 1 : 0)
            ), sprintf("plugin_name = %s", $this->db->quote($pluginName)));
        }
    }
    
    /**
     * Check if a plugin is marked as active.
     * 
     * @param  string $pluginName
     * @return boolean
     */
    public function isPluginActive($pluginName)
    {
        $row = $this->db->fetchOne(sprintf("
            SELECT plugin_active
            FROM plugins
            WHERE plugin_name = %s
        ", $this->db->quote($plugin->getName())));

        if ($row === false) {
            return false;
        } else {
            return (bool) $row['plugin_active'];
        }
    }
}