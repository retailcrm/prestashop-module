<?php
/**
 * MIT License
 *
 * Copyright (c) 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

/**
 * Simple Recursive Autoloader
 *
 * A simple autoloader that loads class files recursively starting in the directory
 * where this class resides.  Additional options can be provided to control the naming
 * convention of the class files.
 *
 * @license http://opensource.org/licenses/MIT  MIT License
 */
class RetailcrmAutoloader
{
    /**
     * File extension as a string. Defaults to ".php".
     */
    protected static $fileExt = '.php';

    /**
     * The top level directory where recursion will begin.
     */
    protected static $pathTop;

    /**
     * The top level directory where recursion for custom classes will begin.
     */
    protected static $pathTopCustom;

    /**
     * Autoload function for registration with spl_autoload_register
     *
     * Looks recursively through project directory and loads class files based on
     * filename match.
     *
     * @param string $className
     */
    public static function loader($className)
    {
        if (file_exists(self::$pathTopCustom) && is_dir(self::$pathTopCustom)) {
            $directory = new RecursiveDirectoryIterator(self::$pathTopCustom);
            $fileIterator = new RecursiveIteratorIterator($directory);
            $filename = $className . self::$fileExt;

            foreach ($fileIterator as $file) {
                if (Tools::strtolower($file->getFilename()) === Tools::strtolower($filename)
                    && $file->isReadable()
                    && !class_exists($className)
                ) {
                    include_once $file->getPathname();

                    return;
                }
            }
        }

        $directory = new RecursiveDirectoryIterator(self::$pathTop);
        $fileIterator = new RecursiveIteratorIterator($directory);
        $filename = $className . self::$fileExt;

        foreach ($fileIterator as $file) {
            if (Tools::strtolower($file->getFilename()) === Tools::strtolower($filename)
                && $file->isReadable()
                && !class_exists($className)
            ) {
                include_once $file->getPathname();

                return;
            }
        }
    }

    /**
     * Sets the $fileExt property
     *
     * @param string $fileExt The file extension used for class files.  Default is "php".
     */
    public static function setFileExt($fileExt)
    {
        self::$fileExt = $fileExt;
    }

    /**
     * Sets the $path property
     *
     * @param string $path The path representing the top level where recursion should
     *                     begin. Defaults to the current directory.
     */
    public static function setPath($path)
    {
        self::$pathTop = $path;
    }

    /**
     * Sets the $pathTopCustom property
     *
     * @param string $path The path representing the top level where recursion for custom
     *                     classes should begin. Defaults to 'modules/retailcrm_custom/classes'.
     */
    public static function setPathCustom($path)
    {
        self::$pathTopCustom = $path;
    }
}

RetailcrmAutoloader::setPath(realpath(__DIR__));
RetailcrmAutoloader::setPathCustom(realpath(_PS_MODULE_DIR_ . '/retailcrm/custom'));
RetailcrmAutoloader::setFileExt('.php');
spl_autoload_register('RetailcrmAutoloader::loader');
