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

require_once dirname(__FILE__) . '/../../bootstrap.php';

class RetailcrmJobsController extends RetailcrmAdminPostAbstractController
{
    protected function postHandler()
    {
        if (!Tools::getIsset('jobName') && !Tools::getIsset('reset')) {
            return [
                'success' => false,
                'errorMsg' => 'Bad request',
            ];
        }

        if (Tools::getIsset('reset')) {
            return $this->resetJobManager();
        }

        $jobName = Tools::getValue('jobName');

        return $this->runJob($jobName);
    }

    protected function getHandler()
    {
        return [
            'success' => true,
            'result' => RetailcrmSettingsHelper::getJobsInfo(),
        ];
    }

    private function resetJobManager()
    {
        $errors = [];
        try {
            if (!RetailcrmJobManager::reset()) {
                $errors[] = 'Job manager internal state was NOT cleared.';
            }
            if (!RetailcrmCli::clearCurrentJob(null)) {
                $errors[] = 'CLI job was NOT cleared';
            }

            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errorMsg' => implode(' ', $errors),
                ];
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errorMsg' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'errorMsg' => $e->getMessage(),
            ];
        }
    }

    private function runJob($jobName)
    {
        try {
            $result = RetailcrmJobManager::execManualJob($jobName);

            return [
                'success' => true,
                'result' => $result,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errorMsg' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'errorMsg' => $e->getMessage(),
            ];
        }
    }
}
