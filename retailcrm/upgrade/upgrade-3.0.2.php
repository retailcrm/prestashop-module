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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to version 3.0.2
 *
 * @param \RetailCRM $module
 *
 * @return bool
 */
function upgrade_module_3_0_2($module)
{
    if ('retailcrm' != $module->name) {
        return false;
    }

    return $module->registerHook('actionCarrierUpdate')
        && upgrade_module_3_0_2_remove_old_files([
            'retailcrm/job/abandonedCarts.php',
            'retailcrm/job/export.php',
            'retailcrm/job/icml.php',
            'retailcrm/job/index.php',
            'retailcrm/job/inventories.php',
            'retailcrm/job/jobs.php',
            'retailcrm/job/missing.php',
            'retailcrm/job/sync.php',
            'retailcrm/lib/CurlException.php',
            'retailcrm/lib/InvalidJsonException.php',
            'retailcrm/lib/JobManager.php',
            'retailcrm/lib/RetailcrmApiClient.php',
            'retailcrm/lib/RetailcrmApiClientV4.php',
            'retailcrm/lib/RetailcrmApiClientV5.php',
            'retailcrm/lib/RetailcrmApiErrors.php',
            'retailcrm/lib/RetailcrmApiResponse.php',
            'retailcrm/lib/RetailcrmDaemonCollector.php',
            'retailcrm/lib/RetailcrmHttpClient.php',
            'retailcrm/lib/RetailcrmInventories.php',
            'retailcrm/lib/RetailcrmProxy.php',
            'retailcrm/lib/RetailcrmService.php',
            'retailcrm/public/css/.gitignore',
            'retailcrm/public/css/retailcrm-upload.css',
            'retailcrm/public/js/.gitignore',
            'retailcrm/public/js/exec-jobs.js',
            'retailcrm/public/js/retailcrm-upload.js',
        ]);
}

/**
 * Remove files that was deleted\moved\renamed in new version and currently outdated
 *
 * @param array $files File paths relative to the `modules/` directory
 */
function upgrade_module_3_0_2_remove_old_files($files)
{
    try {
        foreach ($files as $file) {
            if (0 !== strpos($file, 'retailcrm/')) {
                continue;
            }

            $fullPath = sprintf(
                '%s/../%s', __DIR__, str_replace('retailcrm/', '', $file)
            );

            if (!file_exists($fullPath)) {
                continue;
            }

            RetailcrmLogger::writeCaller(
                __METHOD__, sprintf('Remove `%s`', $file)
            );

            unlink($fullPath);
        }

        return true;
    } catch (Exception $e) {
        RetailcrmLogger::writeCaller(
            __METHOD__,
            sprintf('Error removing `%s`: %s', $file, $e->getMessage())
        );
    } catch (Error $e) {
        RetailcrmLogger::writeCaller(
            __METHOD__,
            sprintf('Error removing `%s`: %s', $file, $e->getMessage())
        );
    }

    return false;
}
