<?php
/**
 * مدیریت ارسال پیامک (Send Logic)
 * هسته مرکزی پردازش و ارسال پیامک‌ها در افزونه
 * * @package    Gravity Forms SMS - MsgWay
 * @author     Ready Studio <info@readystudio.ir>
 * @license    GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_Form_Send
{
    /**
     * راه‌اندازی کلاس و اتصال هوک‌های مورد نیاز
     */
    public static function construct()
    {
        // 1. هوک ارسال فرم (بعد از سابمیت موفق)
        add_filter('gform_confirmation', array(__CLASS__, 'after_submit'), 10, 4);
        
        // 2. هوک تغییر وضعیت پرداخت (برای درگاه‌های بانکی گرویتی)
        add_action('gform_post_payment_status', array(__CLASS__, 'after_payment'), 10, 3);
        
        // 3. هوک تکمیل پرداخت (ادان‌های استاندارد مثل PayPal)
        add_action('gform_paypal_fulfillment', array(__CLASS__, 'paypal_fulfillment'), 10, 4);
        
        // 4. فیلتر جایگزینی تگ‌های اختصاصی (رفع باگ Deprecated در PHP 8.1+)
        add_filter('gform_replace_merge_tags', array(__CLASS__, 'tags'), 10, 7);
    }

    /**
     * تابع اصلی و عمومی ارسال پیامک
     * این متد نقطه ورود تمام درخواست‌های ارسال (تکی، گروهی، وریفای و...) است.
     * * @param string $to شماره موبایل گیرنده
     * @param string $msg متن پیامک
     * @param string $from شماره فرستنده (اختیاری)
     * @param int|string $form_id شناسه فرم (جهت لاگ)
     * @param int|string $entry_id شناسه ورودی (جهت لاگ)
     * @return string نتیجه ارسال (OK یا متن خطا)
     */
    public static function Send($to, $msg, $from = '', $form_id = 0, $entry_id = 0)
    {
        // 1. دریافت تنظیمات
        $settings = GF_MESSAGEWAY::get_option();
        
        // 2. تعیین فرستنده (اگر خالی بود از تنظیمات پیش‌فرض)
        if (empty($from)) {
            $raw_from = isset($settings["from"]) ? $settings["from"] : '';
            // اگر تنظیمات شامل چند شماره بود (CSV)، اولین مورد را بردار
            $from_parts = explode(',', $raw_from);
            $from = trim($from_parts[0]);
        }

        // 3. نرمال‌سازی و اعتبارسنجی شماره گیرنده
        $to = self::change_mobile($to);
        
        // لاگ شروع ارسال (برای دیباگ)
        if (function_exists('msgway_debug')) {
            msgway_debug(array(
                'action' => 'Send Init',
                'to' => $to,
                'from' => $from,
                'msg' => $msg
            ));
        }

        if (empty($to)) {
            return __('شماره گیرنده نامعتبر یا خالی است.', 'GF_SMS');
        }

        // 4. ارسال درخواست به وب‌سرویس (از طریق کلاس رابط Gateway)
        // اطمینان از لود بودن کلاس WebServices
        if (!class_exists('GF_MESSAGEWAY_WebServices')) {
            if (defined('GF_SMS_DIR') && file_exists(GF_SMS_DIR . 'includes/gateways.php')) {
                require_once(GF_SMS_DIR . 'includes/gateways.php');
            }
        }

        if (class_exists('GF_MESSAGEWAY_WebServices')) {
            $result = GF_MESSAGEWAY_WebServices::action($settings, "send", $from, $to, $msg);
        } else {
            $result = "Error: Gateway Class Not Found";
        }

        // 5. ثبت در گزارشات (Log)
        // اگر نتیجه "OK" بود یا حاوی خطای فاحش نبود، لاگ می‌کنیم
        // نکته: برخی درگاه‌ها شناسه عددی برمی‌گردانند، بنابراین is_numeric هم چک می‌شود
        if (class_exists('GF_MESSAGEWAY_SQL')) {
            // همیشه تلاش را لاگ می‌کنیم تا کاربر بداند اقدامی شده
            GF_MESSAGEWAY_SQL::save_sms_sent($form_id, $entry_id, $from, $to, $msg, $result);
        }
        
        // لاگ نتیجه نهایی
        if (function_exists('msgway_debug')) {
            msgway_debug(array('action' => 'Send Result', 'result' => $result));
        }

        return $result;
    }

    /**
     * استانداردسازی شماره موبایل (بدون وابستگی به کلاس درگاه)
     * این تابع تضمین می‌کند شماره‌ها با فرمت صحیح (+98...) ارسال شوند
     */
    public static function change_mobile($mobile)
    {
        if (empty($mobile)) return '';

        // پشتیبانی از ورودی‌های چندگانه (CSV)
        if (strpos($mobile, ',') !== false) {
            $mobiles = explode(',', $mobile);
            $clean_mobiles = array();
            foreach ($mobiles as $m) {
                $c = self::change_mobile($m);
                if ($c) $clean_mobiles[] = $c;
            }
            return implode(',', array_unique($clean_mobiles));
        }

        // 1. تبدیل اعداد فارسی/عربی به انگلیسی
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = range(0, 9);
        
        $mobile = str_replace($persian, $english, $mobile);
        $mobile = str_replace($arabic, $english, $mobile);
        
        // 2. حذف کاراکترهای غیرمجاز (فقط اعداد و + باقی بماند)
        $mobile = preg_replace('/[^0-9+]/', '', $mobile);
        
        if (empty($mobile)) return '';

        // 3. لاجیک اختصاصی شماره‌های ایران
        // الگوهای رایج: 0912..., 912..., +98912..., 0098912...
        if (preg_match('/^(?:\+98|0098|98|0)?(9\d{9})$/', $mobile, $matches)) {
            return '+98' . $matches[1];
        }
        
        // 4. برای شماره‌های بین‌المللی یا نامشخص، اگر + ندارد اضافه کن (اختیاری)
        // اگر شماره با 00 شروع شود تبدیل به +
        if (strpos($mobile, '00') === 0) {
            return '+' . substr($mobile, 2);
        }
        
        return $mobile;
    }

    // =========================================================================
    // هوک‌های گرویتی فرم (Trigger Points)
    // =========================================================================

    public static function after_submit($confirmation, $form, $entry, $ajax)
    {
        self::process_feeds($form, $entry, 'submit');
        return $confirmation;
    }

    public static function after_payment($entry, $config, $status)
    {
        $form = GFAPI::get_form($entry['form_id']);
        $entry['payment_status'] = $status; // آپدیت دستی وضعیت برای دسترسی در لاجیک
        self::process_feeds($form, $entry, 'payment_status');
    }

    public static function paypal_fulfillment($entry, $config, $transaction_id, $amount)
    {
        $form = GFAPI::get_form($entry['form_id']);
        $entry['payment_status'] = 'Paid';
        self::process_feeds($form, $entry, 'payment_status');
    }

    // =========================================================================
    // پردازش منطق فیدها (Feed Logic)
    // =========================================================================

    /**
     * بررسی تمام فیدهای متصل به فرم و ارسال پیامک در صورت تطابق شرایط
     */
    private static function process_feeds($form, $entry, $event)
    {
        if (!isset($form['id'])) return;

        // دریافت تمام فیدهای فعال این فرم
        $feeds = GF_MESSAGEWAY_SQL::get_feed_via_formid($form['id'], true);

        if (empty($feeds)) return;

        foreach ($feeds as $feed) {
            $meta = $feed['meta'];
            
            // --- بررسی زمان ارسال (Trigger) ---
            $when = isset($meta['when']) ? $meta['when'] : 'send_immediately';
            $payment_status = isset($entry['payment_status']) ? strtolower($entry['payment_status']) : '';
            $gateway_required = !empty($meta['gf_sms_is_gateway_checked']); // آیا تیک "فقط با درگاه" خورده؟

            $should_send = false;

            if ($event == 'submit') {
                // اگر رویداد سابمیت فرم است
                if ($when == 'send_immediately') {
                    // اگر شرط درگاه فعال باشد، نباید اینجا بفرستیم (مگر اینکه فرم اصلا درگاه نداشته باشد، که فرض بر این است دارد)
                    // اما اگر فرم قیمت ندارد یا درگاه وصل نیست، شاید کاربر بخواهد بفرستد.
                    // برای سادگی: اگر تیک "فقط با درگاه" خورده باشد، در سابمیت عادی ارسال نمی‌کنیم.
                    if (!$gateway_required) {
                        $should_send = true;
                    }
                }
            } elseif ($event == 'payment_status') {
                // اگر رویداد تغییر وضعیت پرداخت است
                if ($when == 'after_pay') {
                    // هر وضعیتی (موفق/ناموفق)
                    $should_send = true;
                } elseif ($when == 'after_pay_success') {
                    // فقط موفق
                    if (in_array($payment_status, array('paid', 'completed', 'active', 'approved'))) {
                        $should_send = true;
                    }
                } elseif ($when == 'send_immediately' && $gateway_required) {
                    // حالتی که کاربر گفته "بلافاصله" ولی تیک "فقط با درگاه" را زده
                    // منطقاً یعنی "بلافاصله بعد از درگاه"
                    $should_send = true;
                }
            }

            if (!$should_send) continue;

            // --- پردازش پیامک مدیر ---
            self::process_single_notification($feed, $form, $entry, 'admin');

            // --- پردازش پیامک کاربر ---
            self::process_single_notification($feed, $form, $entry, 'client');
        }
    }

    /**
     * پردازش تکی نوتیفیکیشن (مدیر یا کاربر)
     */
    private static function process_single_notification($feed, $form, $entry, $target)
    {
        $meta = $feed['meta'];
        $prefix = ($target == 'admin') ? 'adminsms' : 'clientsms';
        
        // 1. استخراج شماره گیرنده
        $recipients = array();

        if ($target == 'admin') {
            if (!empty($meta['to'])) {
                $recipients = explode(',', $meta['to']);
            }
        } else { // client
            // الف) شماره از فیلد فرم
            if (!empty($meta['customer_field_clientnum'])) {
                $field_val = rgar($entry, $meta['customer_field_clientnum']);
                if ($field_val) $recipients[] = $field_val;
            }
            // ب) شماره‌های CC
            if (!empty($meta['to_c'])) {
                $cc_nums = explode(',', $meta['to_c']);
                $recipients = array_merge($recipients, $cc_nums);
            }
        }

        // حذف تکراری و خالی
        $recipients = array_filter(array_unique($recipients));
        if (empty($recipients)) return;

        // 2. استخراج متن پیام
        $msg_key = ($target == 'admin') ? 'message' : 'message_c';
        $message = isset($meta[$msg_key]) ? $meta[$msg_key] : '';
        if (empty($message)) return;

        // 3. بررسی شرط (Conditional Logic)
        if (!self::check_condition($feed, $form, $entry, $prefix)) return;

        // 4. جایگزینی متغیرها
        $message = GFCommon::replace_variables($message, $form, $entry);
        
        // 5. تعیین فرستنده اختصاصی این فید
        $from = isset($meta['from']) ? $meta['from'] : '';

        // 6. ارسال نهایی
        foreach ($recipients as $to) {
            self::Send($to, $message, $from, $form['id'], $entry['id']);
        }
    }

    /**
     * بررسی منطق شرطی
     */
    private static function check_condition($feed, $form, $entry, $prefix)
    {
        $meta = $feed['meta'];
        $enabled_key = $prefix . '_conditional_enabled';
        
        // اگر شرط غیرفعال است، اجازه ارسال بده
        if (empty($meta[$enabled_key])) return true;

        $type = isset($meta[$prefix . '_conditional_type']) ? $meta[$prefix . '_conditional_type'] : 'all';
        $field_ids = isset($meta[$prefix . '_conditional_field_id']) ? $meta[$prefix . '_conditional_field_id'] : array();
        $operators = isset($meta[$prefix . '_conditional_operator']) ? $meta[$prefix . '_conditional_operator'] : array();
        $values = isset($meta[$prefix . '_conditional_value']) ? $meta[$prefix . '_conditional_value'] : array();

        if (empty($field_ids)) return true;

        $match_count = 0;
        $total_conditions = 0;

        foreach ($field_ids as $i => $field_id) {
            if (empty($field_id)) continue;
            $total_conditions++;

            $field = RGFormsModel::get_field($form, $field_id);
            if (!$field) continue;

            $source_val = RGFormsModel::get_lead_field_value($entry, $field);
            $target_val = isset($values[$i]) ? $values[$i] : '';
            $op = isset($operators[$i]) ? $operators[$i] : 'is';

            if (RGFormsModel::is_value_match($source_val, $target_val, $op)) {
                $match_count++;
            }
        }

        if ($total_conditions == 0) return true;

        if ($type == 'all') {
            return $match_count == $total_conditions;
        } else { // any
            return $match_count > 0;
        }
    }

    // =========================================================================
    // توابع کمکی
    // =========================================================================

    /**
     * فیلتر جایگزینی تگ‌ها (با رفع باگ Deprecated در PHP 8.1)
     */
    public static function tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
    {
        // اطمینان از اینکه ورودی تکست معتبر است
        if (!is_string($text)) return $text;

        $tags = array(
            '{payment_status}' => isset($entry['payment_status']) ? $entry['payment_status'] : '',
            '{transaction_id}' => isset($entry['transaction_id']) ? $entry['transaction_id'] : '',
            '{entry_id}'       => isset($entry['id']) ? $entry['id'] : '',
            '{date_mdy}'       => isset($entry['date_created']) ? date_i18n('Y/m/d', strtotime($entry['date_created'])) : '',
            '{ip}'             => isset($entry['ip']) ? $entry['ip'] : '',
            '{source_url}'     => isset($entry['source_url']) ? $entry['source_url'] : '',
        );

        foreach ($tags as $tag => $value) {
            // تبدیل اجباری مقدار به رشته برای جلوگیری از خطای Passing null to str_replace
            $safe_value = (string)$value;
            $text = str_replace($tag, $safe_value, $text);
        }

        return $text;
    }
}