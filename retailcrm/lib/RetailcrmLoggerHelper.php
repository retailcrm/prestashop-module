<?php
/**
 * MIT License
 *
 * Copyright (c) 2021 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

class RetailcrmLoggerHelper
{
    public static function download($name)
    {
        if (empty($name)) {
            return false;
        }
        $filePath = self::checkFileName($name);

        if (false === $filePath) {
            return false;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($filePath));
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);

        return true;
    }

    public static function downloadAll()
    {
        $zipname = _PS_DOWNLOAD_DIR_ . '/retailcrm_logs_' . date('Y-m-d H-i-s') . '.zip';

        $zipFile = new ZipArchive();
        $zipFile->open($zipname, ZIPARCHIVE::CREATE);

        foreach (self::getLogFilesInfo() as $logFile) {
            $zipFile->addFile($logFile['path'], $logFile['name']);
        }

        $zipFile->close();

        header('Content-Type: ' . mime_content_type($zipname));
        header('Content-disposition: attachment; filename=' . basename($zipname));
        header('Content-Length: ' . filesize($zipname));

        readfile($zipname);
        unlink($zipname);

        return true;
    }

    /**
     * Checks if given logs filename relates to the module
     *
     * @param string $file
     *
     * @return false|string
     */
    public static function checkFileName($file)
    {
        $logDir = RetailcrmLogger::getLogDir();
        if (preg_match('/^retailcrm[a-zA-Z0-9-_]+.log$/', $file)) {
            $path = "$logDir/$file";
            if (is_file($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Retrieves log files basic info for advanced tab
     *
     * @return array
     */
    public static function getLogFilesInfo()
    {
        $fileNames = [];
        $logFiles = self::getLogFiles();

        foreach ($logFiles as $logFile) {
            $fileNames[] = [
                'name' => basename($logFile),
                'path' => $logFile,
                'size' => number_format(filesize($logFile), 0, '.', ' ') . ' bytes',
                'modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            ];
        }

        return $fileNames;
    }

    /**
     * Retrieves log files paths
     *
     * @return Generator|void
     */
    public static function getLogFiles()
    {
        $logDir = RetailcrmLogger::getLogDir();

        if (!is_dir($logDir)) {
            return;
        }

        $handle = opendir($logDir);
        while (($file = readdir($handle)) !== false) {
            if (false !== self::checkFileName($file)) {
                yield "$logDir/$file";
            }
        }

        closedir($handle);
    }
}
