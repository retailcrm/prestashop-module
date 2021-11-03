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
abstract class RetailcrmAbstractDataBuilder
{
    /**
     * @var mixed Any data type (depends on the builder)
     */
    protected $data;

    /**
     * RetailcrmAddressBuilder constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Reset builder state
     *
     * @return $this
     */
    public function reset()
    {
        $this->data = null;

        return $this;
    }

    /**
     * Return cleared built data casted to array
     *
     * @return array
     */
    public function getDataArray()
    {
        if (is_array($this->data)) {
            return RetailcrmTools::clearArray((array) $this->data);
        }

        return [];
    }

    /**
     * Returns built data casted to string
     *
     * @return string
     */
    public function getDataString()
    {
        if (is_string($this->data)) {
            return (string) $this->data;
        }

        return '';
    }

    /**
     * Return builder data without any type-casting
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Build data
     *
     * @return $this
     */
    abstract public function build();
}
