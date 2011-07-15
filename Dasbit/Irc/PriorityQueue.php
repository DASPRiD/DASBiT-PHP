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
 * Priority queue with predictable heap order.
 *
 * @category   DASBiT
 * @package    Dasbit_Irc
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class PriorityQueue extends \SplPriorityQueue
{
    /**
     * Seed used to ensure queue order.
     * 
     * @var integer
     */
    protected $serial = PHP_INT_MAX;
    
    /**
     * insert(): defined by \SplPriorityQueue.
     * 
     * @see    \SplPriorityQueue::insert()
     * @param  mixed $value
     * @param  mixed $priority 
     * @return void
     */
    public function insert($value, $priority)
    {
        if (!is_array($priority)) {
            $priority = array($priority, $this->serial--);
        }
        
        parent::insert($value, $priority);
    }
    
    /**
     * extract(): defined by \SplPriorityQueue.
     * 
     * @see    \SplPriorityQueue::extract()
     * @return mixed
     */
    public function extract()
    {
        if (count($this) === 1) {
            $this->serial = PHP_INT_MAX;
        }
        
        return parent::extract();
    }
}