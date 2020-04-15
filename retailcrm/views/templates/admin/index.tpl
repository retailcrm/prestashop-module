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
<link rel="stylesheet" href="{$assets|escape:'htmlall':'UTF-8'}/css/styles.min.css">
<title>retailCRM</title>
<div class="retail retail-wrap">
    {include file='./module_messages.tpl'}
    <div class="retail-container">
        <h1 class="retail-title">retailCRM</h1>
        <div class="retail-txt retail-descript">
            {l s='retailCRM is a service for online stores that can prevent you from losing orders and increase the income at all stages of the funnel.' mod='retailcrm'}
        </div>
        <div class="retail-video">
            <img src="{$assets|escape:'htmlall':'UTF-8'}/img/video-bg.jpg" alt="" />
            <div class="retail-video__btn" data-popup="#video-popup">
                <svg width="101" height="101" viewBox="0 0 101 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M50.7188 0.46875C59.5897 0.46875 68.0575 2.8881 75.7188 7.32359C83.38 11.7591 89.4284 17.8075 93.8639 25.4688C98.2994 33.13 100.719 41.5978 100.719 50.4688C100.719 59.5413 98.2994 67.8075 93.8639 75.4688C89.4284 83.13 83.38 89.38 75.7188 93.8155C68.0575 98.251 59.5897 100.469 50.7188 100.469C41.6462 100.469 33.38 98.251 25.7188 93.8155C18.0575 89.38 11.8075 83.13 7.37198 75.4688C2.93649 67.8075 0.71875 59.5413 0.71875 50.4688C0.71875 41.5978 2.93649 33.13 7.37198 25.4688C11.8075 17.8075 18.0575 11.7591 25.7188 7.32359C33.38 2.8881 41.6462 0.46875 50.7188 0.46875ZM74.1059 55.3075C75.7188 54.501 76.5252 53.0897 76.5252 51.0736C76.5252 49.2591 75.7188 47.8478 74.1059 46.8397L38.622 25.2671C37.0091 24.4607 35.3962 24.4607 33.7833 25.2671C32.1704 26.2752 31.3639 27.6865 31.3639 29.501V71.4365C31.3639 73.4526 32.1704 74.8639 33.7833 75.6704C35.3962 76.6784 37.0091 76.6784 38.622 75.6704L74.1059 55.3075Z" fill="black"/>
                </svg>
            </div>
        </div>
        <div class="retail-btns">
            <a href="#toggle-form" class="btn btn_max toggle-btn">{l s='I have an account in retailCRM' mod='retailcrm'}</a>
            <div class="retail-btns__separate">{l s='or' mod='retailcrm'}</div>
            <a href="{$registerUrl|escape:'htmlall':'UTF-8'}" target="_black" class="btn btn_max btn_invert">{l s='Get retailCRM for free' mod='retailcrm'}</a>
        </div>
        <div class="retail-form toggle-box" id="toggle-form">
            <form action="{$current|escape:'htmlall':'UTF-8'}&amp;token={$token|escape:'htmlall':'UTF-8'}&amp;configure=retailcrm" method="post">
                <input type="hidden" name="submitretailcrm" value="1" />
                <div class="retail-form__title">{l s='Connection Settings' mod='retailcrm'}</div>
                <div class="retail-form__row">
                    <input required type="text" class="retail-form__area" placeholder="{l s='retailCRM URL' mod='retailcrm'}" name="{$apiUrl|escape:'htmlall':'UTF-8'}">
                </div>
                <div class="retail-form__row">
                    <input required type="text" class="retail-form__area" placeholder="{l s='API key' mod='retailcrm'}" name="{$apiKey|escape:'htmlall':'UTF-8'  }">
                </div>
                <div class="retail-form__row retail-form__row_submit">
                    <input type="submit" value="{l s='Save' mod='retailcrm'}" class="btn btn_invert btn_submit">
                </div>
            </form>
        </div>
        <div class="retail-tabs">
            <div class="retail-tabs__head">
                <a href="#descript" class="retail-tabs__btn retail-tabs__btn_active">{l s='Description' mod='retailcrm'}</a>
                <a href="#faq" class="retail-tabs__btn">FAQ</a>
                <a href="#contacts" class="retail-tabs__btn">{l s='Contacts' mod='retailcrm'}</a>
            </div>
            <div class="retail-tabs__body">
                <div class="retail-tabs__item retail-tabs__item_active" id="descript" style="display: block;">
                    <p>
                        {l s='retailCRM is a service for online stores that can prevent you from losing orders and increase the income at all stages of the funnel.' mod='retailcrm'}
                    </p>
                    <p>
                        {l s='Stop losing leads:' mod='retailcrm'}
                    </p>
                    <ul class="retail-list">
                        <li class="retail-list__item">{l s='LiveChat with active involvement will help you to get more orders from the website' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Chatbots and a single Inbox for Facebook Messengers and WhatsApp prevent you from losing hot leads, who are ready to buy' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Welcome chains warm up your leads and encourage them to make their first purchase' mod='retailcrm'}</li>
                    </ul>
                    <p>
                        {l s='Bring the orders to payment:' mod='retailcrm'}
                    </p>
                    <ul class="retail-list">
                        <li class="retail-list__item">{l s='Up-sales raise the average bill of your orders automatically' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='The abandoned basket scripts increase the number of paid orders' mod='retailcrm'}</li>
                    </ul>
                    <p>
                        {l s='Manage order fulfillment process:' mod='retailcrm'}
                    </p>
                    <ul class="retail-list">
                        <li class="retail-list__item">{l s='CRM-system helps to receive orders, distribute them among employees, manage their statuses and fulfill them' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Notifications about the status of the order automatically inform the customer about every step of his order ' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='SalesApp is an application for retail outlets that helps you to increase offline sales and builds a customer base in a single system' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Integration with the catalog helps to take into account the balances, prices and location of goods' mod='retailcrm'}</li>
                    </ul>
                    <p>
                        {l s='Make your current customers stay with you:' mod='retailcrm'}
                    </p>
                    <ul class="retail-list">
                        <li class="retail-list__item">{l s='CDP (Customer Data Platform) combines the data of your customers from different sources and builds a 360° profile' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Segments help to divide your base into small groups to make your communications more relevant.' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Email, SMS, WhatsApp and Facebook messenger newsletters increase the frequency of purchases in your customer base' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Script "Frequently used goods" helps to automatically remind you to replenish stocks' mod='retailcrm'}</li>
                    </ul>
                    <p>
                        {l s='Make your customers come back:' mod='retailcrm'}
                    </p>
                    <ul class="retail-list">
                        <li class="retail-list__item">{l s='CRM-remarketing helps to launch ads using retailCRM segments' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Abandoned viewing saves the goods that the client looked at the website and offers to pay for them' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Reactivation campaigns make lost customers come back to your store' mod='retailcrm'}</li>
                    </ul>
                    <p>
                        {l s='retailCRM increases the effectiveness of all your marketing channels:' mod='retailcrm'}
                    </p>
                    <ul class="retail-list">
                        <li class="retail-list__item">{l s='LiveChat' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Email' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Facebook Messenger' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='SMS' mod='retailcrm'}</li>
                        <li class="retail-list__item">{l s='Retargeting' mod='retailcrm'}</li>
                    </ul>
                </div>
                <div class="retail-tabs__item" id="faq">
                    <div class="retail-tile">
                        <div class="retail-tile__col">
                            <div class="retail-tile__item">
                                <div class="retail-tile__title">{l s='Is there a trial of the module?' mod='retailcrm'}</div>
                                <div class="retail-tile__descript">{l s='The module has a 14-day trial version in which you can work with the help of the retailCRM module.' mod='retailcrm'}</div>
                            </div>
                            <div class="retail-tile__item">
                                <div class="retail-tile__title">{l s='What is a user?' mod='retailcrm'}</div>
                                <div class="retail-tile__descript">{l s='A user is the person who will work with the retailCRM module as the representative of your business or your website. Each user can create a personal profile and have their own access to the tool panel.' mod='retailcrm'}</div>
                            </div>
                            <div class="retail-tile__item">
                                <div class="retail-tile__title">{l s='In what languages is the module available?' mod='retailcrm'}</div>
                                <div class="retail-tile__descript">{l s='The retailCRM module is available in the following languages:' mod='retailcrm'}
                                    <ul class="retail-list">
                                        <li class="retail-list__item">{l s='Spanish' mod='retailcrm'}</li>
                                        <li class="retail-list__item">{l s='English' mod='retailcrm'}</li>
                                        <li class="retail-list__item">{l s='Russian' mod='retailcrm'}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="retail-tile__col">
                            <div class="retail-tile__item">
                                <div class="retail-tile__title">{l s='How long is the trial?' mod='retailcrm'}</div>
                                <div class="retail-tile__descript">{l s='The duration of the trial version of the retailCRM module is 14 days.' mod='retailcrm'}</div>
                            </div>
                            <div class="retail-tile__item">
                                <div class="retail-tile__title">{l s='Is it paid per user or is it paid per account?' mod='retailcrm'}</div>
                                <div class="retail-tile__descript">{l s='Payment is made per user, if another user is added to the retailCRM system, payment by two users would be made. Each user has the right to an account (web-chat and social networks). In case a user needs to work with more than one account, it is necessary to contact the retailCRM team.' mod='retailcrm'}</div>
                            </div>
                            <div class="retail-tile__item">
                                <div class="retail-tile__title">{l s='How I can pay?' mod='retailcrm'}</div>
                                <div class="retail-tile__descript">
                                    {l s='The methods to make the payment are:' mod='retailcrm'}
                                    <ul class="retail-list">
                                        <li class="retail-list__item">{l s='Wire transfer' mod='retailcrm'}</li>
                                        <li class="retail-list__item">{l s='Credit card' mod='retailcrm'}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="retail-tabs__item" id="contacts">
                    <div class="retail-tile">
                        <div class="retail-tile__col">
                            <div class="retail-tile__item">
                                <div class="retail-tile__title">{l s='Our contacts' mod='retailcrm'}</div>
                                <div class="retail-tile__descript">{l s='Write us in case of questions or doubts' mod='retailcrm'}</div>
                            </div>
                        </div>
                        <div class="retail-tile__col retail-tile__col_contacts">
                            <div class="retail-tile__item">
                                <div class="retail-tile__row">
                                    <a href="mailto:{$supportEmail|escape:'htmlall':'UTF-8'}" class="retail-tile__link">{$supportEmail|escape:'htmlall':'UTF-8'}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="retail-popup-wrap js-popup-close">
    <div class="retail-popup" id="video-popup">
        <div class="retail-popup__close js-popup-close"></div>
        <div id="player"></div>
    </div>
</div>
{*<script src="//ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>*}
{*<script>window.jQuery || document.write('<script src="{$assets}/js/vendor/jquery-3.4.0.min.js"><\/script>')</script>*}
<script>window.RCRMPROMO="{$promoVideoUrl|escape:'htmlall':'UTF-8'}";</script>
<script src="{$assets|escape:'htmlall':'UTF-8'}/js/retailcrm.min.js"></script>
