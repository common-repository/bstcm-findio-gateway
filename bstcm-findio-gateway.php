<?php
/*
Plugin Name: Findio WooCommerce plugin
Plugin URI: https://www.basticom.nl
Description: WooCommerce payment gateway for Findio
Version: 0.5.1
Author: Basticom
Author URI: https://www.basticom.nl
License: GPL2
WC requires at least: 3.3.0
WC tested up to: 3.4.1
Textdomain: bstcm-findio-gateway
*/

defined( 'ABSPATH' ) or exit;

define( 'BFG_PLUGIN_DIR', dirname(__FILE__).'/' );
define( 'BFG_PLUGIN_URL', plugin_dir_url( __FILE__ ).'/' );

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

// Load external files
require_once( BFG_PLUGIN_DIR.'includes/bstcm-findio-functions.php' );
require_once( BFG_PLUGIN_DIR.'includes/bstcm-findio-api.php' );

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function bfg_add_to_gateways( $gateways ) {
	$gateways[] = 'bfg_WC_Gateway_Findio';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'bfg_add_to_gateways' );


// Load front-end scripts and styling
function bfg_gateway_files() {
	wp_register_script( 'bfg_js_functions', plugin_dir_url( __FILE__ ).'assets/js/bstcm-findio-gateway.js');
	wp_localize_script( 'bfg_js_functions', 'the_ajax_script', array( 'bfg_ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
    wp_enqueue_script( 'bfg_js_functions' );
	wp_register_style( 'bfg_css', plugin_dir_url( __FILE__ ).'assets/css/bstcm-findio-gateway.css');
	wp_enqueue_style( 'bfg_css' );
}
add_action( 'wp_enqueue_scripts', 'bfg_gateway_files', 11);
add_action( 'admin_enqueue_scripts', 'bfg_gateway_files', 11);

function bfg_write_log ( $log )  {
    if ( true === WP_DEBUG ) {
        if ( is_array( $log ) || is_object( $log ) ) {
            error_log( print_r( $log, true ) );
        } else {
            error_log( $log );
        }
    }
}
/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function bfg_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bfg_gateway' ) . '">' . __( 'Configure', 'bstcm-findio-gateway' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bfg_gateway_plugin_links' );

function bfg_load_textdomain() {
	load_plugin_textdomain( 'bstcm-findio-gateway', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'bfg_load_textdomain', 12 );

/**
 * Finido Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		bfg_WC_Gateway_Findio
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Basticom
 */
function bfg_gateway_init() {

	bfg_write_log( "bfg_gateway_init() ");

	require_once plugin_dir_path( __FILE__ ) . 'includes/bstcm-findio-gateway-class.php';

	$wc_gateway_findio = new bfg_WC_Gateway_Findio();

	$wc_gateway_findio->init_hooks(); // Initiate hooks

}

add_action( 'plugins_loaded', 'bfg_gateway_init', 15);
