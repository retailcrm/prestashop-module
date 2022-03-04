# Examples

- [Classes](#classes)
    - [Prices with discounts to icml](#prices-with-discounts-to-icml)
- [Filters](#filters)
    - [Set order custom field on status change](#set-order-custom-field-on-status-change)

## Classes

### Prices with discounts to ICML

Customization for generate ICML catalog with prices including discounts

Put code to  `.../retailcrm/custom/classes/RetailcrmCatalog.php`:

```php
<...>
$price = !empty($product['rate'])
? round($product['price'], 2) + (round($product['price'], 2) * $product['rate'] / 100)
: round($product['price'], 2);

// CUSTOMIZATION
$id_group = 0; // All groups
$id_country = 0; // All countries
$id_currency = 0; // All currencies
$id_shop = Shop::getContextShopID();
$specificPrice = SpecificPrice::getSpecificPrice($product['id_product'], $id_shop, $id_currency, $id_country, $id_group, null);

if (isset($specificPrice['reduction'])) {
    if ($specificPrice['reduction_type'] === 'amount') {
        $price = round($price - $specificPrice['reduction'], 2);
    } elseif ($specificPrice['reduction_type'] === 'percentage') {
        $price = round($price - ($price * $specificPrice['reduction']), 2);
    }
}
// END OF CUSTOMIZATION

if (!empty($product['manufacturer_name'])) {
    $vendor = $product['manufacturer_name'];
} else {
    $vendor = null;
}
<...>
```

## Filters

### Set order custom field on status change

Put code to `custom/filters/RetailcrmFilterOrderStatusUpdate.php`:

```php
<?php
// custom/filters/RetailcrmFilterOrderStatusUpdate.php

class RetailcrmFilterOrderStatusUpdate implements RetailcrmFilterInterface
{
    /**
     * {@inheritDoc}
     */
    public static function filter($object, array $parameters)
    {
        // get data from Order object
        $order = new Order($parameters['id_order']);
        
        $trackingNumbers = [];
        foreach ($order->getShipping() as $shipping) {
            $trackingNumbers[] = $shipping['tracking_number'];
        }
        
        $object['customFields']['tracking'] = implode(', ', $trackingNumbers);

        // get data from the database
        $sql = 'SELECT important_data FROM ' . _DB_PREFIX_ . 'important_table 
                WHERE id_order = ' . pSQL($order->id);

        $data = [];
        foreach (Db::getInstance()->ExecuteS($sql) as $row) {
            $data[] = $row['important_data'];
        }

        $object['customFields']['important_data'] = implode(', ', $data);

        return $object;
    }
}
```
