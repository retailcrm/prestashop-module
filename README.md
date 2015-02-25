Prestashop module
=============

Prestashop module for interaction with [IntaroCRM](http://www.intarocrm.com) through [REST API](http://docs.intarocrm.ru/rest-api/).

Module allows:

* Send to IntaroCRM new orders
* Configure relations between dictionaries of IntaroCRM and Prestashop (statuses, payments, delivery types and etc)
* Generate [ICML](http://docs.intarocrm.ru/index.php?n=Пользователи.ФорматICML) (IntaroCRM Markup Language) for catalog loading by IntaroCRM

Installation
-------------

### 1. Manual installation


#### Clone module.
```
git clone git@github.com:/intarocrm/prestashop-module.git
```

#### Install Rest API Client.

Install api-client-php via [composer](http://getcomposer.org)

```
cd prestashop-module
/path/to/composer.phar install
```

#### Create .zip file.
```
zip -r intarocrm.zip intarocrm
```

#### Install via Admin interface.


Go to Modules -> Add module. After that upload your zipped module and activate it.
