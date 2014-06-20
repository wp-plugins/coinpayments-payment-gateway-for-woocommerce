=== CoinPayments.net Payment Gateway for WooCommerce ===
Contributors: CoinPayments, woothemes, mikejolley, jameskoster, CoenJacobs
Donate link: https://gocps.net/3ncyzcq3sy0ww1rxghleip1aky/
Tags: bitcoin, litecoin, altcoins, altcoin, dogecoin, feathercoin, netcoin, frankos, digitalcoin, devcoin, earthcoin, anoncoin, fastcoin, quark, peercoin, store, sales, sell, shop, shopping, cart, checkout, e-commerce, commerce
Requires at least: 3.7.0
Tested up to: 3.8.0
Stable tag: 1.0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin implements a payment gateway for WooCommerce to let buyers pay with Bitcoin, Litecoin, and other cryptocurrencies via CoinPayments.net.

== Description ==

This plugin implements a payment gateway for WooCommerce to let buyers pay with Bitcoin, Litecoin, and other cryptocurrencies via CoinPayments.net.

== Installation ==

1. Upload the `coinpayments-payment-gateway-for-woocommerce` directory to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. In the WooCommerce Settings page go to the Payment Gateways tab, then click CoinPayments.net.
1. Check "Enable CoinPayments.net" and enter your CoinPayments.net Merchant ID and IPN Secret (a long random string you define for security).
1. Click "Save changes" and the gateway will be active.

== Changelog ==

= 1.0.5 =
* Adds option to not send shipping information to the CoinPayments.net checkout page.
* Possible workaround for WooCommerce order ID bug.

= 1.0.4 =
* Modified to count "Queued for nightly payout" as order completion

= 1.0.3 =
* Fix to work with WooCommerce 2.1.0

= 1.0.2 =
* Added additional order completion check

= 1.0.1 =
* Fixed image URL for new folder name.

= 1.0.0 =
* Initial release.
