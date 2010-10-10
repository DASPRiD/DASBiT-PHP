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
 * SplClassLoader implementation that implements the technical interoperability
 * standards for PHP 5.3 namespaces and class names.
 *
 * @category   DASBiT
 * @package    Dasbit_Loader
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Loader
{
    /**
     * File extension of PHP files.
     *
     * @var string
     */
    protected $fileExtension = '.php';

    /**
     * Namespace to load.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Include path to include files from.
     *
     * @var string
     */
    protected $includePath;

    /**
     * Namespace separator.
     *
     * @var string
     */
    protected $namespaceSeparator = '\\';

    /**
     * Creates a new Loader that loads classes of the specified namespace.
     *
     * @param  string $namespace
     * @param  string $includePath
     * @return void
     */
    public function __construct($namespace = null, $includePath = null)
    {
        $this->namespace   = $namespace;
        $this->includePath = $includePath;
    }

    /**
     * Sets the namespace separator used by classes in the namespace of this
     * class loader.
     *
     * @param  string $separator
     * @return Loader
     */
    public function setNamespaceSeparator($separator)
    {
        $this->namespaceSeparator = $separator;
        return $this;
    }

    /**
     * Gets the namespace seperator used by classes in the namespace of this
     * class loader.
     *
     * @return string
     */
    public function getNamespaceSeparator()
    {
        return $this->namespaceSeparator;
    }

    /**
     * Sets the base include path for all class files in the namespace of this
     * class loader.
     *
     * @param  string $includePath
     * @return Loader
     */
    public function setIncludePath($includePath)
    {
        $this->includePath = $includePath;
        return $this;
    }

    /**
     * Gets the base include path for all class files in the namespace of this
     * class loader.
     *
     * @return string
     */
    public function getIncludePath()
    {
        return $this->_includePath;
    }

    /**
     * Sets the file extension of class files in the namespace of this class
     * loader.
     *
     * @param  string $fileExtension
     * @return Loader
     */
    public function setFileExtension($fileExtension)
    {
        $this->fileExtension = $fileExtension;
        return $this;
    }

    /**
     * Gets the file extension of class files in the namespace of this class
     * loader.
     *
     * @return string
     */
    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * Installs this class loader on the SPL autoload stack.
     *
     * @return Loader
     */
    public function register()
    {
        spl_autoload_register(array($this, 'loadClass'));
        return $this;
    }

    /**
     * Uninstalls this class loader from the SPL autoloader stack.
     *
     * @return Loader
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
        return $this;
    }

    /**
     * Loads the given class or interface.
     *
     * @param  string $className
     * @return void
     */
    public function loadClass($className)
    {
        if (
            null === $this->namespace
            || $this->namespace . $this->namespaceSeparator
            === substr($className, 0, strlen($this->namespace . $this->namespaceSeparator))
        ) {
            $fileName  = '';
            $namespace = '';

            if (false !== ($lastNsPos = strripos($className, $this->namespaceSeparator))) {
                $namespace = substr($className, 0, $lastNsPos);
                $className = substr($className, $lastNsPos + 1);
                $fileName  = str_replace($this->namespaceSeparator, DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
            }

            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . $this->fileExtension;

            require ($this->includePath !== null ? $this->includePath . DIRECTORY_SEPARATOR : '') . $fileName;
        }
    }
}