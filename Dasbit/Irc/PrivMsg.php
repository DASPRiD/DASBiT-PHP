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
namespace Dasbit\Irc;

/**
 * Private Message.
 *
 * @category   DASBiT
 * @package    Dasbit_Irc
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class PrivMsg
{
    /**
     * Message data.
     * 
     * @var array
     */
    protected $data;
    
    /**
     * Message target.
     * 
     * @var string
     */
    protected $target;
    
    /**
     * Message content.
     * 
     * @var string
     */
    protected $message;
    
    /**
     * Splitted message.
     * 
     * @var array
     */
    protected $messageSplit;
    
    /**
     * Create a new private message.
     * 
     * @param  array  $data
     * @param  string $target
     * @param  string $message
     * @return void
     */
    public function __construct(array $data, $target, $message)
    {
        $this->data    = $data;
        $this->target  = $target;
        
        $this->setMessage($message);
    }
    
    /**
     * Get the nick.
     * 
     * @return string
     */
    public function getNick()
    {
        return $this->data['nick'];
    }
    
    /**
     * Get the user.
     * 
     * @return string
     */
    public function getUser()
    {
        return $this->data['user'];
    }
    
    /**
     * Get the host.
     * 
     * @return string
     */
    public function getHost()
    {
        return $this->data['host'];
    }
    
    /**
     * Get the message target.
     * 
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }
    
    /**
     * Get the message content.
     * 
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
    
    /**
     * Set the message content.
     * 
     * @param  string $message
     * @return void
     */
    public function setMessage($message)
    {
        $this->message      = $message;
        $this->messageSplit = explode(' ', $message);
    }
    
    /**
     * Get a specific word.
     * 
     * @param  integer $index
     * @return string
     */
    public function getWord($index)
    {
        if (isset($this->messageSplit[$index])) {
            return $this->messageSplit[$index];
        }
        
        return null;
    }
}