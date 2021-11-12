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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

require_once(__DIR__ . '/../../bootstrap.php');

class RetailcrmSettingsController extends RetailcrmAdminAbstractController
{
    public static function getParentId()
    {
        return (int)Tab::getIdFromClassName('IMPROVE');
    }

    public static function getIcon()
    {
        return 'shop';
    }

    public static function getPosition()
    {
        return 7;
    }

    public static function getName()
    {
        $name = [];

        foreach (Language::getLanguages(true) as $lang) {
            $name[$lang['id_lang']] = 'Simla.com';
        }

        return $name;
    }

    public function postProcess()
    {
        $link = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => 'retailcrm',
        ]);

        if (version_compare(_PS_VERSION_, '1.7.0.3', '<')) {
            $link .= '&module_name=retailcrm&configure=retailcrm';
        }

        $this->setRedirectAfter($link);
    }
}
