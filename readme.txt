=== Percentage Coupon per Product for WooCommerce ===
Contributors: nakedcatplugins, webdados
Tags: woocommerce, coupon, discount, marketing
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create WooCommerce coupons with variable discount percentage per product

== Description ==

This plugin creates a new coupon type called “Percentage discount (per product)” that works just like the regular “Percentage discount” coupon, with a twist.
By default the percentage discount to be applied is the one set on the coupon, but you can edit any product and set a specific discount for that product / coupon.
You can also set the specific discount at the product variation level (defaults to product level if not set).

= Features: =

* One coupon, several percentage discounts: the coupon’s percentage is the default, and each product can have its own
* Percentages per variation, or inherited from the product
* Leave a product out of a coupon by setting it to “No”
* Discounts on products on sale are calculated on the sale price, unless the coupon has “Exclude sale items” ticked
* Works with WooCommerce’s own coupon settings: product and category restrictions, minimum and maximum spend, usage limits, “Limit usage to X items” and free shipping
* Optional “Refuse without discount” setting, to refuse the coupon when no product in the cart gets a discount from it
* Percentages with decimals, typed with your shop’s decimal separator
* Integrates with [Magic URL Coupon for WooCommerce](https://wordpress.org/plugins/magic-coupon/) and Invoicing with InvoiceXpress for WooCommerce (Pro)

= PRO add-on (soon to be released) features: =

* Set default discount on the coupon and specific discounts per taxonomy term (instead of per product)
* Technical support

= Other (premium) plugins =

Already know our other WooCommerce (premium) plugins?

* [EU Guarantee Notice and GARAN Label for WordPress and WooCommerce](https://nakedcatplugins.com/product/eu-guarantee-notice-and-garan-label-for-wordpress-and-woocommerce/) - Show the mandatory EU guarantee notice and the EU GARAN label with the official European Commission artwork, and let Proof of Notice check every day that the notice is still on your site
* [Shop as Client for WooCommerce](https://nakedcatplugins.com/product/shop-as-client-for-woocommerce-pro-add-on/) - Quickly create orders on behalf of your customers
* [Taxonomy/Term and Role based Discounts for WooCommerce](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/) - Easily create bulk discount rules for products based on any taxonomy terms (built-in or custom)
* [Simple WooCommerce Order Approval](https://nakedcatplugins.com/product/simple-woocommerce-order-approval/) - The hassle-free solution for WooCommerce order approval before payment

Header photo by [Artem Beliaikin](https://unsplash.com/photos/49mCO5ZRQDk).

== Frequently Asked Questions ==

= How do I set a different percentage for a product? =

Create a coupon with the “Percentage discount (per product)” discount type first. Then edit the product and open the “Percentage discount coupons” tab: each coupon of that type has its own setting there. Leave the percentage empty to use the coupon’s, type a percentage to override it, or set the product to “No” to leave it out of that coupon.

For variable products, each variation has the same settings below its other fields. “No (inherit from product)” uses the product’s setting.

= How do I apply a coupon only to the products I choose? =

Use WooCommerce’s own “Usage restriction > Products” field on the coupon and list the products it is for. Those get their own percentage, or the coupon’s if they have none, and every other product gets nothing.

Or set the coupon’s amount to 0% and give the products you want their own percentage. Only those are discounted.

= Does the discount apply to products on sale? =

Yes, calculated on the sale price. To leave products on sale out, tick “Exclude sale items” on the coupon’s “Usage restriction” tab.

= What happens when no product in the cart gets a discount? =

By default the coupon is accepted with no discount, as with any WooCommerce coupon. The order still records it, so you can keep tracking orders with it, and a coupon with “Allow free shipping” ticked still gives free shipping.

To refuse the coupon on those carts instead, tick “Refuse without discount” on the coupon. WooCommerce then shows its “not applicable to selected products” message.

= Is this plugin compatible with the new WooCommerce High-Performance Order Storage? =

Yes.

= Is this plugin compatible with the new WooCommerce block-based Cart and Checkout? =

Yes.

= I need help, can I get technical support? =

This is a free plugin. It’s our way of giving back to the wonderful WordPress community.

There’s a support tab on the top of this page, where you can ask the community for help. We’ll try to keep an eye on the forums but we cannot promise to answer support tickets.

If you reach us by email or any other direct contact method, we’ll assume you need premium, paid-for support.

= Where do I report security vulnerabilities found in this plugin? =  
 
You can report any security bugs found in the source code of this plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/product-percentage-coupon-woo). The Patchstack team will assist you with verification, CVE assignment and take care of notifying the developers of this plugin.

= Can I contribute with a translation? =

Sure. Go to [GlotPress](https://translate.wordpress.org/projects/wp-plugins/product-percentage-coupon-woo) and help us out.

== Changelog ==

= TBA =
* [NEW] “Refuse without discount” option on “Percentage discount (per product)” coupons, to refuse the coupon when no product in the cart gets a discount from it (off by default, so coupons keep working as before)
* [TWEAK] Faster product editing, and less data stored for each product, when the shop has many “Percentage discount (per product)” coupons
* [FIX] Percentages with decimals set on a product or variation, such as 12,5%, lost their decimals on shops that use a comma as the decimal separator
* [FIX] Products set to “No” for a coupon no longer use up its “Limit usage to X items” allowance
* [FIX] Magic URL Coupon for WooCommerce integration: when the coupon’s own discount is 0%, products without a percentage of their own are no longer treated as eligible for it
* [DEV] Requires PHP 7.4
* [DEV] Tested up to WordPress 7.2-alpha-64319 and WooCommerce 11.2.1

= 1.3 - 2025-06-13 =
* [NEW] We are now called Naked Cat Plugins 😻
* [FIX] PHP notice
* [FIX] Version number
* [DEV] Apply WordPress Coding Standards
* [DEV] Requires WordPress 5.8, WooCommerce 7.1 and PHP 7.2
* [DEV] Tested with WordPress 6.9-alpha-60301 and WooCommerce 9.9.3

= 1.1 - 2023-12-13 =
* Declare WooCommerce block-based Cart and Checkout compatibility
* Requires WordPress 5.4
* Tested with WordPress 6.5-alpha-57159 and WooCommerce 8.4.0

= 1.0 - 2023-07-17 =
* Tested and confirmed WooCommerce High-Performance Order Storage compatibility
* Minor code refactoring
* Requires WooCommerce 5.0
* Tested with WordPress 6.3-beta4-56226 and WooCommerce 7.9.0-rc.3

= 0.7 - 2022-06-29 =
* New brand: PT Woo Plugins 🥳
* Requires WordPress 5.0, WooCommerce 3.0 and PHP 7.0
* Tested with WordPress 6.1-alpha-53556 and WooCommerce 6.7.0-beta.2

= 0.6 =
* Override discount percentages at the variation level (experimental, sponsored by [https://masterswiss.com/](https://masterswiss.com/))
* Tested with WordPress 5.7-beta2-50285 and WooCommerce 5.0

= 0.5 =
* Several tweaks on the [Magic URL Coupon for WooCommerce](https://wordpress.org/plugins/magic-coupon/) integration (sponsored by [https://masterswiss.com/](https://masterswiss.com/))
* Tested with WooCommerce 4.6.1

= 0.4 =
* Bugfix on variations
* Tested with WordPress 5.6-alpha-48976 and WooCommerce 4.5.2

= 0.3 =
* Disable the discount at the product level (sponsored by [https://masterswiss.com/](https://masterswiss.com/))
* Better [Magic URL Coupon for WooCommerce](https://wordpress.org/plugins/magic-coupon/) integration
* Tested with WordPress 5.6-alpha-48937 and WooCommerce 4.5.1

= 0.2 =
* Better plugin naming
* Header photo credits

= 0.1 =
* Initial release (sponsored by [https://masterswiss.com/](https://masterswiss.com/))