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
$(function () {
    function RetailcrmExportForm() {
        this.form = $('input[name=RETAILCRM_EXPORT_ORDERS_COUNT]').closest('form').get(0);

        if (typeof this.form === 'undefined') {
            return false;
        }

        this.ordersCount = parseInt($(this.form).find('input[name="RETAILCRM_EXPORT_ORDERS_COUNT"]').val());
        this.customersCount = parseInt($(this.form).find('input[name="RETAILCRM_EXPORT_CUSTOMERS_COUNT"]').val());
        this.ordersStepSize = parseInt($(this.form).find('input[name="RETAILCRM_EXPORT_ORDERS_STEP_SIZE"]').val());
        this.customersStepSize = parseInt($(this.form).find('input[name="RETAILCRM_EXPORT_CUSTOMERS_STEP_SIZE"]').val());
        this.ordersStep = 0;
        this.customersStep = 0;

        this.submitButton = $(this.form).find('button[id="export-orders-submit"]').get(0);
        this.progressBar = $(this.form).find('div[id="export-orders-progress"]').get(0);

        this.submitAction = this.submitAction.bind(this);
        this.exportAction = this.exportAction.bind(this);
        this.exportDone = this.exportDone.bind(this);
        this.initializeProgressBar = this.initializeProgressBar.bind(this);
        this.updateProgressBar = this.updateProgressBar.bind(this);

        $(this.submitButton).click(this.submitAction);
    }

    RetailcrmExportForm.prototype.submitAction = function (event) {
        event.preventDefault();

        this.initializeProgressBar();
        this.exportAction();
    };

    RetailcrmExportForm.prototype.exportAction = function () {
        let data = {};
        if (this.ordersStep * this.ordersStepSize < this.ordersCount) {
            this.ordersStep++;
            data = {
                submitretailcrm: 1,
                RETAILCRM_EXPORT_ORDERS_STEP: this.ordersStep
            }
        } else {
            if (this.customersStep * this.customersStepSize < this.customersCount) {
                this.customersStep++;
                data = {
                    submitretailcrm: 1,
                    RETAILCRM_EXPORT_CUSTOMERS_STEP: this.customersStep
                }
            } else {
                return this.exportDone();
            }
        }

        let _this = this;

        $.ajax({
            url: this.form.action,
            method: this.form.method,
            timeout: 0,
            data: data
        })
            .done(function (response) {
                _this.updateProgressBar();
                _this.exportAction();
            })
    };

    RetailcrmExportForm.prototype.initializeProgressBar = function () {
        $(this.submitButton).addClass('retail-hidden');
        $(this.progressBar)
            .removeClass('retail-hidden')
            .append($('<div/>', {class: 'retail-progress__loader', text: '0%'}))

        window.addEventListener('beforeunload', this.confirmLeave);
    };

    RetailcrmExportForm.prototype.updateProgressBar = function () {
        let processedOrders = this.ordersStep * this.ordersStepSize;
        if (processedOrders > this.ordersCount)
            processedOrders = this.ordersCount;

        let processedCustomers = this.customersStep * this.customersStepSize;
        if (processedCustomers > this.customersCount)
            processedCustomers = this.customersCount;

        const processed = processedOrders + processedCustomers;
        const total = this.ordersCount + this.customersCount;
        const percents = Math.round(100 * processed / total);

        $(this.progressBar).find('.retail-progress__loader').text(percents + '%');
        $(this.progressBar).find('.retail-progress__loader').css('width', percents + '%');
        $(this.progressBar).find('.retail-progress__loader').attr('alt', processed + '/' + total);
    };

    RetailcrmExportForm.prototype.confirmLeave = function (event) {
        event.preventDefault();
        e.returnValue = 'Export process has been started';
    }

    RetailcrmExportForm.prototype.exportDone = function () {
        window.removeEventListener('beforeunload', this.confirmLeave);
        alert('Export is done')
    }

    window.RetailcrmExportForm = RetailcrmExportForm;
});
