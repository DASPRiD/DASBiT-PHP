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
     * Message content.
     * 
     * @var string
     */
    protected $message;
    
    /**
     * Create a new private message.
     * 
     * @param  array  $data
     * @param  string $message
     * @return void
     */
    public function __construct(array $data, $message)
    {
        $this->data    = $data;
        $this->message = $message;
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
        $this->message = $message;
    }
}