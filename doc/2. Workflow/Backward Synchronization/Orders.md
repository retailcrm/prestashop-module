# Orders

## Create

### Items

### Delivery

### Addresses

Для адреса оплаты `id_address_invoice`, если заказ сделан обычным клиентом, используются данные
клиента `order[customer]`

|  CRM field / value                        | CMS field                 |
|-------------------------------------------|---------------------------|
|  default                                  |  `alias`                  |
|  `order[customer][firstName]`             |  `firstname`              |
|  `order[customer][lastName]`              |  `lastname`               |
|  `order[customer][phones][0][number]`     |  `phone`                  |
|  `order[customer][address][text]`         |  `address1`, `address2`   |
|  `order[customer][address][countryIso]`   |  `id_country`             |
|  `order[customer][address][city]`         |  `city`                   |
|  `order[customer][address][index]`        |  `postcode`               |
|  `order[customer][address][region]`       |  `id_state`               |

Если заказ сделан корпоративным клиентом и опция "Корпоративные клиенты" включена, то используются данные
контакта `order[contact]`, а также указываются дополнительные поля — ИНН и название компании.

|  CRM field / value                        | CMS field                 |
|-------------------------------------------|---------------------------|
|   --                                      |  `alias`                  |
|  `order[contact][firstName]`              |  `firstname`              |
|  `order[contact][lastName]`               |  `lastname`               |
|  `order[contact][phones][0][number]`      |  `phone`                  |
|  `order[company][address][text]`          |  `address1`, `address2`   |
|  `order[company][address][countryIso]`    |  `id_country`             |
|  `order[company][address][city]`          |  `city`                   |
|  `order[company][address][index]`         |  `postcode`               |
|  `order[company][address][region]`        |  `id_state`               |
|  `order[company][contragent][INN]`        |  `vat_number`             |
|  `order[company][name]`                   |  `company`                |

Для адреса доставки (`id_address_delivery`) используются данные заказа.

|  CRM field / value                        | CMS field                 |
|-------------------------------------------|---------------------------|
|  default                                  |  `alias`                  |
|  `order[firstName]`                       |  `firstname`              |
|  `order[lastName]`                        |  `lastname`               |
|  `order[phone]`                           |  `phone`                  |
|  `order[delivery][address][text]`         |  `address1`, `address2`   |
|  `order[delivery][address][countryIso]`   |  `id_country`             |
|  `order[delivery][address][city]`         |  `city`                   |
|  `order[delivery][address][index]`        |  `postcode`               |
|  `order[delivery][address][region]`       |  `id_state`               |

После сборки объекта адреса выполняется проверка на валидность (_RetailcrmTools::validateEntity()_). Если адрес валиден,
то выполняется проверка наличия такого же адреса в CMS (_RetailcrmTools::assignAddressIdsByFields()_) и если адрес
найден, то берется его id (позволяет не создавать дубль адреса). В итоге, если id не был найден, то создается новый
адрес, иначе — сохраняется и используется существующий

### Payments

## Update

Обновление данных адреса в CMS происходит, если в заказе было изменено одно из следующих полей:

* `order[firstName]`
* `order[lastName]`
* `order[delivery][address]`
* `order[phone]`

Если были изменены поля адреса, на основе которых формируется `address1` и `address2` поля заказа, то делается запрос в
CRM для получения полной информации по заказу (проверка осуществляется в
функции `RetailcrmHistoryHelper::isAddressLineChanged()`). При этом данные, полученные по истории перезаписывают данные,
полученные по конкретному заказу — это сделано для сохранения возможности кастомизировать поля в фильтре.

Далее в зависимости от версии CMS возможны 2 варианта:

* На версии < 1.7.7 - происходит создание нового объекта адреса, после чего он присваивается заказу
* На версии \>= 1.7.7 - обновляются данные текущего адреса заказа

## Delete

При удалении заказа из Crm в CMS ничего не изменится
