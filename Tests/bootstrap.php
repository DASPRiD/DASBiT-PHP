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

// Set error reporting pretty high
error_reporting(E_ALL | E_STRICT);

// Get base, application and tests path
define('BASE_PATH',        dirname(__DIR__));
define('APPLICATION_PATH', BASE_PATH . '/application');
define('TESTS_PATH',       BASE_PATH . '/Tests');

// Define rules for clover report
PHPUnit_Util_Filter::addDirectoryToWhitelist(BASE_PATH, '.php');

// Get the autolaoder and set module autoloaders
require_once BASE_PATH . '/Dasbit/Loader.php';

$loader = new \Dasbit\Loader(null, BASE_PATH);
$loader->register();

// Set locale
setlocale(LC_CTYPE, 'en_US.utf-8');
