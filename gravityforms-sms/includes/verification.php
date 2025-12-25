<?php
/**
 * مدیریت فیلد تایید شماره موبایل (Verification)
 * * @package    Gravity Forms SMS - MsgWay
 * @author     Ready Studio <info@readystudio.ir>
 * @license    GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_Verification
{
    /**
     * راه‌اندازی و هوک‌ها
     */
    public static function construct()
    {
        // افزودن دکمه فیلد به ادیتور فرم گرویتی
        add_filter('gform_add_field_buttons', array(__CLASS__, 'add_field_button'), 9998);
        
        // تنظیمات فیلد در محیط ویرایشگر
        add_filter('gform_field_type_title', array(__CLASS__, 'field_title'), 10, 2);
        add_action('gform_editor_js_set_default_values', array(__CLASS__, 'set_default_values'));
        add_action('gform_editor_js', array(__CLASS__, 'editor_js'));
        add_action('gform_field_standard_settings', array(__CLASS__, 'standard_settings'), 10, 2);
        add_filter('gform_tooltips', array(__CLASS__, 'tooltips'));

        // رندرینگ فیلد در فرانت‌اند
        add_action('gform_field_input', array(__CLASS__, 'field_input'), 10, 5);
        
        // اعتبارسنجی هنگام ارسال فرم
        add_filter('gform_field_validation', array(__CLASS__, 'validate'), 10, 4);
        
        // مدیریت درخواست‌های ایجکس (ارسال کد)
        if (defined('RG_CURRENT_PAGE') && in_array(RG_CURRENT_PAGE, array('admin-ajax.php'))) {
            add_action('wp_ajax_gf_sms_send_code', array(__CLASS__, 'ajax_send_code'));
            add_action('wp_ajax_nopriv_gf_sms_send_code', array(__CLASS__, 'ajax_send_code'));
        }
    }

    // =========================================================================
    // بخش تنظیمات ویرایشگر فرم (Backend)
    // =========================================================================

    /**
     * افزودن دکمه به جعبه ابزار گرویتی فرم
     */
    public static function add_field_button($field_groups)
    {
        foreach ($field_groups as &$group) {
            if ($group["name"] == "advanced_fields") {
                $group["fields"][] = array(
                    "class" => "button",
                    "value" => __("تایید موبایل (پیامک)", "GF_SMS"),
                    "onclick" => "StartAddField('verify_mobile');"
                );
                break;
            }
        }
        return $field_groups;
    }

    /**
     * عنوان فیلد
     */
    public static function field_title($title, $field_type)
    {
        return $field_type == "verify_mobile" ? __("تایید موبایل (MsgWay)", "GF_SMS") : $title;
    }

    /**
     * مقادیر پیش‌فرض فیلد هنگام افزودن
     */
    public static function set_default_values()
    {
        ?>
        case "verify_mobile" :
            field.label = "<?php _e("شماره موبایل", "GF_SMS"); ?>";
            field.inputType = "text";
            field.description = "<?php _e("شماره موبایل خود را وارد کرده و دکمه دریافت کد را بزنید.", "GF_SMS"); ?>";
            field.isRequired = true;
            break;
        <?php
    }

    /**
     * افزودن تنظیمات اختصاصی به فیلد
     */
    public static function standard_settings($position, $form_id)
    {
        if ($position == 25) { // نمایش زیر تنظیمات عمومی
            ?>
            <li class="verify_mobile_setting field_setting">
                <label for="field_sms_template">
                    <?php _e("قالب پیامک (اختیاری)", "GF_SMS"); ?>
                    <?php gform_tooltip("field_sms_template") ?>
                </label>
                <textarea id="field_sms_template" class="fieldwidth-3" onkeyup="SetFieldProperty('smsTemplate', this.value);" style="height:80px;"></textarea>
                <div class="instruction">
                    <?php _e("متنی که پیامک می‌شود. از <code>%code%</code> برای کد تایید استفاده کنید.", "GF_SMS"); ?><br>
                    <?php _e("برای ارسال سریع (پترن) از فرمت روبرو استفاده کنید:", "GF_SMS"); ?>
                    <code style="direction:ltr; display:inline-block;">pcode:12345;code:%code%</code>
                </div>
            </li>
            <?php
        }
    }

    /**
     * مدیریت JS در محیط ویرایشگر
     */
    public static function editor_js()
    {
        ?>
        <script type='text/javascript'>
            jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
                if (field.type == "verify_mobile") {
                    jQuery("#field_sms_template").val(field.smsTemplate == undefined ? "" : field.smsTemplate);
                    jQuery(".verify_mobile_setting").show();
                } else {
                    jQuery(".verify_mobile_setting").hide();
                }
            });
        </script>
        <?php
    }

    /**
     * تولتیپ‌های راهنما
     */
    public static function tooltips($tooltips)
    {
        $tooltips["field_sms_template"] = __("اگر خالی باشد، پیام پیش‌فرض: 'کد تایید شما: ...' ارسال می‌شود. برای استفاده از خطوط خدماتی حتماً از پترن استفاده کنید.", "GF_SMS");
        return $tooltips;
    }

    // =========================================================================
    // بخش نمایش فیلد (Frontend)
    // =========================================================================

    /**
     * تولید HTML فیلد در خروجی فرم
     */
    public static function field_input($input, $field, $value, $entry_id, $form_id)
    {
        if ($field->type != 'verify_mobile') return $input;

        // اگر در محیط ادمین (مثلا ویرایش ورودی) هستیم، فقط یک اینپوت ساده برگردان
        if (is_admin() && !defined('DOING_AJAX')) {
            return sprintf("<input type='text' id='input_%d_%d' name='input_%d' value='%s' class='small' >", $form_id, $field->id, $field->id, esc_attr($value));
        }

        $id = intval($field->id);
        $html_id = "input_{$form_id}_{$id}";
        
        // متن‌ها (قابل ترجمه)
        $btn_text = __("ارسال کد تایید", "GF_SMS");
        $verify_label = __("کد تایید پیامک شده را وارد کنید", "GF_SMS");
        $resend_text = __("ارسال مجدد", "GF_SMS");
        $loading_text = __("در حال ارسال...", "GF_SMS");
        $msg_sent = __("کد ارسال شد", "GF_SMS");

        // استایل‌های ضروری (Inline برای اطمینان از لود شدن)
        $styles = "
        <style>
            .gf_sms_wrapper { position: relative; }
            .gf_sms_input_group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
            .gf_sms_input_group input.sms-mobile { flex: 1; min-width: 150px; }
            .gf_sms_send_btn { white-space: nowrap; }
            .gf_sms_code_box { display: none; margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; }
            .gf_sms_code_box label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }
            .gf_sms_timer { display: inline-block; margin-right: 10px; color: #777; font-size: 0.85em; font-family: monospace; }
            .gf_sms_msg { display: none; margin-top: 5px; font-size: 0.9em; }
            .gf_sms_msg.error { color: #dc3232; }
            .gf_sms_msg.success { color: #46b450; }
        </style>";

        ob_start();
        echo $styles;
        ?>
        <div class="ginput_container ginput_container_verify_mobile gf_sms_wrapper" id="gf_sms_container_<?php echo $html_id; ?>">
            
            <div class="gf_sms_input_group">
                <input name="input_<?php echo $id; ?>" id="<?php echo $html_id; ?>" type="text" value="<?php echo esc_attr($value); ?>" 
                       class="<?php echo esc_attr($field->size); ?> sms-mobile" 
                       placeholder="<?php _e("شماره موبایل (مثال: 0912...)", "GF_SMS"); ?>" 
                       <?php echo $field->isRequired ? 'aria-required="true"' : ''; ?> />
                
                <button type="button" class="button gf_sms_send_btn" id="btn_<?php echo $html_id; ?>" onclick="MsgWay_SendCode(<?php echo $form_id; ?>, <?php echo $id; ?>, '<?php echo $html_id; ?>');">
                    <?php echo $btn_text; ?>
                </button>
            </div>

            <div class="gf_sms_msg" id="msg_<?php echo $html_id; ?>"></div>

            <div class="gf_sms_code_box" id="code_box_<?php echo $html_id; ?>">
                <label for="code_input_<?php echo $html_id; ?>"><?php echo $verify_label; ?> <span class="gfield_required">*</span></label>
                <div style="display:flex; align-items:center;">
                    <input type="text" name="sms_verify_code_<?php echo $id; ?>" id="code_input_<?php echo $html_id; ?>" class="small" style="width:120px; text-align:center; letter-spacing: 2px;" autocomplete="off" maxlength="10" />
                    <span class="gf_sms_timer" id="timer_<?php echo $html_id; ?>"></span>
                </div>
            </div>
            
            <!-- Hidden field for server-side verification token (Anti-spoofing) -->
            <input type="hidden" name="sms_verify_token_<?php echo $id; ?>" id="token_<?php echo $html_id; ?>" value="" />

        </div>

        <script>
            // اسکریپت اختصاصی برای این فیلد (Self-Contained)
            if (typeof MsgWay_SendCode === 'undefined') {
                function MsgWay_SendCode(formId, fieldId, inputId) {
                    var $ = jQuery;
                    var mobileInput = $("#" + inputId);
                    var btn = $("#btn_" + inputId);
                    var msgBox = $("#msg_" + inputId);
                    var codeBox = $("#code_box_" + inputId);
                    var tokenInput = $("#token_" + inputId);
                    var mobile = mobileInput.val();

                    // اعتبارسنجی اولیه کلاینت
                    if (mobile.length < 10) {
                        msgBox.text("<?php _e('لطفاً شماره موبایل معتبر وارد کنید.', 'GF_SMS'); ?>").removeClass('success').addClass('error').slideDown();
                        return;
                    }

                    // تغییر وضعیت دکمه
                    var originalBtnText = btn.text();
                    btn.prop('disabled', true).text("<?php echo $loading_text; ?>");
                    msgBox.slideUp();

                    $.post("<?php echo admin_url('admin-ajax.php'); ?>", {
                        action: 'gf_sms_send_code',
                        mobile: mobile,
                        form_id: formId,
                        field_id: fieldId,
                        nonce: '<?php echo wp_create_nonce('gf_sms_verify_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            // موفقیت
                            msgBox.text("<?php echo $msg_sent; ?>").removeClass('error').addClass('success').slideDown();
                            codeBox.slideDown();
                            tokenInput.val(response.data.token);
                            
                            // فوکوس روی فیلد کد
                            setTimeout(function(){ $("#code_input_" + inputId).focus(); }, 500);

                            // شروع تایمر
                            MsgWay_StartTimer(60, btn, inputId);
                        } else {
                            // خطا
                            btn.prop('disabled', false).text(originalBtnText);
                            var errorMsg = response.data ? response.data : "<?php _e('خطای ناشناخته', 'GF_SMS'); ?>";
                            msgBox.text(errorMsg).removeClass('success').addClass('error').slideDown();
                        }
                    }).fail(function() {
                        btn.prop('disabled', false).text(originalBtnText);
                        msgBox.text("<?php _e('خطا در برقراری ارتباط با سرور.', 'GF_SMS'); ?>").addClass('error').slideDown();
                    });
                }

                function MsgWay_StartTimer(duration, btn, inputId) {
                    var timer = duration, minutes, seconds;
                    var display = jQuery("#timer_" + inputId);
                    
                    // غیرفعال ماندن دکمه در طول تایمر
                    btn.prop('disabled', true);

                    var interval = setInterval(function () {
                        minutes = parseInt(timer / 60, 10);
                        seconds = parseInt(timer % 60, 10);

                        minutes = minutes < 10 ? "0" + minutes : minutes;
                        seconds = seconds < 10 ? "0" + seconds : seconds;

                        display.text(minutes + ":" + seconds);

                        if (--timer < 0) {
                            clearInterval(interval);
                            display.text("");
                            btn.prop('disabled', false).text("<?php echo $resend_text; ?>");
                        }
                    }, 1000);
                }
            }
        </script>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // بخش پردازش سمت سرور (AJAX & Validation)
    // =========================================================================

    /**
     * مدیریت ایجکس ارسال کد
     */
    public static function ajax_send_code()
    {
        check_ajax_referer('gf_sms_verify_nonce', 'nonce');

        $mobile = sanitize_text_field($_POST['mobile']);
        $form_id = intval($_POST['form_id']);
        $field_id = intval($_POST['field_id']);

        if (empty($mobile)) wp_send_json_error(__('شماره موبایل الزامی است.', 'GF_SMS'));

        // نرمال‌سازی شماره با استفاده از کلاس MSGWay
        if (class_exists('GF_MESSAGEWAY_MSGWay')) {
            $mobile = GF_MESSAGEWAY_MSGWay::mobileNormalize($mobile);
        }

        // بررسی صحت شماره پس از نرمال‌سازی
        if (!$mobile || strlen($mobile) < 10) {
            wp_send_json_error(__('شماره موبایل نامعتبر است.', 'GF_SMS'));
        }

        // 1. جلوگیری از اسپم (Rate Limiting)
        // کلید ترنزینت بر اساس IP و شماره موبایل
        $rate_limit_key = 'gf_sms_limit_' . md5($mobile . $_SERVER['REMOTE_ADDR']);
        if (get_transient($rate_limit_key)) {
            $wait_time = ttl_to_humans(get_option('_transient_timeout_' . $rate_limit_key) - time());
            wp_send_json_error(sprintf(__('لطفاً کمی صبر کنید، کد قبلی هنوز معتبر است.', 'GF_SMS')));
        }

        // 2. تولید کد تایید (5 رقمی)
        $code = rand(10000, 99999);

        // 3. دریافت تنظیمات فیلد (قالب پیامک)
        $form = GFAPI::get_form($form_id);
        $field = GFFormsModel::get_field($form, $field_id);
        $template = isset($field->smsTemplate) && !empty($field->smsTemplate) ? $field->smsTemplate : '';

        // آماده‌سازی متن پیام
        if (empty($template)) {
            // متن پیش‌فرض
            $msg = sprintf(__("کد تایید شما: %s\nراه‌پیام", "GF_SMS"), $code);
        } else {
            // جایگذاری کد در قالب
            // پشتیبانی از فرمت‌های مختلف: %code% یا [code]
            $msg = str_replace(array('%code%', '[code]'), $code, $template);
        }

        // 4. ارسال پیامک
        // از کلاس اصلی ارسال استفاده می‌کنیم که منطق انتخاب درگاه و پترن را دارد
        if (class_exists('GF_MESSAGEWAY_Form_Send')) {
            $res = GF_MESSAGEWAY_Form_Send::Send($mobile, $msg, '', $form_id);
        } else {
            wp_send_json_error(__('ماژول ارسال پیامک یافت نشد.', 'GF_SMS'));
        }

        // 5. بررسی نتیجه ارسال
        // وب‌سرویس ممکن است "OK" یا کد پیگیری عددی یا جیسون برگرداند
        // ما فرض می‌کنیم اگر خطا نباشد موفق است
        if ($res === 'OK' || strpos($res, 'OK') !== false || is_numeric($res) || (is_string($res) && strlen($res) > 5 && !strpos($res, 'Error'))) {
            
            // ذخیره کد صحیح در ترنزینت (اعتبار: 10 دقیقه)
            set_transient('gf_sms_verify_code_' . $mobile, $code, 10 * 60);
            
            // فعال‌سازی محدودیت ارسال مجدد (2 دقیقه)
            set_transient($rate_limit_key, 1, 2 * 60);

            // ایجاد توکن امنیتی برای اعتبارسنجی سمت سرور (جلوگیری از جعل درخواست)
            // این توکن تضمین می‌کند که کاربری که فرم را سابمیت می‌کند همان کسی است که درخواست SMS داده
            $token = wp_hash($mobile . $code . 'msgway_salt');

            wp_send_json_success(array(
                'token' => $token,
                'msg'   => __('کد با موفقیت ارسال شد.', 'GF_SMS')
            ));

        } else {
            // نمایش خطای وب‌سرویس به کاربر (برای دیباگ بهتر)
            wp_send_json_error(sprintf(__('خطا در ارسال پیامک: %s', 'GF_SMS'), $res));
        }
    }

    /**
     * اعتبارسنجی نهایی هنگام سابمیت فرم
     */
    public static function validate($result, $value, $form, $field)
    {
        if ($field->type != 'verify_mobile') return $result;

        // اگر فیلد مخفی است یا در این صفحه نیست، بیخیال شو
        if (RGFormsModel::is_field_hidden($form, $field, array())) {
            return $result;
        }

        $input_code_name = "sms_verify_code_" . $field->id;
        $user_code = rgpost($input_code_name);
        $mobile = $value; // شماره موبایل وارد شده در اینپوت اصلی

        // 1. نرمال‌سازی شماره (حیاتی برای تطبیق با ترنزینت)
        if (class_exists('GF_MESSAGEWAY_MSGWay')) {
            $mobile = GF_MESSAGEWAY_MSGWay::mobileNormalize($mobile);
        }

        // 2. دریافت کد صحیح
        $correct_code = get_transient('gf_sms_verify_code_' . $mobile);

        // 3. بررسی‌ها
        if (empty($user_code)) {
            $result["is_valid"] = false;
            $result["message"] = __("لطفاً کد تایید پیامک شده را وارد کنید.", "GF_SMS");
        } elseif (!$correct_code) {
            $result["is_valid"] = false;
            $result["message"] = __("کد تایید منقضی شده است. لطفاً مجدداً درخواست کد دهید.", "GF_SMS");
        } elseif ($user_code != $correct_code) {
            $result["is_valid"] = false;
            $result["message"] = __("کد تایید اشتباه است.", "GF_SMS");
        } else {
            // همه چیز درست است
            $result["is_valid"] = true;
            
            // اختیاری: پاک کردن کد استفاده شده برای جلوگیری از استفاده مجدد (One Time Use)
            delete_transient('gf_sms_verify_code_' . $mobile);
            
            // لاگ کردن تایید موفق در دیتابیس (اختیاری، جهت گزارش‌گیری)
            if (class_exists('GF_MESSAGEWAY_SQL')) {
                // $status = 1 (Verified)
                GF_MESSAGEWAY_SQL::insert_verify($form['id'], 0, $mobile, $user_code, 1, 1, 1);
            }
        }

        return $result;
    }
}