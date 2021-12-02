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
$(function () {
    function RetailcrmOrdersForm() {
        this.form = $('#retail-search-orders-form').get(0);

        if (typeof this.form === 'undefined') {
            return false;
        }

        this.isInitiated = false;
        this.initAction = this.initAction.bind(this);

        this.submitButton = $(this.form).find('button[id="search-orders-submit"]').get(0);
        this.submitAction = this.submitAction.bind(this);

        this.ordersTable = $('#retail-orders-table').get(0);
        this.ordersTableBody = $(this.ordersTable).find('tbody').get(0);
        this.rowSample = $(this.ordersTableBody).find('tr').clone();
        $(this.ordersTableBody).empty();
        this.loadOrders = this.loadOrders.bind(this);
        this.searchOrders = this.searchOrders.bind(this);

        this.pagesMenu = $('.retail-table-pagination').get(0);
        $(this.pagesMenu).empty();
        this.loadPagination = this.loadPagination.bind(this);

        this.uploadLink = $('#retail-controller-orders-upload').attr('href');
        $('#retail-controller-orders-upload').remove();
        this.uploadOrder = this.uploadOrder.bind(this);

        this.partitionId = this.partitionId.bind(this);
        this.setLoading = this.setLoading.bind(this);
        this.showMessage = this.showMessage.bind(this);

        this.orders = [];
        this.filter = null;
        this.page = null;

        $(this.submitButton).click(this.submitAction);

        $('.retail-table-filter-btn').click(function (e) {
            $('.retail-table-filter-btn').removeClass('active');
            $(this).addClass('active');
        });

        // todo move to retailcrm.js
        let foldableContainer = $('.retail-container--foldable');
        $(foldableContainer).find('.retail-row--foldable').find('.retail-row__title').click(function (e) {
            $(foldableContainer).find('.retail-row--foldable').removeClass('active');
            $(this).parent().addClass('active');
        })

        $('#retail-uploaded-orders-tab .retail-row__title').click(this.initAction);
    }

    RetailcrmOrdersForm.prototype.initAction = function (event) {
        event.preventDefault();

        if (!this.isInitiated) {
            this.searchOrders();
            this.isInitiated = true;
        }
    }

    RetailcrmOrdersForm.prototype.submitAction = function (event) {
        event.preventDefault();
        let formData = $(this.form).serializeArray().reduce(function (obj, item) {
            obj[item.name] = item.value;
            return obj;
        }, {});

        let idString = formData['search-orders-value'];
        this.orders = this.partitionId(idString.toString().replace(/\s+/g, ''));
        this.filter = formData['search-orders-filter'];

        this.searchOrders();
    };

    RetailcrmOrdersForm.prototype.searchOrders = function (page = 1) {
        this.setLoading(true);

        let _this = this;
        let data = {
            orders: this.orders,
            filter: this.filter,
            page: page
        };

        $.ajax({
            url: this.form.action,
            method: this.form.method,
            dataType: 'json',
            timeout: 0,
            data: data
        })
            .done(function (response) {
                if (response.success !== undefined && response.success === false) {
                    _this.setLoading(false);
                    _this.showMessage('orders-table.error');
                    console.warn(response);
                    return;
                }

                if (response.orders !== undefined && response.orders.length > 0) {
                    _this.loadOrders(response.orders);
                } else {
                    _this.showMessage('orders-table.empty');
                }

                if (response.pagination !== undefined) {
                    _this.loadPagination(response.pagination);
                }

                _this.setLoading(false);
            })
            .fail(function (response) {
                _this.setLoading(false);
                _this.showMessage('orders-table.error');
                console.warn(response);
            })
    }
    RetailcrmOrdersForm.prototype.loadOrders = function (orders) {
        $(this.ordersTableBody).empty();
        $(this.ordersTable).removeClass('hidden');

        let _this = this;
        let crmOrderUrlTemplate = $(this.rowSample).find('td.retail-orders-table__id-crm a').attr('href');
        let cmsOrderUrlTemplate = $(this.rowSample).find('td.retail-orders-table__id-cms a').attr('href');

        $.each(orders, function (key, item) {
            let newRow = _this.rowSample.clone().get(0);
            $(newRow).find('td.retail-orders-table__date').text(item.last_uploaded)
            $(newRow).find('td.retail-orders-table__id-cms a').text(item.id_order)
                .attr('href', cmsOrderUrlTemplate + '&vieworder=&id_order=' + item.id_order)
            if (item.id_order_crm === null) {
                $(newRow).find('td.retail-orders-table__id-crm').empty();
            } else {
                $(newRow).find('td.retail-orders-table__id-crm a').text(item.id_order_crm)
                    .attr('href', crmOrderUrlTemplate + item.id_order_crm + '/edit')
            }

            if (item.errors !== null) {
                let statusDom = $(newRow).find('td.retail-orders-table__status');
                statusDom.addClass('error');

                try {
                    let errors = JSON.parse(item.errors)

                    statusDom.find('.retail-orders-table__error').append('<ul>');
                    for (let error in errors) {
                        statusDom.find('.retail-orders-table__error').append('<li>' + errors[error] + '</li>');
                    }
                    statusDom.find('.retail-orders-table__error').append('</ul>');
                } catch (e) {
                    console.log(e);
                    statusDom.find('.retail-orders-table__error').append(item.errors);
                }

                statusDom.find('.retail-collapsible__input').attr('id', 'errors_' + item.id_order)
                statusDom.find('.retail-collapsible__title').attr('for', 'errors_' + item.id_order)
            }
            $(newRow).find('td.retail-orders-table__upload').click(function (e) {
                    e.preventDefault();
                    _this.uploadOrder(item.id_order);
                }
            )

            $(_this.ordersTableBody).append(newRow);
        })
    }

    RetailcrmOrdersForm.prototype.uploadOrder = function (id_order) {
        this.setLoading(true);
        let _this = this;

        $.ajax({
            url: this.uploadLink,
            method: 'POST',
            dataType: 'json',
            timeout: 0,
            data: {
                orders: [id_order]
            }
        })
            .done(function (response) {
                let message = '';

                if (response.success === undefined || !response.success) {
                    message = 'Error uploading order: ';
                    if (response.errorMsg !== undefined) {
                        message += response.errorMsg;
                    }
                    if (response.errors !== undefined) {
                        for (orderErrors in response.errors) {
                            for (error in response.errors[orderErrors]) {
                                message += ' ' + response.errors[orderErrors][error] + ' ';
                            }
                        }
                    }
                    if (response.skippedOrders !== undefined) {
                        for (skippedOrder in response.skippedOrders) {
                            message += 'Order  ' + skippedOrder + ' already exists';
                        }
                    }
                } else {
                    message = 'Order successfully uploaded';
                }

                _this.setLoading(false);
                alert(message);
            })
            .fail(function (response) {
                console.warn(response);
                _this.setLoading(false);
                alert(retailcrmTranslates['orders-table.error'])
            });
    }

    RetailcrmOrdersForm.prototype.loadPagination = function (pagination) {
        $(this.pagesMenu).empty();
        let _this = this;
        this.page = pagination.currentPage;

        let showFirst = false;
        let showLast = false;
        let pagesToShow = 10;
        let pageFrom = 1;
        let pageTo = pagination.totalPageCount;

        if (pagination.totalPageCount > pagesToShow) {
            pageFrom = pagination.currentPage - (Math.floor(pagesToShow / 2));
            if (pageFrom > 0) {
                showFirst = true;
            } else {
                pageFrom = 1;
            }

            pageTo = pagination.currentPage + (Math.floor(pagesToShow / 2));
            if (pageTo < pagination.totalPageCount) {
                showLast = true
            } else {
                pageTo = pagination.totalPageCount;
            }
        }

        if (showFirst) {
            $(this.pagesMenu)
                .append($('<button/>', {
                    class: 'retail-table-pagination__item',
                    text: 1
                }).click(function (e) {
                    e.preventDefault();
                    _this.searchOrders(1)
                }))
                .append($('<span/>', {
                    class: 'retail-table-pagination__item retail-table-pagination__item--divider',
                    text: '...'
                }));
        }

        for (let page = pageFrom; page <= pageTo; page++) {
            $(this.pagesMenu).append($('<button/>', {
                class: 'retail-table-pagination__item ' + (page === this.page ? ' active' : ''),
                text: page
            }).click(function (e) {
                e.preventDefault();
                _this.searchOrders(page)
            }));
        }
        if (showLast) {
            $(this.pagesMenu)
                .append($('<span/>', {
                    class: 'retail-table-pagination__item retail-table-pagination__item--divider',
                    text: '...'
                }))
                .append($('<button/>', {
                    class: 'retail-table-pagination__item',
                    text: pagination.totalPageCount
                }).click(function (e) {
                    e.preventDefault();
                    _this.searchOrders(pagination.totalPageCount)
                }));
        }
    }

    RetailcrmOrdersForm.prototype.showMessage = function (message) {
        $(this.ordersTableBody)
            .empty()
            .append('<tr class="alert"><td colspan="5">' + retailcrmTranslates[message] + '</td></tr>');
        $(this.ordersTable).removeClass('hidden');
    }

    RetailcrmOrdersForm.prototype.setLoading = function (loading) {
        var loaderId = 'retailcrm-loading-fade',
            indicator = $('#' + loaderId);

        if (indicator.length === 0) {
            $('body').append(`
            <div id="${loaderId}">
                <div id="retailcrm-loader"></div>
            </div>
            `.trim());

            indicator = $('#' + loaderId);
        }

        indicator.css('visibility', (loading ? 'visible' : 'hidden'));
    };

    RetailcrmOrdersForm.prototype.partitionId = function (idList) {
        if (idList === '') {
            return [];
        }

        let itemsList = idList.split(',');
        let ids = itemsList.filter(item => item.toString().indexOf('-') === -1);
        let ranges = itemsList.filter(item => item.toString().indexOf('-') !== -1);
        let resultRanges = [];

        ranges.forEach(item => {
            let rangeData = item.split('-');

            resultRanges = [...resultRanges, ...[...Array(+rangeData[1] + 1)
                .keys()].slice(+rangeData[0], +rangeData[1] + 1)];
        });

        return [...ids, ...resultRanges].map(item => +item).sort((a, b) => a - b);
    };

    window.RetailcrmOrdersForm = RetailcrmOrdersForm;
});
