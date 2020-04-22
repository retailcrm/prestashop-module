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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

require_once(dirname(__FILE__) . '/../RetailcrmPrestashopLoader.php');

abstract class RetailcrmAbstractEvent implements RetailcrmEventInterface
{
    private $cliMode;

    /**
     * @inheritDoc
     */
    abstract public function execute();

    /**
     * @inheritDoc
     */
    public function getName()
    {
        throw new InvalidArgumentException("Not implemented.");
    }

    /**
     * Sets cli mode to true. CLI mode here stands for any execution outside of JobManager context.
     *
     * @param bool $mode
     */
    public function setCliMode($mode)
    {
        $this->cliMode = (bool) $mode;
    }

    /**
     * Returns true if current job is running now
     *
     * @return bool
     */
    protected function isRunning()
    {
        return (strcmp(RetailcrmJobManager::getCurrentJob(), $this->getName() === 0))
            || (strcmp(RetailcrmCli::getCurrentJob(), $this->getName() === 0));
    }

    /**
     * Sets current job as active based on execution context.
     *
     * @return bool
     */
    protected function setRunning()
    {
        if ($this->cliMode) {
            return RetailcrmCli::setCurrentJob($this->getName());
        }

        return RetailcrmJobManager::setCurrentJob($this->getName());
    }
}
