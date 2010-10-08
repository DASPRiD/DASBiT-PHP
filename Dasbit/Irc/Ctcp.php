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
     * Delimiter for extended data
     */
    const DELIMITER = "\1";

    /**
     * Quote character
     */
    const QUOTE = "\20";

    /**
     * Mapping between special characters and their quoted values
     *
     * @var array
     */
    protected $quoteMap = array(
        self::QUOTE => self::QUOTE,
        "\0"        => '0',
        "\r"        => 'r',
        "\n"        => 'n'
    );
    
    /**
     * Quote special characters in a string.
     *
     * @param  string $string
     * @return string
     */
    public function quote($string)
    {
        return preg_replace(
            "([" . self::QUOTE . "\\0\n\r])Se",
            '"' . self::QUOTE . '" . $this->quoteMap["\0"]',
            $string
        );
        
    }

    /**
     * Dequote special characters in a string.
     *
     * @param  string $string
     * @return string
     */
    public function dequote($string)
    {
        return preg_replace(
            "(" . self::QUOTE . "(.))Se",
            '(($value = array_search("\1", $this->quoteMap)) !== false ? $value : "\1")',
            $string
        );
    }
}