=== Frisbii Billing ===
Contributors: reepaydenmark
Tags: woocommerce, subscriptions, ecommerce, e-commerce, commerce
Requires at least: 5.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Frisbii Billing for WooCommerce plugin gives you the best prerequisites to succeed with your subscription business.

== Description ==
Get all the advanced subscription features from Frisbii Billing while still keeping your usual WooCommerce tools. Frisbii Billing for WooCommerce plugin gives you the best prerequisites to succeed with your subscription business.

= WooCommerce Products And Frisbii Billing Plans =

Create a WooCommerce product and choose between Frisbii Billing Simple Subscription and Frisbii Billing Variable Subscription. Connect the product to either a new or existing Frisbii Billing plan. Make your Frisbii Billing plan changes directly in WooCommerce.

- Flexible billing frequencies
- Optional renewal reminder
- Minimum contract period
- Notice period
- Billing cycle methods
- Advanced trial options
- Setup fee
- Tax settings

= Use Add-Ons For Recurring Additional Sales =

Add-ons are additional products that can be attached to a plan and billed for in each billing cycle. Add-ons can be used to solve a wide range of use-cases. E.g. variable number of “user licenses” or “extended support”, in addition to the base subscription plan.

= WooCommerce Subscription View And Frisbii Billing Admin =

We added a WooCommece native subscription view to quickly find a subscription. All links will redirect you directly into the Frisbii Billing Admin for further subscription details. Links can also be found on orders and customers for you to quickly navigate to the Frisbii Billing Admin. The Frisbii Billing Admin is your powerful back office where all our subscription functionalities can be found.

= Add-On Based WooCommerce Shipping =
Do you send subscription boxes to your customers on an ongoing basis? Connect your shipping rates in WooCommerce with Add-ons from Frisbii Billing and make sure to charge your customers for all ongoing shipping.
Choose between new or existing Add-ons and update your Add-ons directly in WooCommerce.

= Frisbii Billing Discounts & WooCommerce Coupons =

Link WooCommerce coupons with Discounts and Coupons in Frisbii Billing. Let your customers use their coupons easily in the WooCommerce checkout.
Define whether the discount should apply to setup fees, plans, additional costs, instant charges, or Add-ons, and select the subscription plans that the discount should work for.
Standard Frisbii Billing features:

- Plans - Setting up multiple subscription plans with various billing intervals and prices.
- Termination and Minimum Contract Period - Defining the notice period to guarantee a number of periods after subscription termination. Also define a binding period, to guarantee a number of periods for a subscription without closure.
- Upgrading / Downgrading - Instant up or downgrade of subscriptions.
- Termination - Automatic termination of subscription, now or by end of period. Many options.
- Parallel price adjusting - Many options on adjusting prices, eg. new price for new subscribers and old for old.
- Discount - Define percent and fixed-amount discounts for subscriptions.
- Coupons - Coupons are codes that can be given to customers who may then trigger a discount.
- Trial periods - Define a trial period to invoke a free period for new customers.
- Additional costs - At any time assign a customer or a subscription an additional cost, it will be added to next invoice.
- One time charge - Create one time charges for a customer or subscription to be paid instant.
- Credit - Assign a subscription credit, which will be deducted next invoice and perhaps following invoices.
- On Hold - Put a subscription on hold, when re-activating the subscription there is lots of options to automatically correct for the period it was on hold.
- Saved card - Save payment cards for later use on new subscriptions.
- One time charges - One time charge using a saved card.
- Trial end and renew email - Inform your customer on upcoming payments or at trial end.
- Email templates - Templates for 20 different emails send based upon triggers: receipt, welcome mail and such.
- Dunning - Upon failed payment Frisbii Billing will communicate with the customer to get updated card information. Setup of the dunning process can be done from within the Frisbii Billing admin.
- Retrying - Frisbii Billing automatically retries failed payment attempts if they can be save the payment.
- Customer, subscription and invoice handling - Search, list and handle customers, subscriptions and invoices.
- Email log - Overview of all emails sent by Frisbii Billing to the customers
- Refunding - Refund of invoices, either full or partial
- Event log - Log of all events on customers, subscription and invoices.
- Statistics - Advanced statistics and key numbers like: churn, avg. income per. customer, MMR and customer growth.

== Installation ==
1. Download the plugin from WordPress repository or install it directly from Plugins page.
2. Activate the plugin and save Private Key Test and Private Key Live in "Frisbii Billing Settings" page.
3. For correct plugin operation install and activate Frisbii Pay for WooCommerce. API keys for both plugins should be the same.

== Changelog ==
v 1.3 -
- [Fix] - Product name change to Billing.
- [Fix] - For orders with subscriptions that had trial time the order received-page halted before showing order details.

v 1.2.15 - 
- [Fix] - Amounts for pro-rated subscriptions on order confirmation page and mail.
- [Improvement] - Product name change to "Frisbii Billing".
- [Improvement] - Tested up to WordPress version 6.8 and WooCommerce version 9.8.1.

v 1.2.14 -
- [Fix] - Support for WordPress posts storage (legacy) as WooCommerce Order data storage setting.

v 1.2.13 -
- [Fix] - Proration setting display in WooCommerce Billwerk+ Billing product.

v 1.2.12 - 
- [Fix] - Lists updates with plain URL permalink structure.

v 1.2.11 -
- [Improvement] - Support WordPress version 6.7

v 1.2.10 -
- [Fix] - WooCommerce versions before 8.7 got critical error about "Call to undefined function" when applying coupons.
- [Fix] - Fixed amount discount from Billwerk coupon did not appear in the initial order of a subscription.

v 1.2.9.2 -
- [Fix] - WebHook URL for plain permalink structure

v 1.2.9.1 - 
- [Fix] - Discounts on renewal order was recorded as a negative fee order item. Now, as a discount order item.

v 1.2.9 -
- [Improvement] - Enable extra checkbox in WC standard checkout for subscription conditions (WooCommerce Blocks checkout support)
- [Improvement] - Change user role for customers who stop subscribing to a product

v 1.2.8 - 
- [Fix] - Bug fix WC discount codes on mixed orders
- [Fix] - Bug fix recurrent shipping fee for renewals
- [Improvement] - Extra checkbox in WC standard checkout for subscription conditions

v 1.2.7 -
- [Fix] - Missing payment_method_reference data in the Billwerk+ customer_payment_method_added webhook could cause PHP fatal error. Subscriptions would not be created when this happened.
- [Compatibility] - Billwerk+ Pay version 1.7.7

v 1.2.6 - 
- [Improvement] Product name change to "Billwerk+ Subscriptions" to "Billwerk+ Billing".
- [Fix] - Subscription variable product coming in as regular orders.
- [Fix] - Missing billing address on split-off subscription orders.
- [Fix] - Mixed orders: Deactivating new order emails for subscriptions also deactivates emails for regular orders.
- [Compatibility] - Billwerk+ Pay version 1.7.6

v 1.2.5 - Add Bundles support
v 1.2.4 - Add extra option for emails
v 1.2.3 - HPOS support
v 1.2.2 - Fix backward capability
v 1.2.1 - Multi-currency feature
v 1.2.0 - Error fixes
v 1.1.0 - Billwerk+ version and thankyou fixes
v 1.0.26 - Billwerk+ naming changes
v 1.0.25 - Error fixes, coupons changes
v 1.0.24 - Subscriptions and renewals live fetch from API
v 1.0.23 - Fix rest API session error
v 1.0.22 - Add sing-up fee to line item
v 1.0.21 - Failed renewals status fixes
v 1.0.20 - Addons fixes
v 1.0.19 - Coupons fixes
v 1.0.18 - Import card fixes
v 1.0.17 - Display coupon name instead of code
v 1.0.16 - Disallow change status on renewal
v 1.0.15 - Add string translations
v 1.0.14 - Woocommerce Memberships support add, Extends import tools, Add user role to plan creating
v 1.0.13 - Small errors fixes
v 1.0.12 - Subscriptions page link fixing
v 1.0.11 - Fix dashboard
v 1.0.10 - My account changes, status renew fixes
v 1.0.9 - my account page view changes
v 1.0.8 - Plan view changes
v 1.0.7 - One side sync
v 1.0.6 - Fix child order create
v 1.0.5 - Fix start time status
v 1.0.4 - Start time feature
v 1.0.3 - Addons fixing
v 1.0.2 - Fixing payment and plans template
v 1.0.1 - Fixing variables
v 1.0.0 - initial
