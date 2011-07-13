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
 * Channel plugin.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Channel extends Plugin
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
            'channel_name' => 'VARCHAR(40)',
            'channel_key'  => 'VARCHAR(40)'
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
             ->registerHook('client.connected', 'connectedHook');
    }
    
    /**
     * Join a channel.
     * 
     * @param  Command $command
     * @return void
     */
    public function join(Command $command)
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
     * @param  Command $command
     * @return void
     */
    public function part(Command $command)
    {
        $this->client->part($command->getWord(0));
        
        $this->db->delete('channels', sprintf("channel_name = %s", $this->db->quote($command->getWord(0))));
    }
    
    /**
     * Called when the client is connected.
     * 
     * @param  string $hook
     * @return void
     */
    public function connectedHook($hook)
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
}