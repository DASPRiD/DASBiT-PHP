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
namespace Plugin;

use \Dasbit\Plugin\AbstractPlugin,
    \Dasbit\Irc\PrivMsg;

/**
 * ZF manual plugin.
 *
 * @category   DASBiT
 * @package    Plugin
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class ZfManual extends AbstractPlugin
{
    /**
     * enable(): defined by Plugin.
     * 
     * @see    Plugin::enable()
     * @return void
     */
    public function enable()
    {
        $this->registerCommand(array('manual', 'm'), 'lookup');
    }
    
    /**
     * Lookup the manual.
     * 
     * @param  PrivMsg $privMsg
     * @return void
     */
    public function lookup(PrivMsg $privMsg)
    {
        $client   = $this->manager->getClient();
        $callback = function($response) use ($client, $privMsg)
        {
            if (!$response->isSuccessful()) {
                $client->reply($privMsg, 'An error occured while querying the search engine.', Client::REPLY_NOTICE);
                return;
            }

            $data = @json_decode($response->getBody());

            if ($data === null) {
                $client->reply($privMsg, 'An error occured while processing the result.', Client::REPLY_NOTICE);
                return;
            } elseif (!isset($data->responseData->results[0])) {
                $this->_client->send('Nothing found', $request);
                return;
            }

            $result = $data->responseData->results[0];
            $client->reply($privMsg, 'See ' . $result->url);
        };
        
        $url = 'http://ajax.googleapis.com/ajax/services/search/web'
             . '?v=1.0'
             . '&q=' . urlencode($privMsg->getMessage() . ' site:http://framework.zend.com/manual/en/');
        
        $client = new \Dasbit\Http\Client($this->manager->getClient()->getReactor());
        var_dump($client->request($callback, $url));
    }
}