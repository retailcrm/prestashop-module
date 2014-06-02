<?xml version="1.0" encoding="utf-8"?>
<yml_catalog date="{$smarty.now|date_format:'%Y-%m-%d %H:%M'}">
    <shop>
        <name>{$shop_name}</name>
        <company>{$yamarket_company_name}</company>
        <url>{$shop_url}</url>
        <platform>PrestaShop</platform>
        <currencies>
            {foreach from=$currencies item=cur name=currencies}
                {if $cur.iso_code != 'GBP'}
                    <currency id="{if $cur.iso_code == 'RUB'}RUR{else}{$cur.iso_code}{/if}" rate="{math equation="1/x" x=$cur.conversion_rate}"/>
                {/if}
            {/foreach}
        </currencies>
        <categories>
            {foreach from=$categories item=cat name=categories}
                {if $cat.id_category!=2}
                    <category id="{$cat.id_category}"{if $cat.id_parent and $cat.id_parent!=2} parentId="{$cat.id_parent}"{/if}>{$cat.name}</category>
                {/if}
            {/foreach}
        </categories>
        {if $yamarket_delivery_price}<local_delivery_cost>{$yamarket_delivery_price}</local_delivery_cost>{/if}
        <offers>
            {foreach from=$products item=offer name=products}
                <offer available="{if $offer.available_for_order}true{else}false{/if}" id="{$offer.id_product}">
                    <url>{$offer.url}</url>
                    <price>{$offer.price}</price>
                    <purchasePrice>{$offer.purchase_price}</purchasePrice>
                    <currencyId>{$currency}</currencyId>
                    <categoryId>{$offer.id_category_default}</categoryId>
                    <picture>{$offer.picture}</picture>
                    <name>{$offer.name}</name>
                    {if $offer.article}
                    <param name="article">{$offer.article}</param>
                    {/if}
                </offer>
            {/foreach}
        </offers>
    </shop>
</yml_catalog>