Prestashop module
=================

Module allows integrate CMS Prestashop with [RetailCRM](http://www.retailcrm.pro)

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

#### Single orders archive export to RetailCRM

```
/usr/bin/php /path/to/your/site/modules/retailcrm/job/export.php
```
