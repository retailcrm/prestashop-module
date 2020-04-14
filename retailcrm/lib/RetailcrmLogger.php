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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class RetailcrmLogger
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @license   GPL
 * @link      https://retailcrm.ru
 */
class RetailcrmLogger
{
    /**
     * Write entry to log
     *
     * @param string $caller
     * @param string $message
     */
    public static function writeCaller($caller, $message)
    {
        error_log(
            sprintf(
                '[%s] @ [%s] %s' . PHP_EOL,
                date(DATE_RFC3339),
                $caller,
                $message
            ),
            3,
            static::getErrorLog()
        );
    }

    /**
     * Write entry to log without caller name
     *
     * @param string $message
     */
    public static function writeNoCaller($message)
    {
        error_log(
            sprintf(
                '[%s] %s' . PHP_EOL,
                date(DATE_RFC3339),
                $message
            ),
            3,
            static::getErrorLog()
        );
    }

    /**
     * Write debug log record
     *
     * @param string $caller
     * @param mixed $message
     */
    public static function writeDebug($caller, $message)
    {
        if (RetailcrmTools::isDebug()) {
            static::writeNoCaller(sprintf(
                '(DEBUG) <%s> %s',
                $caller,
                print_r($message, true)
            ));
        }
    }

    /**
     * Returns error log path
     *
     * @return string
     */
    protected static function getErrorLog()
    {
        if (!defined('_PS_ROOT_DIR_')) {
            return '';
        }

        return _PS_ROOT_DIR_ . '/retailcrm.log';
    }
}