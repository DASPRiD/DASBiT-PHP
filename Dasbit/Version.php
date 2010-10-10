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
namespace Dasbit;

/**
 * Version handling.
 *
 * @category   DASBiT
 * @package    Dasbit_Version
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Version
{
    /**
     * DASBiT version, should be in the format <major>.<minor>.<mini>[<suffix>].
     */
    const VERSION = '6.0.0alpha1';

    /**
     * Constants of version parts.
     */
    const PART_MAJOR  = 'major';
    const PART_MINOR  = 'minor';
    const PART_MINI   = 'mini';
    const PART_SUFFIX = 'suffix';

    /**
     * Reverse map of version parts.
     *
     * @var array
     */
    private static $_reverseMap = array(
        'major'  => 1,
        'minor'  => 2,
        'mini'   => 3,
        'suffix' => 4
    );

    /**
     * Get the version.
     *
     * If $part is null, the entire version is returned. If it is a string,
     * the version part is returned. If a part is given and $fromBeginning is
     * set to true, the version is returned from the beginning to the named
     * part.
     *
     * @param  string  $part
     * @param  boolean $fromBeginning
     * @return string
     */
    public static function getVersion($part = null, $fromBeginning = false)
    {
        if ($part === null) {
            return self::VERSION;
        } elseif (!preg_match('(^(?P<major>\d+)\.(?P<minor>\d+)\.(?P<mini>\d+)(?P<suffix>.*)?$)', self::VERSION, $matches)) {
            throw new App_RuntimeException('Unable to parse application version');
        } elseif (!isset($matches[$part])) {
            throw new App_RuntimeException('Named part "' . $part . '" does not exist in version');
        } elseif ($fromBeginning === false) {
            return $matches[$part];
        } else {
            $version = '';

            for ($i = 1; $i <= self::$_reverseMap[$part]; $i++) {
                $version .= (($i > 1 && $i < 4) ? '.' : '') . $matches[$i];
            }

            return $version;
        }
    }
}