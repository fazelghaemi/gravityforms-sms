<?php 
if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY
{
    public static $version = '2.2.0';
    public static $gf_version = '2.0.0';
    public static $stored_credit;
    public static $get_option;

    public static function construct()
    {
        // بررسی پیش‌نیازها
        if (!class_exists('GFCommon')) return;
        
        // دسترسی‌ها
        if (function_exists('members_get_capabilities')) {
            add_filter('members_get_capabilities', array(__CLASS__, 'members_get_capabilities'));
        }

        // لود فایل‌های مورد نیاز
        self::load_dependencies();

        // راه‌اندازی دیتابیس (در صورت آپدیت)
        GF_MESSAGEWAY_SQL::setup_update();

        // هوک‌های ادمین
        if (is_admin()) {
            add_action('admin_bar_menu', array(__CLASS__, 'admin_bar_menu'), 2000);
            add_filter('gform_addon_navigation', array(__CLASS__, 'submenu'));
            
            // لود کلاس‌های ادمین
            if (!class_exists('GF_MESSAGEWAY_Settings')) {
                require_once(GF_SMS_DIR . 'includes/settings.php');
            }
            RGForms::add_settings_page(array(
                'name' => 'gf_sms_pro', 
                'tab_label' => __('تنظیمات پیامک', 'GF_SMS'), 
                'title' => __('پیامک گرویتی فرم', 'GF_SMS'), 
                'handler' => array('GF_MESSAGEWAY_Settings', 'settings')
            ));

            if (class_exists('GF_MESSAGEWAY_Configurations')) {
                GF_MESSAGEWAY_Configurations::construct();
            }
            if (class_exists('GF_MESSAGEWAY_Feeds')) {
                GF_MESSAGEWAY_Feeds::construct();
            }
            if (class_exists('GF_MESSAGEWAY_Entries_Sidebar')) {
                GF_MESSAGEWAY_Entries_Sidebar::construct();
            }
        }
    }

    private static function load_dependencies()
    {
        $files = [
            'includes/gateways.php',
            'includes/sql.php',
            'includes/verification.php',
            'includes/wp-sms-intergrate.php',
            'includes/send.php',
            'includes/configurations.php',
            'includes/feeds.php',
            'includes/sidebar.php',
            'includes/sent.php'
        ];

        foreach ($files as $file) {
            if (file_exists(GF_SMS_DIR . $file)) {
                require_once(GF_SMS_DIR . $file);
            }
        }

        // فعال‌سازی کلاس‌های استاتیک
        if (class_exists('GF_MESSAGEWAY_Verification')) GF_MESSAGEWAY_Verification::construct();
        if (class_exists('GF_MESSAGEWAY_WP_SMS')) GF_MESSAGEWAY_WP_SMS::construct();
        if (class_exists('GF_MESSAGEWAY_Form_Send')) GF_MESSAGEWAY_Form_Send::construct();
    }

    public static function active()
    {
        // افزودن دسترسی‌ها به مدیر
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('gravityforms_smspanel');
            $role->add_cap('gravityforms_smspanel_uninstall');
        }
    }

    public static function deactive()
    {
        // عملیات غیرفعال‌سازی (اختیاری)
    }

    public static function members_get_capabilities($caps)
    {
        return array_merge($caps, array("gravityforms_smspanel", "gravityforms_smspanel_uninstall"));
    }

    public static function check_access($required_permission)
    {
        return GFCommon::current_user_can_any($required_permission);
    }

    public static function submenu($submenus)
    {
        $permission = "gravityforms_smspanel";
        $submenus[] = array(
            "name" => "gf_smspanel", 
            "label" => __("اطلاع‌رسانی پیامکی", "GF_SMS"), 
            "callback" => array(__CLASS__, "pages"), 
            "permission" => $permission
        );
        return $submenus;
    }

    public static function pages()
    {
        $view = rgget("view");
        if ($view == "edit") {
            GF_MESSAGEWAY_Configurations::configuration();
        } else if ($view == "send") {
            // GF_MESSAGEWAY_Bulk::send_many_numbers(); // فعلاً غیرفعال
            echo '<div class="wrap"><h2>ارسال دستی در دست تعمیر است</h2></div>';
        } else if ($view == "sent") {
            GF_MESSAGEWAY_Pro_Sent::table();
        } else {
            GF_MESSAGEWAY_Feeds::feeds('');
        }
    }

    public static function get_option()
    {
        if (!empty(self::$get_option)) {
            return self::$get_option;
        }
        $options = get_option("gf_sms_settings");
        if (!empty($options) && is_array($options)) {
            self::$get_option = array_map('sanitize_text_field', $options);
        } else {
            self::$get_option = $options;
        }
        return self::$get_option;
    }

    public static function credit($update = false)
    {
        if ($update) {
            self::$get_option = null;
        } else if (!empty(self::$stored_credit)) {
            return self::$stored_credit;
        }
        $settings = self::get_option();
        self::$stored_credit = GF_MESSAGEWAY_WebServices::action($settings, "credit", '', '', '');
        return self::$stored_credit;
    }

    public static function show_credit($show, $label)
    {
        if ($show != "Show") return;

        $credit = self::credit();
        if (empty($credit)) return;

        // اصلاح شده: چون درگاه لینک HTML برمی‌گرداند، دیگر سعی نمی‌کنیم آن را عدد فرض کنیم و رنگ‌بندی کنیم
        // مگر اینکه واقعا عدد باشد
        $display = $credit;
        $color = '#008000'; // سبز پیش‌فرض

        // اگر خروجی صرفاً عدد بود (برخی درگاه‌های دیگر)
        if (is_numeric(strip_tags($credit))) {
            $num = (int)strip_tags($credit);
            if ($num < 1000) $color = '#FF1454'; // قرمز برای اعتبار کم
            elseif ($num < 5000) $color = '#FFC600'; // زرد
            
            $display = $num;
        }

        $pos = is_rtl() ? 'left' : 'right';
        if ($label) {
            echo '<label style="font-size:14px;">' . __('اعتبار: ', 'GF_SMS') . '<span style="color:' . $color . ';">' . $display . '</span></label>';
        } else {
            echo '<span style="position: absolute; ' . $pos . ': 10px; font-weight:bold;">' . $display . '</span>';
        }
    }
    
    // منوی ادمین بار
    public static function admin_bar_menu()
    {
        if (!is_admin_bar_showing()) return;

        $settings = self::get_option();
        if (isset($settings["menu"]) && $settings["menu"] != 'Show') return;

        global $wp_admin_bar;
        
        $title = __('پیامک گرویتی', 'GF_SMS');
        if (isset($settings["cr"]) && $settings["cr"] == 'Show') {
             // اینجا کردیت را فراخوانی نمی‌کنیم تا سرعت لود ادمین بار کم نشود، مگر اینکه کش شده باشد
             // فعلا فقط تایتل
        }

        $menu_id = 'GF_SMS';
        $wp_admin_bar->add_menu(array('id' => $menu_id, 'title' => $title, 'href' => admin_url('admin.php?page=gf_settings&subview=gf_sms_pro')));
        
        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => __('تنظیمات', 'GF_SMS'), 'id' => 'gf-sms-settings', 'href' => admin_url('admin.php?page=gf_settings&subview=gf_sms_pro')));
        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => __('فیدها', 'GF_SMS'), 'id' => 'gf-sms-feeds', 'href' => admin_url('admin.php?page=gf_smspanel')));
        $wp_admin_bar->add_menu(array('parent' => $menu_id, 'title' => __('پیام‌های ارسالی', 'GF_SMS'), 'id' => 'gf-sms-sent', 'href' => admin_url('admin.php?page=gf_smspanel&view=sent')));
    }
}
?>