<?php
/*
Plugin Name: Sprout Invoices Add-on - Sprout Invoice Customizations
Plugin URI: https://sproutapps.co/custom-services
Description: MG
Author: Sprout Apps
Version: 0.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_TRIPT_VERSION', '1.0' );
define( 'SA_ADDON_TRIPT_DOWNLOAD_ID', 000 );
define( 'SA_ADDON_TRIPT_NAME', 'Sprout Invoices Customizations' );
define( 'SA_ADDON_TRIPT_FILE', __FILE__ );
define( 'SA_ADDON_TRIPT_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_TRIPT_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_mg_custom_addon' );
function sa_load_mg_custom_addon() {

	require_once( 'inc/Invoice_Customizations.php' );
	require_once( 'inc/Line_Item_Customizations.php' );
	require_once( 'inc/VAT_Modifications.php' );
	require_once( 'inc/Woo_Commerce_Int_Mods.php' );

	Invoice_Customizations::init();
	Line_Item_Customizations::init();
	VAT_Modifications::init();
	Woo_Commerce_Int_Mods::init();

	require_once( 'template-tags/mixed.php' );
}
