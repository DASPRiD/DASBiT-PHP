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
     * enable(): defined by Plugin.
     * 
     * @see    Plugin::enable()
     * @return void
     */
    public function enable()
    {
        $this->registerCommand('master', 'setMaster')
             ->registerCommand('acl', 'setAcl', 'users.acl')
             ->registerHook('reply.end-of-whois', 'whoisReceivedHook')
             ->registerHook('reply.whois-account', 'whoisReceivedHook');
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
            $this->manager->getClient()->reply($privMsg, 'Master has already been set.', Client::REPLY_NOTICE);
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
        
        $this->manager->getClient()->reply($privMsg, 'You are now the master.', Client::REPLY_NOTICE);
    }
    
    /**
     * Store ACL.
     * 
     * @param  PrivMsg $privMsg 
     * @return void
     */
    public function storeAcl(PrivMsg $privMsg)
    {
        if (!preg_match('(^([^ ]+) ([+\-]?[^.]+\.[^ ]+ ?)+$)', $privMsg->getMessage(), $match)) {
            $this->manager->getClient()->reply($privMsg, 'Invalid parameters for ACL.', Client::REPLY_NOTICE);
            return;
        }
        
        $username  = strtolower($match[1]);
        $modifyAcl = $match[2];
        
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
     * Verify access to a command.
     * 
     * @param  mixed   $callback 
     * @param  PrivMsg $privMsg
     * @param  string  $restrict
     * @return void
     */
    public function verifyAccess($callback, PrivMsg $privMsg, $restrict)
    {
        $this->execute(
            array($this, 'userLoaded'),
            $privMsg,
            array(
                'callback' => $callback,
                'restrict' => $restrict
            )
        );
    }
    
    /**
     * Called after a user was loaded.
     * 
     * @param  PrivMsg $privMsg
     * @param  array   $data
     * @return void
     */
    public function userLoaded(PrivMsg $privMsg, array $data)
    {
        if (!isset($this->users[$this->idents[$privMsg->getIdent()]])) {
            $row = $this->db->fetchOne(sprintf("
                SELECT user_acl
                FROM users
                WHERE user_name = %s
            ", $this->db->quote($this->idents[$privMsg->getIdent()])));
            
            $this->users[$this->idents[$privMsg->getIdent()]] = new Acl($row['user_acl']);
        }
        
        $acl = $this->users[$this->idents[$privMsg->getIdent()]];
        
        if ($acl->isAllowed($data['restrict'])) {
            call_user_func($data['callback'], $privMsg);
        } else {
            $this->manager->getClient()->reply($privMsg, 'You are not allowed to use this command.', Client::REPLY_NOTICE);
        }
    }
    
    /**
     * Execute a command.
     * 
     * @param  mixed   $callback
     * @param  PrivMsg $privMsg 
     * @param  array   $data
     * @return void
     */
    public function execute($callback, PrivMsg $privMsg, array $data = null)
    {
        $ident = $privMsg->getIdent();
        $nick  = $privMsg->getNick();
        
        if (isset($this->idents[$ident])) {           
            call_user_func($callback, $privMsg, $data);
        } else {
            if (!isset($this->actionStack[$nick])) {
                $this->actionStack[$nick] = array();
            }
            
            $this->nicknames[$nick]     = $ident;
            $this->actionStack[$nick][] = array(
                'callback' => $callback,
                'privMsg'  => $privMsg,
                'data'     => $data
            );
            
            $this->manager->getClient()->send('WHOIS', $privMsg->getNick());
        }
    }
    
    /**
     * User whois received.
     * 
     * @param  string $hook
     * @param  array  $data
     * @return void
     */
    public function whoisReceivedHook($hook, array $data)
    {
        if ($hook === 'reply.whois-account') {
            $nickname = $data[0];
            $username = $data[1];
            
            $this->idents[$this->nicknames[$nickname]] = strtolower($username);
                       
            if (isset($this->actionStack[$nickname])) {
                foreach ($this->actionStack[$nickname] as $action) {
                    call_user_func($action['callback'], $action['privMsg'], $action['data']);
                }
                
                unset($this->actionStack[$nickname]);
            }
        } elseif ($hook === 'reply.end-of-whois') {
            $nickname = $data[0];
            
            if (isset($this->actionStack[$nickname])) {
                $this->manager->getClient()->sendNotice($nickname, 'You are not identified with NickServ.');
                
                unset($this->actionStack[$nickname]);
            }
        }
    }
}