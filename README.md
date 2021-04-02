[![Build Status](https://github.com/retailcrm/prestashop-module/workflows/presta/badge.svg)](https://github.com/retailcrm/prestashop-module/actions)
[![Coverage](https://img.shields.io/codecov/c/gh/retailcrm/prestashop-module/master.svg?logo=github)](https://codecov.io/gh/retailcrm/prestashop-module)
[![GitHub release](https://img.shields.io/github/release/retailcrm/prestashop-module.svg?logo=codecov)](https://github.com/retailcrm/prestashop-module/releases)
[![PHP version](https://img.shields.io/badge/PHP->=5.4-blue.svg?logo=php)](https://php.net/)

Prestashop module.
=================

Module allows integrate CMS Prestashop with [RetailCRM](https://www.retailcrm.pro) ([Documentation](https://docs.retailcrm.ru/Users/Integration/SiteModules/PrestaShop) page)

#### Features:

* Export orders to RetailCRM & fetch changes back
* Export product catalog into [ICML](https://help.retailcrm.pro/Developers/ICML) format

#### Setup

* Go to module setup page
* Fill you api url & api key
* Specify directories matching

#### Notice

* This release contains an experimental feature "corporate customers". Use at your own risk.
* This release only supports RetailCRM API v5.

#### Customization

If you want to change the default behavior of a module classes and be sure that these changes won't be overwritten during the module upgrade process, you should **copy the original classes** that you are going to customize to the `prestashop-root/modules/retailcrm_custom/classes` directory. 

From here you can modify the methods of the classes for your own purposes, and they will not be affected during the module upgrade process.

Keep in mind that:

* If the logic and classes of the module have changed a lot after an upgrade, your customized logic may cause the module to malfunction. **You should always check for changes after an upgrade and update your customized classes if needed.**
* This feature does not allow to customize the base class (file `retailcrm.php`). For this you can use the standard Prestashop override feature.
