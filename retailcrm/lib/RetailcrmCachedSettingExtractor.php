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

class RetailcrmCachedSettingExtractor extends RetailcrmAbstractDataBuilder
{
    /** @var string */
    private $cachedKey;

    /** @var string */
    private $configKey;

    /** @var bool */
    private $fromCache;

    /**
     * RetailcrmCachedValueExtractor constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->reset();
    }

    /**
     * @param string $cachedKey
     *
     * @return $this
     */
    public function setCachedKey($cachedKey)
    {
        $this->cachedKey = $cachedKey;

        return $this;
    }

    /**
     * @param string $configKey
     *
     * @return $this
     */
    public function setConfigKey($configKey)
    {
        $this->configKey = $configKey;

        return $this;
    }

    /**
     * @param string $key
     *
     * @return $this
     */
    public function setCachedAndConfigKey($key)
    {
        $this->setCachedKey($key);
        $this->setConfigKey($key);

        return $this;
    }

    public function build()
    {
        /** @var Cache $cache */
        $cache = Cache::getInstance();
        $this->fromCache = false;

        if ($cache->exists($this->cachedKey)) {
            $this->data = $cache->get($this->cachedKey);
            $this->fromCache = true;
        }

        if (empty($this->data)) {
            $this->data = Configuration::get($this->configKey);
            $this->fromCache = false;
        }
    }

    /**
     * Reset inner state
     *
     * @return $this
     */
    public function reset()
    {
        parent::reset();

        $this->cachedKey = '';
        $this->configKey = '';
        $this->fromCache = false;

        return $this;
    }

    /**
     * @return bool
     */
    public function isFromCache()
    {
        return $this->fromCache;
    }
}
