<?php
/*
Plugin Name:  پیامک گرویتی فرم راه پیام
Description: جامع‌ترین افزونه پیامک برای Gravity Forms
Version: 1.1
Plugin URI: https://readystudio.ir/persian-gravity-sms-pro/
Author: فاضل قائمی
Author URI: https://readystudio.ir/
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
	define( 'GF_SMS_URL', plugins_url( null, __FILE__ ) );
}

if ( ! defined( 'GF_SMS_GATEWAY' ) ) {
	define( 'GF_SMS_GATEWAY', plugin_dir_path( __FILE__ ) . 'includes/gateways/' );
}

add_action( 'plugins_loaded', 'gravitysms_load_textdomain' );

function gravitysms_load_textdomain() {
	load_plugin_textdomain( 'GF_SMS', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

require_once( GF_SMS_DIR . 'includes/main.php' );

add_action( 'plugins_loaded', array( 'GFHANNANSMS_Pro', 'construct' ), 10 );

register_activation_hook( __FILE__, array( 'GFHANNANSMS_Pro', 'active' ) );
register_deactivation_hook( __FILE__, array( 'GFHANNANSMS_Pro', 'deactive' ) );