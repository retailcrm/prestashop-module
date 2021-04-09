{**
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
 *}
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/vendor/sumoselect.min.css">
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/sumoselect-custom.min.css">
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/retailcrm-upload.min.css">
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/styles.min.css">
<title>RetailCRM</title>
<div class="retail retail-wrap hidden">
    {include file='./module_messages.tpl'}
    <div class="retail-container retail-column">
        <aside class="retail-column__aside">
            <div class="retail-menu">
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_connection" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_active"><span>{l s='Connection' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_delivery_types" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Delivery' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_order_statuses" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Order statuses' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_payment_types" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Payment types' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_default_types" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Default' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_orders_upload" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Upload orders' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_carts_sync" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Abandoned carts' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_daemon_collector" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Daemon Collector' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&item=consultant" data-tab-trigger="rcrm_tab_consultant" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Online consultant' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_job_manager" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Job Manager' mod='retailcrm'}<span/></a>
            </div>
        </aside>
        <article class="retail-column__content">
            <h1 class="retail-title retail-title_content">RetailCRM</h1>
            <div class="retail-form retail-form_main">
                <form class="rcrm-form-submit-trigger" action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" method="post">
                    <input type="hidden" name="submitretailcrm" value="1" />
                    <div id="rcrm_tab_connection">
                        <div class="retail-form__title">{l s='Connection Settings' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <input required type="text" name="{$urlName|escape:'htmlall':'UTF-8'}" value="{$url|escape:'htmlall':'UTF-8'}" class="retail-form__area" placeholder="{l s='RetailCRM URL' mod='retailcrm'}">
                        </div>
                        <div class="retail-form__row">
                            <input required type="text" name="{$apiKeyName|escape:'htmlall':'UTF-8'}" value="{$apiKey|escape:'htmlall':'UTF-8'}" class="retail-form__area" placeholder="{l s='API key' mod='retailcrm'}">
                        </div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$enableHistoryUploadsName|escape:'htmlall':'UTF-8'}" value="{$enableHistoryUploads|escape:'htmlall':'UTF-8'}" {if $enableHistoryUploads}checked="checked"{/if} id="historyuploads-active">
                                <label for="historyuploads-active">{l s='Enable history uploads' mod='retailcrm'}</label>
                            </div>
                        </div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$enableBalancesReceivingName|escape:'htmlall':'UTF-8'}" value="{$enableBalancesReceiving|escape:'htmlall':'UTF-8'}" {if $enableBalancesReceiving}checked="checked"{/if} id="receivebalances-active">
                                <label for="receivebalances-active">{l s='Receive stocks from RetailCRM' mod='retailcrm'}</label>
                            </div>
                        </div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$enableCorporateName|escape:'htmlall':'UTF-8'}" value="{$enableCorporate|escape:'htmlall':'UTF-8'}" {if $enableCorporate}checked="checked"{/if} id="corpclients-active">
                                <label for="corpclients-active">{l s='Enable corporate clients support' mod='retailcrm'}</label>
                            </div>
                        </div><div class="retail-form__row">
                            <div class="retail-form__message-warning">
                                <span>{l s='Activate only in case if you have enabled the option “Corporate customers” in RetailCRM' mod='retailcrm'}</span>
                            </div>
                        </div>
                    </div>
                    <div id="rcrm_tab_carts_sync">
                        <div class="retail-form__title">{l s='Synchronization of buyer carts' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$synchronizeCartsActiveName|escape:'htmlall':'UTF-8'}" value="{$synchronizeCartsActive|escape:'htmlall':'UTF-8'}" {if $synchronizeCartsActive}checked="checked"{/if} id="ac-active">
                                <label for="ac-active">{l s='Create orders for abandoned carts of buyers' mod='retailcrm'}</label>
                            </div>
                        </div>
                        <div class="retail-form__row">
                            <label class="retail-form__label" for="{$synchronizedCartStatusName|escape:'htmlall':'UTF-8'}">{l s='Order status for abandoned carts of buyers' mod='retailcrm'}</label>
                            <select placeholder="{l s='Choose status' mod='retailcrm'}" class="jq-select" name="{$synchronizedCartStatusName|escape:'htmlall':'UTF-8'}" id="{$synchronizedCartStatusName|escape:'htmlall':'UTF-8'}">
                                {foreach from=$statusesDefaultExport item=cartStatus}
                                    <option value="{$cartStatus.id_option|escape:'htmlall':'UTF-8'}"{if $cartStatus.id_option == $synchronizedCartStatus} selected{/if}>{$cartStatus.name|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="retail-form__row">
                            <label class="retail-form__label" for="{$synchronizedCartDelayName|escape:'htmlall':'UTF-8'}">{l s='Upload abandoned carts' mod='retailcrm'}</label>
                            <select placeholder="{l s='Choose delay' mod='retailcrm'}" class="jq-select" name="{$synchronizedCartDelayName|escape:'htmlall':'UTF-8'}" id="{$synchronizedCartDelayName|escape:'htmlall':'UTF-8'}">
                                {foreach from=$cartsDelays item=delay}
                                    <option value="{$delay.id_option|escape:'htmlall':'UTF-8'}"{if $delay.id_option == $synchronizedCartDelay} selected{/if}>{$delay.name|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div id="rcrm_tab_delivery_types">
                        <div class="retail-form__title">{l s='Delivery' mod='retailcrm'}</div>
                        {foreach from=$deliveryTypes item=item}
                            <div class="retail-form__row">
                                <label class="retail-form__label" for="{$item.name|escape:'htmlall':'UTF-8'}">{l s=$item.label mod='retailcrm'}</label>
                                <select placeholder="{l s='Choose delivery' mod='retailcrm'}" class="jq-select" name="{$item.name|escape:'htmlall':'UTF-8'}" id="{$item.name|escape:'htmlall':'UTF-8'}">
                                    {foreach from=$item.options.query item=option}
                                        <option value="{$option.id_option|escape:'htmlall':'UTF-8'}"{if isset($delivery[$item.subname]) && $delivery[$item.subname] == $option.id_option} selected{/if}>{$option.name|escape:'htmlall':'UTF-8'}</option>
                                    {/foreach}
                                </select>
                            </div>
                        {/foreach}
                    </div>
                    <div id="rcrm_tab_order_statuses">
                        <div class="retail-form__title">{l s='Order statuses' mod='retailcrm'}</div>
                        {foreach from=$orderStatuses item=item}
                            <div class="retail-form__row">
                                <label class="retail-form__label" for="{$item.name|escape:'htmlall':'UTF-8'}">{l s=$item.label mod='retailcrm'}</label>
                                <select placeholder="{l s='Choose status' mod='retailcrm'}" class="jq-select" name="{$item.name|escape:'htmlall':'UTF-8'}" id="{$item.name|escape:'htmlall':'UTF-8'}">
                                    {foreach from=$item.options.query item=option}
                                        <option value="{$option.id_option|escape:'htmlall':'UTF-8'}"{if isset($status[$item.subname]) && $status[$item.subname] == $option.id_option} selected{/if}>{$option.name|escape:'htmlall':'UTF-8'}</option>
                                    {/foreach}
                                </select>
                            </div>
                        {/foreach}
                    </div>
                    <div id="rcrm_tab_payment_types">
                        <div class="retail-form__title">{l s='Payment types' mod='retailcrm'}</div>
                        {foreach from=$paymentTypes item=item}
                            <div class="retail-form__row">
                                <label class="retail-form__label" for="{$item.name|escape:'htmlall':'UTF-8'}">{l s=$item.label mod='retailcrm'}</label>
                                <select placeholder="{l s='Choose type' mod='retailcrm'}" class="jq-select" name="{$item.name|escape:'htmlall':'UTF-8'}" id="{$item.name|escape:'htmlall':'UTF-8'}">
                                    {foreach from=$item.options.query item=option}
                                        <option value="{$option.id_option|escape:'htmlall':'UTF-8'}"{if isset($payment[$item.subname]) && $payment[$item.subname] == $option.id_option} selected{/if}>{$option.name|escape:'htmlall':'UTF-8'}</option>
                                    {/foreach}
                                </select>
                            </div>
                        {/foreach}
                    </div>
                    <div id="rcrm_tab_default_types">
                        <div class="retail-form__title">{l s='Default' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <label class="retail-form__label" for="{$methodsForDefault[0].name|escape:'htmlall':'UTF-8'}">{l s=$methodsForDefault[0].label mod='retailcrm'}</label>
                            <select placeholder="{l s='Choose delivery' mod='retailcrm'}" class="jq-select" name="{$methodsForDefault[0].name|escape:'htmlall':'UTF-8'}" id="{$methodsForDefault[0].name|escape:'htmlall':'UTF-8'}">
                                {foreach from=$methodsForDefault[0].options.query item=option}
                                    <option value="{$option.id_option|escape:'htmlall':'UTF-8'}"{if isset($deliveryDefault) && $deliveryDefault == $option.id_option} selected{/if}>{$option.name|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="retail-form__row">
                            <label class="retail-form__label" for="{$methodsForDefault[1].name|escape:'htmlall':'UTF-8'}">{l s=$methodsForDefault[1].label mod='retailcrm'}</label>
                            <select placeholder="{l s='Choose type' mod='retailcrm'}" class="jq-select" name="{$methodsForDefault[1].name|escape:'htmlall':'UTF-8'}" id="{$methodsForDefault[1].name|escape:'htmlall':'UTF-8'}">
                                {foreach from=$methodsForDefault[1].options.query item=option}
                                    <option value="{$option.id_option|escape:'htmlall':'UTF-8'}"{if isset($paymentDefault) && $paymentDefault == $option.id_option} selected{/if}>{$option.name|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div id="rcrm_tab_orders_upload">
                        <div class="retail-form__title">{l s='Upload orders' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <input type="text" name="{$uploadOrders|escape:'htmlall':'UTF-8'}" value="" class="retail-form__area" placeholder="{l s='Orders IDs' mod='retailcrm'}">
                        </div>
                        <div class="retail-form__row retail-form__row_submit">
                            <button id="upload-orders-submit" class="btn btn_invert btn_submit" style="outline: none;">{l s='Upload' mod='retailcrm'}</button>
                        </div>
                    </div>
                    <div id="rcrm_tab_daemon_collector">
                        <div class="retail-form__title">{l s='Daemon Collector' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$collectorActiveName|escape:'htmlall':'UTF-8'}" value="{$collectorActive|escape:'htmlall':'UTF-8'}" {if $collectorActive}checked="checked"{/if} id="dc-active">
                                <label for="dc-active">{l s='Active' mod='retailcrm'}</label>
                            </div>
                        </div>
                        <div class="retail-form__row">
                            <input type="text" name="{$collectorKeyName|escape:'htmlall':'UTF-8'}" value="{$collectorKey|escape:'htmlall':'UTF-8'}" class="retail-form__area" placeholder="{l s='Site key' mod='retailcrm'}">
                        </div>
                    </div>
                    <div class="retail-form__row retail-form__row_submit" id="main-submit">
                        <input type="submit" value="{l s='Save' mod='retailcrm'}" class="btn btn_invert btn_submit">
                    </div>
                </form>
                <div id="rcrm_tab_consultant">
                    <form class="rcrm-form-submit-trigger" action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&item=consultant" method="post">
                        <input type="hidden" name="submitretailcrm" value="1" />
                        <div class="retail-form__title">{l s='Online consultant' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                        <textarea name="{$consultantScriptName|escape:'htmlall':'UTF-8'}" class="retail-form__area retail-form__area_txt" id="retail-txt-area" placeholder="{l s='Code you need to insert on the web' mod='retailcrm'}">
                            {$consultantScript|escape:'htmlall':'UTF-8'}
                        </textarea>
                        </div>
                        <div class="retail-form__row retail-form__row_submit">
                            <input type="submit" value="{l s='Save' mod='retailcrm'}" class="btn btn_invert btn_submit">
                        </div>
                    </form>
                </div>
                <div id="rcrm_tab_job_manager">
                    <div class="retail-form__title">{l s='Job Manager' mod='retailcrm'}</div>
                    <table class="retail-table retail-table-top">
                        <thead>
                            <th>{l s='Job name' mod='retailcrm'}</th>
                            <th>{l s='Last Run' mod='retailcrm'}</th>
                            <th>{l s='Status' mod='retailcrm'}</th>
                            <th>{l s='Comment' mod='retailcrm'}</th>
                        </thead>
                        <tbody>
                        {foreach from=$lastRunDetails key=key item=item}
                            <tr class="retail-table__row-top">
                                <td>{$key}</td>
                                <td class="retail-table-center retail-table-no-wrap">{if isset($item['lastRun'])}{$item['lastRun']|date_format:'Y-m-d H:i:s'}{/if}</td>
                                <td class="retail-table-center">
                                    {if isset($item['success'])}
                                        {if $item['success'] === true}
                                            <span style="color: green;">
                                            &#10004;
                                            </span>
                                        {else}
                                            <span style="color: red;">
                                            &#10060;
                                            </span>
                                        {/if}
                                    {/if}
                                </td>
                                <td>
                                    {if isset($item['error']['message'])}
                                        <div class="retail-collapsible">
                                            <input type="checkbox" class="retail-collapsible__input" id="error_{$key}">
                                            <label for="error_{$key}" class="retail-collapsible__title">
                                                <span class="retail-error-msg">{$item['error']['message']}</span>
                                                <p class="retail-collapsible__content">
                                                    <b>{l s='StackTrace' mod='retailcrm'}:</b><br>{$item['error']['trace']}
                                                </p>
                                            </label>
                                        </div>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </div>

</div>
{*<script src="//ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>*}
<script>window.jQuery || document.write('<script src="{$assets|escape:'htmlall':'UTF-8'}/js/vendor/jquery-3.4.0.min.js"><\/script>')</script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/vendor/jquery.sumoselect.min.js"></script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm-tabs.min.js"></script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm-upload.min.js"></script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm.min.js"></script>
