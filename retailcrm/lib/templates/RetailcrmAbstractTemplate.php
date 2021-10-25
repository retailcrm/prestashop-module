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

    /** @var array */
    private $errors;

    /** @var array */
    private $warnings;

    /** @var array */
    private $informations;

    /** @var array */
    private $confirmations;

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
        $this->errors = [];
        $this->warnings = [];
        $this->informations = [];
        $this->confirmations = [];
    }

    /**
     * Returns ISO code of current employee language or default language.
     *
     * @return string
     */
    protected function getCurrentLanguageISO()
    {
        $langId = 0;

        global $cookie;

        if (!empty($this->context) && !empty($this->context->employee)) {
            $langId = (int) $this->context->employee->id_lang;
        } elseif ($cookie instanceof Cookie) {
            $langId = (int) $cookie->id_lang;
        } else {
            $langId = (int) Configuration::get('PS_LANG_DEFAULT');
        }

        return (string) Language::getIsoById($langId);
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

        if ($this->template === null) {
            throw new \RuntimeException('Template not be blank');
        }

        // set url post for forms
        if (empty($this->smarty->getTemplateVars('url_post'))) {
            $this->data['url_post'] = $this->smarty->getTemplateVars('current')
                . '&token=' . $this->smarty->getTemplateVars('token');
        }

        $this->smarty->assign(\array_merge($this->data, [
            'moduleErrors' => $this->errors,
            'moduleWarnings' => $this->warnings,
            'moduleConfirmations' => $this->confirmations,
            'moduleInfos' => $this->informations,
        ]));

        return $this->module->display($file, "views/templates/admin/$this->template");
    }

    /**
     * @param $messages
     *
     * @return self
     */
    public function setErrors($messages)
    {
        if (!empty($messages)) {
            $this->errors = $messages;
        }

        return $this;
    }

    /**
     * @param $messages
     *
     * @return self
     */
    public function setWarnings($messages)
    {
        if (!empty($messages)) {
            $this->warnings = $messages;
        }

        return $this;
    }

    /**
     * @param $messages
     *
     * @return self
     */
    public function setInformations($messages)
    {
        if (!empty($messages)) {
            $this->informations = $messages;
        }

        return $this;
    }

    /**
     * @param $messages
     *
     * @return self
     */
    public function setConfirmations($messages)
    {
        if (!empty($messages)) {
            $this->confirmations = $messages;
        }

        return $this;
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
