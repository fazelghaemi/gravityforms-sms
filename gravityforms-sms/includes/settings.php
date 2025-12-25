<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('gform_tooltips', array('GF_MESSAGEWAY_Settings', 'tooltips'));

class GF_MESSAGEWAY_Settings
{
    protected static function check_access($required_permission)
    {
        if (!function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
        }
        return GFCommon::current_user_can_any($required_permission);
    }

    public static function tooltips($tooltips)
    {
        $tooltips["admin_default"] = __("شماره موبایل مدیران جهت دریافت پیامک. می‌توانید چند شماره را با کاما (,) جدا کنید. مثال: 09121234567,09191234567", "GF_SMS");
        
        $tooltips["show_credit"] = __("فعال‌سازی این گزینه پیشنهاد نمی‌شود؛ زیرا با هر بار بارگذاری صفحه تنظیمات، یک درخواست به وب‌سرویس ارسال می‌شود که ممکن است سرعت پیشخوان را کاهش دهد.", "GF_SMS");
        
        $tooltips["country_code"] = __("کد کشور پیش‌فرض برای شماره‌هایی که بدون کد وارد می‌شوند (مثال: +98).", "GF_SMS");
        
        $tooltips["gf_sms_sender"] = __("شماره‌های فرستنده را با کاما (,) جدا کنید.<br>برای استفاده از <strong>پیام‌رسان‌ها</strong> یا <strong>تماس صوتی</strong>، عبارات زیر را وارد کنید:<br><code>eitaa</code>, <code>bale</code>, <code>gap</code>, <code>igap</code>, <code>rubika</code>, <code>ivr</code><br>مثال: 300012345,eitaa,ivr", "GF_SMS");
        
        $tooltips["show_adminbar"] = __("نمایش منوی پیامک در نوار ابزار مدیریت وردپرس.", "GF_SMS");
        
        $tooltips["sidebar_ajax"] = __("اگر فعال باشد، مقادیر متغیرها (Merge Tags) در سایدبار پیامک به صورت ایجکس لود می‌شوند.", "GF_SMS");
        
        return $tooltips;
    }

    public static function settings()
    {
        // لود استایل‌های مورد نیاز (Chosen حذف شد اگر از Select2 بومی گرویتی استفاده شود، اما فعلاً نگه می‌داریم)
        wp_enqueue_script('GF_SMS_Chosen', GF_SMS_URL . '/assets/chosen_v1.8.5/chosen.jquery.min.js', array('jquery'), '1.8.5', true);
        wp_enqueue_style('GF_SMS_Chosen', GF_SMS_URL . '/assets/chosen_v1.8.5/chosen.min.css');

        $settings = GF_MESSAGEWAY::get_option();
        $current_gateway_slug = rgget('gateway') ? sanitize_text_field(rgget('gateway')) : (!empty($settings["ws"]) ? $settings["ws"] : '');
        $current_gateway_slug = strtolower($current_gateway_slug);
        
        $gateway_options = get_option("gf_smspanel_" . $current_gateway_slug);

        // --- عملیات حذف نصب ---
        if (!rgempty("uninstall")) {
            check_admin_referer("uninstall", "gf_smspanel_uninstall");
            if (!self::check_access("gravityforms_smspanel_uninstall")) {
                wp_die(__("شما دسترسی کافی برای حذف این افزونه را ندارید.", "GF_SMS"));
            } else {
                GF_MESSAGEWAY_SQL::drop_table();
                delete_option("gf_sms_settings");
                delete_option("gf_sms_version");
                delete_option("gf_sms_installed");
                delete_option("gf_sms_last_sender");
                foreach ((array)GF_MESSAGEWAY_WebServices::get() as $code => $name) {
                    delete_option("gf_smspanel_" . strtolower($code));
                }
                $plugin = "msgway-gravity-sms/msgway_gravity_sms.php"; // مسیر نسبی فایل اصلی
                deactivate_plugins($plugin);
                echo '<div class="updated fade" style="padding:20px;">' . __("افزونه با موفقیت حذف و اطلاعات پاکسازی شد.", "GF_SMS") . '</div>';
                return;
            }
        } 
        
        // --- عملیات ذخیره تنظیمات ---
        else if (!rgempty("gf_smspanel_submit")) {
            check_admin_referer("update", "gf_smspanel_update");
            
            $new_settings = array(
                "user_name" => sanitize_text_field(rgpost("gf_smspanel_user_name")), // برای درگاه‌های قدیمی
                "password" => sanitize_text_field(rgpost("gf_smspanel_password")),   // برای درگاه‌های قدیمی
                "from" => sanitize_text_field(rgpost("gf_smspanel_from")),
                "code" => sanitize_text_field(rgpost("gf_smspanel_code")),
                "to" => sanitize_text_field(rgpost("gf_smspanel_to")),
                "ws" => sanitize_text_field(rgpost("gf_smspanel_ws")),
                "cr" => sanitize_text_field(rgpost("gf_smspanel_showcr")),
                "menu" => sanitize_text_field(rgpost("gf_smspanel_menu")),
                "sidebar_ajax" => sanitize_text_field(rgpost("gf_smspanel_sidebar_ajax"))
            );

            update_option("gf_sms_settings", $new_settings);

            // ذخیره تنظیمات اختصاصی درگاه انتخاب شده
            if (!empty($new_settings["ws"]) && $new_settings["ws"] != 'no') {
                $Saved_Gateway_Class = 'GF_MESSAGEWAY_' . strtoupper($new_settings["ws"]);
                if (class_exists($Saved_Gateway_Class) && method_exists($Saved_Gateway_Class, 'options')) {
                    $gw_opts = array();
                    foreach ((array)$Saved_Gateway_Class::options() as $option => $name) {
                        $field_name = "gf_smspanel_" . strtolower($new_settings["ws"]) . '_' . $option;
                        $gw_opts[$option] = sanitize_text_field(rgpost($field_name));
                    }
                    update_option("gf_smspanel_" . strtolower($new_settings["ws"]), $gw_opts);
                }
            }

            echo '<div class="updated fade" style="padding:6px">' . __("تنظیمات با موفقیت ذخیره شد.", "GF_SMS") . '</div>';
            $settings = $new_settings; // رفرش متغیر برای نمایش
            $gateway_options = get_option("gf_smspanel_" . $current_gateway_slug);
        }
        ?>

        <div class="wrap">
            <h2>
                <i class="fa fa-mobile" style="font-size: 24px; vertical-align: middle;"></i>
                <?php _e("تنظیمات پیامک گرویتی فرم (نسخه راه‌پیام)", "GF_SMS"); ?>
            </h2>
            <hr>

            <?php
            // نمایش اعتبار
            if (!empty($current_gateway_slug) && $current_gateway_slug != 'no' && !empty($settings["ws"])) {
                if ($current_gateway_slug == strtolower($settings["ws"])) {
                    $credit = GF_MESSAGEWAY::credit(true);
                    if ($credit) {
                        // چون درگاه راه‌پیام لینک HTML برمی‌گرداند، آن را نمایش می‌دهیم
                        // اگر عدد بود، رنگ‌بندی اعمال می‌شود
                        $is_numeric = is_numeric(strip_tags($credit));
                        $style = $is_numeric ? '' : 'font-weight:bold;';
                        ?>
                        <div style="background: #fff; padding: 15px; border-left: 4px solid #00a0d2; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-bottom: 20px;">
                            <?php _e("اعتبار پنل پیامک:", "GF_SMS") ?> 
                            <span style="<?php echo $style; ?> color: #0073aa; margin-right: 10px;"><?php echo $credit; ?></span>
                        </div>
                        <?php
                    }
                }
            } 
            ?>

            <form method="post" action="">
                <?php wp_nonce_field("update", "gf_smspanel_update") ?>

                <table class="form-table">
                    
                    <!-- انتخاب درگاه -->
                    <tr>
                        <th scope="row"><label for="gf_smspanel_ws"><?php _e("درگاه پیامک", "GF_SMS"); ?></label></th>
                        <td>
                            <select id="gf_smspanel_ws" name="gf_smspanel_ws" class="select-gateway" style="width:300px;" onchange="GF_SwitchGateway(jQuery(this).val());">
                                <?php foreach ((array)GF_MESSAGEWAY_WebServices::get() as $code => $name) { ?>
                                    <option value="<?php echo esc_attr($code) ?>" <?php selected($current_gateway_slug, $code); ?>><?php echo esc_html($name) ?></option>
                                <?php } ?>
                            </select>
                            <p class="description"><?php _e("لطفاً درگاه MsgWay (راه‌پیام) را انتخاب کنید.", "GF_SMS"); ?></p>
                        </td>
                    </tr>

                    <!-- فیلدهای اختصاصی درگاه -->
                    <?php
                    if (!empty($current_gateway_slug) && $current_gateway_slug != 'no') {
                        $Gateway_Class = 'GF_MESSAGEWAY_' . strtoupper($current_gateway_slug);
                        if (class_exists($Gateway_Class) && method_exists($Gateway_Class, 'options')) {
                            foreach ((array)$Gateway_Class::options() as $option => $name) { ?>
                                <tr>
                                    <th scope="row"><label for="gf_smspanel_<?php echo $current_gateway_slug . '_' . $option; ?>"><?php echo $name; ?></label></th>
                                    <td>
                                        <input type="text" 
                                               id="gf_smspanel_<?php echo $current_gateway_slug . '_' . $option; ?>"
                                               name="gf_smspanel_<?php echo $current_gateway_slug . '_' . $option; ?>"
                                               value="<?php echo isset($gateway_options[$option]) ? esc_attr($gateway_options[$option]) : '' ?>" 
                                               class="regular-text" style="direction:ltr !important; text-align:left;"/>
                                    </td>
                                </tr>
                            <?php }
                        }
                    } 
                    ?>

                    <!-- کد کشور -->
                    <tr>
                        <th scope="row">
                            <label for="gf_smspanel_code">
                                <?php _e("کد کشور پیش‌فرض", "GF_SMS"); ?>
                                <?php gform_tooltip('country_code') ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="gf_smspanel_code" name="gf_smspanel_code"
                                   value="<?php echo !empty($settings["code"]) ? esc_attr($settings["code"]) : '+98' ?>" 
                                   class="regular-text" style="direction:ltr !important; text-align:left; width: 100px;"/>
                        </td>
                    </tr>

                    <!-- فرستنده -->
                    <tr>
                        <th scope="row">
                            <label for="gf_smspanel_from">
                                <?php _e("شماره فرستنده (From)", "GF_SMS"); ?>
                                <?php gform_tooltip('gf_sms_sender') ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="gf_smspanel_from" name="gf_smspanel_from"
                                   value="<?php echo !empty($settings["from"]) ? esc_attr($settings["from"]) : ''; ?>" 
                                   class="regular-text" style="direction:ltr !important; text-align:left;"/>
                            <p class="description">برای ارسال پیامک، شماره اختصاصی (مثال: 3000xxxxx) و برای پیام‌رسان‌ها نام آن‌ها (مثال: eitaa, bale) را وارد کنید.</p>
                        </td>
                    </tr>

                    <!-- شماره مدیر -->
                    <tr>
                        <th scope="row">
                            <label for="gf_smspanel_to">
                                <?php _e("شماره موبایل مدیر (پیش‌فرض)", "GF_SMS"); ?>
                                <?php gform_tooltip('admin_default') ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="gf_smspanel_to" name="gf_smspanel_to"
                                   value="<?php echo !empty($settings["to"]) ? esc_attr($settings["to"]) : ''; ?>" 
                                   class="regular-text" style="direction:ltr !important; text-align:left;"/>
                        </td>
                    </tr>

                    <!-- نمایش اعتبار -->
                    <tr>
                        <th scope="row">
                            <label><?php _e("نمایش اعتبار در هدر", "GF_SMS"); ?> <?php gform_tooltip('show_credit') ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="gf_smspanel_showcr" value="Show" <?php checked(isset($settings["cr"]) ? $settings["cr"] : '', 'Show'); ?> /> <?php _e("بله", "GF_SMS"); ?></label>
                                &nbsp;&nbsp;
                                <label><input type="radio" name="gf_smspanel_showcr" value="No" <?php checked(isset($settings["cr"]) ? $settings["cr"] : 'No', 'No'); ?> /> <?php _e("خیر (پیشنهادی)", "GF_SMS"); ?></label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- منوی ادمین بار -->
                    <tr>
                        <th scope="row">
                            <label><?php _e("منو در نوار ابزار مدیریت", "GF_SMS"); ?> <?php gform_tooltip('show_adminbar') ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="gf_smspanel_menu" value="Show" <?php checked(isset($settings["menu"]) ? $settings["menu"] : '', 'Show'); ?> /> <?php _e("بله", "GF_SMS"); ?></label>
                                &nbsp;&nbsp;
                                <label><input type="radio" name="gf_smspanel_menu" value="No" <?php checked(isset($settings["menu"]) ? $settings["menu"] : 'No', 'No'); ?> /> <?php _e("خیر", "GF_SMS"); ?></label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- دکمه ذخیره -->
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <input type="submit" name="gf_smspanel_submit" class="button button-primary" value="<?php _e("ذخیره تنظیمات", "GF_SMS") ?>"/>
                        </td>
                    </tr>

                </table>
            </form>

            <!-- بخش حذف افزونه -->
            <div style="margin-top: 50px; border-top: 1px solid #ddd; padding-top: 20px;">
                <h3><?php _e("عملیات خطرناک", "GF_SMS") ?></h3>
                <form action="" method="post" style="display:inline-block;">
                    <?php wp_nonce_field("uninstall", "gf_smspanel_uninstall") ?>
                    <p class="description" style="color: #dc3232;"><?php _e("هشدار: این عملیات تمامی تنظیمات و جداول دیتابیس مربوط به پیامک گرویتی فرم را پاک می‌کند.", "GF_SMS") ?></p>
                    <?php if (self::check_access("gravityforms_smspanel_uninstall")) { ?>
                        <input type="submit" name="uninstall" value="<?php _e("حذف کامل تنظیمات و افزونه", "GF_SMS") ?>" class="button button-link-delete" 
                               onclick="return confirm('<?php _e("آیا مطمئن هستید؟ تمام اطلاعات پیامک‌ها و تنظیمات حذف خواهند شد.", "GF_SMS") ?>'); "/>
                    <?php } ?>
                </form>
            </div>
        </div>

        <script type="text/javascript">
            function GF_SwitchGateway(code) {
                var url = new URL(window.location.href);
                url.searchParams.set("gateway", code);
                window.location.href = url.href;
            }
            jQuery(document).ready(function($) {
                if($.fn.chosen) {
                    $(".select-gateway").chosen({rtl: <?php echo is_rtl() ? 'true' : 'false'; ?>});
                }
            });
        </script>
        <?php
    }
}

// بارگذاری فایل‌های درگاه برای لیست کردن در سلکت باکس
if (defined('GF_SMS_GATEWAY')) {
    foreach (glob(GF_SMS_GATEWAY . "*.php") as $filename) {
        include_once $filename;
        $file_basename = basename($filename, ".php");
        $Gateway_Class = 'GF_MESSAGEWAY_' . strtoupper($file_basename);
        
        if (class_exists($Gateway_Class) && method_exists($Gateway_Class, 'options') && method_exists($Gateway_Class, 'name')) {
            add_filter('gf_sms_gateways', array($Gateway_Class, 'name'));
        }
    }
}