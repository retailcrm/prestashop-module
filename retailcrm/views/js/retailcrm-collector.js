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

(function () {
    class RCRMCollector {
        init = () => {
            this.getCollectorConfig()
                .then((config) => {
                    this.initCollector();

                    if (this.has(config, 'customerId')) {
                        this.executeCollector(config.siteKey, {customerId: config.customerId});
                    } else {
                        this.executeCollector(config.siteKey, {});
                    }
                })
                .catch((err) => this.isNil(err) ? null : console.log(err));
        };

        getCollectorConfig = () => {
            return new Promise((resolve, reject) => {
                fetch('/index.php?fc=module&module=retailcrm&controller=DaemonCollector')
                    .then((data) => data.json())
                    .then((data) => {
                        if (this.has(data, 'siteKey') && data.siteKey.length > 0) {
                            resolve(data);
                        } else {
                            reject();
                        }
                    })
                    .catch((err) => {
                        reject(`Failed to init collector: ${err}`);
                    });
            });
        };

        initCollector = () => {
            (function(_,r,e,t,a,i,l){
                _['retailCRMObject']=a;
                _[a]=_[a]||function(){
                    (_[a].q=_[a].q||[]).push(arguments);
                };
                _[a].l=1*new Date();
                l=r.getElementsByTagName(e)[0];
                i=r.createElement(e);
                i.async=!0;
                i.src=t;
                l.parentNode.insertBefore(i,l)
            })(window,document,'script','https://collector.retailcrm.pro/w.js','_rc');
        };

        executeCollector = (siteKey, settings) => {
            _rc('create', siteKey, settings);
            _rc('send', 'pageView');
        };

        isNil = (value) => {
            return value == null;
        };

        has = (object, key) => {
            return object != null && hasOwnProperty.call(object, key);
        };
    }

    (new RCRMCollector()).init();
})();
