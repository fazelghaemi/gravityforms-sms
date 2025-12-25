<?php
if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_Entries_Sidebar
{
    public static function construct()
    {
        if (is_admin()) {
            // افزودن باکس به صفحه جزئیات ورودی
            add_filter('gform_entry_detail_meta_boxes', array(__CLASS__, 'add_meta_boxes'), 10, 3);
            
            // هندل کردن ارسال دستی پیامک از سایدبار
            add_action('wp_ajax_gf_sms_resend', array(__CLASS__, 'ajax_resend_sms'));
        }
    }

    public static function add_meta_boxes($meta_boxes, $entry, $form)
    {
        $meta_boxes['gf_sms_history'] = array(
            'title'    => __("تاریخچه پیامک‌ها (MsgWay)", "GF_SMS"),
            'callback' => array(__CLASS__, 'render_meta_box'),
            'context'  => 'side',
        );
        return $meta_boxes;
    }

    public static function render_meta_box($args)
    {
        $entry = $args['entry'];
        $form  = $args['form'];
        
        // دریافت تاریخچه پیامک‌های مرتبط با این ورودی از دیتابیس
        global $wpdb;
        $table = GF_MESSAGEWAY_SQL::sent_table();
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE form_id=%d AND entry_id=%d ORDER BY date DESC", $form['id'], $entry['id']));
        
        echo '<div class="gf_sms_sidebar_container" style="max-height: 300px; overflow-y: auto;">';
        
        if ($logs) {
            echo '<ul style="margin:0; padding:0; list-style:none;">';
            foreach ($logs as $log) {
                $status_icon = '✅'; // فرض بر موفقیت چون فقط موفق‌ها لاگ می‌شوند معمولا
                $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->date));
                
                echo '<li style="border-bottom:1px solid #eee; padding:8px 0; font-size:12px;">';
                echo '<strong>' . $status_icon . ' به:</strong> ' . esc_html($log->reciever) . '<br/>';
                echo '<span style="color:#777;">' . $date . '</span><br/>';
                echo '<p style="margin:4px 0; background:#f5f5f5; padding:4px; border-radius:3px;">' . esc_html(mb_substr($log->message, 0, 100)) . (mb_strlen($log->message) > 100 ? '...' : '') . '</p>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __("هیچ پیامکی برای این ورودی ثبت نشده است.", "GF_SMS") . '</p>';
        }

        echo '</div>';
        
        // دکمه ارسال پیامک جدید (ساده)
        ?>
        <div style="margin-top:15px; border-top:1px solid #ddd; padding-top:10px;">
            <a href="javascript:void(0);" onclick="jQuery('#gf_sms_manual_send').slideToggle();" class="button button-secondary" style="width:100%; text-align:center;">
                <?php _e("ارسال پیامک جدید", "GF_SMS"); ?>
            </a>
            
            <div id="gf_sms_manual_send" style="display:none; margin-top:10px;">
                <input type="text" id="gf_sms_manual_to" placeholder="شماره موبایل" value="<?php echo isset($logs[0]) ? esc_attr($logs[0]->reciever) : ''; ?>" style="width:100%; margin-bottom:5px;">
                <textarea id="gf_sms_manual_msg" placeholder="متن پیام..." style="width:100%; height:60px;"></textarea>
                <button type="button" class="button button-primary" style="width:100%; margin-top:5px;" onclick="GF_SMS_ManualSend(<?php echo $entry['id']; ?>, <?php echo $form['id']; ?>);">
                    <?php _e("ارسال", "GF_SMS"); ?>
                </button>
                <div id="gf_sms_send_result" style="margin-top:5px;"></div>
            </div>
        </div>

        <script>
        function GF_SMS_ManualSend(entryId, formId) {
            var to = jQuery('#gf_sms_manual_to').val();
            var msg = jQuery('#gf_sms_manual_msg').val();
            var res = jQuery('#gf_sms_send_result');
            
            if(!to || !msg) { alert('شماره و متن الزامی است.'); return; }
            
            res.html('در حال ارسال...');
            
            jQuery.post(ajaxurl, {
                action: 'gf_sms_resend',
                to: to,
                msg: msg,
                entry_id: entryId,
                form_id: formId,
                nonce: '<?php echo wp_create_nonce('gf_sms_resend'); ?>'
            }, function(response) {
                if(response.success) {
                    res.html('<span style="color:green">ارسال شد! صفحه را رفرش کنید.</span>');
                } else {
                    res.html('<span style="color:red">خطا: ' + response.data + '</span>');
                }
            });
        }
        </script>
        <?php
    }

    public static function ajax_resend_sms() {
        check_ajax_referer('gf_sms_resend', 'nonce');
        
        if (!GFCommon::current_user_can_any('gravityforms_edit_entries')) wp_send_json_error('عدم دسترسی');
        
        $to = sanitize_text_field($_POST['to']);
        $msg = sanitize_textarea_field($_POST['msg']); // یا wp_kses
        $form_id = intval($_POST['form_id']);
        $entry_id = intval($_POST['entry_id']);
        
        $res = GF_MESSAGEWAY_Form_Send::Send($to, $msg, '', $form_id, $entry_id);
        
        if ($res === 'OK' || strpos($res, 'OK') !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error($res);
        }
    }
}