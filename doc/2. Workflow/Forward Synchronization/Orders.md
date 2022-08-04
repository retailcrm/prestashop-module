# Orders

## Create

## Предотвращение дублирования клиентов

При создании нового заказа в Prestashop происходит поиск клиента в CRM по externalId и по email. Если клиент найден, то в CRM обновляется его адрес и `externalId`.
Для отключения поиска по email необходимо использовать [фильтр](../../3.%20Customization/Filters.md) `RetailcrmFilterFindCustomerByEmail`, который должен возвращать пустой массив `[]`.

## Update
