{**
 * MIT License
 *
 * Copyright (c) 2019 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @copyright 2007-2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 *}
{if isset($moduleErrors) && is_array($moduleErrors) && count($moduleErrors) > 0}
    <div class="bootstrap">
        {foreach from=$moduleErrors item=error}
            <div class="module_error alert alert-danger" >
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {if is_array($error) && count($error) > 0}
                <ul>
                {foreach from=$error item=message}
                    <li>{$message|escape:'htmlall':'UTF-8'}</li>
                {/foreach}
                </ul>
            {else}
                    {$error|escape:'htmlall':'UTF-8'}
            {/if}
            </div>
        {/foreach}
    </div>
{/if}
{if isset($moduleWarnings) && is_array($moduleWarnings) && count($moduleWarnings) > 0}
    <div class="bootstrap">
        {foreach from=$moduleWarnings item=warning}
            <div class="module_warning alert alert-warning" >
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {if is_array($warning) && count($warning) > 0}
                <ul>
                {foreach from=$warning item=message}
                    <li>{$message|escape:'htmlall':'UTF-8'}</li>
                {/foreach}
                </ul>
            {else}
                    {$warning|escape:'htmlall':'UTF-8'}
            {/if}
            </div>
        {/foreach}
    </div>
{/if}
{if isset($moduleConfirmations) && is_array($moduleConfirmations) && count($moduleConfirmations) > 0}
    <div class="bootstrap">
        {foreach from=$moduleConfirmations item=confirm}
            <div class="module_confirmation conf confirm alert alert-success" >
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {if is_array($confirm) && count($confirm) > 0}
                <ul>
                {foreach from=$confirm item=message}
                    <li>{$message|escape:'htmlall':'UTF-8'}</li>
                {/foreach}
                </ul>
            {else}
                    {$confirm|escape:'htmlall':'UTF-8'}
            {/if}
            </div>
        {/foreach}
    </div>
{/if}
{if isset($moduleInfos) && is_array($moduleInfos) && count($moduleInfos) > 0}
    <div class="bootstrap">
        {foreach from=$moduleInfos item=info}
            <div class="module_info info alert alert-info" >
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {if is_array($info) && count($info) > 0}
                <ul>
                {foreach from=$info item=message}
                    <li>{$message|escape:'htmlall':'UTF-8'}</li>
                {/foreach}
                </ul>
            {else}
                    {$info|escape:'htmlall':'UTF-8'}
            {/if}
            </div>
        {/foreach}
    </div>
{/if}
