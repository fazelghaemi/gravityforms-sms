<?php
if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_Form_Send
{
    public static function construct()
    {
        // هوک‌های اصلی گرویتی فرم برای زمان‌های مختلف ارسال
        add_filter('gform_confirmation', array(__CLASS__, 'after_submit'), 9999999, 4);
        add_action('gform_post_payment_status', array(__CLASS__, 'after_payment'), 999999, 4);
        add_action('gform_paypal_fulfillment', array(__CLASS__, 'paypal_fulfillment'), 999999, 4);
        
        // جایگزینی متغیرها
        add_filter('gform_replace_merge_tags', array(__CLASS__, 'tags'), 99999, 7);
    }

    /**
     * تابع اصلی ارسال پیامک
     */
    public static function Send($to, $msg, $from = '', $form_id = '', $entry_id = '', $verify_code = '')
    {
        $settings = GF_MESSAGEWAY::get_option();
        
        // تعیین فرستنده پیش‌فرض
        $default_froms = explode(',', isset($settings["from"]) ? $settings["from"] : '');
        $default_from = trim($default_froms[0]);
        
        // اگر فرستنده خاصی پاس داده نشده، از پیش‌فرض استفاده کن
        $from = (!empty($from)) ? $from : $default_from;

        // نرمال‌سازی شماره گیرنده
        $to = self::change_mobile($to);
        if (empty($to)) {
            return 'شماره گیرنده نامعتبر است.';
        }

        // ارسال به وب‌سرویس (Gateway)
        $result = GF_MESSAGEWAY_WebServices::action($settings, "send", $from, $to, $msg);

        // ذخیره در دیتابیس لاگ‌ها
        if ($result == 'OK' || strpos($result, 'OK') !== false) {
            GF_MESSAGEWAY_SQL::save_sms_sent($form_id, $entry_id, $from, $to, $msg, $verify_code);
        } else {
            // می‌توان خطاهای ناموفق را هم لاگ کرد (اختیاری)
            // GF_MESSAGEWAY_SQL::save_sms_sent($form_id, $entry_id, $from, $to, $msg . " [Error: $result]", $verify_code);
        }

        return $result;
    }

    /**
     * نرمال‌سازی و استانداردسازی شماره موبایل
     */
    public static function change_mobile($mobile = '', $code = '')
    {
        if (empty($mobile)) {
            return '';
        }

        // اگر چندین شماره با کاما جدا شده‌اند، بازگشتی انجام بده
        if (strpos($mobile, ',') !== false) {
            $mobiles = explode(',', $mobile);
            $clean_mobiles = array();
            foreach ($mobiles as $m) {
                $clean = self::change_mobile_separately($m, $code);
                if ($clean) $clean_mobiles[] = $clean;
            }
            return implode(',', array_unique($clean_mobiles));
        }

        return self::change_mobile_separately($mobile, $code);
    }

    /**
     * پردازش تکی شماره موبایل
     */
    private static function change_mobile_separately($mobile, $code_override = '')
    {
        // تبدیل اعداد فارسی/عربی به انگلیسی
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = range(0, 9);
        
        $mobile = str_replace($persian, $english, $mobile);
        $mobile = str_replace($arabic, $english, $mobile);
        
        // حذف کاراکترهای غیر عددی به جز +
        $mobile = preg_replace('/[^0-9+]/', '', $mobile);
        
        if (empty($mobile)) return '';

        // دریافت کد کشور از تنظیمات اگر اورراید نشده باشد
        if (empty($code_override)) {
            $settings = GF_MESSAGEWAY::get_option();
            $code_override = !empty($settings["code"]) ? $settings["code"] : '+98';
        }

        // اگر شماره با + شروع شده، استاندارد است
        if (strpos($mobile, '+') === 0) {
            return $mobile;
        }

        // اگر با 00 شروع شده، تبدیل به + شود
        if (strpos($mobile, '00') === 0) {
            return '+' . substr($mobile, 2);
        }

        // اگر با 0 شروع شده، 0 را حذف کن و کد کشور را اضافه کن (فرض بر شماره داخلی)
        if (strpos($mobile, '0') === 0) {
            $mobile = substr($mobile, 1);
        }

        // اطمینان از وجود + در کد کشور
        $code_override = (strpos($code_override, '+') !== 0) ? '+' . $code_override : $code_override;

        return $code_override . $mobile;
    }

    // --- توابع هوک گرویتی فرم ---

    public static function save_number_to_meta($entry, $form)
    {
        if (!isset($form["id"]) || !is_numeric($form["id"])) return;

        $feeds = GF_MESSAGEWAY_SQL::get_feed_via_formid($form["id"], true);
        $all_numbers = array();

        foreach ((array)$feeds as $feed) {
            if (!isset($feed["id"]) || !is_numeric($feed["id"])) continue;

            // استخراج شماره از فیلد داینامیک فرم
            $client_number = '';
            
            // 1. بررسی فیلد مپ شده برای شماره کاربر
            if (isset($feed["meta"]["customer_field_clientnum"])) {
                $field_id = $feed["meta"]["customer_field_clientnum"];
                $raw_val = rgpost('input_' . str_replace(".", "_", $field_id));
                if ($raw_val) {
                    $client_number = self::change_mobile(sanitize_text_field($raw_val));
                }
            }

            // 2. بررسی شماره‌های ثابت اضافی (Extra Numbers)
            $extra_numbers = !empty($feed["meta"]["to_c"]) ? self::change_mobile($feed["meta"]["to_c"]) : '';

            $final_client_numbers = array_filter(array_merge(
                explode(',', $client_number), 
                explode(',', $extra_numbers)
            ));

            if (!empty($final_client_numbers)) {
                $joined = implode(',', $final_client_numbers);
                $all_numbers[] = $joined;
                // ذخیره متای مخصوص این فید (برای استفاده در لاجیک ارسال)
                gform_update_meta($entry["id"], "client_mobile_number_" . $feed["id"], $joined);
            }
        }

        // ذخیره کلیه شماره‌ها در متای اصلی (برای دسترسی‌های عمومی)
        if (!empty($all_numbers)) {
            $unique_all = implode(',', array_unique(explode(',', implode(',', $all_numbers))));
            gform_update_meta($entry["id"], "client_mobile_numbers", $unique_all);
        }
    }

    public static function after_submit($confirmation, $form, $entry, $ajax)
    {
        self::save_number_to_meta($entry, $form);
        self::process_feeds($entry, $form, 'submit');
        return $confirmation;
    }

    public static function after_payment($entry, $config, $status, $transaction_id)
    {
        $form = GFAPI::get_form($entry['form_id']);
        // وضعیت پرداخت را در متا آپدیت کنیم تا در Logic بررسی شود (هرچند گرویتی خودکار انجام می‌دهد)
        $entry['payment_status'] = $status;
        self::process_feeds($entry, $form, 'payment_status_changed', strtolower($status));
    }

    public static function paypal_fulfillment($entry, $config, $transaction_id, $amount)
    {
        $form = GFAPI::get_form($entry['form_id']);
        self::process_feeds($entry, $form, 'fulfillment', 'completed');
    }

    /**
     * پردازش فیدها و ارسال پیامک بر اساس شرایط
     */
    public static function process_feeds($entry, $form, $event, $payment_status = '')
    {
        if (!isset($form["id"])) return;

        $settings = GF_MESSAGEWAY::get_option();
        if (empty($settings["ws"]) || $settings["ws"] == 'no') {
            return; // درگاه فعال نیست
        }

        $feeds = GF_MESSAGEWAY_SQL::get_feed_via_formid($form["id"], true);

        foreach ((array)$feeds as $feed) {
            if (!isset($feed["is_active"]) || !$feed["is_active"]) continue;

            // جلوگیری از ارسال تکراری برای یک فید خاص
            if (gform_get_meta($entry["id"], "gf_smspanel_sent_" . $feed["id"]) == 'yes') {
               // continue; // در نسخه جدید شاید بخواهیم در مراحل مختلف پیامک‌های مختلف بفرستیم، پس این شرط ساده را دقیق‌تر می‌کنیم:
               // فعلا برای سادگی فرض می‌کنیم هر فید فقط یکبار اجرا شود.
               // اگر نیاز به ارسال پیامک‌های متفاوت در وضعیت‌های مختلف پرداخت است، باید کلید متا یونیک‌تر باشد (مثلا ترکیب با وضعیت).
            }

            $feed_when = isset($feed["meta"]["when"]) ? $feed["meta"]["when"] : 'send_immediately';
            
            // بررسی زمان ارسال
            $should_send = false;

            if ($feed_when == 'send_immediately' && $event == 'submit') {
                $should_send = true;
            } 
            elseif ($feed_when == 'after_pay' && ($event == 'payment_status_changed' || $event == 'fulfillment')) {
                // ارسال در هر وضعیت پرداختی (موفق یا ناموفق)
                $should_send = true;
            }
            elseif ($feed_when == 'after_pay_success') {
                // فقط پرداخت موفق
                $valid_statuses = ['paid', 'completed', 'active', 'approved'];
                if (in_array(strtolower($payment_status), $valid_statuses)) {
                    $should_send = true;
                }
            }

            // فیلتر برای توسعه‌دهندگان جهت تغییر منطق ارسال
            $should_send = apply_filters('gf_sms_should_send_feed', $should_send, $feed, $entry, $form);

            if (!$should_send) continue;

            // علامت‌گذاری به عنوان "در حال پردازش"
            gform_update_meta($entry["id"], "gf_smspanel_sent_" . $feed["id"], "yes");
            
            $from = isset($feed["meta"]["from"]) ? $feed["meta"]["from"] : '';

            // --- ارسال به مدیر ---
            if (self::check_condition($entry, $form, $feed, 'adminsms_')) {
                $admin_msg = GFCommon::replace_variables($feed["meta"]["message"], $form, $entry);
                $admin_to = isset($feed["meta"]["to"]) ? $feed["meta"]["to"] : '';
                
                if (!empty($admin_to)) {
                    $res = self::Send($admin_to, $admin_msg, $from, $form['id'], $entry['id']);
                    $note = ($res == 'OK' || strpos($res, 'OK') !== false) ? 
                        sprintf(__('پیامک به مدیر ارسال شد. (%s)', 'GF_SMS'), $admin_to) : 
                        sprintf(__('خطا در ارسال پیامک مدیر: %s', 'GF_SMS'), $res);
                    RGFormsModel::add_note($entry["id"], 0, 'SMS', $note);
                }
            }

            // --- ارسال به کاربر ---
            if (self::check_condition($entry, $form, $feed, 'clientsms_')) {
                $client_msg = GFCommon::replace_variables($feed["meta"]["message_c"], $form, $entry);
                $client_to_meta_key = "client_mobile_number_" . $feed["id"];
                $client_to = gform_get_meta($entry["id"], $client_to_meta_key);

                if (!empty($client_to)) {
                    $res = self::Send($client_to, $client_msg, $from, $form['id'], $entry['id']);
                    $note = ($res == 'OK' || strpos($res, 'OK') !== false) ? 
                        sprintf(__('پیامک به کاربر ارسال شد. (%s)', 'GF_SMS'), $client_to) : 
                        sprintf(__('خطا در ارسال پیامک کاربر: %s', 'GF_SMS'), $res);
                    RGFormsModel::add_note($entry["id"], 0, 'SMS', $note);
                }
            }
        }
    }

    /**
     * بررسی منطق شرطی (Conditional Logic)
     */
    public static function check_condition($entry, $form, $feed, $prefix = 'adminsms_')
    {
        $enabled_key = $prefix . 'conditional_enabled';
        
        // اگر شرط فعال نشده، پس ارسال مجاز است
        if (empty($feed['meta'][$enabled_key])) {
            return true;
        }

        // استفاده از کلاس منطق شرطی خود گرویتی فرم اگر در دسترس باشد
        // اما چون ساختار ذخیره شده در این افزونه کمی متفاوت است، دستی بررسی می‌کنیم
        
        $type = isset($feed['meta'][$prefix . 'conditional_type']) ? $feed['meta'][$prefix . 'conditional_type'] : 'all';
        $conditions_fields = isset($feed['meta'][$prefix . 'conditional_field_id']) ? $feed['meta'][$prefix . 'conditional_field_id'] : [];
        $conditions_ops = isset($feed['meta'][$prefix . 'conditional_operator']) ? $feed['meta'][$prefix . 'conditional_operator'] : [];
        $conditions_vals = isset($feed['meta'][$prefix . 'conditional_value']) ? $feed['meta'][$prefix . 'conditional_value'] : [];

        if (empty($conditions_fields)) return true;

        $match_count = 0;
        $total_conditions = 0;

        foreach ($conditions_fields as $i => $field_id) {
            if (empty($field_id)) continue;
            $total_conditions++;

            $field = RGFormsModel::get_field($form, $field_id);
            $field_value = RGFormsModel::get_lead_field_value($entry, $field);
            
            $target_value = isset($conditions_vals[$i]) ? $conditions_vals[$i] : '';
            $operator = isset($conditions_ops[$i]) ? $conditions_ops[$i] : 'is';

            if (RGFormsModel::is_value_match($field_value, $target_value, $operator)) {
                $match_count++;
            }
        }

        if ($type == 'all') {
            return $match_count == $total_conditions;
        } else { // any
            return $match_count > 0;
        }
    }

    /**
     * جایگزینی تگ‌های اختصاصی (مثل وضعیت پرداخت)
     */
    public static function tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
    {
        // تگ‌های پیش‌فرض گرویتی کار می‌کنند، اینجا فقط تگ‌های خاص اضافه می‌شود
        $custom_tags = array(
            '{payment_gateway}' => rgar($entry, 'payment_method'),
            '{payment_status}'  => rgar($entry, 'payment_status'),
            '{transaction_id}'  => rgar($entry, 'transaction_id'),
        );

        foreach ($custom_tags as $tag => $value) {
            $text = str_replace($tag, $value, $text);
        }
        
        return $text;
    }
}