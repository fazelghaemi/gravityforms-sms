<?php
/*
Plugin Name: Ready Studio Gravity SMS
Plugin URI: https://readystudio.ir
Description: افزونه پیامک گرویتی فرم برای Ready Studio. (بر پایه Persian Gravity Forms SMS Pro)
Version: 2.3.0
Author: Ready Studio
Author URI: https://readystudio.ir
Text Domain: GF_SMS
Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GF_SMS_DIR' ) ) {
	define( 'GF_SMS_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'GF_SMS_URL' ) ) {
	// Fix for plugins_url
	define( 'GF_SMS_URL', plugins_url( '', __FILE__ ) );
}

if ( ! defined( 'GF_SMS_GATEWAY' ) ) {
	define( 'GF_SMS_GATEWAY', plugin_dir_path( __FILE__ ) . 'includes/gateways/' );
}

// Load text domain
add_action( 'plugins_loaded', 'gravitysms_load_textdomain_ready' );
function gravitysms_load_textdomain_ready() {
	load_plugin_textdomain( 'GF_SMS', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Include main class
require_once( GF_SMS_DIR . 'includes/main.php' );

// Hook up the plugin
add_action( 'plugins_loaded', array( 'GFHANNANSMS_Pro', 'construct' ), 10 );

register_activation_hook( __FILE__, array( 'GFHANNANSMS_Pro', 'active' ) );
register_deactivation_hook( __FILE__, array( 'GFHANNANSMS_Pro', 'deactive' ) );

