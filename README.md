[![Build Status](https://github.com/retailcrm/prestashop-module/workflows/presta/badge.svg)](https://github.com/retailcrm/prestashop-module/actions)
[![Coverage](https://img.shields.io/codecov/c/gh/retailcrm/prestashop-module/master.svg?logo=github)](https://codecov.io/gh/retailcrm/prestashop-module)
[![GitHub release](https://img.shields.io/github/release/retailcrm/prestashop-module.svg?logo=codecov)](https://github.com/retailcrm/prestashop-module/releases)
[![PHP version](https://img.shields.io/badge/PHP->=7.1-blue.svg?logo=php)](https://php.net/)

Prestashop module.
=================

Module allows integrate CMS Prestashop with [Simla.com](https://simla.com) ([Documentation](https://www.simla.com/docs/Users/Integration/SiteModules/PrestaShop) page)

#### Features:

* Export orders to Simla.com & fetch changes back
* Export product catalog into [ICML](https://help.retailcrm.pro/Developers/ICML) format

#### Setup

* Go to module setup page
* Fill you api url & api key
* Specify directories matching

#### Notice

* This release contains an experimental feature "corporate customers". Use at your own risk.
* This release only supports Simla.com API v5.

#### Customization

You can customize your module behavior using [Custom Filters](doc/3.%20Customization/Filters.md) or [Custom Classes](doc/3.%20Customization/Classes.md)

#### Local testing

To local testing:
* cp .env.dist .env
* make run_local_tests

#### Documentation

[Here](doc/README.md) you can find more information about module setup and workflow