<?php
/*
Plugin Name: MessageWay for gravity form
Description: MessageWay addon for gravity forms
Version: 0.2
Plugin URI: http://msgway.com
Author: FazelGhaemi
Text Domain: GF_SMS
Domain Path: /languages/
*/
if (!defined('ABSPATH')) {
	exit;
}
if (!defined('GF_SMS_DIR')) {
	define('GF_SMS_DIR', plugin_dir_path(__FILE__));
}
if (!defined('GF_SMS_URL')) {
	define('GF_SMS_URL', plugins_url(null, __FILE__));
}
if (!defined('GF_SMS_GATEWAY')) {
	define('GF_SMS_GATEWAY', plugin_dir_path(__FILE__) . 'includes/gateways/');
}
add_action('plugins_loaded', 'gravitysms_load_textdomain');
function gravitysms_load_textdomain()
{
	load_plugin_textdomain('GF_SMS', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

require_once(GF_SMS_DIR . 'includes/main.php');
add_action('plugins_loaded', array('GF_MESSAGEWAY', 'construct'), 10);
register_activation_hook(__FILE__, array('GF_MESSAGEWAY', 'active'));
register_deactivation_hook(__FILE__, array('GF_MESSAGEWAY', 'deactive'));
function gvDebug($data, $file = 'gv_debug.txt')
{
	$url = plugin_dir_path(__FILE__) . $file;
	$file = @file_get_contents($url);
	if (is_array($data) || is_object($data)) $data = json_encode($data);
	@file_put_contents($url, $file . "\n" . $data);
	return;
}