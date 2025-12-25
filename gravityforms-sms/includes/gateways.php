<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('GF_MESSAGEWAY_WebServices')) {
    class GF_MESSAGEWAY_WebServices
    {
        /**
         * لیست درگاه‌های موجود را برمی‌گرداند (فیلتر شده)
         */
        public static function get()
        {
            return apply_filters('gf_sms_gateways', array('no' => __('یک درگاه انتخاب کنید', 'GF_SMS')));
        }

        /**
         * اجرای عملیات درگاه (ارسال، اعتبار، و...)
         */
        public static function action($settings, $action, $from, $to, $messages)
        {
            $gateway_slug = isset($settings["ws"]) ? strtolower($settings["ws"]) : '';
            
            if (empty($gateway_slug) || $gateway_slug == 'no') {
                return __('هیچ درگاهی انتخاب نشده است.', 'GF_SMS');
            }

            // پاکسازی پیام
            $messages = str_replace(array("<br>", "<br/>", "<br />", '&nbsp;'), array("\n", "\n", "\n", ' '), $messages);
            $messages = strip_tags($messages);

            // ساخت نام کلاس بر اساس استاندارد: GF_MESSAGEWAY_SLUG
            $gateway_class_name = 'GF_MESSAGEWAY_' . strtoupper($gateway_slug);

            // اگر کلاس وجود ندارد، فایل را لود کن
            if (!class_exists($gateway_class_name)) {
                $file_path = GF_SMS_GATEWAY . $gateway_slug . '.php';
                if (file_exists($file_path)) {
                    require_once($file_path);
                }
            }

            // بررسی وجود کلاس و متد process
            if (class_exists($gateway_class_name) && method_exists($gateway_class_name, 'process')) {
                // دریافت تنظیمات ذخیره شده برای این درگاه خاص
                $gateway_options = get_option("gf_smspanel_" . $gateway_slug);
                
                // اجرای متد process
                $result = $gateway_class_name::process($gateway_options, $action, $from, $to, $messages);
                return $result;
            }

            return sprintf(__('درگاه %s یافت نشد یا غیرفعال است.', 'GF_SMS'), $gateway_slug);
        }
    }
}