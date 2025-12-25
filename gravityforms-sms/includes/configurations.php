<?php
if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_Configurations
{
    public static function construct()
    {
        if (defined('RG_CURRENT_PAGE') && in_array(RG_CURRENT_PAGE, array('admin-ajax.php'))) {
            add_action('wp_ajax_gf_select_smspanel_form', array(__CLASS__, 'select_forms_ajax'));
            // دسترسی nopriv برداشته شد چون تنظیمات فقط برای ادمین است
        }
    }

    public static function configuration()
    {
        // استایل‌های ادمین
        wp_register_style('gform_admin_sms', GFCommon::get_base_url() . '/css/admin.css');
        wp_print_styles(array('jquery-ui-styles', 'gform_admin_sms', 'wp-pointer'));
        
        // استایل اختصاصی برای برندینگ و بهبود UI
        ?>
        <style>
            .ready-studio-badge {
                background: #fff;
                border: 1px solid #e5e5e5;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .ready-studio-badge a {
                text-decoration: none;
                font-weight: bold;
                color: #0073aa;
            }
            .ready-studio-badge .rs-logo {
                font-size: 12px;
                color: #777;
            }
            .gf_admin_page_title {
                font-family: Tahoma, sans-serif !important;
            }
            /* استایل‌های شرطی */
            .delete_admin_condition, .add_admin_condition, .delete_client_condition, .add_client_condition {
                text-decoration: none !important;
                color: #555;
                font-size: 16px;
                margin: 0 5px;
                vertical-align: middle;
            }
            .delete_admin_condition:hover, .delete_client_condition:hover { color: #d63638; }
            .add_admin_condition:hover, .add_client_condition:hover { color: #00a0d2; }
            .condition_field_value { width: 200px !important; }
            .gf_adminsms_conditional_div, .gf_clientsms_conditional_div { margin: 5px 0; padding: 5px; background: #f9f9f9; border: 1px dashed #ddd; border-radius: 4px; }
            #dpWrapper { position: fixed; z-index: 100000; top: 10%; bottom: 10%; right: 10%; left: 10%; background: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.2); border-radius: 5px; display: flex; flex-direction: column; }
            .dpTitle { padding: 15px; border-bottom: 1px solid #eee; background: #fcfcfc; font-weight: bold; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
            .dpTitle .close { cursor: pointer; font-size: 20px; line-height: 1; }
            .dpBody { flex: 1; overflow-y: auto; padding: 20px; }
            .dpFooter { padding: 15px; border-top: 1px solid #eee; background: #fcfcfc; text-align: left; }
            .pattern-message { margin-top: 10px; padding: 10px; border-radius: 4px; background: #e7f5fe; border: 1px solid #bde0f7; color: #0c5460; }
        </style>
        <?php

        $id = !rgempty("smspanel_setting_id") ? rgpost("smspanel_setting_id") : absint(rgget("id"));
        $config = empty($id) ? array("is_active" => true, "meta" => array()) : GF_MESSAGEWAY_SQL::get_feed($id);
        
        $get_feeds = GF_MESSAGEWAY_SQL::get_feeds();
        $form_name = '';
        $_get_form_id = !empty($config["form_id"]) ? $config["form_id"] : rgget('fid');
        
        foreach ((array)$get_feeds as $get_feed) {
            if ($get_feed['id'] == $id) {
                $form_name = $get_feed['form_title'];
            }
        }
        ?>

        <div class="wrap gforms_edit_form gf_browser_gecko">

            <!-- برندینگ ردی استودیو -->
            <div class="ready-studio-badge">
                <div>
                    <strong>افزونه پیامک گرویتی فرم (نسخه حرفه‌ای راه‌پیام)</strong>
                </div>
                <div class="rs-logo">
                    توسعه و بهینه‌سازی توسط <a href="https://readystudio.ir" target="_blank" title="طراحی سایت و افزونه وردپرس">ردی استودیو</a>
                </div>
            </div>

            <h2 class="gf_admin_page_title">
                <?php _e("پیکربندی پیامک فرم‌ها", "GF_SMS") ?>
                <?php if (!empty($_get_form_id)) { ?>
                    <span class="gf_admin_page_subtitle">
                        <span class="gf_admin_page_formid"><?php echo sprintf(__("شناسه فید: %s", "GF_SMS"), $id) ?></span>
                        <span class="gf_admin_page_formname"><?php echo sprintf(__("نام فرم: %s", "GF_SMS"), $form_name) ?></span>
                    </span>
                <?php } ?>
            </h2>

            <a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_sms_pro" style="margin:8px 9px;">
                <?php _e("تنظیمات عمومی پیامک", "GF_SMS") ?>
            </a>

            <?php
            $settings = GF_MESSAGEWAY::get_option();
            $is_OK = (!empty($settings["ws"]) && $settings["ws"] != 'no');
            
            if ($is_OK) {
                echo '<div style="display:inline-table;margin-top:7px !important; margin-right: 10px;">';
                GF_MESSAGEWAY::show_credit($settings["cr"], true);
                echo '</div>';
            } else {
               echo '<div class="error"><p>' . __('لطفاً ابتدا در تنظیمات عمومی، درگاه MsgWay را انتخاب و تنظیم کنید.', 'GF_SMS') . '</p></div>';
               // wp_die(); // حذف شد تا کاربر بتواند منوها را ببیند
            }

            // --- ذخیره تنظیمات ---
            if (!rgempty("gf_smspanel_submit")) {
                check_admin_referer("update", "gf_smspanel_feed");
                
                $config["form_id"] = absint(rgpost("gf_smspanel_form"));
                
                // جمع‌آوری داده‌ها
                $config["meta"]["from"] = sanitize_text_field(rgpost("gf_smspanel_from"));
                $config["meta"]["to"] = sanitize_text_field(rgpost("gf_smspanel_to"));
                $config["meta"]["to_c"] = sanitize_text_field(rgpost("gf_smspanel_to_c"));
                $config["meta"]["when"] = sanitize_text_field(rgpost("gf_smspanel_when"));
                
                // پیام‌ها (اجازه استفاده از تگ‌های مجاز)
                $config["meta"]["message"] = wp_kses_post(rgpost("gf_smspanel_message"));
                $config["meta"]["message_c"] = wp_kses_post(rgpost("gf_smspanel_message_c"));
                
                // تنظیمات کد کشور
                $config["meta"]["gf_sms_change_code"] = rgpost('gf_sms_change_code');
                $config["meta"]["gf_change_code_type"] = rgpost("gf_change_code_type");
                $config["meta"]["gf_code_static"] = sanitize_text_field(rgpost("gf_code_static"));
                $config["meta"]["gf_code_dyn"] = rgpost("smspanel_gf_code_dyn");
                
                // تنظیمات درگاه پرداخت
                $config["meta"]["gf_sms_is_gateway_checked"] = rgpost('gf_sms_is_gateway_checked');
                
                // فیلد موبایل کاربر
                $config["meta"]["customer_field_clientnum"] = rgpost("smspanel_customer_field_clientnum");
                
                // منطق شرطی مدیر
                $config["meta"]["adminsms_conditional_enabled"] = rgpost('gf_adminsms_conditional_enabled');
                $config["meta"]["adminsms_conditional_type"] = rgpost('gf_adminsms_conditional_type');
                $config["meta"]["adminsms_conditional_field_id"] = rgpost('gf_adminsms_conditional_field_id');
                $config["meta"]["adminsms_conditional_operator"] = rgpost('gf_adminsms_conditional_operator');
                $config["meta"]["adminsms_conditional_value"] = rgpost('gf_adminsms_conditional_value');
                
                // منطق شرطی کاربر
                $config["meta"]["clientsms_conditional_enabled"] = rgpost('gf_clientsms_conditional_enabled');
                $config["meta"]["clientsms_conditional_type"] = rgpost('gf_clientsms_conditional_type');
                $config["meta"]["clientsms_conditional_field_id"] = rgpost('gf_clientsms_conditional_field_id');
                $config["meta"]["clientsms_conditional_operator"] = rgpost('gf_clientsms_conditional_operator');
                $config["meta"]["clientsms_conditional_value"] = rgpost('gf_clientsms_conditional_value');

                // Sanitize Data Loop
                $safe_data = array();
                foreach ($config["meta"] as $key => $val) {
                    if (in_array($key, array('adminsms_conditional_operator', 'clientsms_conditional_operator'))) {
                        $safe_data[$key] = $val; // عملگرها امن هستند (دراپ دان)
                    } else if (in_array($key, array('message', 'message_c'))) {
                        $safe_data[$key] = $val; // قبلا kses شدند
                    } else if (!is_array($val)) {
                        $safe_data[$key] = sanitize_text_field($val);
                    } else {
                        // آرایه‌ها (مثل مقادیر شرطی)
                        $safe_data[$key] = array_map('sanitize_text_field', $val);
                    }
                }
                $config["meta"] = $safe_data;

                // ذخیره در دیتابیس
                $id = GF_MESSAGEWAY_SQL::update_feed($id, $config["form_id"], $config["is_active"], $config["meta"]);
                
                if (!headers_sent()) {
                    echo "<script>window.location.href='" . admin_url('admin.php?page=gf_smspanel&view=edit&id=' . $id . '&updated=true') . "';</script>";
                    exit;
                }
            }

            $_get_form_id = !empty($config["form_id"]) ? $config["form_id"] : rgget('fid');
            
            if (rgget('updated') == 'true') {
                echo '<div class="updated fade" style="padding:6px">' . sprintf(__("فید بروزرسانی شد. %sبازگشت به لیست%s", "GF_SMS"), "<a href='?page=gf_smspanel'>", "</a>") . '</div>';
            }

            // --- نوار ابزار انتخاب فرم ---
            if (!empty($_get_form_id)) { ?>
                <div id="gf_form_toolbar">
                    <ul id="gf_form_toolbar_links">
                        <?php
                        $menu_items = apply_filters('gform_toolbar_menu', GFForms::get_toolbar_menu_items($_get_form_id), $_get_form_id);
                        echo GFForms::format_toolbar_menu_items($menu_items); 
                        ?>
                        <li class="gf_form_switcher">
                            <label for="form_switcher"><?php _e('انتخاب فید', 'GF_SMS') ?></label>
                            <?php $feeds = GF_MESSAGEWAY_SQL::get_feeds(); ?>
                            <select name="form_switcher" id="form_switcher" onchange="GF_SwitchForm(jQuery(this).val());">
                                <option value=""><?php _e('تغییر فید پیامک', 'GF_SMS') ?></option>
                                <?php foreach ($feeds as $feed) {
                                    $selected = $feed["id"] == $id ? "selected='selected'" : ""; ?>
                                    <option value="<?php echo $feed["id"] ?>" <?php echo $selected ?> >
                                        <?php echo sprintf(__('فرم: %s (فید: %s)', 'GF_SMS'), $feed["form_title"], $feed["id"]) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </li>
                    </ul>
                </div>
            <?php } ?>

            <!-- تب‌های تنظیمات -->
            <div id="gform_tab_group" class="gform_tab_group vertical_tabs">
                <?php if (!empty($_get_form_id)) { ?>
                    <ul id="gform_tabs" class="gform_tabs">
                        <?php
                        $get_form = GFFormsModel::get_form_meta($_get_form_id);
                        $current_tab = rgempty('subview', $_GET) ? 'settings' : rgget('subview');
                        $current_tab = !empty($current_tab) ? $current_tab : '';
                        $setting_tabs = GFFormSettings::get_tabs($get_form['id']);
                        
                        if (!empty($current_tab)) {
                            foreach ($setting_tabs as $tab) {
                                $query = array('page' => 'gf_edit_forms', 'view' => 'settings', 'subview' => $tab['name'], 'id' => $get_form['id']);
                                $url = add_query_arg($query, admin_url('admin.php'));
                                echo $tab['name'] == 'sms' ? '<li class="active">' : '<li>'; ?>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($tab['label']) ?></a>
                                <span></span>
                                </li>
                                <?php
                            }
                        } ?>
                    </ul>
                <?php } ?>

                <div id="gform_tab_container_<?php echo $_get_form_id ? $_get_form_id : 1 ?>" class="gform_tab_container">
                    <div class="gform_tab_content" id="tab_<?php echo !empty($current_tab) ? $current_tab : '' ?>">
                        <div id="form_settings" class="gform_panel gform_panel_form_settings">
                            
                            <h3>
                                <span><i class="fa fa-mobile"></i> <?php _e("پیکربندی عمومی", "GF_SMS"); ?></span>
                            </h3>

                            <form method="post" action="" id="gform_form_settings">
                                <?php wp_nonce_field("update", "gf_smspanel_feed") ?>
                                <input type="hidden" name="smspanel_setting_id" value="<?php echo $id ?>"/>

                                <!-- جدول تنظیمات عمومی -->
                                <table class="gforms_form_settings" cellspacing="0" cellpadding="0">
                                    <tbody>
                                    <tr>
                                        <td colspan="2">
                                            <h4 class="gf_settings_subgroup_title"><?php _e("تنظیمات پایه", "GF_SMS"); ?></h4>
                                        </td>
                                    </tr>

                                    <tr id="smspanel_form_container">
                                        <th><?php _e("انتخاب فرم", "GF_SMS"); ?></th>
                                        <td>
                                            <select id="gf_smspanel_form" name="gf_smspanel_form" onchange="SelectFormAjax(jQuery(this).val());">
                                                <option value=""><?php _e("لطفاً یک فرم انتخاب کنید", "GF_SMS"); ?> </option>
                                                <?php
                                                $forms = RGFormsModel::get_forms();
                                                foreach ((array)$forms as $form) {
                                                    $selected = absint($form->id) == $_get_form_id ? "selected='selected'" : ""; ?>
                                                    <option value="<?php echo absint($form->id) ?>" <?php echo $selected ?>><?php echo esc_html($form->title) ?></option>
                                                <?php } ?>
                                            </select>&nbsp;&nbsp;
                                            <img src="<?php echo esc_url(GFCommon::get_base_url()) ?>/images/spinner.gif" id="smspanel_wait" style="display: none;"/>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>

                                <!-- کانتینر اصلی تنظیمات فید -->
                                <table class="gforms_form_settings" id="smspanel_field_group" <?php echo empty($_get_form_id) ? "style='display:none;'" : "" ?> cellspacing="0" cellpadding="0">
                                    <tbody>
                                    
                                    <!-- فرستنده -->
                                    <tr>
                                        <th><?php _e("شماره فرستنده", "GF_SMS"); ?></th>
                                        <td>
                                            <select id="gf_smspanel_from" name="gf_smspanel_from">
                                                <option value=""><?php _e("انتخاب شماره فرستنده", "GF_SMS"); ?></option>
                                                <?php
                                                $sender_num = isset($settings["from"]) ? $settings["from"] : '';
                                                $sender_nums = array();
                                                if (!empty($settings["from"])) {
                                                    $sender_nums = explode(',', $settings["from"]);
                                                }
                                                // پشتیبانی از IVR و نام‌های مسنجر
                                                $extra_senders = ['ivr', 'eitaa', 'gap', 'bale', 'rubika'];
                                                $sender_nums = array_merge($sender_nums, $extra_senders);
                                                $sender_nums = array_unique($sender_nums);

                                                foreach ((array)$sender_nums as $sender_num) {
                                                    $sender_num = trim($sender_num);
                                                    if(empty($sender_num)) continue;
                                                    $selected = (isset($config["meta"]["from"]) && $sender_num == $config["meta"]["from"]) ? "selected='selected'" : ""; ?>
                                                    <option value="<?php echo $sender_num ?>" <?php echo $selected ?> ><?php echo $sender_num ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    </tr>

                                    <!-- ادغام با درگاه پرداخت -->
                                    <tr>
                                        <th><?php _e("ادغام با درگاه پرداخت", "GF_SMS"); ?></th>
                                        <td>
                                            <input type="checkbox" id="gf_sms_is_gateway_checked" name="gf_sms_is_gateway_checked" value="1"
                                                   onclick="if(this.checked){jQuery('#gf_sms_is_gateway_checked_box').fadeIn('fast');} else{ jQuery('#gf_sms_is_gateway_checked_box').fadeOut('fast'); }" 
                                                   <?php echo rgar($config['meta'], 'gf_sms_is_gateway_checked') ? "checked='checked'" : "" ?>/>
                                            <label for="gf_sms_is_gateway_checked"><?php _e("فقط زمانی ارسال شود که فرم به درگاه پرداخت متصل باشد.", "GF_SMS"); ?></label>
                                            
                                            <div id="gf_sms_is_gateway_checked_box" style="margin-top:10px; <?php echo !rgar($config['meta'], 'gf_sms_is_gateway_checked') ? 'display:none' : ''; ?>">
                                                <p><?php _e("زمان ارسال پیامک:", "GF_SMS") ?></p>
                                                <select id="gf_smspanel_when" name="gf_smspanel_when">
                                                    <option value="send_immediately" <?php selected(rgar($config["meta"], "when"), "send_immediately"); ?>><?php _e("بلافاصله بعد از ثبت فرم", "GF_SMS"); ?> </option>
                                                    <option value="after_pay" <?php selected(rgar($config["meta"], "when"), "after_pay"); ?>><?php _e("بعد از بازگشت از پرداخت (موفق یا ناموفق)", "GF_SMS"); ?> </option>
                                                    <option value="after_pay_success" <?php selected(rgar($config["meta"], "when"), "after_pay_success"); ?>><?php _e("فقط بعد از پرداخت موفق", "GF_SMS"); ?> </option>
                                                </select>
                                                <p class="description"><?php _e('نکته: درگاه پرداخت شما باید استاندارد باشد و از اکشن gform_post_payment_status پشتیبانی کند.', 'GF_SMS') ?></p>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- تغییر کد کشور -->
                                    <tr>
                                        <th><?php _e("تغییر کد کشور", "GF_SMS"); ?></th>
                                        <td>
                                            <input type="checkbox" id="gf_sms_change_code" name="gf_sms_change_code" value="1"
                                                   onclick="if(this.checked){jQuery('#gf_sms_change_code_box').fadeIn('fast');} else{ jQuery('#gf_sms_change_code_box').fadeOut('fast'); }" 
                                                   <?php echo rgar($config['meta'], 'gf_sms_change_code') ? "checked='checked'" : "" ?>/>
                                            <label for="gf_sms_change_code"><?php _e("تغییر پیش‌فرض کد کشور (+98)", "GF_SMS"); ?></label>
                                            
                                            <div id="gf_sms_change_code_box" style="margin-top:10px; <?php echo isset($config['meta']) && !rgar($config['meta'], 'gf_sms_change_code') ? 'display:none' : ''; ?>">
                                                <input type="radio" name="gf_change_code_type" id="gf_change_code_type_static" value="static" <?php echo rgar($config['meta'], 'gf_change_code_type') != 'dyn' ? "checked='checked'" : "" ?>/>
                                                <label for="gf_change_code_type_static" class="inline"><?php _e('ثابت', 'GF_SMS'); ?></label>

                                                <input type="radio" name="gf_change_code_type" id="gf_change_code_type_dyn" value="dyn" <?php echo rgar($config['meta'], 'gf_change_code_type') == 'dyn' ? "checked='checked'" : "" ?>/>
                                                <label for="gf_change_code_type_dyn" class="inline"><?php _e('پویا (از فیلد فرم)', 'GF_SMS'); ?></label>

                                                <div style="margin-top:5px;">
                                                    <input type="text" name="gf_code_static" id="smspanel_gf_code_static"
                                                           value="<?php echo isset($config["meta"]["gf_code_static"]) ? esc_attr($config["meta"]["gf_code_static"]) : (isset($settings["code"]) ? $settings["code"] : ''); ?>"
                                                           style="direction:ltr !important; text-align:left; <?php echo isset($config['meta']) && rgar($config['meta'], 'gf_change_code_type') == 'dyn' ? 'display:none' : ''; ?>">

                                                    <span id="smspanel_gf_code_dyn_div" <?php echo isset($config['meta']) && rgar($config['meta'], 'gf_change_code_type') == 'dyn' ? '' : 'style="display:none"'; ?>>
                                                        <?php
                                                        if (!empty($_get_form_id)) {
                                                            $form_meta = RGFormsModel::get_form_meta($_get_form_id);
                                                            echo !empty($form_meta) ? self::get_country_code($form_meta, $config) : '';
                                                        } ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- تنظیمات پیامک مدیر -->
                                    <tr>
                                        <td colspan="2">
                                            <h4 class="gf_settings_subgroup_title">
                                                <?php _e("تنظیمات پیامک مدیر", "GF_SMS"); ?>
                                                <span style="font-weight:normal; font-size:12px;">(برای عدم ارسال خالی بگذارید)</span>
                                            </h4>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e("شماره‌های مدیر", "GF_SMS"); ?></th>
                                        <td>
                                            <input type="text" class="fieldwidth-1" name="gf_smspanel_to"
                                                   value="<?php echo isset($config["meta"]["to"]) ? esc_attr($config["meta"]["to"]) : (isset($settings["to"]) ? $settings["to"] : ''); ?>"
                                                   style="direction:ltr !important; text-align:left;">
                                            <span class="description"><?php _e("با کاما (,) جدا کنید. مثال: 09121234567", "GF_SMS") ?></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e("متن پیامک مدیر", "GF_SMS"); ?></th>
                                        <td>
                                            <select id="gf_smspanel_message_variable_select" onchange="InsertVariable('gf_smspanel_message');">
                                                <?php if (!empty($_get_form_id)) {
                                                    $form_meta = RGFormsModel::get_form_meta($_get_form_id);
                                                    echo !empty($form_meta) ? self::get_form_fields_merge($form_meta) : '';
                                                } ?>
                                            </select>
                                            <br/>
                                            <textarea id="gf_smspanel_message" name="gf_smspanel_message" class="fieldwidth-3 fieldheight-2"><?php echo rgget("message", $config["meta"]) ?></textarea>
                                            
                                            <!-- دکمه‌های راهنما و پترن -->
                                            <div style="margin-top:5px;">
                                                <a class="patternlearn button button-secondary"><?php _e("آموزش ثبت الگو", "GF_SMS") ?></a>
                                                <a class="pattern-wizard button button-secondary"><?php _e("ابزار تولید پترن", "GF_SMS") ?></a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- منطق شرطی مدیر -->
                                    <?php self::render_conditional_logic('adminsms', $config); ?>

                                    <!-- تنظیمات پیامک کاربر -->
                                    <tr>
                                        <td colspan="2">
                                            <h4 class="gf_settings_subgroup_title">
                                                <?php _e("تنظیمات پیامک کاربر", "GF_SMS"); ?>
                                            </h4>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e("فیلد شماره موبایل کاربر", "GF_SMS"); ?></th>
                                        <td id="smspanel_customer_field">
                                            <?php
                                            if (!empty($_get_form_id)) {
                                                $form_meta = RGFormsModel::get_form_meta($_get_form_id);
                                                echo !empty($form_meta) ? self::get_client_information($form_meta, $config) : '';
                                            } ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e("شماره‌های اضافی", "GF_SMS"); ?></th>
                                        <td>
                                            <input type="text" class="fieldwidth-1" name="gf_smspanel_to_c"
                                                   value="<?php echo isset($config["meta"]["to_c"]) ? esc_attr($config["meta"]["to_c"]) : ''; ?>"
                                                   style="direction:ltr !important; text-align:left;">
                                            <span class="description"><?php _e("شماره‌های ثابت دیگری که می‌خواهید همراه کاربر دریافت کنند.", "GF_SMS") ?></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e("متن پیامک کاربر", "GF_SMS"); ?></th>
                                        <td>
                                            <select id="gf_smspanel_message_c_variable_select" onchange="InsertVariable('gf_smspanel_message_c');">
                                                <?php
                                                if (!empty($_get_form_id)) {
                                                    $form_meta = RGFormsModel::get_form_meta($_get_form_id);
                                                    echo !empty($form_meta) ? self::get_form_fields_merge($form_meta) : '';
                                                } ?>
                                            </select>
                                            <br/>
                                            <textarea id="gf_smspanel_message_c" name="gf_smspanel_message_c" class="fieldwidth-3 fieldheight-2"><?php echo rgget("message_c", $config["meta"]) ?></textarea>
                                            <div style="margin-top:5px;">
                                                <a class="patternlearn button button-secondary"><?php _e("آموزش ثبت الگو", "GF_SMS") ?></a>
                                                <a class="pattern-wizard button button-secondary"><?php _e("ابزار تولید پترن", "GF_SMS") ?></a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- منطق شرطی کاربر -->
                                    <?php self::render_conditional_logic('clientsms', $config); ?>

                                    <tr>
                                        <td>
                                            <input type="submit" class="button-primary gfbutton" name="gf_smspanel_submit" value="<?php _e("ذخیره تنظیمات", "GF_SMS"); ?>"/>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; color: #999;">
                <p>پشتیبانی شده توسط <a href="https://readystudio.ir" target="_blank" style="text-decoration:none; color:#0073aa;">Ready Studio</a></p>
            </div>

        </div>

        <!-- اسکریپت‌های صفحه -->
        <script type="text/javascript">
            var form = [];
            form = <?php echo !empty($form_meta) ? GFCommon::json_encode($form_meta) : GFCommon::json_encode(array()) ?>;

            jQuery(document).ready(function ($) {
                // مدیریت لاجیک شرطی
                $('.delete_admin_condition').first().hide();
                $('.delete_client_condition').first().hide();

                // تغییر نوع کد کشور
                $(document.body).on('click', 'input[name="gf_change_code_type"]', function () {
                    if ($('input[name="gf_change_code_type"]:checked').val() === 'dyn') {
                        $("#smspanel_gf_code_dyn_div").show("slow");
                        $("#smspanel_gf_code_static").hide("slow");
                    } else {
                        $("#smspanel_gf_code_dyn_div").hide("slow");
                        $("#smspanel_gf_code_static").show("slow");
                    }
                });

                // تغییر فیلد شرطی (Admin)
                $(document.body).on('change', '.gf_adminsms_conditional_field_id', function () {
                    var id = $(this).attr('id').replace('gf_adminsms_', '').replace('__conditional_field_id', '');
                    var selectedOperator = $('#gf_adminsms_' + id + '__conditional_operator').val();
                    $('#gf_adminsms_' + id + '__conditional_value_container').html(GetConditionalFieldValues("gf_adminsms_" + id + "__conditional", $(this).val(), selectedOperator, "", 20, id));
                });

                // تغییر عملگر شرطی (Admin)
                $(document.body).on('change', '.gf_adminsms_conditional_operator', function () {
                    var id = $(this).attr('id').replace('gf_adminsms_', '').replace('__conditional_operator', '');
                    var selectedOperator = $(this).val();
                    var field_id = $('#gf_adminsms_' + id + '__conditional_field_id').val();
                    $('#gf_adminsms_' + id + '__conditional_value_container').html(GetConditionalFieldValues("gf_adminsms_" + id + "__conditional", field_id, selectedOperator, "", 20, id));
                });

                // افزودن شرط جدید (Admin)
                $(document.body).on('click', '.add_admin_condition', function () {
                    var parent_div = $(this).parent('.gf_adminsms_conditional_div');
                    var counter = $('#gf_adminsms_conditional_counter');
                    var new_id = parseInt(counter.val()) + 1;
                    var content = parent_div[0].outerHTML
                        .replace(new RegExp('gf_adminsms_\\d+__', 'g'), ('gf_adminsms_' + new_id + '__'))
                        .replace(new RegExp('\\[\\d+\\]', 'g'), ('[' + new_id + ']'));
                    counter.val(new_id);
                    counter.before(content);
                    RefreshConditionRow("gf_adminsms_" + new_id + "__conditional", "", "is", "", new_id);
                    $('.delete_admin_condition').show();
                    return false;
                });

                // حذف شرط (Admin)
                $(document.body).on('click', '.delete_admin_condition', function () {
                    if ($('.gf_adminsms_conditional_div').length > 1) {
                        $(this).parent('.gf_adminsms_conditional_div').remove();
                    }
                    if ($('.gf_adminsms_conditional_div').length === 1) {
                        $('.delete_admin_condition').hide();
                    }
                    return false;
                });

                // کپی لاجیک برای Client (مشابه Admin)
                $(document.body).on('change', '.gf_clientsms_conditional_field_id', function () {
                    var id = $(this).attr('id').replace('gf_clientsms_', '').replace('__conditional_field_id', '');
                    var selectedOperator = $('#gf_clientsms_' + id + '__conditional_operator').val();
                    $('#gf_clientsms_' + id + '__conditional_value_container').html(GetConditionalFieldValues("gf_clientsms_" + id + "__conditional", $(this).val(), selectedOperator, "", 20, id));
                });
                $(document.body).on('change', '.gf_clientsms_conditional_operator', function () {
                    var id = $(this).attr('id').replace('gf_clientsms_', '').replace('__conditional_operator', '');
                    var selectedOperator = $(this).val();
                    var field_id = $('#gf_clientsms_' + id + '__conditional_field_id').val();
                    $('#gf_clientsms_' + id + '__conditional_value_container').html(GetConditionalFieldValues("gf_clientsms_" + id + "__conditional", field_id, selectedOperator, "", 20, id));
                });
                $(document.body).on('click', '.add_client_condition', function () {
                    var parent_div = $(this).parent('.gf_clientsms_conditional_div');
                    var counter = $('#gf_clientsms_conditional_counter');
                    var new_id = parseInt(counter.val()) + 1;
                    var content = parent_div[0].outerHTML
                        .replace(new RegExp('gf_clientsms_\\d+__', 'g'), ('gf_clientsms_' + new_id + '__'))
                        .replace(new RegExp('\\[\\d+\\]', 'g'), ('[' + new_id + ']'));
                    counter.val(new_id);
                    counter.before(content);
                    RefreshConditionRow("gf_clientsms_" + new_id + "__conditional", "", "is", "", new_id);
                    $('.delete_client_condition').show();
                    return false;
                });
                $(document.body).on('click', '.delete_client_condition', function () {
                    if ($('.gf_clientsms_conditional_div').length > 1) {
                        $(this).parent('.gf_clientsms_conditional_div').remove();
                    }
                    if ($('.gf_clientsms_conditional_div').length === 1) {
                        $('.delete_client_condition').hide();
                    }
                    return false;
                });

                // مقداردهی اولیه شرط‌های ذخیره شده
                <?php self::init_saved_conditions('adminsms', $config); ?>
                <?php self::init_saved_conditions('clientsms', $config); ?>
                
                // پاپ‌آپ پترن ویزارد
                jQuery(function ($) {
                    function smspanelShowMessage(title, message, footer) {
                        $('body').append('<div id="dpWrapper"><div class="dpTitle">' + title + '<span class="close">×</span></div><div class="dpBody">' + message + '</div><div class="dpFooter">' + footer + '<button class="close button-secondary" style="float:left">بستن</button></div></div>');
                        $('#dpWrapper .close').click(function () { $('#dpWrapper').remove(); });
                    }

                    $('.patternlearn').on('click', function () {
                        var html = '<p>برای استفاده از خطوط خدماتی و ارسال آنی، باید از الگو (Pattern) استفاده کنید.</p>' +
                                   '<p>1. وارد پنل راه‌پیام شوید (بخش الگوها).</p>' +
                                   '<p>2. الگوی خود را ثبت کنید. مثال: <code>کد تایید شما: [code]</code></p>' +
                                   '<p>3. پس از تایید الگو، کد آن را دریافت کنید.</p>';
                        smspanelShowMessage('راهنمای الگو', html, 'MsgWay Service');
                    });

                    $(".pattern-wizard").click(function () {
                        var targettextarea = $(this).closest('td').find('textarea');
                        var html = '<div class="onlinepattern">' +
                                   '<div class="form-group"><label>کد الگو (Pattern ID): </label><input class="patterncodeinput regular-text" type="text" placeholder="مثال: 12345"> ' +
                                   '<button class="button button-primary onlinepcodechecker" type="button">بررسی الگو</button></div>' +
                                   '<div class="pattern-message"></div><div class="pcodecheckresult" style="margin-top:10px;"></div>' +
                                   '<div style="margin-top:10px;"><button class="button button-primary patterninsert hidden" type="button">درج در فرم</button></div></div>';
                        
                        smspanelShowMessage('ابزار تنظیم پترن', html, 'اتصال به راه‌پیام');

                        $('.onlinepcodechecker').on('click', function () {
                            var pcode = $('.patterncodeinput').val();
                            if(!pcode) { alert('لطفا کد الگو را وارد کنید'); return; }
                            
                            $.ajax({
                                type: 'post',
                                url: ajaxurl,
                                data: {
                                    action: 'gf_smspanel_checkPattern',
                                    patternCode: pcode
                                },
                                beforeSend: function() { $('.pattern-message').html('در حال بررسی...'); },
                                success: function(json) {
                                    var obj = JSON.parse(json);
                                    if(obj.status === 0) {
                                        $('.pattern-message').html('الگو یافت شد: ' + obj.message);
                                        var varsHtml = '';
                                        obj.vars.forEach(function(v) {
                                            varsHtml += '<div style="margin:5px 0"><label style="width:100px;display:inline-block">' + v + ':</label><input type="text" class="pvar-input" data-var="'+v+'" placeholder="مقدار یا شرت‌کد"></div>';
                                        });
                                        $('.pcodecheckresult').html(varsHtml);
                                        $('.patterninsert').removeClass('hidden');
                                        
                                        $('.patterninsert').off('click').on('click', function() {
                                            var finalStr = 'pcode:' + pcode;
                                            $('.pvar-input').each(function() {
                                                finalStr += ';' + $(this).data('var') + ':' + $(this).val();
                                            });
                                            targettextarea.val(finalStr);
                                            $('#dpWrapper').remove();
                                        });
                                    } else {
                                        $('.pattern-message').html('خطا: ' + obj.message);
                                    }
                                }
                            });
                        });
                    });
                });
            });

            // توابع کمکی JS
            function SelectFormAjax(formId) {
                if (!formId) {
                    jQuery("#smspanel_field_group").slideUp();
                    return;
                }
                jQuery("#smspanel_wait").show();
                jQuery("#smspanel_field_group").slideUp();
                
                jQuery.post(ajaxurl, {
                    action: "gf_select_smspanel_form",
                    gf_select_smspanel_form: "<?php echo wp_create_nonce("gf_select_smspanel_form") ?>",
                    form_id: formId
                }, function (data) {
                    form = data.form;
                    var fields = data["fields"];
                    
                    // بازنشانی فیلدها
                    jQuery("#gf_smspanel_message_variable_select").html(fields);
                    jQuery("#gf_smspanel_message_c_variable_select").html(fields);
                    jQuery("#smspanel_customer_field").html(data["customer_field"]);
                    jQuery("#smspanel_gf_code_dyn_div").html(data["gf_code"]);
                    
                    // بازنشانی شرط‌ها
                    resetConditions('adminsms');
                    resetConditions('clientsms');

                    jQuery("#smspanel_field_group").slideDown();
                    jQuery("#smspanel_wait").hide();
                }, "json");
            }

            function resetConditions(prefix) {
                var container = jQuery(".gf_" + prefix + "_conditional_div");
                // حذف همه بجز اولی
                container.not(":first").remove();
                // ریست کردن اولی
                var first = container.first();
                // آپدیت نام‌ها و آی‌دی‌ها به ایندکس 1
                // (پیاده‌سازی ساده‌تر: رفرش صفحه برای فرم جدید بهترین راه است، اما اینجا کلاینت ساید انجام میدهیم)
                RefreshConditionRow("gf_" + prefix + "_1__conditional", "", "is", "", 1);
            }

            function RefreshConditionRow(input, selectedField, selectedOperator, selectedValue, index) {
                var field_id = jQuery("#" + input + "_field_id");
                field_id.html(GetSelectableFields(selectedField, 30));
                
                if (field_id.val()) {
                    jQuery("#" + input + "_value_container").html(GetConditionalFieldValues(input, field_id.val(), selectedOperator, selectedValue, 20, index));
                    jQuery("#" + input + "_operator").val(selectedOperator);
                }
            }

            function GetConditionalFieldValues(input, fieldId, selectedOperator, selectedValue, labelMaxCharacters, index) {
                if (!fieldId) return "";
                var name = input.replace(new RegExp('_\\d+__', 'g'), '_') + "_value[" + index + "]";
                var field = GetFieldById(fieldId);
                if (!field) return "";

                var str = "";
                // منطق ساخت ورودی مقدار (Text یا Select)
                if (field.choices && (selectedOperator == 'is' || selectedOperator == 'isnot')) {
                    str += "<select class='condition_field_value' name='" + name + "'>";
                    for (var i = 0; i < field.choices.length; i++) {
                        var val = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
                        var selected = (val == selectedValue) ? "selected='selected'" : "";
                        str += "<option value='" + val + "' " + selected + ">" + field.choices[i].text + "</option>";
                    }
                    str += "</select>";
                } else {
                    str += "<input type='text' class='condition_field_value' name='" + name + "' value='" + selectedValue + "'>";
                }
                return str;
            }

            function GetSelectableFields(selectedFieldId, labelMaxCharacters) {
                var str = "";
                if (typeof form.fields !== "undefined") {
                    for (var i = 0; i < form.fields.length; i++) {
                        var field = form.fields[i];
                        if (IsConditionalLogicField(field)) {
                            var selected = (field.id == selectedFieldId) ? "selected='selected'" : "";
                            str += "<option value='" + field.id + "' " + selected + ">" + field.label + "</option>";
                        }
                    }
                }
                return str;
            }

            function IsConditionalLogicField(field) {
                var inputType = field.inputType ? field.inputType : field.type;
                var supported = ["checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect"];
                return jQuery.inArray(inputType, supported) >= 0;
            }

            function GetFieldById(id) {
                for (var i = 0; i < form.fields.length; i++) {
                    if (form.fields[i].id == id) return form.fields[i];
                }
                return null;
            }

            function InsertVariable(element_id) {
                var variable_select = jQuery('#' + element_id + '_variable_select');
                var variable = variable_select.val();
                var messageElement = jQuery("#" + element_id);
                
                if (document.selection) {
                    messageElement[0].focus();
                    document.selection.createRange().text = variable;
                } else if (messageElement[0].selectionStart || messageElement[0].selectionStart == '0') {
                    var startPos = messageElement[0].selectionStart;
                    var endPos = messageElement[0].selectionEnd;
                    messageElement.val(messageElement.val().substring(0, startPos) + variable + messageElement.val().substring(endPos, messageElement.val().length));
                } else {
                    messageElement.val(messageElement.val() + variable);
                }
                variable_select[0].selectedIndex = 0;
            }

            function GF_SwitchForm(id) {
                if (id) {
                    window.location.href = "?page=gf_smspanel&view=edit&id=" + id;
                }
            }
        </script>
        <?php
    }

    // --- توابع کمکی رندرینگ ---

    private static function render_conditional_logic($prefix, $config) {
        $enabled = rgar($config['meta'], $prefix . '_conditional_enabled');
        ?>
        <tr id="gf_<?php echo $prefix; ?>_conditional_option">
            <th><?php _e("منطق شرطی", "GF_SMS"); ?></th>
            <td>
                <input type="checkbox" id="gf_<?php echo $prefix; ?>_conditional_enabled" name="gf_<?php echo $prefix; ?>_conditional_enabled" value="1"
                       onclick="if(this.checked){jQuery('#gf_<?php echo $prefix; ?>_conditional_container').fadeIn('fast');} else{ jQuery('#gf_<?php echo $prefix; ?>_conditional_container').fadeOut('fast'); }" 
                       <?php echo $enabled ? "checked='checked'" : "" ?>/>
                <label for="gf_<?php echo $prefix; ?>_conditional_enabled"><?php _e("فعال‌سازی شرط", "GF_SMS"); ?></label>
                
                <div id="gf_<?php echo $prefix; ?>_conditional_container" style="margin-top:10px; <?php echo !$enabled ? "display:none" : "" ?>">
                    <span><?php _e("ارسال پیامک اگر", "GF_SMS") ?></span>
                    <select name="gf_<?php echo $prefix; ?>_conditional_type">
                        <option value="all" <?php selected(rgar($config['meta'], $prefix . '_conditional_type'), 'all'); ?>><?php _e("همه", "GF_SMS") ?></option>
                        <option value="any" <?php selected(rgar($config['meta'], $prefix . '_conditional_type'), 'any'); ?>><?php _e("هریک", "GF_SMS") ?></option>
                    </select>
                    <span><?php _e("از شرایط زیر برقرار باشد:", "GF_SMS") ?></span>

                    <?php
                    $conditions = rgar($config['meta'], $prefix . '_conditional_field_id');
                    if (!is_array($conditions)) $conditions = array('1' => '');
                    
                    // حلقه برای نمایش شرط‌های موجود
                    foreach ($conditions as $i => $field_id) {
                        // در JS پر می‌شود اما ساختار HTML اولیه را می‌سازیم
                        ?>
                        <div class="gf_<?php echo $prefix; ?>_conditional_div" id="gf_<?php echo $prefix; ?>_<?php echo $i; ?>__conditional_div">
                            <select class="gf_<?php echo $prefix; ?>_conditional_field_id" 
                                    id="gf_<?php echo $prefix; ?>_<?php echo $i; ?>__conditional_field_id" 
                                    name="gf_<?php echo $prefix; ?>_conditional_field_id[<?php echo $i; ?>]">
                            </select>

                            <select class="gf_<?php echo $prefix; ?>_conditional_operator" 
                                    id="gf_<?php echo $prefix; ?>_<?php echo $i; ?>__conditional_operator" 
                                    name="gf_<?php echo $prefix; ?>_conditional_operator[<?php echo $i; ?>]">
                                <option value="is"><?php _e("هست", "GF_SMS") ?></option>
                                <option value="isnot"><?php _e("نیست", "GF_SMS") ?></option>
                                <option value=">"><?php _e("بزرگتر از", "GF_SMS") ?></option>
                                <option value="<"><?php _e("کوچکتر از", "GF_SMS") ?></option>
                                <option value="contains"><?php _e("شامل", "GF_SMS") ?></option>
                                <option value="starts_with"><?php _e("شروع شود با", "GF_SMS") ?></option>
                                <option value="ends_with"><?php _e("تمام شود با", "GF_SMS") ?></option>
                            </select>

                            <div id="gf_<?php echo $prefix; ?>_<?php echo $i; ?>__conditional_value_container" style="display:inline;"></div>

                            <a class="add_<?php echo $prefix; ?>_condition gficon_link" href="#"><i class="fa fa-plus-circle"></i></a>
                            <a class="delete_<?php echo $prefix; ?>_condition gficon_link" href="#"><i class="fa fa-minus-circle"></i></a>
                        </div>
                    <?php } ?>
                    
                    <input type="hidden" id="gf_<?php echo $prefix; ?>_conditional_counter" value="<?php echo max(array_keys($conditions)); ?>">
                </div>
            </td>
        </tr>
        <?php
    }

    private static function init_saved_conditions($prefix, $config) {
        $conditions = rgar($config['meta'], $prefix . '_conditional_field_id');
        $values = rgar($config['meta'], $prefix . '_conditional_value');
        $operators = rgar($config['meta'], $prefix . '_conditional_operator');

        if (is_array($conditions)) {
            foreach ($conditions as $i => $field_id) {
                $val = isset($values[$i]) ? $values[$i] : '';
                $op = isset($operators[$i]) ? $operators[$i] : 'is';
                echo "RefreshConditionRow('gf_{$prefix}_{$i}__conditional', '{$field_id}', '{$op}', '{$val}', {$i});\n";
            }
        } else {
            echo "RefreshConditionRow('gf_{$prefix}_1__conditional', '', 'is', '', 1);\n";
        }
    }

    public static function select_forms_ajax()
    {
        check_ajax_referer("gf_select_smspanel_form", "gf_select_smspanel_form");
        $form_id = intval(rgpost("form_id"));
        $form = RGFormsModel::get_form_meta($form_id);
        
        $fields = self::get_form_fields_merge($form);
        $customer_field = self::get_client_information($form, null);
        $gf_code = self::get_country_code($form, null);
        
        $result = array(
            "form" => $form,
            "fields" => $fields,
            "customer_field" => $customer_field,
            "gf_code" => $gf_code
        );
        
        wp_send_json($result);
    }

    // --- توابع کمکی تولید HTML ---

    public static function get_client_information($form, $config)
    {
        $form_fields = self::get_client_form_fields($form);
        $selected_field = rgar($config['meta'], "customer_field_clientnum");
        return self::get_mapped_fields("customer_field_clientnum", $selected_field, $form_fields, true);
    }

    public static function get_country_code($form, $config)
    {
        $form_fields = self::get_client_form_fields($form);
        $selected_field = rgar($config['meta'], "gf_code_dyn");
        return self::get_mapped_fields("gf_code_dyn", $selected_field, $form_fields, false);
    }

    public static function get_mapped_fields($variable_name, $selected_field, $fields, $empty)
    {
        $field_name = "smspanel_" . $variable_name; // نام فیلد در فرم
        $str = "<select name=\"$field_name\" id=\"$field_name\">";
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

    public static function get_client_form_fields($form)
    {
        $fields = array();
        if (is_array($form["fields"])) {
            foreach ($form["fields"] as $field) {
                if (isset($field["inputs"]) && is_array($field["inputs"])) {
                    foreach ($field["inputs"] as $input) {
                        if (!GFCommon::is_pricing_field($field["type"])) {
                            $fields[] = array($input["id"], GFCommon::get_label($field, $input["id"]));
                        }
                    }
                } else if (!rgar($field, 'displayOnly')) {
                    if (!GFCommon::is_pricing_field($field["type"])) {
                        $fields[] = array($field["id"], GFCommon::get_label($field));
                    }
                }
            }
        }
        return $fields;
    }

    public static function get_form_fields_merge($form)
    {
        $str = "<option value=''>" . __("برچسب‌های ادغام (Merge Tags)", "gravityforms") . "</option>";
        $str .= "<optgroup label='" . __("فیلدهای فرم", "gravityforms") . "'>";
        
        foreach ($form["fields"] as $field) {
            if ($field["displayOnly"]) continue;
            $str .= self::get_fields_options($field);
        }
        $str .= "</optgroup>";
        
        $str .= "<optgroup label='" . __("سایر", "gravityforms") . "'>
            <option value='{payment_status}'>وضعیت پرداخت</option>
            <option value='{transaction_id}'>شناسه تراکنش</option>
            <option value='{entry_id}'>شناسه ورودی</option>
            <option value='{date_mdy}'>تاریخ</option>
            <option value='{ip}'>آی‌پی کاربر</option>
        </optgroup>";
        
        return $str;
    }

    public static function get_fields_options($field)
    {
        $str = "";
        if (is_array($field["inputs"])) {
            foreach ($field["inputs"] as $input) {
                $label = esc_html(GFCommon::get_label($field, $input["id"]));
                $str .= "<option value='{" . $label . ":" . $input["id"] . "}'>" . $label . "</option>";
            }
        } else {
            $label = esc_html(GFCommon::get_label($field));
            $str .= "<option value='{" . $label . ":" . $field["id"] . "}'>" . $label . "</option>";
        }
        return $str;
    }
}