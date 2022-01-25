# Examples

- [Classes](#classes)  
  - [Prices with discounts to icml](#prices-with-discounts-to-icml)
- [Filters](#filters)  

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
### ...
...

Put code to  `...`:
```php
<...>
code
<...>
```
