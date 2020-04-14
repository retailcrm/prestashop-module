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

class RetailcrmJobsModuleFrontController extends ModuleFrontController
{
    /**
     * Universal render function for 1.6 and 1.7
     *
     * @param string $response
     */
    private function renderData($response)
    {
        if (property_exists($this, 'ajax')) {
            $this->ajax = true;
        }

        header('Content-Type: application/json');

        if (Tools::substr(_PS_VERSION_, 0, 3) == '1.6') {
            echo $response;
        } else {
            try {
                parent::initContent();
                $this->ajaxRender($response);
            } catch (\Exception $exception) {
                // Sometimes ps_shoppingcart throws exception which breaks our controller.
                // We don't care about ps_shoppingcart here, so, we will fallback to old way.
                echo $response;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function initContent()
    {
        $this->renderData(json_encode($this->getData()));
    }

    /**
     * PrestaShop 1.6 compatibility
     */
    public function run()
    {
        $this->initContent();
    }

    /**
     * Runs jobs
     */
    protected function getData()
    {
        RetailcrmJobManager::startJobs(
            array(
                'RetailcrmAbandonedCartsEvent' => null,
                'RetailcrmIcmlEvent' => new \DateInterval('PT4H'),
                'RetailcrmSyncEvent' => new \DateInterval('PT7M'),
                'RetailcrmInventoriesEvent' => new \DateInterval('PT15M')
            )
        );

        return array('success' => true);
    }
}
