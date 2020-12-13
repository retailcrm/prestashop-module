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

$(function(){
    var Main = {
        init: function() {
            this.player.init();
            this.tabs.init();
            this.uploadForm.init(this.settingsTabs.init());
            this.selects.init();
            this.popup.init();
            this.toggleBox();
            this.trimConsultant();
            this.showSettings();
        },
        selects: {
            init: function () {
                var _this = this;

                try {
                    $('.jq-select').SumoSelect();
                    $('li.opt').each((_, el) => {
                        if ($(el).find('label').html().length === 0) {
                            let select = $(el).closest('ul').closest('div').parent().find('select');
                            $(el).find('label').html(select.attr('placeholder'));
                            $(el).addClass('disabled');
                        }
                    });

                    // auto disabled selected options
                    _this.update();
                    $(document).on('change', '.jq-select', function() {
                        _this.update();
                    });

                } catch (e) {
                    console.warn('Cannot initialize select: ' + e.message);
                }
            },
            update: function() {

                var selected = {};

                let selects = $('.retail-tab__enabled').find('select');
                selects.each((i, select) => {

                    var value = $(select).val();
                    if (value && value.length) {
                        selected[i] = $('option[value="' + $(select).val() + '"]', $(select)).index();
                    }
                });

                let values = Object.values(selected);

                selects.each((i, select) => {
                    $('option', select).each((o, option) => {

                        if ($.inArray(o, values) === -1 || (typeof selected[i] !== 'undefined' && selected[i] == o)) {
                            select.sumo.enableItem(o);
                        } else {
                            select.sumo.disableItem(o);
                        }
                    });
                });
            }
        },
        player: {
            init: function () {
                window.player = {};
                window.onYouTubeIframeAPIReady = function () {
                    window.player = new YT.Player('player', {
                        height: '100%',
                        width: '100%',
                        videoId: window.RCRMPROMO,
                    });
                }
                var ytAPI = document.createElement('script');
                ytAPI.src = 'https://www.youtube.com/iframe_api';
                document.body.appendChild(ytAPI);
            }
        },
        settingsTabs: {
            init: function () {
                if (typeof RCRMTabs === 'undefined') {
                    return;
                }

                let tabs = new RCRMTabs(
                    'div[id^="rcrm_tab_"]',
                    '.retail-menu__btn',
                    'retail-tab__enabled',
                    'retail-tab__disabled',
                    'retail-menu__btn_active',
                    'retail-menu__btn_inactive',
                    'tab-trigger',
                    '.rcrm-form-submit-trigger'
                );

                let mainSubmitHide = {
                    beforeActivate: function () {
                        $('#main-submit').hide();
                    },
                    afterDeactivate: function () {
                        $('#main-submit').show();
                    }
                };

                tabs.tabsCallbacks({
                    'rcrm_tab_consultant': mainSubmitHide,
                    'rcrm_tab_orders_upload': mainSubmitHide
                })
                tabs.initializeTabs();

                return tabs;
            }
        },
        uploadForm: {
            init: function (tabController) {
                if (!(typeof RetailcrmUploadForm === 'undefined')) {
                    new RetailcrmUploadForm(tabController);
                }
            }
        },
        tabs: {
            init: function () {
                $('.retail-tabs__btn').on('click', this.swithTab);
            },
            swithTab: function (e) {
                e.preventDefault();

                var id = $(this).attr('href');
                $('.retail-tabs__btn_active').removeClass('retail-tabs__btn_active');
                $(".retail-tabs__item_active").removeClass('retail-tabs__item_active')
                    .fadeOut(150, function () {
                        $(id).addClass("retail-tabs__item_active")
                            .fadeIn(150);
                    });
                $(this).addClass('retail-tabs__btn_active');
            }
        },
        popup: {
            init: function () {
                var _this = this;

                $('[data-popup]').on('click', function (e) {
                    var id = $(this).data('popup');
                    _this.open($(id));
                });
                $('.retail-popup-wrap').on('click', function (e) {
                    if (!$(e.target).hasClass('js-popup-close')) {
                        return;
                    }
                    var $popup = $(this).find('.retail-popup');
                    _this.close($popup);
                });
            },
            open: function (popup) {
                if (!popup) {
                    return;
                }
                var $wrap = popup.closest('.retail-popup-wrap');

                $wrap.fadeIn(200);
                popup.addClass('open');
                player.playVideo();
            },
            close: function (popup) {
                var $wrap = popup.closest('.retail-popup-wrap');
                popup.removeClass('open');
                $wrap.fadeOut(200);
                player.stopVideo();
            }
        },
        toggleBox: function () {
            $('.toggle-btn').on('click', function (e) {
                e.preventDefault();

                var id = $(this).attr('href');
                var $box = $(id);
                var $hideBox = $(this).closest('.retail-btns');

                $hideBox.addClass('retail-btns_hide').slideUp(100);
                $box.slideDown(100);
            })
        },
        trimConsultant: function () {
            let $consultantTextarea = $('#rcrm_tab_consultant textarea');
            $consultantTextarea.text($consultantTextarea.text().trim());
        },
        showSettings: function () {
            $('.retail.retail-wrap.hidden').removeClass('hidden');
        }
    };

    Main.init();
});