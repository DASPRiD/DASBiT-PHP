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
 * IRC Client
 *
 * @category   DASBiT
 * @package    Dasbit_Irc
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Client
{
    /**
     * Message types
     */
    const TYPE_MESSAGE = 'message';
    const TYPE_ACT     = 'act';
    const TYPE_NOTICE  = 'notice';


    /**
     * Reactor instance
     * 
     * @var \Dasbit\Reactor
     */
    protected $reactor;

    /**
     * Address of the IRC server
     *
     * @var string
     */
    protected $address;

    /**
     * Port of the IRC server
     *
     * @var string
     */
    protected $port;

    /**
     * Nickname for the client
     *
     * @var string
     */
    protected $nickname;

    /**
     * Username for the client
     * 
     * @var string
     */
    protected $username;

    /**
     * Whether the client is connected
     *
     * @var boolean
     */
    protected $connected;

    /**
     * Client socket
     *
     * @var resource
     */
    protected $socket;

    /**
     * Buffer containing data read from the socket but yet not used
     *
     * @var string
     */
    protected $buffer;

    /**
     * Instantiate a new IRC client.
     *
     * @param \Dasbit\Reactor $reactor
     * @param string          $hostname
     * @param integer         $port
     * @param string          $nickname
     * @param string          $username
     * @return void
     */
    public function __construct(\Dasbit\Reactor $reactor, $hostname, $port, $nickname, $username)
    {
        $address = gethostbyname($hostname);

        if (ip2long($address) === false || ($address === gethostbyaddr($address)
            && preg_match("#.*\.[a-zA-Z]{2,3}$#", $hostname) === 0) )
        {
           throw new InvalidArgumentException('Hostname is not valid');
        }

        $this->reactor  = $reactor;
        $this->address  = $address;
        $this->port     = $port;
        $this->nickname = $nickname;
        $this->username = $username;
    }

    /**
     * Connect to the server.
     *
     * @return void
     */
    public function connect()
    {
        while (!$this->connected) {
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

        $this->sendMessage('NICK', $this->nickname);
        $this->sendMessage('USER', array($this->username, $this->address, $this->address, 'DASBiT'));
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
    }

    /**
     * Receive data.
     *
     * @return void
     */
    public function receiveData()
    {
        while ('' !== ($data = socket_read($this->socket, 512))) {
            if ($data === false) {
                // Socket not ready
                return;
                /*
                $errorCode    = socket_last_error($this->socket);
                $errorMessage = socket_strerror($errorCode);
                socket_clear_error($this->socket);

                echo $errorMessage . "\r\n";
                echo 'Socket not ready';
                break;
                 */
            }

            echo $data;
            $this->buffer .= $data;

            while (false !== ($pos = strpos($this->buffer, "\r\n"))) {
                $message      = substr($this->buffer, 0, $pos + 2);
                $this->buffer = substr($this->buffer, $pos + 2);

                if ($message === "\r\n") {
                    continue;
                }

                $this->handleMessage($message);
            }
        }
    }

    /**
     * Handle an incoming message
     *
     * @param  string $message
     * @return void
     */
    protected function handleMessage($message)
    {
        $prefix;
        $command;
        $params;

        extract($this->parseMessage($message), EXTR_IF_EXISTS);

        if (is_numeric($command)) {
            if ($command > 400) {
                // Error
            } else {
                // Command
            }
        } else {
            switch ($command) {
                case 'PRIVMSG':
                    $this->handlePrivMsg($prefix, $params);
                    break;
            }
        }
    }

    protected function handlePrivMsg($source, array $params)
    {
        list($target, $message) = $params;

        if (preg_match('(^' . chr(1) . '[A-Za-z]+' . chr(1) . '$)S', $message, $matches) === 1) {
            // This is a CTCP message
        }
    }

    /**
     * Send a message to a user or channel.
     *
     * @param  string $message
     * @param  mixed  $target
     * @param  string $type
     * @return void
     */
    public function send($message, $target, $type = self::TYPE_MESSAGE)
    {
        if ($target instanceof Request) {
            if ($type === self::TYPE_NOTICE) {
                $target = $target->getNickname();
            } else {
                $target = $target->getSource();
            }
        }

        switch ($type) {
            case self::TYPE_MESSAGE:
                $this->sendRaw('PRIVMSG ' . $target . ' :' . $message);
                break;

            case self::TYPE_ACT:
                $chr = chr(1);
                $this->sendRaw('PRIVMSG ' . $target . ' :' . $chr . 'ACTION ' . $message . $chr);
                break;

            case self::TYPE_NOTICE:
                $this->sendRaw('NOTICE ' . $target . ' :' . $message);
                break;
        }
    }

    /**
     * Send a message to the server
     *
     * @param  string $command
     * @param  mixed  $params
     * @return void
     */
    public function sendMessage($command, $params = '')
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
        $result = preg_match(
            '(^'
            . '(?::'
            .   '(?<prefix>'
            .     '(?<servername>[^ ]+)'
            .     '|'
            .     '(?<nick>[A-Za-z][A-Za-z0-9\-\[\]\\\\`\^{}]*)'
            .     '(?:!(?<user>[^\x20\x0\xd\xa]+))?'
            .     '(?:@(?<host>[^ ]+))?'
            .   ')'
            . '[ ]+)?'
            . '(?<command>[A-Za-z]+|\d{3})'
            . '(?<params>[ ]+'
            .   '(?:'
            .     '(?<trailing>[^\x0\xd\xa]*)'
            .     '|'
            .     '(?<middle>[^:\x20\x0\xd\xa][^\x20\x0\xd\xa]*)(?P>params)'
            .   ')'
            . ')'
            . '\r\n$)S',
            $message,
            $matches
        );

        if ($result === 0) {
            echo 'Could not parse message:';
            var_dump($message);
            exit;
        }

        $params = preg_split('([ ]+:?)', ltrim($matches['params'], ' '));

        return array(
            'prefix'  => $matches['prefix'],
            'command' => strtoupper($matches['command']),
            'params'  => $params
        );
    }
}