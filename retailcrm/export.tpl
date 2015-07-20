<?xml version="1.0" encoding="utf-8"?>
<yml_catalog date="{$smarty.now|date_format:'%Y-%m-%d %H:%M'}">
    <shop>
        <name>{$shop_name|escape}</name>
        <company>{$company|escape}</company>
        <url>{$shop_url|escape}</url>
        <categories>
            {foreach from=$categories item=cat name=categories}
                {if $cat.id_category > 2}
                    <category id="{$cat.id_category}"{if $cat.id_parent and $cat.id_parent!=2} parentId="{$cat.id_parent}"{/if}>{$cat.name}</category>
                {/if}
            {/foreach}
        </categories>
        <offers>
            {foreach from=$products item=offer name=products}
                <offer id="{$offer.id_product}" productId="{$offer.id_product}">
                    <productActivity>{if $offer.available_for_order}Y{else}N{/if}</productActivity>
                    <url>{$offer.url|escape}</url>
                    <price>{$offer.price}</price>
                    <purchasePrice>{$offer.purchase_price}</purchasePrice>
                    <categoryId>{$offer.id_category_default}</categoryId>
                    <picture>{$offer.picture|escape}</picture>
                    <name>{$offer.name|escape}</name>
                    {if $offer.article}
                    <param name="article">{$offer.article|escape}</param>
                    {/if}
                </offer>
            {/foreach}
        </offers>
    </shop>
</yml_catalog>
