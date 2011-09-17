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

use \Dasbit\Net\Reactor,
    \Dasbit\Net\Socket,
    \Dasbit\Cli,
    \Dasbit\Plugin\Manager as PluginManager;

/**
 * IRC Client.
 *
 * @category   DASBiT
 * @package    Dasbit_Irc
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Client
{
    /**
     * Reply constants.
     */
    const REPLY_NORMAL = 'normal';
    const REPLY_NOTICE = 'notice';
    
    /**
     * CLI instance.
     *
     * @var Cli
     */
    protected $cli;
    
    /**
     * Plugin manager.
     * 
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * Hostname of the IRC server.
     *
     * @var string
     */
    protected $hostname;

    /**
     * Port of the IRC server.
     *
     * @var string
     */
    protected $port;
   
    /**
     * Nickname for the client.
     *
     * @var string
     */
    protected $nickname;

    /**
     * Username for the client.
     * 
     * @var string
     */
    protected $username;
    
    /**
     * Current nickname.
     * 
     * @var string
     */
    protected $currentNickname;

    /**
     * Client socket.
     *
     * @var Socket
     */
    protected $socket;

    /**
     * Buffer containing data read from the socket but yet not used.
     *
     * @var string
     */
    protected $buffer;

    /**
     * CTCP handler
     *
     * @var Ctcp
     */
    protected $ctcp;
    
    /**
     * The last time data were received.
     * 
     * @var integer
     */
    protected $lastTimeReceived;
    
    /**
     * Priority queue for sending.
     * 
     * @var PriorityQueue
     */
    protected $sendQueue;
    
    /**
     * Send penalty.
     * 
     * @var integer
     */
    protected $sendPenalty = 10;

    /**
     * Instantiate a new IRC client.
     *
     * @param  Cli           $cli
     * @param  PluginManager $pluginManager
     * @return void
     */
    public function __construct(Cli $cli, PluginManager $pluginManager)
    {
        $pluginManager->setClient($this);
        
        $this->cli           = $cli;
        $this->pluginManager = $pluginManager;
        $this->ctcp          = new Ctcp();
        $this->sendQueue     = new PriorityQueue();
        $this->socket        = new Socket();
        
        $client = $this;
        
        $this->socket->onConnect(function() use ($client) {
            $client->send('NICK', $client->getNickname(), 100, 0, 1);
            $client->send('USER', array($client->getUsername(), $client->getHostname(), $client->getHostname(), 'DASBiT'), 100, 1);
            
            Reactor::addTimeout(60, array($client, 'checkForLag'));
        })->onRead(
            array($this, 'receiveData')
        )->onDisconnect(function() use ($client) {
            $client->disconnect();
            $client->connect();
        }); 
        
        Reactor::addTimeout(1, array($this, 'sendQueued'));
    }

    /**
     * Connect to the server.
     *
     * @param string          $hostname
     * @param integer         $port
     * @param string          $nickname
     * @param string          $username
     * @return void
     */
    public function connect($hostname = null, $port = null, $nickname = null, $username = null)
    {
        if ($hostname !== null && $port !== null && $nickname !== null && $username !== null) {
            $this->hostname = $hostname;
            $this->port     = $port;
            $this->nickname = $nickname;
            $this->username = $username;
        }

        $this->currentNickname  = $this->nickname;
        $this->lastTimeReceived = time();
        
        $this->socket->connect($this->hostname, $this->port);
    }

    /**
     * Disconnect from the server.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->sendQueue   = new PriorityQueue();
        $this->sendPenalty = 10;
        
        $this->socket->close();
    }

    /**
     * Join one or multiple channels.
     *
     * @param  mixed $channels
     * @param  mixed $keys
     * @return void
     */
    public function join($channels, $keys = null)
    {
        if (!is_array($channels)) {
            $channels = array($channels);
        }

        if (!is_array($keys)) {
            $keys = array($keys);
        }

        $this->send('JOIN', array(implode(',', $channels), implode(',', $keys)), 40, 1);
    }

    /**
     * Part one or multiple channels.
     *
     * @param  mixed $channels
     * @return void
     */
    public function part($channels)
    {
        if (!is_array($channels)) {
            $channels = array($channels);
        }

        $this->send('PART', array(implode(',', $channels)), 41, 1);
    }

    /**
     * Send a private message.
     *
     * @param  string $target
     * @param  string $message
     * @return void
     */
    public function sendPrivMsg($target, $message)
    {
        $this->send('PRIVMSG', array($target, $message), 11, 0);
    }

    /**
     * Send a notice.
     *
     * @param  string $target
     * @param  string $message
     * @return void
     */
    public function sendNotice($target, $message)
    {
        $this->send('NOTICE', array($target, $message), 10, 0);
    }

    /**
     * Quit the IRC network
     */
    public function quit()
    {
        $this->send('QUIT', '', 100, 0);
    }
    
    /**
     * Reply to a private message.
     * 
     * @param  PrivMsg $source
     * @param  string  $message
     * @param  string  $mode 
     * @return void
     */
    public function reply(PrivMsg $source, $message, $mode = self::REPLY_NORMAL)
    {
        if ($mode === self::REPLY_NORMAL) {
            if ($source->getTarget() === $this->currentNickname) {
                $target = $source->getNick();
            } else {
                $target = $source->getTarget();
            }
            
            $this->sendPrivMsg($target, $message);
        } elseif ($mode === self::REPLY_NOTICE) {
            $this->sendNotice($source->getNick(), $message);
        }
    }

    /**
     * Receive data.
     *
     * @param  string $data
     * @return void
     */
    public function receiveData($data)
    {
        $this->buffer .= $data;
        
        while (false !== ($pos = strpos($this->buffer, "\r\n"))) {
            $message      = substr($this->buffer, 0, $pos + 2);
            $this->buffer = substr($this->buffer, $pos + 2);

            $this->cli->serverOutput($message);

            if ($message === "\r\n") {
                continue;
            }

            $this->handleMessage($message);
        }
    }

    /**
     * Handle an incoming message.
     *
     * @param  string $message
     * @return void
     */
    protected function handleMessage($message)
    {
        if (null === ($data = $this->parseMessage($message))) {
            return;
        }

        if (is_numeric($data['command'])) {
            if ($data['command'] > 400) {
                // Error
                switch ($data['command']) {
                    case 403:
                        $this->pluginManager->triggerHook('error.no-such-channel', $data['params'][0]);
                        break;
                    
                    case 405:
                        $this->pluginManager->triggerHook('error.too-many-channels', $data['params'][0]);
                        break;
                    
                    case 433:
                        $this->pluginManager->triggerHook('error.nickname-in-use', $data['params'][0]);
                        break;
                }
            } else {
                // Reply
                switch ($data['command']) {
                    case 422:
                    case 376:
                        $this->pluginManager->triggerHook('reply.connected');
                        break;

                    case 318:
                        $this->pluginManager->triggerHook('reply.end-of-whois', array($data['params'][1]));
                        break;
                    
                    case 330:
                        $this->pluginManager->triggerHook('reply.whois-account', array($data['params'][1], $data['params'][2]));
                        break;
                }
            }
        } else {
            switch ($data['command']) {
                case 'PRIVMSG':
                    $this->handlePrivMsg($data);
                    break;

                case 'NOTICE':
                    $this->handleNotice($data);
                    break;

                case 'PING':
                    $this->send('PONG', $data['params'][0], 110, 1);
                    break;
            }
        }
    }

    /**
     * Handle a private message.
     *
     * @param  array $data
     * @return void
     */
    protected function handlePrivMsg(array $data)
    {
        list($target, $message) = $data['params'];

        $parts = $this->ctcp->unpackMessage($message);

        foreach ($parts as $part) {
            if (is_string($part)) {
                $this->pluginManager->checkMessage(new PrivMsg($data, $target, $part));
            } else {
                $this->handleCtcpRequest($data['nick'], $part);
            }
        }
    }

    /**
     * Handle a notice.
     *
     * @param  array $data
     * @return void
     */
    protected function handleNotice(array $data)
    {
        list($target, $message) = $data['params'];
        
        $this->pluginManager->checkMessage(new Notice($data, $target, $message));
    }

    /**
     * Handle a CTCP request.
     *
     * @see    http://www.invlogic.com/irc/ctcp2_4.html
     * @param  string $nick
     * @param  array  $request
     * @return void
     */
    protected function handleCtcpRequest($nick, array $request)
    {
        switch ($request['tag']) {
            case 'VERSION':
                $this->sendNotice(
                    $nick,
                    $this->ctcp->packMessage(array(
                        array(
                            'tag'  => 'VERSION',
                            'data' => 'DASBiT ' . \Dasbit\Version::getVersion() . ' ' . PHP_OS
                        )
                    ))
                );
                break;

            case 'PING':
                $this->sendNotice(
                    $nick,
                    $this->ctcp->packMessage(array(
                        array(
                            'tag'  => 'PING',
                            'data' => $request['date']
                        )
                    ))
                );
                break;

            case 'CLIENTINFO':
                $this->sendNotice(
                    $nick,
                    $this->ctcp->packMessage(array(
                        array(
                            'tag'  => 'CLIENTINFO',
                            'data' => 'PING VERSION TIME CLIENTINFO'
                        )
                    ))
                );
                break;

            case 'TIME':
                $this->sendNotice(
                    $nick,
                    $this->ctcp->packMessage(array(
                        array(
                            'tag'  => 'TIME',
                            'data' => date('r')
                        )
                    ))
                );
                break;

            case 'ACTION':
                // This is not really a CTCP request
                break;

            default:
                $this->sendNotice(
                    $nick,
                    $this->ctcp->packMessage(array(
                        array(
                            'tag'  => 'ERRMSG',
                            'data' => 'Unknown request'
                        )
                    ))
                );
        }
    }
    
    /**
     * Check for lag.
     * 
     * @return void
     */
    public function checkForLag()
    {
        if ($this->socket->isConnected() && $this->lastTimeReceived + 300 <= time()) {
            $this->cli->clientOutput('Maximum lag reached, reconnecting...');

            $this->disconnect();
            $this->connect();
            
            Reactor::addTimeout(60, array($this, 'checkForLag'));
        }
    }

    /**
     * Send data to the server.
     *
     * @param  string  $command
     * @param  mixed   $params
     * @param  integer $priority
     * @return void
     */
    public function send($command, $params = '', $priority = 0, $penalty = 0)
    {
        $message = $command;

        if (is_array($params)) {
            if (count($params) === 0) {
                $lastParam = '';
            } else {
                $lastParam = array_pop($params);
            }

            if (count($params) > 0) {
                $message .= ' ' . implode(' ', $params);
            }

            $message .= ' :' . $lastParam;
        } else {
            $message .= ' :' . $params;
        }
        
        $message .= "\r\n";

        $this->sendQueue->insert(
            array(
                'penalty' => floor((1 + (strlen($message) + 1) / 100) + $penalty),
                'message' => $message
            ),
            $priority
        );
        
        $this->sendQueued(false);
    }
    
    /**
     * Send queued data out to the server.
     * 
     * @return void
     */
    public function sendQueued($raisePenalty = true)
    {
        if ($raisePenalty) {
            $this->sendPenalty = min(10, $this->sendPenalty + 1);
        }
        
        while ($this->sendPenalty > 0 && count($this->sendQueue) > 0) {
            $item = $this->sendQueue->extract();
            
            $this->sendPenalty -= $item['penalty'];
            
            $this->cli->clientOutput(rtrim($item['message']));
            $this->socket->write($item['message']);
        }

        Reactor::addTimeout(1, array($this, 'sendQueued'));
    }

    /**
     * Parse a message.
     *
     * As of the complexity of hostnames, we are not strictly validating them
     * with the included regex and instead just assume they are correct.
     *
     * @param  string $message
     * @return mixed
     */
    protected function parseMessage($message)
    {
        // General validating and parsing
        $result = preg_match(
            '(^'
            . '(?::'
            .   '(?<prefix>'
            .     '(?<nick>[A-Za-z][A-Za-z0-9\-\[\]\\\\`\^{}]*?)'
            .     '(?:!(?<user>[^\x20\x0\xd\xa]+?))?'
            .     '(?:@(?<host>[^ ]+))?'
            .     '|'
            .     '(?<servername>[^ ]+)'
            .   ')'
            . '[ ]+)?'
            . '(?<command>[A-Za-z]+|\d{3})'
            . '(?<params>[ ]+'
            .   '(?:'
            .     ':(?<trailing>[^\x0\xd\xa]*)'
            .     '|'
            .     '(?<middle>[^:\x20\x0\xd\xa][^\x20\x0\xd\xa]*)(?P>params)'
            .   ')'
            . ')'
            . '\r\n$)S',
            $message,
            $matches
        );

        if ($result === 0) {
            return null;
        }

        // Parameter parsing
        preg_match_all(
            '([ ]+'
            .   '(:)?(?<param>(?(1)'
            .     '[^\x0\xd\xa]*'
            .     '|'
            .     '[^:\x20\x0\xd\xa][^\x20\x0\xd\xa]*'
            .   '))'
            . ')S',
            $matches['params'],
            $paramMatches
        );

        // Data generation
        $data = array(
            'command' => strtoupper($matches['command']),
            'params'  => $paramMatches['param']
        );

        if (isset($matches['servername']) && !empty($matches['servername'])) {
            $data['servername'] = $matches['servername'];
        } else {
            $data['nick'] = $matches['nick'];
            $data['user'] = (isset($matches['user']) && !empty($matches['user'])) ? $matches['user'] : null;
            $data['host'] = (isset($matches['host']) && !empty($matches['host'])) ? $matches['host'] : null;
        }

        return $data;
    }
    
    /**
     * Get the reactor the client is attached to.
     * 
     * @return Reactor
     */
    public function getReactor()
    {
        return $this->reactor;
    }
    
    /**
     * Get hostname.
     * 
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }
    
    /**
     * Get port.
     * 
     * @return integer
     */
    public function getPort()
    {
        return $this->port;
    }
    
    /**
     * Get username.
     * 
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }
    
    /**
     * Get defined nickname.
     * 
     * @return string
     */
    public function getNickname()
    {
        return $this->nickname;
    }
    
    /**
     * Get currently assigned nickname.
     * 
     * @return string
     */
    public function getCurrentNickname()
    {
        return $this->currentNickname;
    }
}