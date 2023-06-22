<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit20a39eae146d0eddfc4743659342a91f
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'WC_Product_Reepay_Simple_Subscription' => __DIR__ . '/../..' . '/includes/WC_Product_Reepay_Simple_Subscription.php',
        'WC_Product_Reepay_Variable_Subscription' => __DIR__ . '/../..' . '/includes/WC_Product_Reepay_Variable_Subscription.php',
        'WC_RS_Log' => __DIR__ . '/../..' . '/includes/WC_RS_Log.php',
        'WC_Reepay_Account_Page' => __DIR__ . '/../..' . '/includes/WC_Reepay_Account_Page.php',
        'WC_Reepay_Admin_Frontend' => __DIR__ . '/../..' . '/includes/WC_Reepay_Admin_Frontend.php',
        'WC_Reepay_Checkout' => __DIR__ . '/../..' . '/includes/WC_Reepay_Checkout.php',
        'WC_Reepay_Discounts_And_Coupons' => __DIR__ . '/../..' . '/includes/WC_Reepay_Discounts_And_Coupons.php',
        'WC_Reepay_Import' => __DIR__ . '/../..' . '/includes/import/WC_Reepay_Import.php',
        'WC_Reepay_Import_AJAX' => __DIR__ . '/../..' . '/includes/import/WC_Reepay_Import_AJAX.php',
        'WC_Reepay_Import_Helpers' => __DIR__ . '/../..' . '/includes/import/WC_Reepay_Import_Helpers.php',
        'WC_Reepay_Import_Menu' => __DIR__ . '/../..' . '/includes/import/WC_Reepay_Import_Menu.php',
        'WC_Reepay_Memberships_Integrations' => __DIR__ . '/../..' . '/includes/memberships/WC_Reepay_Memberships_Integrations.php',
        'WC_Reepay_My_Account' => __DIR__ . '/../..' . '/includes/my-account/WC_Reepay_My_Account.php',
        'WC_Reepay_My_Account_Orders_Page' => __DIR__ . '/../..' . '/includes/my-account/WC_Reepay_My_Account_Orders_Page.php',
        'WC_Reepay_My_Account_Payment_Method' => __DIR__ . '/../..' . '/includes/my-account/WC_Reepay_My_Account_Payment_Method.php',
        'WC_Reepay_My_Account_Subscription_Page' => __DIR__ . '/../..' . '/includes/my-account/WC_Reepay_My_Account_Subscription_Page.php',
        'WC_Reepay_My_Account_Subscriptions_Page' => __DIR__ . '/../..' . '/includes/my-account/WC_Reepay_My_Account_Subscriptions_Page.php',
        'WC_Reepay_Renewals' => __DIR__ . '/../..' . '/includes/WC_Reepay_Renewals.php',
        'WC_Reepay_Statistics' => __DIR__ . '/../..' . '/includes/WC_Reepay_Statistics.php',
        'WC_Reepay_Subscription_API' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_API.php',
        'WC_Reepay_Subscription_Addons' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Addons.php',
        'WC_Reepay_Subscription_Addons_Rest' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Addons_Rest.php',
        'WC_Reepay_Subscription_Addons_Shipping' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Addons_Shipping.php',
        'WC_Reepay_Subscription_Admin_Notice' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Admin_Notice.php',
        'WC_Reepay_Subscription_Coupons_Rest' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Coupons_Rest.php',
        'WC_Reepay_Subscription_Discounts_Rest' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Discounts_Rest.php',
        'WC_Reepay_Subscription_Plan_Simple' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Plan_Simple.php',
        'WC_Reepay_Subscription_Plan_Simple_Rest' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Plan_Simple_Rest.php',
        'WC_Reepay_Subscription_Plan_Variable' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscription_Plan_Variable.php',
        'WC_Reepay_Subscriptions_List' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscriptions_List.php',
        'WC_Reepay_Subscriptions_Table' => __DIR__ . '/../..' . '/includes/WC_Reepay_Subscriptions_Table.php',
        'WC_Reepay_Subscriptions_Update' => __DIR__ . '/../..' . '/includes/update/WC_Reepay_Subscriptions_Update.php',
        'WC_Reepay_Sync' => __DIR__ . '/../..' . '/includes/sync/WC_Reepay_Sync.php',
        'WC_Reepay_Sync_Customers' => __DIR__ . '/../..' . '/includes/sync/WC_Reepay_Sync_Customers.php',
        'WC_Reepay_Sync_Subscriptions' => __DIR__ . '/../..' . '/includes/sync/WC_Reepay_Sync_Subscriptions.php',
        'WC_Reepay_Woo_Blocks' => __DIR__ . '/../..' . '/includes/woo-blocks/WC_Reepay_Woo_Blocks.php',
        'WC_Reepay_Woocommerce_Subscription_Extension' => __DIR__ . '/../..' . '/includes/WC_Reepay_Woocommerce_Subscription_Extension.php',
        'WC_Subscription' => __DIR__ . '/../..' . '/includes/memberships/WC_Reepay_Memberships_Integrations.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit20a39eae146d0eddfc4743659342a91f::$classMap;

        }, null, ClassLoader::class);
    }
}
