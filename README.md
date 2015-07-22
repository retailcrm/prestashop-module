Prestashop module
=================

Модуль интеграции CMS Prestashop c [RetailCRM](http://www.retailcrm.com)

Модуль позволяет:

* Экспортировать в CRM данные о заказах и клиентах и получать обратно изменения по этим данным
* Синхронизировать справочники (способы доставки и оплаты, статусы заказов и т.п.)
* Выгружать каталог товаров в формате [ICML](http://retailcrm.ru/docs/Разработчики/ФорматICML) (IntaroCRM Markup Language)

Установка
-------------

### 1. Ручная установка


#### Скопируйте модуль
```
git clone git@github.com:/intarocrm/prestashop-module.git
```

#### Создайте загружаемый .zip архив.
```
cd prestashop-module
zip -r retailcrm.zip retailcrm
```

#### Установите через административный интерфейс управления модулями.

![Установка модуля](/docs/images/add.png)

#### Перейдите к настройкам

![Настройка модуля](/docs/images/setup.png)

#### Введите адрес и API ключ вашей CRM и задайте соответствие справочников

![Справочники](/docs/images/ref.png)

