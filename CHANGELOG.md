# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Fixed
- birthday of the customer in order import
- check_cart performance issue

## 2.9.49
### Changed
- migrated Shopgate integration for xt:Commerce 4.x/5.x to GitHub
### Fixed
- fixed compatibility issues with the xt_cart_popup module

## 2.9.48
- fixed wrong order detail view in veyton admin when a coupon was redeemed
- shopgate coupons will not be treated as items on cancellations

## 2.9.47
- Fixed bug in class.shopgate_plugin_veyton -> access method on array
- uses Shopgate Library 2.9.65

## 2.9.46
- reverted the presumed fix for missing child products (v## 2.9.39)

## 2.9.45
- added API shippingmethod name to order view

## 2.9.44
- uses Shopgate Library 2.9.61

## 2.9.43
- added order payment status mapping
- reworked installation process for compatibility with xt:Commerce 5 in certain scenarios
- restored compatibility with Shopgate Connect for xt:Commerce 5
- fixed default birthday on create customer addresses
- fixed attempted cancellation synchronization via cron for orders that have Shopgate coupons

## 2.9.42
- fixed wrong product prices in cart, in case coupons with percentages were used
- fixed issue with removing of order items from desktop orders
- fixed import crashes when master items have options

## 2.9.41
- removed dummy variant export if no attributes exist in the sub-product
- cart validation happens in mobile mode now

## 2.9.40
- fixed db error during addOrder

## 2.9.39
- fixed db error when fetching categories in non multi-store exports
- fixed bug with missing child products

## 2.9.38
- fixed bug in customer validation
- fixed db error when fetching customer data
- fixed empty category names in multi-store exports

## 2.9.37
- implemented return of payment methods in method get_settings
- implemented restriction of valid payment methods while checking/validating the cart

## 2.9.36
- fixed a bug in set_shipping_completed cronjob

## 2.9.35
- fixed a bug that caused the order import to crash when the shop has several subshops
- added missing translations for configuration fields when using version 4.2.00 or greater
- applied an internal refactoring on how required plugin files are loaded

## 2.9.34
- extended support of plugin xt_sperrgut

## 2.9.33
- added support for category deeplink url rewrites in mobile shop
- added synchronization of cancelled orders back to Shopgate
- fixed a bug in the export of taxes

## 2.9.32
- fixed a bug that caused register_customer to fail

## 2.9.31
- fixed a bug in check_cart for child products
- extended get_items to export specific products per uid

## 2.9.30
- fixed a bug in exporting a products availability
- fixed tier prices in product export
- fixed issue in coupon validation

## 2.9.29
- implemented mobile redirect for search & manufacturer pages
- added saving to DB or printing of custom fields

## 2.9.28
- fixed issue with php version lower then lower then 5.3
- fixed issue in weight calculation of our cart validation
- fixed SQL bug in get settings
- reworked get_orders
- fixed wrong coupon amount

## 2.9.27
- added order synchronization (library function get_orders)
- added XML export for products

## 2.9.26
- changed returning customer group when user logs in from the mobile website to new API definition
- added returning the customer group to the real-time cart validation for users logged in via mobile redirect
- fixed issue with coupon redemption
- fixed check_cart and check_stock errors for Veyton versions below 4.2

## 2.9.25
- fixed issue with coupon validation
- added validation of product stock quantities to the real-time cart validation
- added real-time stock validation

## 2.9.24
- externel coupons can be used now
- uses the Shopgate Library 2.9.20

## 2.9.23
- uses the Shopgate Library 2.9.19

## 2.9.22
- fixed issue related to duplicate products in product export

## 2.9.21
- the BuI Hinsche plugin "xt_product_options" is now supported from version 4.0.0
- fixed a bug in exporting master/slave products from multi stores
- fixed issue related to duplicate products in product export
- added exporting categories in the XML format
- added exporting product reviews in the XML format

## 2.9.20
- fixed a bug related to the "Options- und Freitext" plugin from BuI Hinsche GmbH
- the default plugin "xt_special_products" is now also supported

## 2.9.19
- fixed a bug in checking out with BillPay on the desktop site
- bug in reading the special price to products fixed

## 2.9.18
- the default customer group is considered for price calculation now
- the code for shipping methods will be set correctly now

## 2.9.17
- customers day of birth will be set correctly
- bug in getting the right shipping methods fixed

## 2.9.16
- fixed a bug in using the plugin xt_sperrgut
- fixed a bug in basic_price

## 2.9.15
- fixed a bug in the export basic pricing
- fixed a bug in saving addresses

## 2.9.14
- fixed a bug that cause the product export to crash

## 2.9.13
- signature of ShopgateItemModel::addImage() didn't match parent function's signature

## 2.9.12
- fixed a bug in exporting product images

## 2.9.11
- fixed a bug in exporting tax settings
- base price is now exported in the same way as in the desktop shop
- special prices without end date will now also be exported

## 2.9.10
- bugfix in splitted product export

## 2.9.9
- fixed errors that occured during installation of the plugin in certain system configurations
- uses Shopgate Library 2.9.10
- the order status "shipped" and "canceled" will be sent to Shopgate now

## 2.9.8
- packaging units will be exported completely now
- products with future available dates are exported now as well

## 2.9.7
- while order import tax percentages for shipping methods will be calculated in correct way

## 2.9.6
- plugin is now compatible to Veyton 4.2.0
- fixed a bug in the real-time calculation of shipping costs

## 2.9.5
- added support for xt_sperrgut (bulk shipping) in real-time shipping rate calculation

## 2.9.4
- it is not possible do set the default redirect in the Shopgate plugin settings anymore
- uses Shopgate Library 2.9.7

## 2.9.3
- problem with git tag fixed

## 2.9.2
- bug in shopgate connect fixed

## 2.9.1
- missing database constants added
- using Veyton logic to export shipping
- uses Shopgate Library 2.9.4

## 2.9.0
- uses Shopgate Library 2.9.2
- guest orders are not allowed in veyton. in this case we didn't return shipping methods.

## 2.8.5
- while reading user data from the database, an \ will be added as prefix to invalid chars in the email address(escaping)

## 2.8.4
- fixed a bug in the plugin's default configuration

## 2.8.3
- check if product option tables exist
- bug in ShopgateConnect fixed
- bug in loading Shopgate configuration from database fixed
- uses Shopgate Library 2.9.1

## 2.8.2
- maximum amount of product options will be calculated
- bug in Shopgate connect fixed
- bug in loading Shopgate config fixed

## 2.8.1
- tax from bulk amount will be subtracted correctly
- removed anonymous sort function
- taxes will now be correctly substracted from bulk amount
- removed anonymous sorting function in order to prevent errors on older PHP versions
- fixed a bug when using multiple stores in Veyton
- uses Shopgate Library 2.8.10

## 2.8.0
- uses Shopgate Library 2.8.3
- Shopgate plugin configuration now gets stored in the database
- name of an constant for order confirmation mails changed
- tax rules can be requested

## 2.7.2
- missing includes added

## 2.7.1
- missing constant added
- valid shipping methods can be checked out

## 2.7.0
- uses Shopgate Library 2.7.2
- Shopgate properties will be saved in database now

## 2.6.7
- Bug in database query fixed
- added support for module Afterbuy (xt_afterbuy)


## 2.6.6
- uses Shopgate Library 2.6.8
- Default Redirect Parameter is set via the config

## 2.6.5
- bug in vpe price calculation fixed

## 2.6.4
- bug in shipping price calculation fixed

## 2.6.3
- shipping price will now be exported as net

## 2.6.2
- Shipping methods can be selected

## 2.6.1
- default redirect can be enabled/disabled in the Shopgate Plugin settings

## 2.6.0
- uses Shopgate Library 2.6.6

## 2.5.6
- bugfix in plugin

## 2.5.5
- uses Shopgate Library 2.5.6
- bugfix in shipping cost calculation

## 2.5.4
- bug while request Shopgate plugin properties fixed

## 2.5.3
- order import bug fixed
- english translation revised

## 2.5.2
- uses Shopgate Library 2.5.6
- plugin ping function extended

## 2.5.1
- uses Shopgate Library 2.5.5
- request Shopgate plugin properties

## 2.5.0
- uses Shopgate Library 2.5.3
- plugin installation optimized

## 2.4.8
- Added a new error case for regitering an customer automatically

## 2.4.7
- Bug in category export with multiple shopgs fixed
- uses Shopgate Library 2.4.14

## 2.4.6
- uses Shopgate Library 2.4.12

## 2.4.5
- uses Shopgate Library 2.4.11

## 2.4.4
- added head comment (license) into plugin files

## 2.4.3
- uses Shopgate Library 2.4.7
- register_custermor implemented

## 2.4.2
- send confirmation mails for shopgate orders can now be enabled.

## 2.4.1
- the customer group to be used for the export of special pricing can now be configured

## 2.4.0
- uses Shopgate Library 2.4.0
- fixed issue with import of customer addresses

## 2.3.2
- uses Shopgate Library 2.3.10
- debug logging extended

## 2.3.1
- The shopgate options can now use an textarea as option input field.
- uses Shopgate Library 2.3.6
- fixed issue with refund module specific code

## 2.3.0
- removed hookpoint styles.php:bottom
- uses Shopgate Library 2.3.3

## 2.1.16
- fixed issue with export in method _getRelatedShopItems()

## 2.1.15
- fixed issue with module "xt_master_slave" in export

## 2.1.14
- it is now possible to choose a combination of the products description and the short description on the Shopgate settings page
- uses Shopgate Library 2.1.29
- fixed issue with module xt_sperrgut

## 2.1.13
- fixed an issue that caused a timeout while processing the set_shipping_completed cronjob without having an orders status id set in the Shopgate configuration
- uses Shopgate Library 2.1.26
- support new version 4.1.00

## 2.1.12
- uses Shopgate Library 2.1.25
- method updateOrder() doesn't throw an exception anymore if payment is done after shipping and shipping was not blocked by Shopgate
- the cron for the job "set_shipping_completed" now also checks the orders status history to get all shipped orders
- fixed issue for Bui Hinsche module

## 2.1.11
- uses Shopgate Library 2.1.23
- export seo urls for categories and products

## 2.1.10
- uses Shopgate Library 2.1.21
- unused configuration fields removed
- js header output in <head> HTML tag
- <link rel="alternate" ...> HTML tag output in <head>

## 2.1.9
- re-enabled/fixed the "hack" that allowed to save "0" for the CNAME in order to deactivate it (since empty input fields are not allowed in Veyton < 4.0.15)

## 2.1.8
- orders that are marked as shipped at shopgate will now be updated correctly while executing the cronjob

## 2.1.7
- for not active countries an exception will be thrown at the import of orders

## 2.1.6
- now checking for the version of the "Options- und Freitextplugin" from the BuI Hinsche GmbH
- Support for AfterBuy module of pimpmyxt (xt_pimpmyxt)
- uses Shopgate Library 2.1.17

## 2.1.5
- uses Shopgate Library 2.1.13
- fixed incompatibility issues between different PHP versions

## 2.1.4
- fixed issues in products export
- uses Shopgate Library 2.1.6

## 2.1.3
- fixed an error concerning Shopgate Library
- uses Shopgate Library 2.1.3

## 2.1.2
- request and debug log are now separately saved for every multi store

## 2.1.1
- fixed incompatibility issue with PHP < 5.3
- uses Shopgate Library 2.1.1

## 2.1.0
- Bestellungen auf Rechnung, bei denen die Rechnung bereits durch Shopgate oder einen dritten Dienstleister versendet wird, erhalten den entsprechenden Kommentar, dass in diesem Fall keine weitere Rechnung durch den Händler versendet werden darf.
- the flag is_customer_invoice_blocked is now checked and inserts an information comment to the order status on invoice payments
- purchases on account that are handled by Shopgate or a third party organization (e.g. Klarna or BillSAFE) are provided with a comment to clarify that a merchant must not send an invoice on his own.
- uses Shopgate Library 2.1.0

## 2.0.31
- bugfix export parent/child products

## 2.0.30
- integration of the [http://www.bui-hinsche.de/onlineshop/xt-commerce-veyton/plugins/38/options-und-freitext-plugin "Options- und Freitext" plugin] in versions from 2.4.0 up<br />With the friendly assistance of ''Business und Internetagentur Hinsche GmbH''.

## 2.0.29
- fixed calculation of payment fees and fees for bulky goods

## 2.0.28
- uses Shopgate Library 2.0.34
- installation: add a new status "Shipping blocked (Shopgate)" and set is as default

## 2.0.27
- Bugfix: The stock was not decremented

## 2.0.26
- Support for ''xt_sperrgut'' module

## 2.0.25
- Export block price for one prouct

## 2.0.24
- uses Shopgate Library 2.0.29 (bugfixes and preparation for item input fields)

## 2.0.23
- added changelog.txt

## 2.0.22
- uses Shopgate Library 2.0.26
- supports the "Redirect to tablets (yes/no)" setting
- supports remote cron jobs via Shopgate Plugin API
- remote cron job for synchronization of order status at Shopgate

[Unreleased]: https://github.com/shopgate/cart-integration-xtcommerce4/compare/2.9.49...HEAD
