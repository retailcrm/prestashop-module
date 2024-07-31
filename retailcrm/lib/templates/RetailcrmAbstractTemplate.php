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

abstract class RetailcrmAbstractTemplate
{
    /** @var Module|\RetailCRM */
    protected $module;
    protected $smarty;
    protected $assets;

    /** @var string */
    protected $template;
    /** @var array */
    protected $data;

    /** @var Context */
    protected $context;

    /**
     * RetailcrmAbstractTemplate constructor.
     *
     * @param Module $module
     * @param $smarty
     * @param $assets
     */
    public function __construct(Module $module, $smarty, $assets)
    {
        $this->module = $module;
        $this->smarty = $smarty;
        $this->assets = $assets;
    }

    /**
     * @param $file
     *
     * @return mixed
     *
     * @throws RuntimeException
     */
    public function render($file)
    {
        $this->buildParams();
        $this->setTemplate();

        if (null === $this->template) {
            throw new \RuntimeException('Template not be blank');
        }

        // set url post for forms
        if (empty($this->smarty->getTemplateVars('url_post'))) {
            $this->data['url_post'] = $this->smarty->getTemplateVars('current')
                . '&token=' . $this->smarty->getTemplateVars('token');
        }

        $this->smarty->assign($this->data);

        return $this->module->display($file, "views/templates/admin/$this->template");
    }

    /**
     * @param $context
     *
     * @return self
     */
    public function setContext($context)
    {
        if (!empty($context)) {
            $this->context = $context;
        }

        return $this;
    }

    abstract protected function buildParams();

    abstract protected function setTemplate();
}
