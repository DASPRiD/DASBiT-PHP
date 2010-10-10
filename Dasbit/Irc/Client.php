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
     * Reactor instance.
     * 
     * @var \Dasbit\Reactor
     */
    protected $reactor;

    /**
     * CLI instance.
     *
     * @var \Dasbit\Cli
     */
    protected $cli;

    /**
     * Address of the IRC server.
     *
     * @var string
     */
    protected $address;

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
     * Whether the client is connected.
     *
     * @var boolean
     */
    protected $connected;

    /**
     * Client socket.
     *
     * @var resource
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
     * Instantiate a new IRC client.
     *
     * @param \Dasbit\Reactor $reactor
     * @param \Dasbit\Cli     $cli
     * @param string          $hostname
     * @param integer         $port
     * @param string          $nickname
     * @param string          $username
     * @return void
     */
    public function __construct(\Dasbit\Reactor $reactor, \Dasbit\Cli $cli, $hostname, $port, $nickname, $username)
    {
        $address = gethostbyname($hostname);

        if (ip2long($address) === false || ($address === gethostbyaddr($address)
            && preg_match("#.*\.[a-zA-Z]{2,3}$#", $hostname) === 0) )
        {
           throw new InvalidArgumentException('Hostname is not valid');
        }

        $this->reactor  = $reactor;
        $this->cli      = $cli;
        $this->address  = $address;
        $this->port     = $port;
        $this->nickname = $nickname;
        $this->username = $username;
        $this->ctcp     = new Ctcp();
    }

    /**
     * Connect to the server.
     *
     * @return void
     */
    public function connect()
    {
        while (!$this->connected) {
            $this->cli->clientOutput(sprintf('Connecting to %s port %d...', $this->address, $this->port));

            $this->socket = @socket_create(AF_INET, SOCK_STREAM, 0);

            if ($this->socket === false) {
                throw new SocketException('Could not create socket');
            }

            $this->connected = socket_connect($this->socket, $this->address, $this->port);
        }

        if (@socket_set_nonblock($this->socket) === false) {
            throw new SocketException('Could not set socket to non-block');
        }

        $this->reactor->addReader($this->socket, array($this, 'receiveData'));

        $this->cli->clientOutput('Connected, authenticating...');

        $this->send('NICK', $this->nickname);
        $this->send('USER', array($this->username, $this->address, $this->address, 'DASBiT'));
    }

    /**
     * Disconnect from the server.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->reactor->removeReader($this->socket);
        socket_close($this->socket);
        $this->connected = false;
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

        $this->send('JOIN', array(implode(',', $channels), implode(',', $keys)));
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

        $this->send('PART', array(implode(',', $channels)));
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
        $this->send('PRIVMSG', array($target, $message));
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
        $this->send('NOTICE', array($target, $message));
    }

    /**
     * Quit the IRC network
     */
    public function quit()
    {
        $this->send('QUIT');
    }

    /**
     * Receive data.
     *
     * @return void
     */
    public function receiveData()
    {
        while (false !== ($data = socket_read($this->socket, 512))) {
            if ($data === '') {
                $this->cli->clientOutput('Disconnected from server, reconnecting in 10 seconds...');

                $this->disconnect();
                $this->reactor->addTimeout(10, array($this, 'connect'));
                return;
            }

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
            } else {
                // Command
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
                    $this->send('PONG', $data['params'][0]);
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
     * Send data to the server.
     *
     * @param  string $command
     * @param  mixed  $params
     * @return void
     */
    public function send($command, $params = '')
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

        $this->cli->clientOutput($message);

        $result = socket_write($this->socket, $message . "\r\n", strlen($message) + 1);
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
}