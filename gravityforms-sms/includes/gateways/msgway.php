<?php

if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_MSGWay
{
    const API_URL = 'https://api.msgway.com';

    public static function options()
    {
        return array(
            'apikey' => __('API Key', 'GF_SMS')
        );
    }

    public static function name($gateways)
    {
        $name = __('MsgWay (راه‌پیام)', 'GF_SMS');
        $gateway = array('msgway' => $name);
        return array_merge($gateways, $gateway);
    }

    /**
     * پردازش اصلی ارسال پیامک
     */
    public static function process($options, $action, $from, $to, $message)
    {
        $apiKey = isset($options['apikey']) ? trim($options['apikey']) : '';

        if (empty($apiKey)) {
            return __('API Key تنظیم نشده است.', 'GF_SMS');
        }

        if ($action == 'credit') {
            return self::getCredit($apiKey);
        }

        // تشخیص متد و پرووایدر بر اساس شماره فرستنده ($from)
        $config = self::getProviderAndMethod($from);

        if ($action == 'send') {
            // بررسی ارسال پترن (الگو)
            // فرمت پترن: patterncode:123;var1:val1
            if (strpos($message, 'patterncode:') === 0 || strpos($message, 'pcode:') === 0) {
                return self::sendPattern($apiKey, $to, $message, $config);
            }
            
            // ارسال معمولی (تبلیغاتی/بالک)
            return self::sendNormal($apiKey, $to, $message, $config);
        }

        return false;
    }

    /**
     * ارسال معمولی
     */
    public static function sendNormal($apiKey, $to, $message, $config)
    {
        $recipients = self::prepareRecipients($to);
        if (empty($recipients)) return __('گیرنده‌ای وجود ندارد.', 'GF_SMS');

        $lastResult = '';

        foreach ($recipients as $mobile) {
            $body = [
                'mobile' => $mobile,
                'method' => $config['method'],
                'provider' => $config['provider'],
                'text' => $message
            ];

            // اگر متد مسنجر یا IVR است، پارامتر text ممکن است متفاوت باشد یا templateID بخواهد
            // طبق مستندات برای ارسال ساده متن هم از /send استفاده می‌شود
            // اما برای مسنجرها معمولا ارسال متن ساده هم پشتیبانی می‌شود.

            $response = self::remoteRequest('/send', $apiKey, $body);

            if (is_wp_error($response)) {
                $lastResult = $response->get_error_message();
            } else {
                if (isset($response['status']) && $response['status'] === 'success') {
                    $lastResult = 'OK';
                } else {
                    $errorMsg = isset($response['error']['message']) ? $response['error']['message'] : 'Unknown Error';
                    $lastResult = $errorMsg;
                }
            }
        }

        return $lastResult;
    }

    /**
     * ارسال از طریق پترن (الگو)
     */
    public static function sendPattern($apiKey, $to, $message, $config)
    {
        // تبدیل خطوط جدید به سمی‌کالن برای پارس کردن
        $message = str_replace(array("\r\n", "\r", "\n"), ';', $message);
        
        $parts = explode(';', $message);
        $params = [];
        $templateID = 0;

        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            if (strpos($part, ':') !== false) {
                list($key, $value) = explode(':', $part, 2);
                $key = trim($key);
                $value = trim($value);

                if (in_array(strtolower($key), ['pcode', 'patterncode', 'pid'])) {
                    $templateID = (int)$value;
                } else {
                    $params[$key] = $value;
                }
            }
        }

        if (empty($templateID)) {
            return __('کد الگو (Pattern ID) یافت نشد.', 'GF_SMS');
        }

        $recipients = self::prepareRecipients($to);
        $lastResult = '';

        foreach ($recipients as $mobile) {
            $body = [
                'mobile' => $mobile,
                'method' => $config['method'],
                'provider' => $config['provider'],
                'templateID' => $templateID,
                'params' => $params
            ];

            $response = self::remoteRequest('/send', $apiKey, $body);

            if (is_wp_error($response)) {
                $lastResult = $response->get_error_message();
            } else {
                if (isset($response['status']) && $response['status'] === 'success') {
                    $lastResult = 'OK';
                } else {
                    $errorMsg = isset($response['error']['message']) ? $response['error']['message'] : 'Unknown Error';
                    $lastResult = $errorMsg;
                }
            }
        }

        return $lastResult;
    }

    /**
     * تشخیص متد و پرووایدر بر اساس ورودی فیلد "فرستنده"
     */
    private static function getProviderAndMethod($from)
    {
        $from = strtolower(trim($from));
        $method = 'sms';
        $provider = 1; // پیش‌فرض مگفا (3000)

        // 1. بررسی مسنجرها و IVR
        switch ($from) {
            case 'gap':
                return ['method' => 'messenger', 'provider' => 2];
            case 'igap':
                return ['method' => 'messenger', 'provider' => 8];
            case 'eitaa':
                return ['method' => 'messenger', 'provider' => 9];
            case 'bale':
                return ['method' => 'messenger', 'provider' => 10];
            case 'rubika':
                return ['method' => 'messenger', 'provider' => 12];
            case 'ivr':
                return ['method' => 'ivr', 'provider' => 1]; // IVR معمولا پرووایدر خاصی نمی‌خواهد اما عدد باید ارسال شود
        }

        // 2. بررسی پیش‌شماره‌های SMS
        // حذف +98 یا 0 ابتدایی برای بررسی دقیق‌تر
        $cleanFrom = preg_replace('/^(\+98|0098|98|0)/', '', $from);

        if (strpos($cleanFrom, '3000') === 0) {
            $provider = 1; // مگفا
        } elseif (strpos($cleanFrom, '2000') === 0) {
            $provider = 2; // آتیه
        } elseif (strpos($cleanFrom, '9000') === 0) {
            $provider = 3; // آسیاتک
        } elseif (strpos($cleanFrom, '50004') === 0) {
            $provider = 5; // ارمغان راه طلایی
        } elseif (is_numeric($from) && strlen($from) < 5) {
            // اگر کاربر مستقیماً عدد پرووایدر (مثلا 1 یا 10) را وارد کرده باشد
            $provider = (int)$from;
            // اگر عدد مربوط به مسنجرها باشد، متد را تغییر می‌دهیم
            if (in_array($provider, [8, 9, 10, 12])) {
                $method = 'messenger';
            } elseif ($provider == 2) {
                // عدد 2 هم می‌تواند آتیه (SMS) باشد هم گپ (Messenger)
                // پیش‌فرض را SMS می‌گیریم مگر کاربر کلمه gap را نوشته باشد
                $method = 'sms';
            }
        }

        return ['method' => $method, 'provider' => $provider];
    }

    /**
     * دریافت اعتبار
     */
    public static function getCredit($apiKey)
    {
         // چون API جدید متد مشخصی برای دریافت اعتبار عددی ساده ندارد، لینک نمایش داده می‌شود
        return '<a href="https://panel.msgway.com" target="_blank" style="text-decoration:none;">مشاهده در پنل کاربری</a>';
    }

    /**
     * بررسی پترن (AJAX)
     */
    public static function checkPattern()
    {
        $patternCode = isset($_POST['patternCode']) ? sanitize_text_field($_POST['patternCode']) : '';
        
        if (empty($patternCode)) {
            return array('status' => -1, 'message' => 'کد الگو را وارد نمایید.');
        }

        $options = get_option('gf_smspanel_msgway');
        if (empty($options['apikey'])) {
            return array('status' => -1, 'message' => 'API Key تنظیم نشده است.');
        }

        $body = ['templateID' => (int)$patternCode];
        $response = self::remoteRequest('/template/get', $options['apikey'], $body);

        if (is_wp_error($response)) {
            return array('status' => -1, 'message' => $response->get_error_message());
        }

        if (isset($response['status']) && $response['status'] === 'success') {
            $templateData = $response['data']['template'] ?? '';
            
            // استخراج متغیرها
            preg_match_all('/\[([A-Za-z0-9_]+)\]/', $templateData, $matches);
            $vars = !empty($matches[1]) ? $matches[1] : [];
            $vars = array_unique($vars);

            return array(
                'status' => 0, 
                'message' => $templateData, 
                'vars' => array_values($vars)
            );
        } else {
            $msg = isset($response['error']['message']) ? $response['error']['message'] : 'خطا در دریافت الگو';
            return array('status' => -1, 'message' => $msg);
        }
    }

    // --- توابع کمکی ---

    private static function prepareRecipients($to)
    {
        if (!is_array($to)) {
            $to = explode(',', $to);
        }
        
        $cleaned = [];
        foreach ($to as $number) {
            $normalized = self::mobileNormalize($number);
            if ($normalized) {
                $cleaned[] = $normalized;
            }
        }
        return array_unique($cleaned);
    }

    public static function mobileNormalize($mobile)
    {
        $mobile = self::convertDigits($mobile);
        $mobile = trim($mobile);
        $mobile = preg_replace('/[^0-9+]/', '', $mobile);

        if (empty($mobile)) return null;

        if (preg_match('/^(?:\+98|0098|98|0)?(9\d{9})$/', $mobile, $matches)) {
            return '+98' . $matches[1];
        }

        return $mobile; // برای شماره‌های بین‌المللی یا نامشخص همان را برمی‌گردانیم
    }

    private static function convertDigits($string)
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $num = range(0, 9);
        $converted = str_replace($persian, $num, $string);
        return str_replace($arabic, $num, $converted);
    }

    private static function remoteRequest($endpoint, $apiKey, $body = [])
    {
        $url = self::API_URL . $endpoint;
        
        $args = [
            'body'        => json_encode($body),
            'headers'     => [
                'Content-Type'    => 'application/json',
                'apiKey'          => $apiKey,
                'accept-language' => 'fa'
            ],
            'timeout'     => 15,
            'data_format' => 'body'
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $responseBody = wp_remote_retrieve_body($response);
        return json_decode($responseBody, true);
    }
}