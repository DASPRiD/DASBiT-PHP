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
 * Plugins plugin.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Plugins extends AbstractPlugin
{
    /**
     * $dbSchema: defined by Plugin.
     * 
     * @see Plugin::$dbSchema
     * @var array
     */
    protected $dbSchema = array(
        'plugins' => array(
            'plugin_id'      => 'INTEGER PRIMARY KEY',
            'plugin_name'    => 'TEXT',
            'plugin_enabled' => 'INTEGER'
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
        $this->registerCommand('plugin', 'switchEnabled', '(enable|disable) <plugin-name>');
    }

    /**
     * Switch the enabled status of a plugin.
     * 
     * @param  PrivMsg $privMsg
     * @return void
     */
    protected function switchEnabled(PrivMsg $privMsg)
    {
        $enable     = ($command->getWord(0) === 'enable' ? true : false);
        $pluginName = strtolower($command->getWord(1));
        
        if ($pluginName === 'core') {
            $this->manager->getClient()->reply($privMsg, sprintf('Plugin "%s" cannot be %s', $pluginName, ($enable ? 'enabled' : 'disabled')), Client::REPLY_NOTICE);
            return;
        } elseif (!$this->manager->hasPlugin($pluginName)) {
            $this->manager->getClient()->reply($privMsg, sprintf('Plugin "%s" does not exist', $pluginName), Client::REPLY_NOTICE);
            return;
        }
               
        $row = $this->db->fetchOne(sprintf("
            SELECT plugin_enabled
            FROM plugins
            WHERE plugin_name = %s
        ", $this->db->quote($pluginName)));
               
        if ($row === false) {
            $this->db->insert('plugins', array(
                'plugin_name'   => $pluginName,
                'plugin_enabled' => ($enable ? 1 : 0)
            ));
        } else {
            $this->db->update('plugins', array(
                'plugin_name'   => $pluginName,
                'plugin_enabled' => ($enable ? 1 : 0)
            ), sprintf("plugin_name = %s", $this->db->quote($pluginName)));
        }
        
        $this->manager->getClient()->reply($privMsg, sprintf('Plugin "%s" was %s', $pluginName, ($enable ? 'enabled' : 'disabled')), Client::REPLY_NOTICE);
    }
    
    /**
     * Check if a plugin is marked as enabled.
     * 
     * @param  string $pluginName
     * @return boolean
     */
    public function isEnabled($pluginName)
    {
        $row = $this->db->fetchOne(sprintf("
            SELECT plugin_enabled
            FROM plugins
            WHERE plugin_name = %s
        ", $this->db->quote($pluginName)));

        if ($row === false) {
            return false;
        } else {
            return (bool) $row['plugin_enabled'];
        }
    }
}