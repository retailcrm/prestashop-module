[![Build Status](https://img.shields.io/travis/retailcrm/prestashop-module/master.svg?style=flat-square)](https://travis-ci.org/retailcrm/prestashop-module)
[![GitHub release](https://img.shields.io/github/release/retailcrm/prestashop-module.svg?style=flat-square)](https://github.com/retailcrm/prestashop-module/releases)
[![PHP version](https://img.shields.io/badge/PHP->=5.3-blue.svg?style=flat-square)](https://php.net/)

Prestashop module
=================

Module allows integrate CMS Prestashop with [retailCRM](http://www.retailcrm.pro)

#### Features:

* Export orders to retailCRM & fetch changes back
* Export product catalog into [ICML](http://www.retailcrm.pro/docs/Developers/ICML) format

#### Setup

* Go to module setup page
* Fill you api url & api key
* Specify directories matching

#### Setting product catalog export

Add to cron:

```
* */4 * * * /usr/bin/php /path/to/your/site/modules/retailcrm/job/icml.php
```

#### Getting changes in orders

Add to cron:

```
*/7 * * * * /usr/bin/php /path/to/your/site/modules/retailcrm/job/sync.php
```

#### Receiving balances from retailCRM

Add to cron:

```
*/15 * * * * /usr/bin/php /path/to/your/site/modules/retailcrm/job/inventories.php
```

#### Single orders archive export to retailCRM

```
/usr/bin/php /path/to/your/site/modules/retailcrm/job/export.php
```
