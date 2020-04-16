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
class RCRMTabs {
    currentTab = '';
    withHistory = false;
    callbacks = {};

    constructor(
        tabSelector,
        triggerSelector,
        activeTabClass,
        inactiveTabClass,
        activeTriggerClass,
        inactiveTriggerClass,
        dataTabId,
        formSubmitTrigger,
        initialize = false,
        withHistory = true
    ) {
        this.tabSelector = tabSelector;
        this.triggerSelector = triggerSelector;
        this.activeTabClass = activeTabClass;
        this.inactiveTabClass = inactiveTabClass;
        this.activeTriggerClass = activeTriggerClass;
        this.inactiveTriggerClass = inactiveTriggerClass;
        this.dataTabId = dataTabId;
        this.withHistory = withHistory;
        this.formSubmitTrigger = formSubmitTrigger;

        if (initialize) {
            this.initializeTabs();
        }
    };

    tabsCallbacks = (list) => {
        this.callbacks = list;
    };

    switchTab = (tab) => {
        if (this.isTabExists(tab)) {
            this.activateTab(this.getTab(tab));
        }
    };

    initializeTabs = () => {
        let initialTabId = this.parseUri().rcrmtab || '';

        $(this.triggerSelector).each((index, el) => {
            let $el = $(el);
            let $tab = this.getTab($el.data(this.dataTabId));

            if (this.isTriggerActive($el)) {
                if (!this.isTabActive($tab)) {
                    this.activateTab($tab);
                }
            } else {
                if (this.isTabActive($tab)) {
                    this.deactivateTab($tab);
                } else if (!$tab.hasClass(this.inactiveTabClass)) {
                    $tab.addClass(this.inactiveTabClass);
                }
            }

            $el.on('click', (e) => {
                e.preventDefault();
                this.switchTab($el.data(this.dataTabId));
            });
        });

        if (initialTabId !== this.currentTab && this.isTabExists(initialTabId)) {
            this.deactivateTab(this.getTab(this.currentTab));
            this.activateTab(this.getTab(initialTabId));
        }

        document.querySelectorAll(this.formSubmitTrigger).forEach((form) => {
            form.addEventListener("submit", (event) => {
                let target = event.target;
                this.storeTabInAction(target);
            });
        });

        if (this.withHistory) {
            $(window).bind('popstate', (event) => {
                let state = event.originalEvent.state;

                if (typeof state === "object" &&
                    state !== null &&
                    typeof state.rcrmtab === 'string' &&
                    state.rcrmtab.length > 0
                ) {
                    this.switchTab(state.rcrmtab);
                }
            });
        }
    };

    storeTabInAction = (form) => {
        if (form instanceof HTMLFormElement) {
            let baseUri = location.href.replace(location.search, '');
            let parsedAction = this.parseUri(form.action.replace(baseUri, ''));
            parsedAction.rcrmtab = this.currentTab;
            form.action = baseUri + this.generateUri(parsedAction);
        }
    };

    getTab = (id) => {
        return $(document.getElementById(id));
    };

    getTrigger = (id) => {
        return $('[data-' + this.dataTabId + '="' + id + '"]');
    };

    isTriggerActive = ($el) => {
        return $el.hasClass(this.activeTriggerClass);
    };

    isTabActive = ($el) => {
        return $el.hasClass(this.activeTabClass);
    };

    isTabExists = (tabId) => {
        return this.getTrigger(tabId).length > 0 && this.getTab(tabId).length === 1;
    };

    activateTab = ($el) => {
        this.deactivateTab(this.getTab(this.currentTab));

        if (!$el.hasClass(this.activeTabClass)) {
            let $trigger = this.getTrigger($el.prop('id')),
                $currentTriggers = $('.' + this.activeTriggerClass);

            if (this.callbacks.hasOwnProperty($el.prop('id')) && this.callbacks[$el.prop('id')].hasOwnProperty('beforeActivate')) {
                this.callbacks[$el.prop('id')].beforeActivate();
            }

            $currentTriggers.each((index, el) => {
                $(el).removeClass(this.activeTriggerClass);
            });

            if (!$trigger.hasClass(this.activeTriggerClass)) {
                $trigger.removeClass(this.inactiveTriggerClass);
                $trigger.addClass(this.activeTriggerClass);
            }

            $el.removeClass(this.inactiveTabClass);
            $el.addClass(this.activeTabClass);
            this.currentTab = $el.prop('id');

            if (this.callbacks.hasOwnProperty($el.prop('id')) && this.callbacks[$el.prop('id')].hasOwnProperty('afterActivate')) {
                this.callbacks[$el.prop('id')].afterActivate();
            }

            if (this.withHistory) {
                let uri = this.parseUri();
                uri.rcrmtab = this.currentTab;
                window.history.pushState({rcrmtab: this.currentTab}, this.currentTab, this.generateUri(uri));
            }
        }
    };

    deactivateTab = ($el) => {
        if ($el.length === 0) {
            return;
        }

        if ($el.hasClass(this.activeTabClass)) {
            let $trigger = this.getTrigger($el.prop('id'));

            if (this.callbacks.hasOwnProperty($el.prop('id')) && this.callbacks[$el.prop('id')].hasOwnProperty('beforeDeactivate')) {
                this.callbacks[$el.prop('id')].beforeDeactivate();
            }

            if ($trigger.hasClass(this.activeTriggerClass)) {
                $trigger.removeClass(this.activeTriggerClass);
                $trigger.addClass(this.inactiveTriggerClass);
            }

            $el.removeClass(this.activeTabClass);
            $el.addClass(this.inactiveTabClass);

            if (this.callbacks.hasOwnProperty($el.prop('id')) && this.callbacks[$el.prop('id')].hasOwnProperty('afterDeactivate')) {
                this.callbacks[$el.prop('id')].afterDeactivate();
            }
        }
    };

    parseUri = (parsableString) => {
        return Object.fromEntries((parsableString || location.search)
            .substr(1)
            .split('&')
            .map((item) => item.split('=')));
    };

    generateUri = (parsedUri) => {
        if (parsedUri != null) {
            return '?' + Object.entries(parsedUri)
                .map((item) => item.join('='))
                .join('&')
                .replace(/\n+/igm, '')
                .trim()
        } else {
            throw new Error('Invalid URI data');
        }
    };
}