<?php
/**
 * پیکربندی پیشرفته فیدهای پیامک (Feed Configuration)
 * مدیریت تنظیمات اختصاصی ارسال پیامک برای هر فرم با قابلیت‌های کامل
 * * @package    Gravity Forms SMS - MsgWay
 * @author     Ready Studio <info@readystudio.ir>
 * @version    2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_Configurations
{
    /**
     * راه‌اندازی کلاس و هوک‌های مورد نیاز
     */
    public static function construct()
    {
        // ثبت درخواست‌های ایجکس برای محیط مدیریت
        if (is_admin()) {
            // دریافت فیلدهای فرم هنگام تغییر فرم در دراپ‌دان
            add_action('wp_ajax_gf_select_smspanel_form', array(__CLASS__, 'select_forms_ajax'));
            // بررسی پترن (توسط کلاس MsgWay هندل می‌شود اما اینجا اکشن را یادآوری می‌کنیم)
            // add_action('wp_ajax_gf_smspanel_checkPattern', array('GF_MESSAGEWAY_MSGWay', 'checkPattern')); 
        }
    }

    /**
     * تابع اصلی نمایش و مدیریت صفحه پیکربندی
     */
    public static function configuration()
    {
        // بارگذاری استایل‌ها و اسکریپت‌های هسته گرویتی فرم
        wp_register_style('gform_admin_sms', GFCommon::get_base_url() . '/css/admin.css');
        wp_print_styles(array('jquery-ui-styles', 'gform_admin_sms', 'wp-pointer'));
        
        // دریافت شناسه فید (اگر ویرایش باشد)
        $feed_id = !rgempty("smspanel_setting_id") ? rgpost("smspanel_setting_id") : absint(rgget("id"));
        
        // دریافت اطلاعات فید یا ایجاد آرایه پیش‌فرض
        $config = empty($feed_id) ? array("is_active" => true, "meta" => array()) : GF_MESSAGEWAY_SQL::get_feed($feed_id);
        
        // تعیین شناسه فرم
        $form_id = !empty($config["form_id"]) ? $config["form_id"] : absint(rgget('fid'));
        
        // دریافت اطلاعات فرم
        $form_meta = !empty($form_id) ? RGFormsModel::get_form_meta($form_id) : null;
        $form_title = $form_meta ? $form_meta['title'] : '';

        // بررسی تنظیمات کلی افزونه (جهت هشدار)
        $settings = GF_MESSAGEWAY::get_option();
        $is_api_configured = (!empty($settings["ws"]) && $settings["ws"] != 'no');

        // --- پردازش ذخیره‌سازی اطلاعات ---
        if (!rgempty("gf_smspanel_submit")) {
            check_admin_referer("update", "gf_smspanel_feed");
            
            $posted_form_id = absint(rgpost("gf_smspanel_form"));
            
            if (empty($posted_form_id)) {
                echo '<div class="notice notice-error"><p>' . __('لطفاً یک فرم را انتخاب کنید.', 'GF_SMS') . '</p></div>';
            } else {
                // آماده‌سازی داده‌ها برای ذخیره
                $feed_meta = array();
                
                // 1. تنظیمات عمومی فید
                $feed_meta["from"] = sanitize_text_field(rgpost("gf_smspanel_from"));
                $feed_meta["when"] = sanitize_text_field(rgpost("gf_smspanel_when"));
                $feed_meta["gf_sms_is_gateway_checked"] = rgpost("gf_sms_is_gateway_checked");
                
                // 2. تنظیمات پیش‌شماره
                $feed_meta["gf_sms_change_code"] = rgpost("gf_sms_change_code");
                $feed_meta["gf_change_code_type"] = rgpost("gf_change_code_type");
                $feed_meta["gf_code_static"] = sanitize_text_field(rgpost("gf_code_static"));
                $feed_meta["gf_code_dyn"] = rgpost("smspanel_gf_code_dyn");

                // 3. تنظیمات پیامک مدیر
                $feed_meta["to"] = sanitize_text_field(rgpost("gf_smspanel_to"));
                $feed_meta["message"] = wp_kses_post(rgpost("gf_smspanel_message"));
                
                // شرط‌های مدیر
                $feed_meta["adminsms_conditional_enabled"] = rgpost("gf_adminsms_conditional_enabled");
                $feed_meta["adminsms_conditional_type"] = rgpost("gf_adminsms_conditional_type");
                $feed_meta["adminsms_conditional_field_id"] = rgpost("gf_adminsms_conditional_field_id");
                $feed_meta["adminsms_conditional_operator"] = rgpost("gf_adminsms_conditional_operator");
                $feed_meta["adminsms_conditional_value"] = rgpost("gf_adminsms_conditional_value");

                // 4. تنظیمات پیامک کاربر
                $feed_meta["customer_field_clientnum"] = rgpost("smspanel_customer_field_clientnum");
                $feed_meta["to_c"] = sanitize_text_field(rgpost("gf_smspanel_to_c"));
                $feed_meta["message_c"] = wp_kses_post(rgpost("gf_smspanel_message_c"));
                
                // شرط‌های کاربر
                $feed_meta["clientsms_conditional_enabled"] = rgpost("gf_clientsms_conditional_enabled");
                $feed_meta["clientsms_conditional_type"] = rgpost("gf_clientsms_conditional_type");
                $feed_meta["clientsms_conditional_field_id"] = rgpost("gf_clientsms_conditional_field_id");
                $feed_meta["clientsms_conditional_operator"] = rgpost("gf_clientsms_conditional_operator");
                $feed_meta["clientsms_conditional_value"] = rgpost("gf_clientsms_conditional_value");

                // پاکسازی آرایه‌ها (شرط‌ها)
                foreach ($feed_meta as $key => $val) {
                    if (is_array($val)) {
                        $feed_meta[$key] = array_map('sanitize_text_field', $val);
                    }
                }

                // عملیات دیتابیس
                if ($feed_id == 0) {
                    $feed_id = GF_MESSAGEWAY_SQL::save_feed($feed_meta, $posted_form_id);
                } else {
                    GF_MESSAGEWAY_SQL::update_feed($feed_id, $posted_form_id, $config["is_active"], $feed_meta);
                }

                echo '<div class="updated fade"><p>' . __('تنظیمات فید با موفقیت ذخیره شد.', 'GF_SMS') . '</p></div>';
                
                // بروزرسانی متغیرهای صفحه
                $config = GF_MESSAGEWAY_SQL::get_feed($feed_id);
                $form_id = $config["form_id"];
                $form_meta = RGFormsModel::get_form_meta($form_id);
            }
        }
        ?>

        <!-- استایل‌های اختصاصی صفحه -->
        <style>
            .ready-studio-wrap { font-family: Tahoma, sans-serif; direction: rtl; }
            /* هدر برندینگ */
            .ready-studio-badge {
                background: #fff; border: 1px solid #ccd0d4; border-right: 4px solid #0073aa;
                padding: 12px 15px; border-radius: 4px; margin-bottom: 20px;
                display: flex; align-items: center; justify-content: space-between;
                box-shadow: 0 1px 2px rgba(0,0,0,.05);
            }
            .ready-studio-badge .rs-title { font-size: 15px; font-weight: 600; color: #23282d; }
            .ready-studio-badge .rs-title i { color: #0073aa; margin-left: 5px; font-size: 20px; vertical-align: middle; }
            .ready-studio-badge .rs-link { font-size: 12px; color: #666; }
            .ready-studio-badge .rs-link a { text-decoration: none; color: #0073aa; font-weight: bold; }
            
            /* جدول تنظیمات */
            .gforms_form_settings { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .gforms_form_settings th { width: 220px; padding: 15px 10px; text-align: right; vertical-align: top; font-weight: 600; border-bottom: 1px solid #eee; }
            .gforms_form_settings td { padding: 15px 10px; border-bottom: 1px solid #eee; }
            .gform_panel { background: #fff; border: 1px solid #e5e5e5; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-bottom: 20px; }
            .gform_panel h3 { margin: 0; padding: 12px 15px; background: #f8f9fa; border-bottom: 1px solid #e5e5e5; font-size: 14px; font-weight: 600; color: #23282d; }
            .gform_panel_content { padding: 0 15px 15px; }
            
            /* المان‌های فرم */
            input[type=text], select, textarea { box-shadow: none; border-color: #ddd; }
            input[type=text]:focus, select:focus, textarea:focus { border-color: #0073aa; box-shadow: 0 0 2px rgba(0,115,170,.4); }
            textarea { width: 98%; min-height: 80px; font-family: monospace; }
            
            /* شرط‌ها */
            .gf_sms_conditional_div {
                background: #fcfcfc; border: 1px dashed #ccc; padding: 10px; margin-bottom: 8px; border-radius: 3px;
                display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
            }
            .add_condition_link, .delete_condition_link { font-size: 18px; text-decoration: none; color: #888; }
            .add_condition_link:hover { color: #4caf50; }
            .delete_condition_link:hover { color: #f44336; }
            
            /* پاپ‌آپ */
            #dpWrapper { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100000; display: flex; align-items: center; justify-content: center; }
            .dpContainer { background: #fff; width: 600px; max-width: 90%; max-height: 90vh; display: flex; flex-direction: column; border-radius: 5px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
            .dpTitle { padding: 15px; border-bottom: 1px solid #eee; font-weight: bold; background: #f9f9f9; border-radius: 5px 5px 0 0; display: flex; justify-content: space-between; }
            .dpBody { padding: 20px; overflow-y: auto; }
            .dpFooter { padding: 15px; border-top: 1px solid #eee; background: #f9f9f9; border-radius: 0 0 5px 5px; text-align: left; }
            .spinner { float: none; margin: 0 5px; }
        </style>

        <div class="wrap gforms_edit_form gf_browser_gecko ready-studio-wrap">

            <!-- هدر با برندینگ -->
            <div class="ready-studio-badge">
                <div class="rs-title">
                    <i class="dashicons dashicons-email-alt"></i>
                    <?php _e("پیکربندی فید پیامک (نسخه حرفه‌ای راه‌پیام)", "GF_SMS"); ?>
                </div>
                <div class="rs-link">
                    توسعه و پشتیبانی: <a href="https://readystudio.ir" target="_blank">Ready Studio</a>
                </div>
            </div>

            <!-- عنوان صفحه -->
            <h1 class="wp-heading-inline" style="margin-bottom: 20px;">
                <?php $feed_id == 0 ? _e("ایجاد فید جدید", "GF_SMS") : _e("ویرایش فید پیامک", "GF_SMS"); ?>
            </h1>
            
            <a href="admin.php?page=gf_smspanel" class="page-title-action"><?php _e("بازگشت به لیست", "GF_SMS") ?></a>

            <?php if (!empty($form_title)): ?>
                <div class="notice notice-info inline" style="margin: 10px 0;">
                    <p>
                        <?php printf(__('در حال ویرایش فید برای فرم: <strong>%s</strong> (شناسه فید: %s)', 'GF_SMS'), esc_html($form_title), $feed_id); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!$is_api_configured): ?>
                <div class="notice notice-error"><p><?php _e('خطا: تنظیمات وب‌سرویس انجام نشده است. لطفاً ابتدا API Key را در تنظیمات عمومی وارد کنید.', 'GF_SMS'); ?></p></div>
            <?php endif; ?>

            <!-- فرم اصلی تنظیمات -->
            <form method="post" action="" id="gform_form_settings">
                <?php wp_nonce_field("update", "gf_smspanel_feed") ?>
                <input type="hidden" name="smspanel_setting_id" value="<?php echo $feed_id ?>"/>

                <!-- بخش 1: انتخاب فرم -->
                <div class="gform_panel" id="form_selection_panel">
                    <h3><?php _e("انتخاب فرم", "GF_SMS"); ?></h3>
                    <div class="gform_panel_content">
                        <table class="gforms_form_settings">
                            <tr>
                                <th><label for="gf_smspanel_form"><?php _e("فرم مربوطه", "GF_SMS"); ?></label></th>
                                <td>
                                    <select id="gf_smspanel_form" name="gf_smspanel_form" onchange="SelectFormAjax(this.value);" style="width: 300px;">
                                        <option value=""><?php _e("یک فرم را انتخاب کنید...", "GF_SMS"); ?></option>
                                        <?php
                                        $forms = RGFormsModel::get_forms(null, "title");
                                        foreach ($forms as $form) {
                                            $selected = absint($form->id) == $form_id ? "selected='selected'" : "";
                                            echo '<option value="' . absint($form->id) . '" ' . $selected . '>' . esc_html($form->title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <span id="smspanel_wait" style="display:none;" class="spinner is-active"></span>
                                    <p class="description"><?php _e("با تغییر فرم، فیلدها و متغیرهای پایین صفحه بروزرسانی می‌شوند.", "GF_SMS"); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- کانتینر تنظیمات (نمایش تنها در صورت انتخاب فرم) -->
                <div id="smspanel_field_group" <?php echo empty($form_id) ? "style='display:none;'" : "" ?>>

                    <!-- بخش 2: تنظیمات عمومی ارسال -->
                    <div class="gform_panel">
                        <h3><?php _e("تنظیمات ارسال", "GF_SMS"); ?></h3>
                        <div class="gform_panel_content">
                            <table class="gforms_form_settings">
                                
                                <!-- انتخاب پرووایدر -->
                                <tr>
                                    <th><label for="gf_smspanel_from"><?php _e("سرویس‌دهنده (Provider)", "GF_SMS"); ?></label></th>
                                    <td>
                                        <select id="gf_smspanel_from" name="gf_smspanel_from" style="width:300px;">
                                            <option value="" <?php selected(rgar($config["meta"], "from"), ""); ?>>
                                                <?php _e("استفاده از تنظیمات پیش‌فرض افزونه", "GF_SMS"); ?>
                                            </option>
                                            
                                            <optgroup label="<?php _e("پیامک (SMS)", "GF_SMS"); ?>">
                                                <option value="1" <?php selected(rgar($config["meta"], "from"), "1"); ?>>مگفا (سرشماره 3000)</option>
                                                <option value="2000" <?php selected(rgar($config["meta"], "from"), "2000"); ?>>آتیه (سرشماره 2000)</option>
                                                <option value="3" <?php selected(rgar($config["meta"], "from"), "3"); ?>>آسیاتک (سرشماره 9000)</option>
                                                <option value="5" <?php selected(rgar($config["meta"], "from"), "5"); ?>>ارمغان راه طلایی (سرشماره 50004)</option>
                                            </optgroup>

                                            <optgroup label="<?php _e("پیام‌رسان‌های داخلی", "GF_SMS"); ?>">
                                                <option value="eitaa" <?php selected(rgar($config["meta"], "from"), "eitaa"); ?>>ایتا (Eitaa)</option>
                                                <option value="bale" <?php selected(rgar($config["meta"], "from"), "bale"); ?>>بله (Bale)</option>
                                                <option value="gap" <?php selected(rgar($config["meta"], "from"), "gap"); ?>>گپ (Gap)</option>
                                                <option value="igap" <?php selected(rgar($config["meta"], "from"), "igap"); ?>>آیگپ (iGap)</option>
                                                <option value="rubika" <?php selected(rgar($config["meta"], "from"), "rubika"); ?>>روبیکا (Rubika)</option>
                                            </optgroup>

                                            <optgroup label="<?php _e("سایر", "GF_SMS"); ?>">
                                                <option value="ivr" <?php selected(rgar($config["meta"], "from"), "ivr"); ?>>پیام صوتی (IVR)</option>
                                            </optgroup>
                                        </select>
                                        <p class="description"><?php _e("می‌توانید برای این فرم خاص، روش ارسال متفاوتی نسبت به تنظیمات اصلی انتخاب کنید.", "GF_SMS"); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th><label><?php _e("زمان ارسال", "GF_SMS"); ?></label></th>
                                    <td>
                                        <select name="gf_smspanel_when">
                                            <option value="send_immediately" <?php selected(rgar($config["meta"], "when"), "send_immediately"); ?>><?php _e("بلافاصله پس از ثبت فرم", "GF_SMS"); ?></option>
                                            <option value="after_pay" <?php selected(rgar($config["meta"], "when"), "after_pay"); ?>><?php _e("بعد از بازگشت از درگاه (موفق یا ناموفق)", "GF_SMS"); ?></option>
                                            <option value="after_pay_success" <?php selected(rgar($config["meta"], "when"), "after_pay_success"); ?>><?php _e("فقط پس از پرداخت موفق", "GF_SMS"); ?></option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th><?php _e("شرط درگاه پرداخت", "GF_SMS"); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="gf_sms_is_gateway_checked" value="1" <?php checked(rgar($config["meta"], "gf_sms_is_gateway_checked"), "1"); ?> />
                                            <?php _e("ارسال فقط در صورتی که فرم به درگاه پرداخت متصل باشد.", "GF_SMS"); ?>
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th><?php _e("کد کشور پیش‌فرض", "GF_SMS"); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="gf_sms_change_code" name="gf_sms_change_code" value="1" onclick="jQuery('#country_code_box').toggle();" <?php checked(rgar($config["meta"], "gf_sms_change_code"), "1"); ?> />
                                            <?php _e("تغییر پیش‌شماره پیش‌فرض (+98)", "GF_SMS"); ?>
                                        </label>
                                        
                                        <div id="country_code_box" style="margin-top:10px; padding:10px; border:1px solid #ddd; border-radius:3px; <?php echo !rgar($config["meta"], "gf_sms_change_code") ? 'display:none;' : ''; ?>">
                                            <p>
                                                <input type="radio" name="gf_change_code_type" value="static" id="code_type_static" <?php checked(rgar($config["meta"], "gf_change_code_type") != 'dyn'); ?> onclick="jQuery('#code_static_wrap').show();jQuery('#code_dyn_wrap').hide();">
                                                <label for="code_type_static"><?php _e("مقدار ثابت", "GF_SMS"); ?></label>
                                                
                                                <input type="radio" name="gf_change_code_type" value="dyn" id="code_type_dyn" <?php checked(rgar($config["meta"], "gf_change_code_type"), 'dyn'); ?> onclick="jQuery('#code_static_wrap').hide();jQuery('#code_dyn_wrap').show();">
                                                <label for="code_type_dyn"><?php _e("از فیلد فرم (داینامیک)", "GF_SMS"); ?></label>
                                            </p>
                                            
                                            <div id="code_static_wrap" <?php echo rgar($config["meta"], "gf_change_code_type") == 'dyn' ? 'style="display:none;"' : ''; ?>>
                                                <input type="text" name="gf_code_static" value="<?php echo esc_attr(rgar($config["meta"], "gf_code_static")); ?>" placeholder="+1" style="width:80px; direction:ltr;">
                                            </div>
                                            <div id="code_dyn_wrap" <?php echo rgar($config["meta"], "gf_change_code_type") != 'dyn' ? 'style="display:none;"' : ''; ?>>
                                                <span id="smspanel_gf_code_dyn">
                                                    <?php 
                                                    if($form_meta) {
                                                        echo self::get_mapped_fields("gf_code_dyn", rgar($config["meta"], "gf_code_dyn"), self::get_client_form_fields($form_meta), false);
                                                    } 
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- بخش 3: پیامک مدیر -->
                    <div class="gform_panel">
                        <h3><?php _e("تنظیمات پیامک مدیر", "GF_SMS"); ?></h3>
                        <div class="gform_panel_content">
                            <table class="gforms_form_settings">
                                <tr>
                                    <th><label><?php _e("شماره‌های گیرنده", "GF_SMS"); ?></label></th>
                                    <td>
                                        <input type="text" name="gf_smspanel_to" value="<?php echo esc_attr(rgar($config["meta"], "to")); ?>" style="direction:ltr; text-align:left; width: 100%;">
                                        <p class="description"><?php _e("شماره‌ها را با کاما (,) جدا کنید. مثال: 09121111111,09122222222", "GF_SMS"); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php _e("متن پیامک", "GF_SMS"); ?></label></th>
                                    <td>
                                        <?php self::render_message_area('gf_smspanel_message', rgar($config["meta"], "message"), $form_id); ?>
                                    </td>
                                </tr>
                                <?php self::render_conditional_ui('adminsms', $config); ?>
                            </table>
                        </div>
                    </div>

                    <!-- بخش 4: پیامک کاربر -->
                    <div class="gform_panel">
                        <h3><?php _e("تنظیمات پیامک کاربر", "GF_SMS"); ?></h3>
                        <div class="gform_panel_content">
                            <table class="gforms_form_settings">
                                <tr>
                                    <th><label><?php _e("فیلد شماره موبایل", "GF_SMS"); ?></label></th>
                                    <td id="smspanel_customer_field">
                                        <?php 
                                        if($form_meta) {
                                            echo self::get_mapped_fields("customer_field_clientnum", rgar($config["meta"], "customer_field_clientnum"), self::get_client_form_fields($form_meta), true);
                                        } 
                                        ?>
                                        <p class="description"><?php _e("فیلدی که کاربر در آن شماره موبایل خود را وارد می‌کند انتخاب کنید.", "GF_SMS"); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php _e("رونوشت (CC) به", "GF_SMS"); ?></label></th>
                                    <td>
                                        <input type="text" name="gf_smspanel_to_c" value="<?php echo esc_attr(rgar($config["meta"], "to_c")); ?>" style="direction:ltr; text-align:left; width: 100%;">
                                        <p class="description"><?php _e("شماره‌های ثابتی که می‌خواهید کپی پیام کاربر را دریافت کنند.", "GF_SMS"); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php _e("متن پیامک", "GF_SMS"); ?></label></th>
                                    <td>
                                        <?php self::render_message_area('gf_smspanel_message_c', rgar($config["meta"], "message_c"), $form_id); ?>
                                    </td>
                                </tr>
                                <?php self::render_conditional_ui('clientsms', $config); ?>
                            </table>
                        </div>
                    </div>

                    <!-- دکمه ذخیره -->
                    <div class="gform_panel_content" style="background:transparent; padding:0;">
                        <input type="submit" name="gf_smspanel_submit" class="button-primary gfbutton" style="font-size:16px; padding:5px 20px; height:auto;" value="<?php _e("ذخیره تنظیمات فید", "GF_SMS"); ?>"/>
                    </div>

                </div>
            </form>
            
            <div style="margin-top:30px; border-top:1px solid #ddd; padding-top:15px; text-align:center; color:#999; font-size:12px;">
                <p>Designed with <span style="color:#d00;">❤</span> by <strong>Ready Studio</strong> for MsgWay</p>
            </div>

        </div>

        <!-- جاوااسکریپت‌های مورد نیاز -->
        <script type="text/javascript">
            // آبجکت فرم برای استفاده در شرط‌ها
            var gf_sms_form_object = <?php echo !empty($form_id) ? GFCommon::json_encode(RGFormsModel::get_form_meta($form_id)) : "null"; ?>;

            jQuery(document).ready(function($) {
                // مقداردهی اولیه شرط‌های ذخیره شده
                <?php self::js_init_conditions('adminsms', $config); ?>
                <?php self::js_init_conditions('clientsms', $config); ?>
            });

            // --- انتخاب فرم (AJAX) ---
            function SelectFormAjax(formId) {
                if(!formId) { jQuery("#smspanel_field_group").slideUp(); return; }
                
                jQuery("#smspanel_wait").show();
                
                jQuery.post(ajaxurl, {
                    action: "gf_select_smspanel_form",
                    gf_select_smspanel_form: "<?php echo wp_create_nonce("gf_select_smspanel_form") ?>",
                    form_id: formId
                }, function(data) {
                    gf_sms_form_object = data.form; // بروزرسانی متغیر سراسری
                    
                    // آپدیت فیلدها
                    jQuery("#smspanel_customer_field").html(data.customer_field);
                    jQuery("#smspanel_gf_code_dyn").html(data.gf_code);
                    jQuery(".sms_merge_tags").html(data.merge_tags);
                    
                    // ریست کردن شرط‌ها
                    ResetConditions('adminsms');
                    ResetConditions('clientsms');
                    
                    jQuery("#smspanel_field_group").slideDown();
                    jQuery("#smspanel_wait").hide();
                }, "json");
            }

            // --- درج متغیر ---
            function InsertVariable(selectId, textareaId) {
                var variable = jQuery("#" + selectId).val();
                if(!variable) return;
                
                var textarea = jQuery("#" + textareaId);
                var cursorPos = textarea.prop('selectionStart');
                var v = textarea.val();
                var textBefore = v.substring(0,  cursorPos);
                var textAfter  = v.substring(cursorPos, v.length);

                textarea.val(textBefore + variable + textAfter);
                jQuery("#" + selectId).val(''); 
                textarea.focus();
            }

            // --- ویزارد پترن (Pattern Wizard) ---
            function OpenPatternWizard(textareaId) {
                var html = `
                <div style="padding:15px;">
                    <p style="font-size:14px; margin-bottom:15px;">برای ارسال سریع با خطوط خدماتی (و عبور از بلک‌لیست)، باید از پترن استفاده کنید.</p>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="text" id="rs_pcode" class="regular-text" placeholder="کد الگو (مثال: 12345)" style="font-size:16px;">
                        <button type="button" class="button button-primary button-large" onclick="CheckPattern('${textareaId}')">بررسی و فراخوانی</button>
                    </div>
                    <div id="rs_presult" style="margin-top:20px; min-height:50px;"></div>
                </div>`;
                
                jQuery('body').append('<div id="dpWrapper"><div class="dpContainer"><div class="dpTitle">ابزار پترن راه‌پیام <span class="close" onclick="jQuery(\'#dpWrapper\').remove()">×</span></div><div class="dpBody">'+html+'</div><div class="dpFooter"><small>Powered by Ready Studio</small></div></div></div>');
            }

            function CheckPattern(textareaId) {
                var pcode = jQuery("#rs_pcode").val();
                if(!pcode) { alert("لطفاً کد الگو را وارد کنید"); return; }
                
                jQuery("#rs_presult").html('<div style="text-align:center;"><span class="spinner is-active" style="float:none;"></span> در حال ارتباط با راه‌پیام...</div>');
                
                jQuery.post(ajaxurl, {
                    action: "gf_smspanel_checkPattern",
                    patternCode: pcode
                }, function(res) {
                    // هندلینگ ریسپانس (ممکن است جیسون باشد یا رشته)
                    var data = res;
                    if(typeof res === 'string') {
                        try { data = JSON.parse(res); } catch(e) {}
                    }
                    
                    if(data.status === 0) {
                        var html = '<div style="background:#e7f5fe; color:#0c5460; padding:10px; border-radius:3px; margin-bottom:15px; border:1px solid #bee5eb;">الگو تایید شد: <b>'+data.message+'</b></div>';
                        html += '<table class="widefat" style="border:none; box-shadow:none;">';
                        
                        if(data.vars && data.vars.length > 0) {
                            html += '<thead><tr><th style="width:120px;">نام متغیر</th><th>مقدار (ثابت یا شورت‌کد)</th></tr></thead><tbody>';
                            data.vars.forEach(function(v) {
                                html += '<tr><td><strong>'+v+'</strong></td><td><input type="text" class="rs-pvar" data-key="'+v+'" placeholder="مثال: {Name:1} یا علی" style="width:100%"></td></tr>';
                            });
                            html += '</tbody>';
                        } else {
                            html += '<tr><td colspan="2">این الگو هیچ متغیری ندارد.</td></tr>';
                        }
                        html += '</table>';
                        html += '<div style="margin-top:20px; text-align:right; border-top:1px solid #eee; padding-top:10px;"><button type="button" class="button button-primary button-large" onclick="InsertPattern(\''+textareaId+'\', \''+pcode+'\')">درج در تنظیمات</button></div>';
                        
                        jQuery("#rs_presult").html(html);
                    } else {
                        jQuery("#rs_presult").html('<div class="notice notice-error inline"><p>خطا: '+data.message+'</p></div>');
                    }
                });
            }

            function InsertPattern(textareaId, pcode) {
                var str = "pcode:" + pcode;
                jQuery(".rs-pvar").each(function() {
                    var val = jQuery(this).val();
                    if(val) str += ";" + jQuery(this).data("key") + ":" + val;
                });
                jQuery("#" + textareaId).val(str);
                jQuery("#dpWrapper").remove();
            }

            // --- توابع منطق شرطی (Conditional Logic) ---
            
            function AddCondition(prefix, index) {
                var container = jQuery("#gf_" + prefix + "_conditions_list");
                // استفاده از تمپلیت مخفی برای ساخت سطر جدید
                var template = jQuery("#gf_sms_cond_template").html();
                
                // جایگذاری ایندکس و پیشوند
                var newHtml = template.replace(/\{i\}/g, index).replace(/\{prefix\}/g, prefix);
                container.append(newHtml);
                
                // پر کردن دراپ‌دان فیلدها
                var select = jQuery("#gf_" + prefix + "_" + index + "__field");
                select.html(GetFieldOptions());
                
                // نمایش دکمه‌های حذف (اگر بیشتر از یکی شد)
                CheckConditionButtons(prefix);
            }

            function RemoveCondition(el) {
                jQuery(el).closest('.gf_sms_conditional_div').remove();
                // چک کردن دکمه‌ها (اگر همه پاک شدند یکی بساز یا...)
                // در اینجا فرض بر این است که دکمه حذف فقط وقتی نمایش داده میشود که بیش از یکی باشد
            }

            function CheckConditionButtons(prefix) {
                // فعلا ساده نگه میداریم: همیشه اجازه حذف و اضافه میدهیم
            }

            function UpdateConditionValueUI(el) {
                var container = jQuery(el).closest('.gf_sms_conditional_div');
                var fieldId = container.find('.gf_sms_cond_field').val();
                var op = container.find('.gf_sms_cond_operator').val();
                var valContainer = container.find('.gf_sms_cond_value_box');
                
                // نام فیلد مقدار (Value Input Name)
                // نام input اصلی gf_prefix_conditional_field_id[i] است. ما باید ایندکس را پیدا کنیم.
                var nameAttr = jQuery(el).attr('name'); 
                // استخراج ایندکس از نام (مثلا [0])
                var indexMatch = nameAttr.match(/\[(\d+)\]/);
                var index = indexMatch ? indexMatch[1] : 0;
                
                var prefixMatch = nameAttr.match(/gf_([a-z]+)_/);
                var prefixStr = prefixMatch ? prefixMatch[1] : 'adminsms';
                
                var inputName = "gf_" + prefixStr + "_conditional_value[" + index + "]";

                if(!fieldId || !gf_sms_form_object) { valContainer.html(''); return; }

                // جستجوی فیلد در آبجکت فرم
                var field = null;
                for(var i=0; i<gf_sms_form_object.fields.length; i++) {
                    if(gf_sms_form_object.fields[i].id == fieldId) { field = gf_sms_form_object.fields[i]; break; }
                }

                var html = '';
                // اگر فیلد دارای گزینه‌های از پیش تعریف شده است (Dropdown, Radio)
                if(field && field.choices && (op == 'is' || op == 'isnot')) {
                    html = '<select name="'+inputName+'" style="min-width:150px;">';
                    for(var i=0; i<field.choices.length; i++) {
                        var val = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
                        html += '<option value="'+val+'">'+field.choices[i].text+'</option>';
                    }
                    html += '</select>';
                } else {
                    html = '<input type="text" name="'+inputName+'" value="" style="min-width:150px;">';
                }
                valContainer.html(html);
            }

            function GetFieldOptions() {
                var str = '<option value="">انتخاب فیلد</option>';
                if(gf_sms_form_object && gf_sms_form_object.fields) {
                    // فیلدهایی که از منطق شرطی پشتیبانی می‌کنند
                    var supported = ["text", "textarea", "select", "multiselect", "number", "checkbox", "radio", "hidden", "phone", "email", "website", "date", "time"];
                    
                    for(var i=0; i<gf_sms_form_object.fields.length; i++) {
                        var f = gf_sms_form_object.fields[i];
                        var type = f.inputType ? f.inputType : f.type;
                        
                        if(jQuery.inArray(type, supported) !== -1) {
                            str += '<option value="'+f.id+'">'+f.label+'</option>';
                        }
                    }
                }
                return str;
            }

            function ResetConditions(prefix) {
                jQuery("#gf_" + prefix + "_conditions_list").html('');
                AddCondition(prefix, 0); 
            }

        </script>
        
        <!-- تمپلیت مخفی HTML برای تولید سطرهای شرط -->
        <script type="text/template" id="gf_sms_cond_template">
            <div class="gf_sms_conditional_div">
                <select class="gf_sms_cond_field" id="gf_{prefix}_{i}__field" name="gf_{prefix}_conditional_field_id[{i}]" onchange="UpdateConditionValueUI(this)">
                    <!-- گزینه‌ها توسط JS پر می‌شوند -->
                </select>
                
                <select class="gf_sms_cond_operator" name="gf_{prefix}_conditional_operator[{i}]" onchange="UpdateConditionValueUI(this)">
                    <option value="is">هست</option>
                    <option value="isnot">نیست</option>
                    <option value=">">بزرگتر از</option>
                    <option value="<">کوچکتر از</option>
                    <option value="contains">شامل</option>
                    <option value="starts_with">شروع با</option>
                    <option value="ends_with">پایان با</option>
                </select>
                
                <div class="gf_sms_cond_value_box" style="display:inline;">
                    <input type="text" name="gf_{prefix}_conditional_value[{i}]" value="">
                </div>
                
                <div class="gf_sms_cond_actions" style="display:inline-block; margin-right:10px;">
                    <a href="#" onclick="RemoveCondition(this);return false;" class="delete_condition_link" title="حذف این شرط">
                        <i class="dashicons dashicons-dismiss" style="vertical-align:middle;"></i>
                    </a>
                    <a href="#" onclick="AddCondition('{prefix}', jQuery('#gf_{prefix}_conditions_list .gf_sms_conditional_div').length);return false;" class="add_condition_link" title="افزودن شرط جدید">
                        <i class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></i>
                    </a>
                </div>
            </div>
        </script>
        <?php
    }

    // =========================================================================
    // توابع کمکی PHP برای رندر کردن بخش‌ها
    // =========================================================================

    /**
     * رندر کردن ناحیه متن پیام (Textarea) به همراه ابزارک‌ها
     */
    private static function render_message_area($id, $val, $form_id) {
        ?>
        <div class="gf_sms_msg_area">
            <div style="margin-bottom:5px; display:flex; gap:5px;">
                <select id="<?php echo $id; ?>_vars" class="sms_merge_tags" onchange="InsertVariable('<?php echo $id; ?>_vars', '<?php echo $id; ?>')" style="max-width:200px;">
                    <?php 
                    if($form_id) {
                        $form = RGFormsModel::get_form_meta($form_id);
                        echo self::get_form_fields_merge($form);
                    } else {
                        echo '<option>ابتدا فرم را انتخاب کنید</option>';
                    }
                    ?>
                </select>
                <button type="button" class="button button-small button-secondary" onclick="OpenPatternWizard('<?php echo $id; ?>')">
                    <span class="dashicons dashicons-magic" style="line-height:1.5; font-size:16px;"></span> پترن ویزارد
                </button>
            </div>
            
            <textarea id="<?php echo $id; ?>" name="<?php echo $id; ?>"><?php echo esc_textarea($val); ?></textarea>
            <p class="description">برای ارسال پیامک متنی معمولی متن را بنویسید. برای پترن از ویزارد استفاده کنید.</p>
        </div>
        <?php
    }

    /**
     * رندر کردن کانتینر منطق شرطی
     */
    private static function render_conditional_ui($prefix, $config) {
        $enabled = rgar($config['meta'], $prefix . '_conditional_enabled');
        ?>
        <tr>
            <th><label><?php _e("منطق شرطی", "GF_SMS"); ?></label></th>
            <td>
                <input type="checkbox" id="gf_<?php echo $prefix; ?>_cond_enable" name="gf_<?php echo $prefix; ?>_conditional_enabled" value="1" onclick="jQuery('#gf_<?php echo $prefix; ?>_cond_box').toggle();" <?php checked($enabled, 1); ?>>
                <label for="gf_<?php echo $prefix; ?>_cond_enable"><?php _e("فعال‌سازی این گزینه", "GF_SMS"); ?></label>
                
                <div id="gf_<?php echo $prefix; ?>_cond_box" style="margin-top:15px; padding:15px; border-top:1px dashed #ccc; <?php echo !$enabled ? 'display:none' : ''; ?>">
                    <div style="margin-bottom:10px;">
                        <?php _e("ارسال کن اگر", "GF_SMS"); ?>
                        <select name="gf_<?php echo $prefix; ?>_conditional_type">
                            <option value="all" <?php selected(rgar($config['meta'], $prefix.'_conditional_type'), 'all'); ?>><?php _e("همه", "GF_SMS"); ?></option>
                            <option value="any" <?php selected(rgar($config['meta'], $prefix.'_conditional_type'), 'any'); ?>><?php _e("هریک", "GF_SMS"); ?></option>
                        </select>
                        <?php _e("از شرط‌های زیر برقرار باشد:", "GF_SMS"); ?>
                    </div>
                    
                    <div id="gf_<?php echo $prefix; ?>_conditions_list">
                        <!-- شرط‌ها توسط JS پر می‌شوند -->
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * تولید کدهای JS برای مقداردهی اولیه شرط‌ها هنگام لود صفحه
     */
    private static function js_init_conditions($prefix, $config) {
        $field_ids = rgar($config['meta'], $prefix.'_conditional_field_id');
        $vals = rgar($config['meta'], $prefix.'_conditional_value');
        $ops = rgar($config['meta'], $prefix.'_conditional_operator');

        // اگر دیتایی ذخیره شده است، حلقه بزن و بساز
        if(is_array($field_ids) && count($field_ids) > 0) {
            foreach($field_ids as $i => $fid) {
                // فیلتر کردن ردیف‌های خالی، مگر اینکه تنها ردیف باشد
                if(empty($fid) && count($field_ids) > 1) continue;
                
                $val = isset($vals[$i]) ? esc_js($vals[$i]) : '';
                $op = isset($ops[$i]) ? $ops[$i] : 'is';
                ?>
                AddCondition('<?php echo $prefix; ?>', <?php echo $i; ?>);
                // استفاده از Timeout برای اطمینان از ساخته شدن المان‌ها در DOM
                setTimeout(function(){
                    var row = jQuery("#gf_<?php echo $prefix; ?>_conditions_list .gf_sms_conditional_div").eq(<?php echo $i; ?>);
                    row.find(".gf_sms_cond_field").val('<?php echo $fid; ?>');
                    row.find(".gf_sms_cond_operator").val('<?php echo $op; ?>');
                    
                    // آپدیت UI ولیو (تبدیل به دراپ‌دان اگر لازم بود)
                    UpdateConditionValueUI(row.find(".gf_sms_cond_field"));
                    
                    // ست کردن مقدار ولیو
                    setTimeout(function(){ row.find(".gf_sms_cond_value_box input, .gf_sms_cond_value_box select").val('<?php echo $val; ?>'); }, 50);
                }, 100);
                <?php
            }
        } else {
            // حالت پیش‌فرض: یک ردیف خالی
            echo "AddCondition('$prefix', 0);";
        }
    }

    // =========================================================================
    // توابع کمکی AJAX Responses و تولید آپشن‌ها
    // =========================================================================

    public static function select_forms_ajax()
    {
        check_ajax_referer("gf_select_smspanel_form", "gf_select_smspanel_form");
        $form_id = intval(rgpost("form_id"));
        
        $form = RGFormsModel::get_form_meta($form_id);
        
        $merge_tags = self::get_form_fields_merge($form);
        $customer_field = self::get_mapped_fields("customer_field_clientnum", "", self::get_client_form_fields($form), true);
        $gf_code = self::get_mapped_fields("gf_code_dyn", "", self::get_client_form_fields($form), false);
        
        wp_send_json(array(
            "form" => $form,
            "merge_tags" => $merge_tags,
            "customer_field" => $customer_field,
            "gf_code" => $gf_code
        ));
    }

    public static function get_client_form_fields($form)
    {
        $fields = array();
        if (is_array($form["fields"])) {
            foreach ($form["fields"] as $field) {
                // پشتیبانی از فیلدهای ورودی (Inputs)
                if (isset($field["inputs"]) && is_array($field["inputs"])) {
                    foreach ($field["inputs"] as $input) {
                        if (!GFCommon::is_pricing_field($field["type"])) {
                            $fields[] = array($input["id"], GFCommon::get_label($field, $input["id"]));
                        }
                    }
                } 
                // پشتیبانی از فیلدهای معمولی
                else if (!rgar($field, 'displayOnly')) {
                    if (!GFCommon::is_pricing_field($field["type"])) {
                        $fields[] = array($field["id"], GFCommon::get_label($field));
                    }
                }
            }
        }
        return $fields;
    }

    public static function get_mapped_fields($variable_name, $selected_field, $fields, $empty)
    {
        $field_name = "smspanel_" . $variable_name;
        $str = "<select name=\"$field_name\" id=\"$field_name\" style=\"width:100%; max-width:300px;\">";
        $str .= $empty ? "<option value=\"\"></option>" : "";
        foreach ($fields as $field) {
            $field_id = $field[0];
            $field_label = esc_html(GFCommon::truncate_middle($field[1], 40));
            $selected = $field_id == $selected_field ? "selected='selected'" : "";
            $str .= "<option value=\"$field_id\" $selected>$field_label</option>";
        }
        $str .= "</select>";
        return $str;
    }

    public static function get_form_fields_merge($form)
    {
        $str = "<option value=''>" . __("افزودن متغیر فرم (Merge Tag)", "GF_SMS") . "</option>";
        $str .= "<optgroup label='" . __("فیلدهای فرم", "GF_SMS") . "'>";
        
        foreach ($form["fields"] as $field) {
            if ($field["displayOnly"]) continue;
            
            if (is_array($field["inputs"])) {
                foreach ($field["inputs"] as $input) {
                    $label = esc_html(GFCommon::get_label($field, $input["id"]));
                    $str .= "<option value='{" . $label . ":" . $input["id"] . "}'>" . $label . "</option>";
                }
            } else {
                $label = esc_html(GFCommon::get_label($field));
                $str .= "<option value='{" . $label . ":" . $field["id"] . "}'>" . $label . "</option>";
            }
        }
        $str .= "</optgroup>";
        
        $str .= "<optgroup label='اطلاعات دیگر'>
            <option value='{entry_id}'>شناسه ورودی</option>
            <option value='{ip}'>آی‌پی کاربر</option>
            <option value='{date_mdy}'>تاریخ ثبت</option>
            <option value='{source_url}'>آدرس صفحه</option>
            <option value='{payment_status}'>وضعیت پرداخت</option>
            <option value='{transaction_id}'>شناسه تراکنش</option>
        </optgroup>";
        
        return $str;
    }
}