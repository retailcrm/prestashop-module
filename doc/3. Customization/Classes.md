# Custom classes

If you want to change the default behavior of a module classes and be sure that these changes won't be overwritten during the module upgrade process, you can create your own custom classes. 
Note, that for more compatibility with future module versions it's recommended to use [custom filters](Filters.md) instead.

## Usage

To create custom class **copy the original class** that you are going to customize to the `<prestashop-root>/modules/retailcrm/custom/classes` directory.

From here you can modify the methods of the classes for your own purposes, and they will not be affected during the module upgrade process.

## Precautions

Keep in mind that:

* If the logic and classes of the module have changed a lot after an upgrade, your customized logic may cause the module to malfunction. **You should always check for changes after an upgrade and update your customized classes if needed.**
* This feature does not allow to customize the base class (file `retailcrm.php`). For this you can use the standard Prestashop override feature.
