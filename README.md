# Karbo for WooCommerce

Karbo for WooCommerce is a Wordpress plugin that allows merchants to accept KRB at WooCommerce-powered online stores.

Contributors: KittyCatTech, gesman

Tags: karbo, karbo wordpress plugin, karbo plugin, karbo payments, accept karbo, karbos

Requires at least: 3.0.1

Tested up to: 4.4.1

Stable tag: trunk

License: BipCot NoGov Software License bipcot.org

License URI: https://github.com/Karbovanets/karbo-woocommerce/blob/master/LICENSE

## Description

Your online store must use WooCommerce platform (free wordpress plugin).
Once you have installed and activated WooCommerce, you may install and activate Karbo for WooCommerce.

### Benefits 

* Fully automatic operation.
* Can be used with view only wallet so only the view private key is on the server and none of the spend private keys are required to be kept anywhere on your online store server.
* Accept payments in Karbos directly into your Karbo wallet.
* Karbo wallet payment option completely removes dependency on any third party service and middlemen.
* Accept payment in Karbos for physical and digital downloadable products.
* Add Karbo option to your existing online store with alternative main currency.
* Flexible exchange rate calculations fully managed via administrative settings.
* Zero fees and no commissions for Karbo processing from any third party.
* Set main currency of your store in USD, KRB or BTC.
* Automatic conversion to Karbo via realtime exchange rate feed and calculations.
* Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.


## Installation 


1.  Install WooCommerce plugin and configure your store (if you haven't done so already - http://wordpress.org/plugins/woocommerce/).
2.  Install "Karbo for WooCommerce" wordpress plugin just like any other Wordpress plugin.
3.  Activate.
4.  Download and install on your computer Karbo wallet program from: https://karbo.org/
5.  Copy and setup your wallet on the server. Change permission to executable. Run karbod as a service.
6.  Generate Container (optionally reset containter to view only container and add view only address). Run walletd as a service.
7.  Get your wallet address from walletd.
8.  Within your site's Wordpress admin, navigate to:
	    WooCommerce -> Settings -> Checkout -> Karbo
	    and paste your wallet address into "Wallet Address" field.
9.  Select "Karbo service provider" = "Local Wallet" and fill-in other settings at Karbo management panel.
10. Press [Save changes]
11. If you do not see any errors - your store is ready for operation and to access payments in Karbos!


## Remove plugin

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


## Changelog

none
