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
 * Users plugin.
 *
 * @category   DASBiT
 * @package    Dasbit_Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Users extends AbstractPlugin
{
    /**
     * $dbSchema: defined by Plugin.
     * 
     * @see Plugin::$dbSchema
     * @var array
     */
    protected $dbSchema = array(
        'users' => array(
            'user_id'   => 'INTEGER PRIMARY KEY',
            'user_name' => 'TEXT',
            'user_acl'  => 'TEXT'
        )
    );
    
    /**
     * List of users.
     * 
     * @var array
     */
    protected $users = array();
    
    /**
     * List of idents.
     * 
     * @var array
     */
    protected $idents = array();
    
    /**
     * List of nicknames.
     * 
     * @var array
     */
    protected $nicknames = array();
    
    /**
     * Stack of actions to execute.
     * 
     * @var array
     */
    protected $actionStack = array();

    /**
     * init(): defined by Plugin.
     * 
     * @see    Plugin::init()
     * @return void
     */
    protected function init()
    {
        $this->registerCommand('master', 'setMaster', '')
             ->registerCommand('acl', 'setAcl', '')
             ->registerTrigger('Information on [^ ]+ \(account [^\)]+\)', 'userInfoReceived')
             ->registerTrigger('=[^ ]+ is not registered', 'userInfoReceived');
    }

    /**
     * Set bot master.
     * 
     * @param  PrivMsg $privMsg
     * @return void
     */
    public function setMaster(PrivMsg $privMsg)
    {
        $hasUsers = ($this->db->fetchOne("SELECT user_id FROM users") !== false);
        
        if ($hasUsers) {
            $this->manager->getClient()->reply($privMsg, 'Master has already been set.');
            return;
        }
        
        $this->execute(array($this, 'storeMaster'), $privMsg);
    }
    
    /**
     * Set ACL of a user.
     * 
     * @param  PrivMsg $privMsg 
     * @return void
     */
    public function setAcl(PrivMsg $privMsg)
    {       
        $this->execute(array($this, 'storeAcl'), $privMsg);
    }
    
    /**
     * Store bot master.
     * 
     * @param  PrivMsg $privMsg
     * @return void
     */
    public function storeMaster(PrivMsg $privMsg)
    {
        $this->db->insert('users', array(
            'user_name' => $this->idents[$privMsg->getIdent()],
            'user_acl'  => '*.*'
        ));
    }
    
    /**
     * Store ACL.
     * 
     * @param  PrivMsg $privMsg 
     * @return void
     */
    public function storeAcl(PrivMsg $privMsg)
    {
        if (!preg_match('(^([^ ]) ([+\-][^.]\.[^,] ?)+$)', $privMsg->getMessage(), $match)) {
            $this->manager->getClient()->reply($privMsg, 'Invalid parameters for ACL.', Client::REPLY_NOTICE);
            return;
        }
        
        $username  = strtolower($match[1]);
        $modifyAcl = explode(' ', $match[2]);
        
        $row = $this->db->fetchOne(sprintf("
            SELECT user_acl
            FROM users
            WHERE user_name = %s
        ", $this->db->quote($username)));

        if ($row === false) {
            $acl = (isset($this->users[$username]) ? $this->users[$username] : new Acl($modifyAcl));
            
            $this->db->insert('users', array(
                'user_name' => $username,
                'user_acl'  => (string) $acl
            ));
        } else {
            $acl = (isset($this->users[$username]) ? $this->users[$username] : new Acl($row['user_acl']));
            $acl->modify($modifyAcl);
            
            $this->db->update('users', array(
                'user_name' => $username,
                'user_acl'  => (string) $acl
            ), sprintf("user_name = %s", $this->db->quote($username)));
        }
        
        $this->manager->getClient()->reply($privMsg, 'ACL has been modified.', Client::REPLY_NOTICE);
    }
    
    /**
     * Execute a command.
     * 
     * @param  mixed   $callback
     * @param  PrivMsg $privMsg 
     * @return void
     */
    public function execute($callback, PrivMsg $privMsg)
    {
        $ident = $privMsg->getIdent();
        $nick  = $ident->getNick();
        
        if (isset($this->idents[$ident])) {
            call_user_func($callback, $privMsg);
        } else {
            if (!isset($this->actionStack[$nick])) {
                $this->actionStack[$nick] = array();
            }
            
            $this->nicknames[$nick]     = $ident;
            $this->actionStack[$nick][] = array(
                'callback' => $callback,
                'privMsg'  => $privMsg
            );
            
            $this->manager->getClient()->sendPrivMsg('NickServ', 'INFO =' . $privMsg->getNick());
        }
    }
    
    /**
     *
     * @param PrivMsg $privMsg
     * @return type 
     */
    public function userInfoReceived(PrivMsg $privMsg)
    {
        if ($privMsg->getNick() !== 'NickServ') {
            return;
        }
        
        if (preg_match('(^Information on ([^ ]+) \(account ([^)]+)\)$)', $privMsg->getMessage(), $match)) {
            if (!isset($nicknames[$match[1]])) {
                return;
            }
            
            $this->idents[$nicknames[$match[1]]] = strtolower($match[2]);
            
            if (isset($this->actionStack[$match[1]])) {
                foreach ($this->actionStack[$match[1]] as $action) {
                    call_user_func($action['callback'], $action['privMsg']);
                }
                
                unset($this->actionStack[$match[1]]);
            }
        } elseif (preg_match('(^=([^ ]+) is not registered$)', $privMsg->getMessage(), $match)) {
            $this->manager->getClient()->sentNotice($match[1], 'You are not identified with NickServ.');
        }
    }
}