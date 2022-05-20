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
 * Upgrade module to version 3.3.6
 *
 * @param \RetailCRM $module
 *
 * @return bool
 */
function upgrade_module_3_3_6($module)
{
    if ('retailcrm' != $module->name) {
        return false;
    }

    return $module->removeOldFiles([
        // old files from 2.x versions
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
        'retailcrm/lib/RetailcrmProxy.php',
        'retailcrm/lib/RetailcrmService.php',
        'retailcrm/public/css/.gitignore',
        'retailcrm/public/css/retailcrm-upload.css',
        'retailcrm/public/js/.gitignore',
        'retailcrm/public/js/exec-jobs.js',
        'retailcrm/public/js/retailcrm-upload.js',

        // old files after Vue implementation
        'retailcrm/lib/templates/RetailcrmBaseTemplate.php',
        'retailcrm/controllers/admin/RetailcrmOrdersUploadController.php',
        'retailcrm/views/templates/admin/module_translates.tpl',
        'retailcrm/views/css/less/index.php',
        'retailcrm/views/fonts/OpenSans/index.php',
        'retailcrm/views/fonts/OpenSansBold/index.php',
        'retailcrm/views/fonts/index.php',
        'retailcrm/views/css/index.php',
        'retailcrm/views/css/fonts.min.css',
        'retailcrm/views/css/less/fonts.less',
        'retailcrm/views/css/less/retailcrm-export.less',
        'retailcrm/views/css/less/retailcrm-orders.less',
        'retailcrm/views/css/less/retailcrm-upload.less',
        'retailcrm/views/css/less/styles.less',
        'retailcrm/views/css/less/sumoselect-custom.less',
        'retailcrm/views/css/retailcrm-export.min.css',
        'retailcrm/views/css/retailcrm-orders.min.css',
        'retailcrm/views/css/retailcrm-upload.min.css',
        'retailcrm/views/css/styles.min.css',
        'retailcrm/views/css/sumoselect-custom.min.css',
        'retailcrm/views/css/vendor/index.php',
        'retailcrm/views/css/vendor/sumoselect.min.css',
        'retailcrm/views/fonts/OpenSans/opensans-regular.eot',
        'retailcrm/views/fonts/OpenSans/opensans-regular.svg',
        'retailcrm/views/fonts/OpenSans/opensans-regular.ttf',
        'retailcrm/views/fonts/OpenSans/opensans-regular.woff',
        'retailcrm/views/fonts/OpenSans/opensans-regular.woff2',
        'retailcrm/views/fonts/OpenSansBold/opensans-bold.eot',
        'retailcrm/views/fonts/OpenSansBold/opensans-bold.svg',
        'retailcrm/views/fonts/OpenSansBold/opensans-bold.ttf',
        'retailcrm/views/fonts/OpenSansBold/opensans-bold.woff',
        'retailcrm/views/fonts/OpenSansBold/opensans-bold.woff2',
        'retailcrm/views/img/simla.png',
        'retailcrm/views/js/retailcrm-advanced.js',
        'retailcrm/views/js/retailcrm-advanced.min.js',
        'retailcrm/views/js/retailcrm-collector.js',
        'retailcrm/views/js/retailcrm-collector.min.js',
        'retailcrm/views/js/retailcrm-compat.js',
        'retailcrm/views/js/retailcrm-compat.min.js',
        'retailcrm/views/js/retailcrm-consultant.js',
        'retailcrm/views/js/retailcrm-consultant.min.js',
        'retailcrm/views/js/retailcrm-export.js',
        'retailcrm/views/js/retailcrm-export.min.js',
        'retailcrm/views/js/retailcrm-icml.js',
        'retailcrm/views/js/retailcrm-icml.min.js',
        'retailcrm/views/js/retailcrm-jobs.js',
        'retailcrm/views/js/retailcrm-jobs.min.js',
        'retailcrm/views/js/retailcrm-orders.js',
        'retailcrm/views/js/retailcrm-orders.min.js',
        'retailcrm/views/js/retailcrm-tabs.js',
        'retailcrm/views/js/retailcrm-tabs.min.js',
        'retailcrm/views/js/retailcrm-upload.js',
        'retailcrm/views/js/retailcrm-upload.min.js',
        'retailcrm/views/js/retailcrm.js',
        'retailcrm/views/js/retailcrm.min.js',
        'retailcrm/views/js/vendor/index.php',
        'retailcrm/views/js/vendor/jquery-3.4.0.min.js',
        'retailcrm/views/js/vendor/jquery.sumoselect.min.js',
        'retailcrm/views/templates/admin/module_messages.tpl',
        'retailcrm/views/templates/admin/settings.tpl',
    ])
    && $module->uninstallOldTabs()
    && $module->installTab()
    ;
}
