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
class Ctcp
{
    /**
     * Delimiter for extended data.
     */
    const DELIMITER = "\1";

    /**
     * Low-level quote character.
     */
    const M_QUOTE = "\20";

    /**
     * CTCP quote character.
     */
    const X_QUOTE = "\134";

    /**
     * Mapping between low-level characters and their quoted values.
     *
     * @var array
     */
    protected $mQuoteMap = array(
        self::M_QUOTE => self::M_QUOTE,
        "\0"        => '0',
        "\r"        => 'r',
        "\n"        => 'n'
    );

    /**
     * Mapping between CTCP-level characters and their quoted values.
     *
     * @var array
     */
    protected $xQuoteMap = array(
        self::X_QUOTE   => self::X_QUOTE,
        self::DELIMITER => 'a'
    );

    /**
     * List of known tags.
     *
     * @var array
     */
    protected $allowedTags = array(
        'VERSION',
        'PING',
        'CLIENTINFO',
        'ACTION',
        'FINGER',
        'TIME',
        'DCC',
        'ERRMSG',
        'PLAY'
    );

    /**
     * Pack a message.
     *
     * The parts array can contain standard and extended messages. Standard
     * messages must be strings, while extended messages must be arrays,
     * containing a 'tag' and optionally additional 'data'.
     *
     * @param  array $parts
     * @return string
     */
    public function packMessage(array $parts)
    {
        $extendedMessage = '';
        $standardMessage = '';

        foreach ($parts as $part) {
            $partIsExtended = is_array($part);

            if ($partIsExtended) {
                $tag  = (isset($part['tag']) ? $part['tag'] : null);
                $data = (isset($part['data']) ? $part['data'] : null);

                $extendedMessage .= $this->createExtendedMessage($tag, $data);
            } else {
                $standardMessage .= $part;
            }
        }

        return $this->lowLevelQuote($extendedMessage . $standardMessage);
    }

    /**
     * Unpack a message.
     *
     * The returned array will contain all splitted standard and extended
     * messages. Standard messages will simply be strings, while extended
     * messages will be represented as arrays containing 'tag' and 'data'.
     *
     * @param  string $message
     * @return array
     */
    public function unpackMessage($message)
    {
        $message = $this->lowLevelDequote($message);

        preg_match_all(
            "("
            . "(" . self::DELIMITER . ")?"
            . "(?(1)"
            .   "[^" . self::DELIMITER . "]*" . self::DELIMITER
            . "|"
            .   ".+?(?:$|(?=" . self::DELIMITER ."))"
            . "))Ss",
            $message,
            $matches
        );

        $parts           = array();
        $standardMessage = '';

        foreach ($matches[0] as $match) {
            $match = $this->ctcpDequote($match);

            if ($match[0] === self::DELIMITER) {
                $extendedMessage = $this->parseExtendedMessage($match);

                if ($extendedMessage !== null) {
                    $parts[] = $extendedMessage;
                }
            } else {
                $standardMessage .= $match;
            }
        }

        if (!empty($standardMessage)) {
            $parts[] = $standardMessage;
        }

        return $parts;
    }

    /**
     * Create an extended message.
     *
     * @param  string $tag
     * @param  string $data
     * @return string
     */
    protected function createExtendedMessage($tag = null, $data = null)
    {
        $message = self::DELIMITER;

        if ($tag !== null) {
            if (!in_array($tag, $this->allowedTags)) {
                throw new UnexpectedValueException(sprintf('"%s" is not a valid tag', $tag));
            }

            $message .= $this->ctcpQuote($tag);

            if ($data !== null) {
                $message .= ' ' . $this->ctcpQuote($data);
            }
        }

        $message .= self::DELIMITER;

        return $message;
    }

    /**
     * Parse an extended message.
     *
     * @param  string $message
     * @return array
     */
    protected function parseExtendedMessage($message)
    {
        $result = preg_match(
            "(" . self::DELIMITER
            . "(?:(?<tag>[^\1\40]+)(?: (?<data>[^\1]*))?)?"
            . self::DELIMITER . ")",
            $message,
            $matches
        );

        if ($result === 1 && isset($matches['tag'])) {
            if (!in_array($matches['tag'], $this->allowedTags)) {
                return null;
            }

            $result = array(
                'tag' => $matches['tag']
            );

            if (isset($matches['data'])) {
                $result['data'] = $matches['data'];
            } else {
                $result['data'] = null;
            }

            return $result;
        }

        return null;
    }

    /**
     * Quote CTCP-level characters.
     *
     * @param  string $string
     * @return string
     */
    protected function ctcpQuote($string)
    {
        return $this->quote($this->xQuoteMap, self::X_QUOTE, self::X_QUOTE . self::DELIMITER, $string);
    }

    /**
     * Quote CTCP-level characters.
     *
     * @param  string $string
     * @return string
     */
    protected function ctcpDequote($string)
    {
        return $this->dequote($this->xQuoteMap, self::X_QUOTE, $string);
    }
    
    /**
     * Quote low-level characters.
     *
     * @param  string $string
     * @return string
     */
    protected function lowLevelQuote($string)
    {
        return $this->quote($this->mQuoteMap, self::M_QUOTE, self::M_QUOTE . "\\0\n\r", $string);        
    }

    /**
     * Dequote low-level characters.
     *
     * @param  string $string
     * @return string
     */
    protected function lowLevelDequote($string)
    {
        return $this->dequote($this->mQuoteMap, self::M_QUOTE, $string);
    }

    /**
     * Quote a string.
     *
     * @param  string $quoteMap
     * @param  string $quoteChar
     * @param  string $specialChars
     * @param  string $string
     * @return string
     */
    protected function quote($quoteMap, $quoteChar, $specialChars, $string)
    {
        return preg_replace(
            "([" . $specialChars . "])Se",
            '$quoteChar . $quoteMap["\0"]',
            $string
        );
    }

    /**
     * Dequote a string.
     *
     * @param  string $quoteMap
     * @param  string $quoteChar
     * @param  string $string
     * @return string
     */
    protected function dequote($quoteMap, $quoteChar, $string)
    {
        return preg_replace(
            "(\\" . $quoteChar . "(.))Se",
            '(($value = array_search("\1", $quoteMap)) !== false ? $value : "\1")',
            $string
        );
    }
}