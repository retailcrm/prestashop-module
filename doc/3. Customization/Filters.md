# Custom filters

## Usage

If you want to modify data, sent between CRM and CMS you can use custom filters.
To use filters you should define a new class in `<prestashop-root>/modules/retailcrm/custom/filters`. Filename and classname must match the filter name.
Filter class should implement interface *RetailcrmFilterInterface*. In filter class you must define *filter()* function, which will take initial `$object` and return customized `$object`.

You can see more examples on the [Examples](Examples.md) page

## List of filters

There are list of available filters:

* *RetailcrmFilterProcessOrder* - order array, which will be sent to CRM
* *RetailcrmFilterOrderStatusUpdate* - order array, which will be sent to CRM when the status is changed
* *RetailcrmFilterProcessCustomer* - customer array, which will be sent to CRM
* *RetailcrmFilterProcessCustomerCorporate* - corporate customer array, which will be sent to CRM
* *RetailcrmFilterProcessAddress* - address array, which will be sent to CRM
* *RetailcrmFilterProcessOffer* - offer array, which will be sent to CRM (saved into Icml file)

* *RetailcrmFilterCustomersHistory* - array with assembled history for customer, loaded from CRM
* *RetailcrmFilterCustomersHistoryUpdate* - array with customer info, loaded from CRM
* *RetailcrmFilterOrdersHistory* - array with assembled history for order, loaded from CRM
* *RetailcrmFilterOrdersHistoryCreate* - array with order info, loaded from CRM
* *RetailcrmFilterOrdersHistoryUpdate* - array with assembled history for order, loaded from CRM

* *RetailcrmFilterSaveCustomer* - built customer object, which will be saved to CMS
* *RetailcrmFilterSaveCustomerAddress* - built customer address object, which will be saved to CMS
* *RetailcrmFilterSaveCorporateCustomer* - built corporate customer object, which will be saved to CMS
* *RetailcrmFilterSaveCorporateCustomerAddress* - built corporate customer address object, which will be saved to CMS

* *RetailcrmFilterJobManagerIntervals* - array with jobs as keys and intervals as values
* *RetailcrmFilterWeight* - array with weight units in ICML
* *RetailcrmFilterMiddlewares* - array with middlewares, which running before and after request
