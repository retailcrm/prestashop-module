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

require_once dirname(__FILE__) . '/../RetailcrmPrestashopLoader.php';

class RetailcrmAbandonedCartsEvent extends RetailcrmAbstractEvent implements RetailcrmEventInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute()
    {
        if ($this->isRunning()) {
            return false;
        }

        $this->setRunning();

        $shops = $this->getShops();

        if (!$shops) {
            return true;
        }

        foreach ($shops as $shop) {
            RetailcrmContextSwitcher::setShopContext((int) $shop['id_shop']);

            $syncCartsActive = Configuration::get(RetailCRM::SYNC_CARTS_ACTIVE);

            if (empty($syncCartsActive)) {
                RetailcrmLogger::writeDebug(__METHOD__, 'Abandoned carts is disabled, skipping...');

                continue;
            }

            $api = RetailcrmTools::getApiClient();

            if (null === $api) {
                continue;
            }

            $reference = new RetailcrmReferences($api);
            $site = $reference->getSite();

            RetailcrmCartUploader::init();
            RetailcrmCartUploader::$api = $api;
            RetailcrmCartUploader::$site = $site['code'] ?? '';
            RetailcrmCartUploader::setSyncDelay(Configuration::get(RetailCRM::SYNC_CARTS_DELAY));
            RetailcrmCartUploader::run();
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'RetailcrmAbandonedCartsEvent';
    }
}
