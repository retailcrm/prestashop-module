# Examples

- [Classes](#classes)
    - [Example](#example)
- [Filters](#filters)
    - [Set order custom field on status change](#set-order-custom-field-on-status-change)
    - [Prices with discounts to icml](#prices-with-discounts-to-icml)

## Classes

### Example

```php
```

## Filters

### Set order custom field on status change

```php
<?php
// custom/filters/RetailcrmFilterOrderStatusUpdate.php

class RetailcrmFilterOrderStatusUpdate implements RetailcrmFilterInterface
{
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

### Prices with discounts to ICML

```php
<?php
// custom/filters/RetailcrmFilterProcessOffer.php

class RetailcrmFilterProcessOffer implements RetailcrmFilterInterface
{
    public static function filter($object, array $parameters)
    {
        $product = $parameters['product'];
        $price = $object['price'] ?? null;

        $id_group = 0; // All groups
        $id_country = 0; // All countries
        $id_currency = 0; // All currencies
        $id_shop = Shop::getContextShopID();
        $specificPrice = SpecificPrice::getSpecificPrice($product['id_product'], $id_shop, $id_currency, $id_country, $id_group, null);

        if (isset($specificPrice['reduction'])) {
            if ($specificPrice['reduction_type'] === 'amount') {
                $object['price'] = round($price - $specificPrice['reduction'], 0);
            } elseif ($specificPrice['reduction_type'] === 'percentage') {
                $object['price'] = round($price - ($price * $specificPrice['reduction']), 0);
            }
        }

        return $object;
    }
}
```