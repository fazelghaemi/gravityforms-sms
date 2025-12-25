<?php
/**
 * مدیریت ارسال پیامک (Send Logic)
 * پردازش فیدها، بررسی شرایط، جایگزینی متغیرها و ارسال نهایی
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
     * راه‌اندازی و تعریف هوک‌ها
     */
    public static function construct()
    {
        // 1. هوک ارسال فرم (بعد از ذخیره ورودی)
        add_filter('gform_confirmation', array(__CLASS__, 'after_submit'), 10, 4);
        
        // 2. هوک تغییر وضعیت پرداخت (برای درگاه‌ها)
        add_action('gform_post_payment_status', array(__CLASS__, 'after_payment'), 10, 3);
        
        // 3. هوک تکمیل پرداخت (ادان‌های استاندارد مثل پی‌پال)
        add_action('gform_paypal_fulfillment', array(__CLASS__, 'paypal_fulfillment'), 10, 4);
        
        // 4. فیلتر جایگزینی تگ‌های اختصاصی (رفع باگ نال)
        add_filter('gform_replace_merge_tags', array(__CLASS__, 'tags'), 10, 7);
    }

    /**
     * تابع اصلی ارسال پیامک (Static Wrapper)
     * این تابع توسط بخش‌های مختلف (وریفای، ارسال مجدد، فیدها) صدا زده می‌شود
     * * @param string $to شماره گیرنده
     * @param string $msg متن پیام
     * @param string $from فرستنده (اختیاری)
     * @param int $form_id شناسه فرم (اختیاری)
     * @param int $entry_id شناسه ورودی (اختیاری)
     */
    public static function Send($to, $msg, $from = '', $form_id = 0, $entry_id = 0)
    {
        $settings = GF_MESSAGEWAY::get_option();
        
        // تعیین فرستنده
        // اگر فرستنده خاصی در آرگومان‌ها نبود، از تنظیمات کلی بخوان
        if (empty($from)) {
            $from = isset($settings["from"]) ? $settings["from"] : '';
            // اگر تنظیمات کلی هم چندتایی بود (CSV)، اولی را به عنوان پیش‌فرض بردار
            $froms = explode(',', $from);
            $from = trim($froms[0]);
        }

        // نرمال‌سازی شماره گیرنده
        $to = self::change_mobile($to);
        if (empty($to)) {
            return __('شماره گیرنده نامعتبر است.', 'GF_SMS');
        }

        // ارسال به وب‌سرویس از طریق کلاس گیت‌وی
        $result = GF_MESSAGEWAY_WebServices::action($settings, "send", $from, $to, $msg);

        // لاگ کردن نتیجه در دیتابیس
        // معمولا درگاه‌های ما "OK" یا کد عددی برمی‌گردانند، یا متن خطا
        // اگر نتیجه شامل خطا نباشد، لاگ می‌کنیم (یا همه را لاگ می‌کنیم)
        if (class_exists('GF_MESSAGEWAY_SQL')) {
            GF_MESSAGEWAY_SQL::save_sms_sent($form_id, $entry_id, $from, $to, $msg, $result);
        }

        return $result;
    }

    /**
     * نرمال‌سازی و استانداردسازی شماره موبایل
     */
    public static function change_mobile($mobile)
    {
        if (empty($mobile)) return '';

        // اگر چندین شماره با کاما جدا شده‌اند، بازگشتی حل کن
        if (strpos($mobile, ',') !== false) {
            $mobiles = explode(',', $mobile);
            $clean_mobiles = array();
            foreach ($mobiles as $m) {
                $c = self::change_mobile($m);
                if ($c) $clean_mobiles[] = $c;
            }
            return implode(',', array_unique($clean_mobiles));
        }

        // استفاده از متد استاندارد کلاس درگاه اصلی (اگر در دسترس باشد)
        // این بهترین روش است چون منطق یکپارچه می‌ماند
        if (class_exists('GF_MESSAGEWAY_MSGWay') && method_exists('GF_MESSAGEWAY_MSGWay', 'mobileNormalize')) {
            return GF_MESSAGEWAY_MSGWay::mobileNormalize($mobile);
        }

        // فال‌بک (اگر کلاس درگاه لود نشده باشد)
        // تبدیل اعداد فارسی به انگلیسی
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = range(0, 9);
        $mobile = str_replace($persian, $english, $mobile);
        
        $mobile = preg_replace('/[^0-9+]/', '', $mobile);
        
        // اصلاح فرمت ایران
        if (preg_match('/^(?:\+98|0098|98|0)?(9\d{9})$/', $mobile, $matches)) {
            return '+98' . $matches[1];
        }
        
        return $mobile;
    }

    // =========================================================================
    // هوک‌های گرویتی فرم
    // =========================================================================

    /**
     * هوک بعد از ثبت فرم (Confirmation)
     */
    public static function after_submit($confirmation, $form, $entry, $ajax)
    {
        // پردازش فیدها با رویداد 'submit'
        self::process_feeds($form, $entry, 'submit');
        return $confirmation;
    }

    /**
     * هوک تغییر وضعیت پرداخت (Payment Status Changed)
     */
    public static function after_payment($entry, $config, $status)
    {
        $form = GFAPI::get_form($entry['form_id']);
        // آپدیت وضعیت در آبجکت entry برای استفاده در منطق ارسال
        $entry['payment_status'] = $status;
        self::process_feeds($form, $entry, 'payment_status');
    }

    /**
     * هوک تکمیل پرداخت (PayPal Fulfillment)
     */
    public static function paypal_fulfillment($entry, $config, $transaction_id, $amount)
    {
        $form = GFAPI::get_form($entry['form_id']);
        // فرض بر این است که تکمیل یعنی پرداخت موفق
        $entry['payment_status'] = 'Paid';
        self::process_feeds($form, $entry, 'payment_status');
    }

    // =========================================================================
    // پردازش منطق ارسال (Feeds Logic)
    // =========================================================================

    /**
     * پردازش تمام فیدهای متصل به فرم و ارسال در صورت تطابق شرایط
     */
    private static function process_feeds($form, $entry, $event)
    {
        if (!isset($form['id'])) return;

        // دریافت فیدهای فعال این فرم
        $feeds = GF_MESSAGEWAY_SQL::get_feed_via_formid($form['id'], true);

        foreach ($feeds as $feed) {
            $meta = $feed['meta'];
            
            // 1. بررسی زمان ارسال (Trigger Check)
            $when = isset($meta['when']) ? $meta['when'] : 'send_immediately';
            $payment_status = isset($entry['payment_status']) ? strtolower($entry['payment_status']) : '';
            
            $should_send = false;

            if ($event == 'submit') {
                // در مرحله سابمیت هستیم
                if ($when == 'send_immediately') {
                    // بررسی شرط "فقط در صورت اتصال به درگاه"
                    // اگر این تیک خورده باشد، نباید در سابمیت عادی ارسال کنیم (چون هنوز پرداخت نشده)
                    // مگر اینکه فرم اصلا درگاه نداشته باشد؟ خیر، منطق میگوید صبر کن.
                    $is_gateway_condition = !empty($meta['gf_sms_is_gateway_checked']);
                    
                    if (!$is_gateway_condition) {
                        $should_send = true;
                    }
                }
            } elseif ($event == 'payment_status') {
                // در مرحله تغییر وضعیت پرداخت هستیم
                if ($when == 'after_pay') {
                    // هر وضعیتی (موفق یا ناموفق)
                    $should_send = true;
                } elseif ($when == 'after_pay_success') {
                    // فقط موفق
                    if (in_array($payment_status, array('paid', 'completed', 'active', 'approved'))) {
                        $should_send = true;
                    }
                } elseif ($when == 'send_immediately' && !empty($meta['gf_sms_is_gateway_checked'])) {
                    // اگر کاربر گفته "بلافاصله" ولی تیک "همراه با درگاه" را زده، اینجا فرصت ارسال است
                    // (معمولا بهتر است "بعد از پرداخت" را انتخاب کند، اما این یک فال‌بک است)
                    $should_send = true;
                }
            }

            if (!$should_send) continue;

            // 2. پردازش پیامک مدیر
            self::process_admin_sms($feed, $form, $entry);

            // 3. پردازش پیامک کاربر
            self::process_client_sms($feed, $form, $entry);
        }
    }

    /**
     * پردازش و ارسال پیامک مدیر
     */
    private static function process_admin_sms($feed, $form, $entry)
    {
        $meta = $feed['meta'];
        $to = isset($meta['to']) ? $meta['to'] : '';
        $msg = isset($meta['message']) ? $meta['message'] : '';
        $from = isset($meta['from']) ? $meta['from'] : ''; // فرستنده اختصاصی فید

        if (empty($to) || empty($msg)) return;

        // بررسی شرط (Conditional Logic)
        if (!self::check_condition($feed, $form, $entry, 'adminsms')) return;

        // جایگزینی متغیرها
        $msg = GFCommon::replace_variables($msg, $form, $entry);
        
        // ارسال (پشتیبانی از چند گیرنده)
        $recipients = explode(',', $to);
        foreach ($recipients as $recipient) {
            self::Send(trim($recipient), $msg, $from, $form['id'], $entry['id']);
        }
    }

    /**
     * پردازش و ارسال پیامک کاربر
     */
    private static function process_client_sms($feed, $form, $entry)
    {
        $meta = $feed['meta'];
        $msg = isset($meta['message_c']) ? $meta['message_c'] : '';
        $from = isset($meta['from']) ? $meta['from'] : '';

        if (empty($msg)) return;

        // یافتن شماره موبایل کاربر
        $to = '';
        
        // الف) از فیلد انتخاب شده در تنظیمات فید
        if (!empty($meta['customer_field_clientnum'])) {
            $field_id = $meta['customer_field_clientnum'];
            $field_val = rgar($entry, $field_id);
            if ($field_val) $to = $field_val;
        }

        // ب) شماره‌های اضافی (CC)
        $cc = isset($meta['to_c']) ? $meta['to_c'] : '';

        // بررسی شرط
        if (!self::check_condition($feed, $form, $entry, 'clientsms')) return;

        // جایگزینی متغیرها
        $msg = GFCommon::replace_variables($msg, $form, $entry);

        // ارسال به کاربر اصلی
        if (!empty($to)) {
            self::Send($to, $msg, $from, $form['id'], $entry['id']);
        }

        // ارسال به گیرندگان CC
        if (!empty($cc)) {
            $ccs = explode(',', $cc);
            foreach ($ccs as $cc_num) {
                self::Send(trim($cc_num), $msg, $from, $form['id'], $entry['id']);
            }
        }
    }

    /**
     * بررسی منطق شرطی
     */
    private static function check_condition($feed, $form, $entry, $prefix)
    {
        $meta = $feed['meta'];
        $enabled = isset($meta[$prefix . '_conditional_enabled']) ? $meta[$prefix . '_conditional_enabled'] : false;

        if (!$enabled) return true; // شرط غیرفعال است، پس ارسال مجاز است

        $type = isset($meta[$prefix . '_conditional_type']) ? $meta[$prefix . '_conditional_type'] : 'all';
        $field_ids = isset($meta[$prefix . '_conditional_field_id']) ? $meta[$prefix . '_conditional_field_id'] : array();
        $operators = isset($meta[$prefix . '_conditional_operator']) ? $meta[$prefix . '_conditional_operator'] : array();
        $values = isset($meta[$prefix . '_conditional_value']) ? $meta[$prefix . '_conditional_value'] : array();

        if (empty($field_ids)) return true;

        $match_count = 0;
        $conditions_count = 0;

        foreach ($field_ids as $i => $field_id) {
            if (empty($field_id)) continue;
            
            $conditions_count++;
            
            $operator = isset($operators[$i]) ? $operators[$i] : 'is';
            $target_value = isset($values[$i]) ? $values[$i] : '';
            
            // دریافت فیلد و مقدار آن از Entry
            $field = RGFormsModel::get_field($form, $field_id);
            if (empty($field)) continue;
            
            // بررسی مخفی بودن فیلد (اختیاری)
            // if (RGFormsModel::is_field_hidden($form, $field, array())) continue;

            $source_value = RGFormsModel::get_lead_field_value($entry, $field);

            if (RGFormsModel::is_value_match($source_value, $target_value, $operator)) {
                $match_count++;
            }
        }

        if ($conditions_count == 0) return true;

        if ($type == 'all') {
            return $match_count == $conditions_count;
        } else { // any
            return $match_count > 0;
        }
    }

    // =========================================================================
    // توابع کمکی
    // =========================================================================

    /**
     * جایگزینی تگ‌های اختصاصی و رفع مشکل Deprecated در PHP 8.1
     */
    public static function tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
    {
        // لیست تگ‌های اختصاصی این پلاگین
        // استفاده از اپراتور نال سیف (??) یا کستینگ به string برای جلوگیری از خطای null
        
        $tags = array(
            '{payment_status}' => isset($entry['payment_status']) ? (string)$entry['payment_status'] : '',
            '{transaction_id}' => isset($entry['transaction_id']) ? (string)$entry['transaction_id'] : '',
            '{entry_id}'       => isset($entry['id']) ? (string)$entry['id'] : '',
            '{date_mdy}'       => isset($entry['date_created']) ? date_i18n('Y/m/d', strtotime($entry['date_created'])) : '',
            '{ip}'             => isset($entry['ip']) ? (string)$entry['ip'] : '',
            '{source_url}'     => isset($entry['source_url']) ? (string)$entry['source_url'] : '',
        );

        // جایگزینی امن
        foreach ($tags as $tag => $value) {
            // اطمینان حاصل می‌کنیم که مقدار جایگزین حتماً رشته است
            $safe_val = is_null($value) ? '' : $value;
            $text = str_replace($tag, $safe_val, $text);
        }

        return $text;
    }
}