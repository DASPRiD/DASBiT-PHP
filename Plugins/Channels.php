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

use \Dasbit\Plugin\AbstractPlugin,
    \Dasbit\Irc\PrivMsg;

/**
 * Channel plugin.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Channels extends AbstractPlugin
{
    /**
     * $dbSchema: defined by Plugin.
     * 
     * @see Plugin::$dbSchema
     * @var array
     */
    protected $dbSchema = array(
        'channels' => array(
            'channel_id'   => 'INTEGER PRIMARY KEY',
            'channel_name' => 'TEXT',
            'channel_key'  => 'TEXT'
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
        $this->registerCommand('join', 'join')
             ->registerCommand('part', 'part')
             ->registerHook('reply.connected', 'connectedHook')
             ->registerHook('error.no-such-channel', 'removeHook')
             ->registerHook('error.too-many-channels', 'removeHook');
    }
    
    /**
     * Join a channel.
     * 
     * @param  PrivMsg $privMsg
     * @return void
     */
    public function join(PrivMsg $privMsg)
    {
        $this->client->join($command->getWord(0), $command->getWord(1));
        
        $this->db->insert('channels', array(
            'channel_name' => $command->getWord(0),
            'channel_key'  => $command->getWord(1)
        ));
    }
    
    /**
     * Part a channel.
     * 
     * @param  PrivMsg $privMsg
     * @return void
     */
    public function part(PrivMsg $privMsg)
    {
        $this->client->part($command->getWord(0));
        
        $this->db->delete('channels', sprintf("channel_name = %s", $this->db->quote($command->getWord(0))));
    }
    
    /**
     * Called when the client is connected.
     * 
     * @param  string $hook
     * @param  mixed  $data
     * @return void
     */
    public function connectedHook($hook, $data)
    {
        $channels = $this->db->fetchAll("
            SELECT channel_name,
                   channel_key
            FROM channels
        ");
        
        foreach ($channels as $channel) {
            $this->client->join($channel['channel_name'], $channel['channel_key']);
        }
    }
    
    /**
     * Called when the joining a channel failed.
     * 
     * @param  string $hook
     * @param  mixed  $data
     * @return void
     */
    public function removeHook($hook, $data)
    {
        $this->db->delete('channels', sprintf("channel_name = %s", $this->db->quote($data)));
    }
}