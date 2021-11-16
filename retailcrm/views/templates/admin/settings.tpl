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
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/retailcrm-orders.min.css">
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/retailcrm-upload.min.css">
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/retailcrm-export.min.css">
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/styles.min.css">

{assign var="systemName" value="Simla.com"}
{capture name="catalogTitleName"}{l s='Icml catalog' mod='retailcrm'}{/capture}
{assign var="catalogTitleName" value=$smarty.capture.catalogTitleName}

<title>{$systemName|escape:'htmlall':'UTF-8'}</title>
<div class="retail retail-wrap hidden">
    {include file='./module_messages.tpl'}
    {include file='./module_translates.tpl'}
    <div class="retail-container retail-column">
        <aside class="retail-column__aside">
            <div class="retail-menu">
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_connection" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_active"><span>{l s='Connection' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_delivery_types" class="retail-menu__btn retail-menu__btn_big{if in_array('delivery', $errorTabs)} retail-menu__btn_error{/if}"><span>{l s='Delivery' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_order_statuses" class="retail-menu__btn retail-menu__btn_big{if in_array('statuses', $errorTabs)} retail-menu__btn_error{/if}"><span>{l s='Order statuses' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_payment_types" class="retail-menu__btn retail-menu__btn_big{if in_array('payment', $errorTabs)} retail-menu__btn_error{/if}"><span>{l s='Payment types' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_default_types" class="retail-menu__btn retail-menu__btn_big{if in_array('deliveryDefault', $errorTabs) || in_array('paymentDefault', $errorTabs)} retail-menu__btn_error{/if}"><span>{l s='Default' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_stock" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_active"><span>{l s='Stock' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_orders_upload" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Upload orders' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_carts_sync" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Abandoned carts' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_catalog" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive{if in_array('catalog', $errorTabs)} retail-menu__btn_error{/if}"><span>{$catalogTitleName|escape:'htmlall':'UTF-8'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_daemon_collector" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Daemon Collector' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&item=consultant" data-tab-trigger="rcrm_tab_consultant" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive"><span>{l s='Online consultant' mod='retailcrm'}<span/></a>
                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" data-tab-trigger="rcrm_tab_advanced" class="retail-menu__btn retail-menu__btn_big retail-menu__btn_inactive retail-menu__btn_hidden"><span>{l s='Advanced' mod='retailcrm'}<span/></a>
            </div>
        </aside>
        <article class="retail-column__content">
            <h1 class="retail-title retail-title_content">{$systemName|escape:'htmlall':'UTF-8'}</h1>
            <div class="retail-form retail-form_main">
                <form class="rcrm-form-submit-trigger" action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" method="post" id="submitretailcrm-form">
                    <input type="hidden" name="submitretailcrm" value="1" />
                    <div id="rcrm_tab_connection">
                        <div class="retail-form__title">{l s='Connection Settings' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <input required type="text" name="{$urlName|escape:'htmlall':'UTF-8'}" value="{$url|escape:'htmlall':'UTF-8'}" class="retail-form__area" placeholder="{l s='Simla.com URL' mod='retailcrm'}">
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
                        <div class="retail-form__title"
                             style="margin-top: 40px;">{l s='Order number' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$enableOrderNumberSendingName|escape:'htmlall':'UTF-8'}" value="{$enableOrderNumberSending|escape:'htmlall':'UTF-8'}" {if $enableOrderNumberSending}checked="checked"{/if} id="sendnumbers-active">
                                <label for="sendnumbers-active">{l s='Send order number to Simla.com' mod='retailcrm'}</label>
                            </div>
                        </div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$enableOrderNumberReceivingName|escape:'htmlall':'UTF-8'}" value="{$enableOrderNumberReceiving|escape:'htmlall':'UTF-8'}" {if $enableOrderNumberReceiving}checked="checked"{/if} id="receivenumbers-active">
                                <label for="receivenumbers-active">{l s='Receive order number from Simla.com' mod='retailcrm'}</label>
                            </div>
                        </div>
                        <div class="retail-form__title"
                             style="margin-top: 40px;">{l s='Corporate clients' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$enableCorporateName|escape:'htmlall':'UTF-8'}" value="{$enableCorporate|escape:'htmlall':'UTF-8'}" {if $enableCorporate}checked="checked"{/if} id="corpclients-active">
                                <label for="corpclients-active">{l s='Enable corporate clients support' mod='retailcrm'}</label>
                            </div>
                        </div><div class="retail-form__row">
                            <div class="retail-form__message-warning">
                                <span>{l s='Activate only in case if you have enabled the option “Corporate customers” in Simla.com' mod='retailcrm'}</span>
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
                    <div id="rcrm_tab_catalog">
                        {assign var="showUpdateButton" value=false}
                        <div class="retail-form__title">
                            {$catalogTitleName|escape:'htmlall':'UTF-8'}
                            {if $catalogInfoMultistore|count == 1}
                                {assign var='catalogInfo' value=$catalogInfoMultistore[$catalogInfoMultistore|@key] }
                                <a href="{$url|cat:'/admin/sites/'|escape:'htmlall':'UTF-8'}{if isset($catalogInfo.siteId) and $catalogInfo.siteId}{$catalogInfo.siteId|cat:'/edit#t-catalog'|escape:'htmlall':'UTF-8'}{/if}"
                                   target="_blank"
                                   class="retail-form__title_link">{l s='Manage site settings' mod='retailcrm'}</a>
                            {/if}
                        </div>
                        {foreach from=$catalogInfoMultistore key=catalogShopId item=catalogInfo}
                        <div class="retail-form__title" style="margin-top: 50px;">
                            {if $catalogInfoMultistore|count > 1}
                                <a href="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&rcrmtab=rcrm_tab_catalog&setShopContext=s-{$catalogShopId|escape:'htmlall':'UTF-8'}">{$shopsInfo[$catalogShopId].name|escape:'htmlall':'UTF-8'}</a>
                                <a href="{$url|cat:'/admin/sites/'|escape:'htmlall':'UTF-8'}{if isset($catalogInfo.siteId) and $catalogInfo.siteId}{$catalogInfo.siteId|cat:'/edit#t-catalog'|escape:'htmlall':'UTF-8'}{/if}"
                                   target="_blank"
                                   class="retail-form__title_link">{l s='Manage site settings' mod='retailcrm'}</a>
                            {/if}
                        </div>
                        {if $catalogInfo and isset($catalogInfo.lastGenerated) and $catalogInfo.lastGenerated}
                            {if  $catalogInfo.isOutdated }
                                <div class="retail-alert retail-alert-danger">
                                    <div class="retail-alert-text">
                                        {$catalogTitleName|escape:'htmlall':'UTF-8'} {l s='is outdated' mod='retailcrm'}
                                    </div>
                            {elseif !isset($catalogInfo.isUrlActual) or !$catalogInfo.isUrlActual}
                                    {assign var="showUpdateButton" value=true}
                                <div class="retail-alert retail-alert-warning">
                                    <div class="retail-alert-text">
                                        {l s='URL for Icml catalog file in Prestashop and in %s do not match' mod='retailcrm' sprintf=[$systemName]}
                                    </div>
                            {else}
                                <div class="retail-alert retail-alert-success">
                                    <div class="retail-alert-text">
                                        {$catalogTitleName|escape:'htmlall':'UTF-8'} {l s='connected' mod='retailcrm'}
                                    </div>
                            {/if}
                                    <div class="retail-alert-note">
                                        {$catalogInfo.lastGenerated|date_format:"%Y-%m-%d %H:%M:%S"|escape:'htmlall':'UTF-8'}
                                    </div>
                                </div>
                                <div class="retail-form__row">
                                    <div class="retail-form__label">
                                    <span style="font-weight: bold; font-size: 1.3em;">
                                    {if $catalogInfo.lastGeneratedDiff.days > 7}
                                        {l s='More than 7 days' mod='retailcrm'}
                                    {else}
                                        {if $catalogInfo.lastGeneratedDiff.days > 0}
                                            {$catalogInfo.lastGeneratedDiff.days|escape:'htmlall':'UTF-8'}  {l s='d' mod='retailcrm'}.
                                        {/if}
                                        {if $catalogInfo.lastGeneratedDiff.hours > 0}
                                            {$catalogInfo.lastGeneratedDiff.hours|escape:'htmlall':'UTF-8'}  {l s='h' mod='retailcrm'}.
                                        {/if}
                                        {$catalogInfo.lastGeneratedDiff.minutes|escape:'htmlall':'UTF-8'} {l s='min' mod='retailcrm'}.
                                    {/if}
                                    </span>
                                        {l s='passed since last run' mod='retailcrm'}
                                    </div>
                                    {if isset($catalogInfo.productsCount) and isset($catalogInfo.offersCount)}
                                        <div class="retail-form__label">
                                            <span style="font-weight: bold; font-size: 1.3em;">
                                                {$catalogInfo.productsCount|escape:'htmlall':'UTF-8'}
                                            </span>
                                            {l s='Products' mod='retailcrm'}
                                            <span style="font-weight: bold; font-size: 1.3em;">
                                                {$catalogInfo.offersCount|escape:'htmlall':'UTF-8'}
                                            </span>
                                            {l s='Offers' mod='retailcrm'}
                                        </div>
                                    {/if}
                                </div>
                        {else}
                                <div class="retail-alert retail-alert-warning">
                                    <div class="retail-alert-text">
                                        {$catalogTitleName|escape:'htmlall':'UTF-8'} {l s='was not generated yet' mod='retailcrm'}
                                    </div>
                                    <div class="retail-alert-note">
                                        {l s='Press the below button to generate the %s' mod='retailcrm' sprintf=[$catalogTitleName]}
                                    </div>
                                </div>
                        {/if}
                                    {/foreach}
                                <input type="hidden" name="{$runJobName|escape:'htmlall':'UTF-8'}" value="">
                                <div class="retail-form__row retail-form__row_submit"
                                     style="height: 60px; margin-bottom: 20px; margin-top: 50px; clear:both;">
                                    <button id="update-icml-submit"
                                            class="btn btn_invert btn_warning"
                                            style="outline: none;{if !$showUpdateButton} display: none;{/if}">{l s='Update URL' mod='retailcrm'}</button>
                                    <button id="generate-icml-submit"
                                            class="btn btn_invert btn_submit"
                                            style="outline: none;{if $showUpdateButton} display: none;{/if}">{l s='Generate now' mod='retailcrm'}</button>
                                </div>
                    </div>
                    <div id="rcrm_tab_delivery_types">
                        <div class="retail-form__title">
                            {l s='Delivery' mod='retailcrm'}
                            <a href="{$url|cat:'/admin/delivery-types'|escape:'htmlall':'UTF-8'}" target="_blank" class="retail-form__title_link">{l s='Manage delivery types' mod='retailcrm'}</a>
                        </div>
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
                        <div class="retail-form__title">
                            {l s='Order statuses' mod='retailcrm'}
                            <a href="{$url|cat:'/admin/statuses'|escape:'htmlall':'UTF-8'}" target="_blank" class="retail-form__title_link">{l s='Manage order statuses' mod='retailcrm'}</a>
                        </div>
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
                        <div class="retail-form__title">
                            {l s='Payment types' mod='retailcrm'}
                            <a href="{$url|cat:'/admin/payment-types'|escape:'htmlall':'UTF-8'}" target="_blank" class="retail-form__title_link">{l s='Manage payment types' mod='retailcrm'}</a>
                        </div>
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
                    <div id="rcrm_tab_stock">
                        <div class="retail-form__title">
                            {l s='Stock settings' mod='retailcrm'}
                        </div>
                        <div class="retail-form__row">
                            <div class="retail-form__checkbox">
                                <input type="checkbox" name="{$enableBalancesReceivingName|escape:'htmlall':'UTF-8'}" value="{$enableBalancesReceiving|escape:'htmlall':'UTF-8'}" {if $enableBalancesReceiving}checked="checked"{/if} id="receivebalances-active">
                                <label for="receivebalances-active">{l s='Receive stocks from Simla.com' mod='retailcrm'}</label>
                            </div>
                        </div>
                        <div class="retail-form__title" style="margin-top: 40px;">
                            {l s='Out of stock' mod='retailcrm'}
                        </div>
                        <div class="retail-form__label">
                            {l s='Changing of order status if the product is out of stock and its ordering with zero stock is denied.' mod='retailcrm'}
                        </div>
                        {foreach from=$outOfStockStatuses item=item}
                            <div class="retail-form__row">
                                <label class="retail-form__label" for="{$item.name|escape:'htmlall':'UTF-8'}">{l s=$item.label mod='retailcrm'}</label>
                                <select placeholder="{l s='Choose status' mod='retailcrm'}" class="jq-select" name="{$item.name|escape:'htmlall':'UTF-8'}" id="{$item.name|escape:'htmlall':'UTF-8'}">
                                    {foreach from=$item.options.query item=option}
                                        <option value="{$option.id_option|escape:'htmlall':'UTF-8'}"{if isset($outOfStockStatus[$item.subname]) && $outOfStockStatus[$item.subname] == $option.id_option} selected{/if}>{$option.name|escape:'htmlall':'UTF-8'}</option>
                                    {/foreach}
                                </select>
                            </div>
                        {/foreach}
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

                <div id="rcrm_tab_orders_upload">
                    <div class="retail-container--foldable">
                        <div class="retail-row--foldable active">
                            <div class="retail-form__title retail-row__title">{l s='Upload orders' mod='retailcrm'}</div>
                            <div class="retail-form__row retail-row__content">
                                <div class="retail-form__label">{l s='Enter order IDs to upload to Simla.com, divided by a comma. You can also specify ranges, like "1-10". It\'s allowed to upload to 10 orders at a time.' mod='retailcrm'}</div>

                                <form class="rcrm-form-submit-trigger"
                                      action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm"
                                      method="post"
                                >
                                    <input type="hidden" name="submitretailcrm" value="1"/>

                                    <div class="retail-form__row">
                                        <input type="text" name="{$uploadOrders|escape:'htmlall':'UTF-8'}" value=""
                                               class="retail-form__area"
                                               placeholder="{l s='Orders IDs' mod='retailcrm'}">
                                    </div>
                                    <div class="retail-form__row retail-form__row_submit">
                                        <button id="upload-orders-submit" class="btn btn_invert btn_submit"
                                                style="outline: none;">{l s='Upload' mod='retailcrm'}</button>
                                    </div>
                                </form>
                                <div class="retail-form__label">{l s='You can export all the orders and customers from CMS to Simla.com by pressing "Export" button. This process can take a long time, and it\'s required that you keep the tab opened until it\'s done.' mod='retailcrm'}</div>

                                <form class="rcrm-form-submit-trigger"
                                      action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm"
                                      method="post"
                                >
                                    <div class="retail-form__row">
                                        <div class="retail-circle">
                                            <div class="retail-circle__title">{l s='Orders' mod='retailcrm'}</div>
                                            <input type="text" name="RETAILCRM_EXPORT_ORDERS_COUNT" readonly="readonly"
                                                   class="retail-circle__content"
                                                   value="{$ordersCount|escape:'htmlall':'UTF-8'}"/>
                                            <input type="hidden" name="RETAILCRM_EXPORT_ORDERS_STEP_SIZE"
                                                   value="{$exportOrdersStepSize|escape:'htmlall':'UTF-8'}"/>
                                        </div>
                                        <div class="retail-circle">
                                            <div class="retail-circle__title">
                                                {l s='Customers' mod='retailcrm'}
                                            </div>
                                            <input type="text" readonly="readonly"
                                                   title="{l s='Customers without orders' mod='retailcrm'}: {$exportCustomersCount|escape:'htmlall':'UTF-8'}"
                                                   class="retail-circle__content"
                                                   value="{$customersCount|escape:'htmlall':'UTF-8'}"/>
                                            <input type="hidden" name="RETAILCRM_EXPORT_CUSTOMERS_COUNT"
                                                   value="{$exportCustomersCount|escape:'htmlall':'UTF-8'}"/>
                                            <input type="hidden" name="RETAILCRM_EXPORT_CUSTOMERS_STEP_SIZE"
                                                   value="{$exportCustomersStepSize|escape:'htmlall':'UTF-8'}"/>
                                        </div>
                                    </div>
                                    <div class="retail-form__row retail-form__row_submit"
                                         style="text-align: center; height: 60px; margin-bottom: 20px; clear:both;">
                                        <button id="export-orders-submit" class="btn btn_invert btn_submit"
                                                style="outline: none;">{l s='Export' mod='retailcrm'}</button>
                                        <div id="export-orders-progress" class="retail-progress retail-hidden"></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="retail-row--foldable">
                            <div class="retail-form__title retail-row__title">{l s='Uploaded orders' mod='retailcrm'}</div>
                            <div class="retail-form__row retail-row__content">
                                <div class="retail-form__label">{l s='In this section you can check the orders export results and manualy upload order to' mod='retailcrm'} {$systemName|escape:'htmlall':'UTF-8'}</div>
                                <div class="retail-form__row">
                                    <form action="{$retailControllerOrders|escape:'htmlall':'UTF-8'}"
                                          id="retail-search-orders-form" method="GET">
                                        <input type="text" name="search-orders-value" value=""
                                               class="retail-form__area"
                                               placeholder="{l s='Orders IDs' mod='retailcrm'}">

                                        <button id="search-orders-submit" class="btn"
                                                style="outline: none;">{l s='Search' mod='retailcrm'}</button>

                                        <div class="retail-table-filter">
                                            <label for="retail-table-filter-status-all"
                                                   class="retail-table-filter-btn">
                                                <span>{l s='All' mod='retailcrm'}</span>
                                                <input type="radio" name="search-orders-filter"
                                                       id="retail-table-filter-status-all"
                                                       value="0" class="search-orders-filter">
                                            </label>
                                            <label for="retail-table-filter-status-ok"
                                                   class="retail-table-filter-btn">
                                                <span>{l s='Uploaded' mod='retailcrm'}</span>
                                                <input type="radio" name="search-orders-filter"
                                                       id="retail-table-filter-status-ok"
                                                       value="1" class="search-orders-filter">
                                            </label>
                                            <label for="retail-table-filter-status-fail"
                                                   class="retail-table-filter-btn">
                                                <span>{l s='Error' mod='retailcrm'}</span>
                                                <input type="radio" name="search-orders-filter"
                                                       id="retail-table-filter-status-fail"
                                                       value="2" class="search-orders-filter">
                                            </label>
                                        </div>
                                    </form>
                                </div>
                                <div class="retail-form__row retail-table-pagination"></div>
                                <div class="retail-table-wrapper">
                                    <a href="{$retailControllerOrdersUpload|escape:'htmlall':'UTF-8'}"
                                       id="retail-controller-orders-upload" class="retail-controller-link"></a>
                                    <table class="retail-table retail-table-sort retail-table-center"
                                           id="retail-orders-table">
                                        <thead>
                                        <tr>
                                            <th><span>{l s='Date and Time' mod='retailcrm'}</span></th>
                                            <th><span>{l s='ID in' mod='retailcrm'} Prestashop</span></th>
                                            <th>
                                                <span>{l s='ID in' mod='retailcrm'} {$systemName|escape:'htmlall':'UTF-8'}</span>
                                            </th>
                                            <th><span>{l s='Status' mod='retailcrm'}</span></th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td class="retail-orders-table__date"></td>
                                            <td class="retail-orders-table__id-cms">
                                                <a href="{$adminControllerOrders|escape:'htmlall':'UTF-8'}"
                                                   target="_blank"></a>
                                            </td>
                                            <td class="retail-orders-table__id-crm">
                                                <a href="{$url|cat:'/orders/'|escape:'htmlall':'UTF-8'}"
                                                   target="_blank"></a>
                                            </td>
                                            <td class="retail-orders-table__status">
                                                <p class="retail-orders-table__status--success">
                                                    <span style="color: #2e8b57;">&#10004;</span>
                                                    {l s='Uploaded' mod='retailcrm'}
                                                </p>
                                                <p class="retail-orders-table__status--error">
                                                    <input type="checkbox" class="retail-collapsible__input">
                                                    <label class="retail-collapsible__title">
                                                        <span style="color: #dd2e44;">&#10060;</span>
                                                        {l s='Error' mod='retailcrm'}
                                                        <span class="retail-orders-table__error retail-collapsible__content"></span>
                                                    </label>
                                                </p>
                                            </td>
                                            <td class="retail-table-center retail-orders-table__upload">
                                                <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                     xmlns:xlink="http://www.w3.org/1999/xlink" x="0px"
                                                     y="0px" viewBox="0 0 29.978 29.978"
                                                     xml:space="preserve"
                                                     class="retail-btn-svg"
                                                >
                                                    <g>
                                                        <path d="M25.462,19.105v6.848H4.515v-6.848H0.489v8.861c0,1.111,0.9,2.012,2.016,2.012h24.967c1.115,0,2.016-0.9,2.016-2.012   v-8.861H25.462z"/>
                                                        <path d="M14.62,18.426l-5.764-6.965c0,0-0.877-0.828,0.074-0.828s3.248,0,3.248,0s0-0.557,0-1.416c0-2.449,0-6.906,0-8.723   c0,0-0.129-0.494,0.615-0.494c0.75,0,4.035,0,4.572,0c0.536,0,0.524,0.416,0.524,0.416c0,1.762,0,6.373,0,8.742   c0,0.768,0,1.266,0,1.266s1.842,0,2.998,0c1.154,0,0.285,0.867,0.285,0.867s-4.904,6.51-5.588,7.193   C15.092,18.979,14.62,18.426,14.62,18.426z"/>
                                                    </g>
                                                </svg>
                                                {l s='Upload' mod='retailcrm'}
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="rcrm_tab_consultant">
                    <form class="rcrm-form-submit-trigger"
                          action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&item=consultant"
                          method="post">
                        <input type="hidden" name="submitretailcrm" value="1"/>
                        <div class="retail-form__title">{l s='Online consultant' mod='retailcrm'}</div>
                        <div class="retail-form__row">
                        <textarea name="{$consultantScriptName|escape:'htmlall':'UTF-8'}"
                                  class="retail-form__area retail-form__area_txt" id="retail-txt-area"
                                  placeholder="{l s='Code you need to insert on the web' mod='retailcrm'}">
                            {$consultantScript|escape:'htmlall':'UTF-8'}
                        </textarea>
                        </div>
                        <div class="retail-form__row retail-form__row_submit">
                            <input type="submit" value="{l s='Save' mod='retailcrm'}" class="btn btn_invert btn_submit">
                        </div>
                    </form>
                </div>

                <div id="rcrm_tab_advanced">
                    <div class="retail-form__title">{l s='Advanced' mod='retailcrm'}</div>
                    <div class="retail-form__row">
                        <div class="retail-form__checkbox">
                            <input form="submitretailcrm-form" type="checkbox"
                                   name="{$debugModeName|escape:'htmlall':'UTF-8'}"
                                   value="{$debugMode|escape:'htmlall':'UTF-8'}"
                                   {if $debugMode}checked="checked"{/if} id="debugmode-active">
                            <label for="debugmode-active"
                                   class="retail-form__label">{l s='Debug mode' mod='retailcrm'}</label>
                        </div>
                        <div class="retail-form__checkbox">
                            <input form="submitretailcrm-form" type="checkbox"
                                   name="{$webJobsName|escape:'htmlall':'UTF-8'}"
                                   value="{$webJobs|escape:'htmlall':'UTF-8'}"
                                   {if $webJobs}checked="checked"{/if} id="webjobs-active">
                            <label for="webjobs-active"
                                   class="retail-form__label">{l s='Web Jobs' mod='retailcrm'}</label>
                        </div>
                    </div>

                    <div class="retail-form__row">
                        <input form="submitretailcrm-form" type="submit" value="{l s='Save' mod='retailcrm'}" class="btn btn_invert btn_submit">
                    </div>

                    <div class="retail-form__row">
                        <label class="retail-form__label">{l s='Job Manager' mod='retailcrm'}</label>
                        <div class="retail-table-wrapper">
                            <table class="retail-table retail-table-sort">
                                <thead>
                                <tr>
                                    <th>
                                        <span>{l s='Job name' mod='retailcrm'}</span></th>
                                    <th>
                                        <div class="retail-table-sort__btn-wrap">
                                            <span class="retail-table-sort__asc retail-table-sort__btn">&#x25B2</span>
                                            <span class="retail-table-sort__desc retail-table-sort__btn retail-table-sort__initial">&#x25BC</span>
                                        </div>
                                        <span class="retail-table-sort__switch">{l s='Last Run' mod='retailcrm'}</span>
                                    </th>
                                    <th>
                                        <span>{l s='Status' mod='retailcrm'}</span></th>
                                    <th>
                                        <span>{l s='Comment' mod='retailcrm'}</span></th>
                                </tr>
                                </thead>
                                <tbody>
                                {foreach from=$lastRunDetails key=key item=item}
                                    <tr class="{if $key === $currentJob || $key === $currentJobCli} retail-table__row-bold{/if}">
                                        <td>
                                            {if isset($jobsNames[$key]) }
                                                <span title="{$key|escape:'htmlall':'UTF-8'}">{l s=$jobsNames[$key] mod='retailcrm'}</span>
                                            {else}
                                                {$key|escape:'htmlall':'UTF-8'}
                                            {/if}
                                        </td>
                                        <td class="retail-table-center retail-table-no-wrap">{if isset($item['lastRun'])}{$item['lastRun']|date_format:'Y-m-d H:i:s'|escape:'htmlall':'UTF-8'}{/if}</td>
                                        <td class="retail-table-center">
                                            {if $key === $currentJob || $key === $currentJobCli}
                                                <span>&#8987;</span>
                                            {else}
                                                {if isset($item['success'])}
                                                    {if $item['success'] === true}
                                                        <span style="color: #2e8b57;">&#10004;</span>
                                                    {else}
                                                        <span style="color: #dd2e44;">&#10060;</span>
                                                    {/if}
                                                {/if}
                                            {/if}
                                        </td>
                                        <td class="retail-collapsible">
                                            {if isset($item['error']['message'])}
                                                <input type="checkbox" class="retail-collapsible__input"
                                                       id="error_{$key|escape:'htmlall':'UTF-8'}">
                                                <label for="error_{$key|escape:'htmlall':'UTF-8'}"
                                                       class="retail-collapsible__title retail-error-msg">
                                                    <span class="retail-error-msg">{$item['error']['message']|escape:'htmlall':'UTF-8'}</span>
                                                    <p class="retail-collapsible__content">
                                                        <b>{l s='StackTrace' mod='retailcrm'}
                                                            :</b><br>{$item['error']['trace']|escape:'htmlall':'UTF-8'}
                                                    </p>
                                                </label>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="retail-form__row retail-form__row_submit">
                        <form class="rcrm-form-submit-trigger"
                              action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&amp;ajax=1"
                              method="post">
                            <input type="submit" id="reset-jobs-submit" class="btn btn_submit"
                                   value="{l s='Reset jobs' mod='retailcrm'}"/>
                        </form>
                    </div>

                    <div class="retail-form__row">
                        <label class="retail-form__label">{l s='Logs' mod='retailcrm'}</label>
                        <div class="retail-table-wrapper">
                            <table class="retail-table retail-table-sort">
                                <thead>
                                <tr>
                                    <th><span>{l s='File name' mod='retailcrm'}</span></th>
                                    <th>
                                        <div class="retail-table-sort__btn-wrap">
                                            <span class="retail-table-sort__asc retail-table-sort__btn">&#x25B2</span>
                                            <span class="retail-table-sort__desc retail-table-sort__btn retail-table-sort__initial">&#x25BC</span>
                                        </div>
                                        <span class="retail-table-sort__switch">{l s='Modified date' mod='retailcrm'}</span>
                                    </th>
                                    <th>
                                        <div class="retail-table-sort__btn-wrap">
                                            <span class="retail-table-sort__asc retail-table-sort__btn">&#x25B2</span>
                                            <span class="retail-table-sort__desc retail-table-sort__btn">&#x25BC</span>
                                        </div>
                                        <span class="retail-table-sort__switch">{l s='Size' mod='retailcrm'}</span>
                                    </th>
                                    <th><span>{l s='Actions' mod='retailcrm'}</span></th>
                                </tr>
                                </thead>
                                <tbody>
                                {foreach from=$retailcrmLogsInfo key=key item=logItem}
                                    <tr class="retail-table__row-top">
                                        <td>{$logItem.name|escape:'htmlall':'UTF-8'}</td>
                                        <td class="retail-table-center">{$logItem.modified|escape:'htmlall':'UTF-8'}</td>
                                        <td class="retail-table-center">{$logItem.size|escape:'htmlall':'UTF-8'}</td>
                                        <td class="retail-table-center">
                                            <form class="rcrm-form-submit-trigger"
                                                  action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&amp;ajax=1"
                                                  method="post">
                                                <input type="hidden" name="submitretailcrm" value="1"/>
                                                <input type="hidden" name="RETAILCRM_DOWNLOAD_LOGS" value="1"/>
                                                <input type="hidden" name="RETAILCRM_DOWNLOAD_LOGS_NAME"
                                                       value="{$logItem.name|escape:'htmlall':'UTF-8'}"/>
                                                <input type="submit" id="download-log-{$key|escape:'htmlall':'UTF-8'}" style="display: none;"/>
                                                <label for="download-log-{$key|escape:'htmlall':'UTF-8'}"
                                                       style="width: 100%; text-align: center;"
                                                       class="retail-btn-svg_wrapper"
                                                       title=" {l s='Download' mod='retailcrm'}"
                                                >
                                                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                         xmlns:xlink="http://www.w3.org/1999/xlink" x="0px"
                                                         y="0px" viewBox="0 0 29.978 29.978"
                                                         xml:space="preserve"
                                                         class="retail-btn-svg"
                                                    >
                                                    <g>
                                                        <path d="M25.462,19.105v6.848H4.515v-6.848H0.489v8.861c0,1.111,0.9,2.012,2.016,2.012h24.967c1.115,0,2.016-0.9,2.016-2.012   v-8.861H25.462z"/>
                                                        <path d="M14.62,18.426l-5.764-6.965c0,0-0.877-0.828,0.074-0.828s3.248,0,3.248,0s0-0.557,0-1.416c0-2.449,0-6.906,0-8.723   c0,0-0.129-0.494,0.615-0.494c0.75,0,4.035,0,4.572,0c0.536,0,0.524,0.416,0.524,0.416c0,1.762,0,6.373,0,8.742   c0,0.768,0,1.266,0,1.266s1.842,0,2.998,0c1.154,0,0.285,0.867,0.285,0.867s-4.904,6.51-5.588,7.193   C15.092,18.979,14.62,18.426,14.62,18.426z"/>
                                                    </g>
                                                    </svg>
                                                    {l s='Download' mod='retailcrm'}
                                                </label>
                                            </form>
                                        </td>
                                    </tr>
                                {/foreach}
                                </tbody>
                            </table>
                        </div>
                        <div class="retail-form__row retail-form__row_submit">
                            <form class="rcrm-form-submit-trigger"
                                  action="{$url_post|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm&amp;ajax=1"
                                  method="post">
                                <input type="hidden" name="submitretailcrm" value="1"/>
                                <input type="hidden" name="RETAILCRM_DOWNLOAD_LOGS" value="1"/>
                                <button type="submit" id="download-log-all"  class="btn btn_submit">
                                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                         xmlns:xlink="http://www.w3.org/1999/xlink" x="0px"
                                         y="0px" viewBox="0 0 29.978 29.978"
                                         style="width: 20px; fill: #0068FF;" xml:space="preserve">
                                        <g>
                                            <path d="M25.462,19.105v6.848H4.515v-6.848H0.489v8.861c0,1.111,0.9,2.012,2.016,2.012h24.967c1.115,0,2.016-0.9,2.016-2.012   v-8.861H25.462z"/>
                                            <path d="M14.62,18.426l-5.764-6.965c0,0-0.877-0.828,0.074-0.828s3.248,0,3.248,0s0-0.557,0-1.416c0-2.449,0-6.906,0-8.723   c0,0-0.129-0.494,0.615-0.494c0.75,0,4.035,0,4.572,0c0.536,0,0.524,0.416,0.524,0.416c0,1.762,0,6.373,0,8.742   c0,0.768,0,1.266,0,1.266s1.842,0,2.998,0c1.154,0,0.285,0.867,0.285,0.867s-4.904,6.51-5.588,7.193   C15.092,18.979,14.62,18.426,14.62,18.426z"/>
                                        </g>
                                    </svg>
                                    {l s='Download All' mod='retailcrm'}
                                </button>
                            </form>
                        </div>
                    </div>
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
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm-orders.min.js"></script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm-icml.min.js"></script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm-export.min.js"></script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm-advanced.min.js"></script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm.min.js"></script>
