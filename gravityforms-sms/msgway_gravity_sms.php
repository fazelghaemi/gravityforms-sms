<?php
/*
Plugin Name: پیامک گرویتی فرم (نسخه راه‌پیام)
Plugin URI: https://www.msgway.com/r/lr
Description: افزونه حرفه‌ای ارسال پیامک گرویتی فرم با پشتیبانی اختصاصی از سامانه راه‌پیام (MsgWay) و خطوط خدماتی.
Version: 2.2.0
Author: Ready Studio
Author URI: https://readystudio.ir
Text Domain: GF_SMS
Domain Path: /languages/
*/

if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثوابت مسیردهی
if (!defined('GF_SMS_DIR')) {
    define('GF_SMS_DIR', plugin_dir_path(__FILE__));
}

if (!defined('GF_SMS_URL')) {
    define('GF_SMS_URL', plugins_url('', __FILE__));
}

if (!defined('GF_SMS_GATEWAY')) {
    define('GF_SMS_GATEWAY', GF_SMS_DIR . 'includes/gateways/');
}

// بارگذاری فایل ترجمه
add_action('plugins_loaded', 'gravitysms_msgway_load_textdomain');
function gravitysms_msgway_load_textdomain()
{
    load_plugin_textdomain('GF_SMS', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// بارگذاری کلاس اصلی
require_once(GF_SMS_DIR . 'includes/main.php');

// هوک‌های فعال‌سازی و راه‌اندازی
add_action('plugins_loaded', array('GF_MESSAGEWAY', 'construct'), 10);
register_activation_hook(__FILE__, array('GF_MESSAGEWAY', 'active'));
register_deactivation_hook(__FILE__, array('GF_MESSAGEWAY', 'deactive'));

/**
 * تابع کمکی برای لاگ گرفتن در فایل دیباگ
 * فقط زمانی کار می‌کند که WP_DEBUG فعال باشد
 * * @param mixed $data داده‌ای که باید لاگ شود
 * @param string $file نام فایل لاگ (اختیاری)
 */
if (!function_exists('msgway_debug')) {
    function msgway_debug($data, $file = 'msgway_debug.log')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $path = GF_SMS_DIR . $file;
            $log_entry = date('[Y-m-d H:i:s] ') . print_r($data, true) . PHP_EOL;
            
            // استفاده از error_log وردپرس برای امنیت بیشتر یا نوشتن در فایل
            // اینجا در فایل اختصاصی می‌نویسیم
            @file_put_contents($path, $log_entry, FILE_APPEND);
        }
    }
}