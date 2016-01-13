Prestashop module
=================

Модуль интеграции CMS Prestashop c [RetailCRM](http://www.retailcrm.com)

Модуль позволяет:

* Экспортировать в CRM данные о заказах и клиентах и получать обратно изменения по этим данным
* Синхронизировать справочники (способы доставки и оплаты, статусы заказов и т.п.)
* Выгружать каталог товаров в формате [ICML](http://retailcrm.ru/docs/Разработчики/ФорматICML) (IntaroCRM Markup Language)

##Установка

####Скачайте модуль

[Cкачать](http://download.retailcrm.pro/modules/prestashop/retailcrm-2.0.zip)

####Установите через административный интерфейс управления модулями.

![Установка модуля](/docs/images/add.png)


##Настройка

####Перейдите к настройкам

![Настройка модуля](/docs/images/setup.png)

####Введите адрес и API ключ вашей CRM и задайте соответствие справочников

![Справочники](/docs/images/ref.png)


####Регулярная генерация выгрузки каталога

Добавьте в крон запись вида

```
* */4 * * * /usr/bin/php /path/to/your/site/modules/retailcrm/job/icml.php
```

####Регулярное получение изменение из RetailCRM

Добавьте в крон запись вида

```
*/7 * * * * /usr/bin/php /path/to/your/site/modules/retailcrm/job/sync.php
```

####Единоразовая выгрузка архива клиентов и заказов в RetailCRM

```
/usr/bin/php /path/to/your/site/modules/retailcrm/job/export.php
```
