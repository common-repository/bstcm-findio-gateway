=== Findio WooCommerce plugin ===

Contributors: basticom
Donate link: https://www.basticom.nl/
Tags: connector, api, findio, xml, woocommerce, payment, gateway
Requires at least: 4.7.0
Requires PHP: 7.0
Tested up to: 4.9.7
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin creates a new WooCommerce payment gateway for [Findio](https://www.findio.nl/) credit services.

== Description ==

This plugin creates a new WooCommerce payment gateway for [Findio](https://www.findio.nl/) credit services.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bstcm-findigo-gateway` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WooCommerce -> Settings -> Checkout -> Findio to configure the gateway

== Frequently Asked Questions ==

= Who can use this plugin? =

This plugin can be used with WooCommerce and WordPress. To use this plugin, you need valid credentials for the Findio API. [Contact Findio](https://www.findio.nl/contact) for more information.

== Screenshots ==
1. Findio Payment Gateway – Back-end configuration page
2. Findio Payment Gateway – Single product loan offer
3. Findio Payment Gateway – Cart total loan offer
4. Findio Payment Gateway – Checkout total loan offer (detailed)
5. Findio Payment Gateway – Loan offer calculation table

== Upgrade Notice ==

First public alpha release; please upgrade to enable all new features.

== Changelog ==

= 0.1.0 =
* Alpha release to setup plugin base

= 0.2.0 =
* Setup back-end settings and configuration page
* First draft for front-end shortcodes

= 0.3.0 =
* Replaced shortcode markup with final versions
* Created callback functions and payment statuses
* Added additional configuration settings

= 0.4.0 =
* Implemented minimal and maximal loan amount settings
* Added support for product archive loop shortcode
* Added support for variable products

= 0.5.0 =
* Prepared plugin for distribution in WordPress repository

= 0.5.1 =
* Changed readme.txt descriptions (added working hyperlinks)
