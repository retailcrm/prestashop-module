# Backward Synchronization

Обратная синхронизация — передача данных из CRM в CMS Prestashop. Реализуется в RetailcrmHistory.php посредством запросов к api методам истории. Вызывается в [Job Manager](../CLI%20&%20Job%20Manager/README.md) в команде RetailcrmSyncEvent с интервалом 7 минут

Каждый запрос получения истории изменений сопровождается параметром _sinceId_, Который хранится в конфигурации модуля

1. [Синхронизация данных клиентов](Customers.md)
2. [Синхронизация данных заказов](Orders.md)
3. [Inventories](Inventories.md)