<?php
if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_Feeds
{
    public static function construct()
    {
        if (defined('RG_CURRENT_PAGE') && in_array(RG_CURRENT_PAGE, array('admin-ajax.php'))) {
            add_action('wp_ajax_gf_feed_ajax_active', array(__CLASS__, 'ajax'));
            // دسترسی nopriv برداشته شد چون تغییر تنظیمات فقط برای مدیر لاگین شده است
        }
        
        // افزودن منو به تنظیمات هر فرم
        add_filter('gform_form_settings_menu', array(__CLASS__, 'toolbar'), 10, 2);
        add_action('gform_form_settings_page_sms', array(__CLASS__, 'sms_form_settings_page'));
    }

    /**
     * تغییر وضعیت فعال/غیرفعال فید به صورت ایجکس
     */
    public static function ajax()
    {
        check_ajax_referer('gf_feed_ajax_active', 'nonce');
        
        if (!GFCommon::current_user_can_any('gravityforms_smspanel')) {
            wp_die('Access denied');
        }

        $id = intval(rgpost("feed_id"));
        $is_active = intval(rgpost("is_active"));
        
        $feed = GF_MESSAGEWAY_SQL::get_feed($id);
        if ($feed) {
            GF_MESSAGEWAY_SQL::update_feed($id, $feed["form_id"], $is_active, $feed["meta"]);
        }
        die();
    }

    /**
     * افزودن آیکون پیامک به نوار ابزار تنظیمات فرم
     */
    public static function toolbar($menu_items, $form_id)
    {
        $menu_items[] = array(
            'name' => 'sms',
            'label' => __('پیامک', 'GF_SMS'),
            'icon' => 'fa fa-comments'
        );
        return $menu_items;
    }

    /**
     * نمایش صفحه لیست فیدها در داخل تنظیمات فرم
     */
    public static function sms_form_settings_page()
    {
        GFFormSettings::page_header();
        
        $form_id = rgget('id');
        ?>
        <div class="wrap">
            <h3>
                <span><i class="fa fa-mobile"></i> <?php esc_html_e('تنظیمات پیامک', 'GF_SMS') ?></span>
                <a id="add-new-confirmation" class="add-new-h2" href="<?php echo admin_url("admin.php?page=gf_smspanel&view=edit&fid=" . $form_id) ?>">
                    <?php esc_html_e('افزودن فید جدید', 'GF_SMS') ?>
                </a>
            </h3>
            <?php self::feeds('settings', $form_id); ?>
        </div>
        <?php
        GFFormSettings::page_footer();
    }

    /**
     * تابع اصلی نمایش جدول فیدها (مشترک بین منوی اصلی و تنظیمات فرم)
     */
    public static function feeds($arg, $form_id = null)
    {
        $is_settings_tab = ($arg == 'settings');
        
        // بررسی نسخه گرویتی فرم
        if (class_exists("GFCommon") && !version_compare(GFCommon::$version, GF_MESSAGEWAY::$gf_version, ">=")) {
            echo '<div class="error p-20">' . sprintf(__("برای استفاده از این افزونه باید نسخه گرویتی فرم شما %s یا بالاتر باشد.", "GF_SMS"), GF_MESSAGEWAY::$gf_version) . '</div>';
            return;
        }

        // حذف فید
        if (!rgempty("delfeed")) {
            check_admin_referer("delfeed", "gf_smspanel_delfeed");
            GF_MESSAGEWAY_SQL::delete_feed(absint(rgget('delfeed')));
            echo '<div class="updated fade" style="padding:6px">' . __("فید با موفقیت حذف شد.", "GF_SMS") . '</div>';
        }

        // دریافت لیست فیدها
        $feeds = GF_MESSAGEWAY_SQL::get_feeds();
        ?>
        
        <form method="post">
            <?php wp_nonce_field("delfeed", "gf_smspanel_delfeed") ?>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                <thead>
                <tr>
                    <th scope="col" style="width:50px"><?php _e("شناسه", "GF_SMS") ?></th>
                    <?php if (!$is_settings_tab) { ?>
                        <th scope="col"><?php _e("فرم متصل", "GF_SMS") ?></th>
                    <?php } ?>
                    <th scope="col"><?php _e("گیرنده مدیر", "GF_SMS") ?></th>
                    <th scope="col"><?php _e("گیرنده کاربر", "GF_SMS") ?></th>
                    <th scope="col" style="width:100px"><?php _e("وضعیت", "GF_SMS") ?></th>
                    <th scope="col" style="width:150px"><?php _e("عملیات", "GF_SMS") ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $has_item = false;
                if (is_array($feeds)) {
                    foreach ($feeds as $feed) {
                        // فیلتر کردن بر اساس فرم اگر در صفحه تنظیمات فرم هستیم
                        if ($is_settings_tab && $feed["form_id"] != $form_id) continue;
                        
                        $form = RGFormsModel::get_form_meta($feed["form_id"]);
                        if (empty($form)) continue; // فرم حذف شده است

                        $has_item = true;
                        $meta = $feed["meta"];
                        ?>
                        <tr>
                            <td><?php echo $feed["id"] ?></td>
                            
                            <?php if (!$is_settings_tab) { ?>
                                <td>
                                    <a href="<?php echo admin_url("admin.php?page=gf_edit_forms&id=" . $form['id']) ?>">
                                        <strong><?php echo esc_html($form["title"]) ?></strong>
                                    </a>
                                </td>
                            <?php } ?>
                            
                            <td>
                                <?php 
                                if (!empty($meta['adminsms_conditional_enabled'])) echo '<span class="dashicons dashicons-yes" title="شرطی"></span> ';
                                echo !empty($meta['to']) ? esc_html($meta['to']) : '<span style="color:#ccc">غیرفعال</span>'; 
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($meta['clientsms_conditional_enabled'])) echo '<span class="dashicons dashicons-yes" title="شرطی"></span> ';
                                echo !empty($meta['customer_field_clientnum']) ? __('انتخاب شده از فرم', 'GF_SMS') : '<span style="color:#ccc">غیرفعال</span>'; 
                                ?>
                            </td>
                            <td>
                                <img src="<?php echo GF_SMS_URL ?>/images/active<?php echo $feed["is_active"] ?>.png" 
                                     style="cursor: pointer; max-width: 24px;" 
                                     onclick="GF_SMS_ToggleActive(this, <?php echo $feed['id'] ?>);" 
                                     alt="Toggle"
                                     title="<?php echo $feed["is_active"] ? __('فعال', 'GF_SMS') : __('غیرفعال', 'GF_SMS') ?>"
                                     />
                            </td>
                            <td>
                                <a href="admin.php?page=gf_smspanel&view=edit&id=<?php echo $feed["id"] ?>" class="button button-small">
                                    <?php _e("ویرایش", "GF_SMS") ?>
                                </a>
                                <a href="<?php echo wp_nonce_url("admin.php?page=gf_smspanel&delfeed=" . $feed["id"], "delfeed", "gf_smspanel_delfeed") ?>" 
                                   class="button button-small button-link-delete" 
                                   onclick="return confirm('<?php _e("آیا از حذف این مورد اطمینان دارید؟", "GF_SMS") ?>');">
                                    <?php _e("حذف", "GF_SMS") ?>
                                </a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                
                if (!$has_item) {
                    echo '<tr><td colspan="6" style="padding:20px; text-align:center;">' . __("هیچ فید پیامکی تعریف نشده است.", "GF_SMS") . '</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </form>

        <script>
            function GF_SMS_ToggleActive(img, feed_id) {
                var is_active = img.src.indexOf("active1.png") >= 0;
                
                // تغییر ظاهری سریع
                if (is_active) {
                    img.src = img.src.replace("active1.png", "active0.png");
                } else {
                    img.src = img.src.replace("active0.png", "active1.png");
                }

                jQuery.post(ajaxurl, {
                    action: "gf_feed_ajax_active",
                    nonce: "<?php echo wp_create_nonce("gf_feed_ajax_active") ?>",
                    feed_id: feed_id,
                    is_active: is_active ? 0 : 1
                });
            }
        </script>
        <?php
    }
}