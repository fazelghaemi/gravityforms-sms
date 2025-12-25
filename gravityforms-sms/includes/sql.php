<?php
if (!defined('ABSPATH')) {
    exit;
}

class GF_MESSAGEWAY_SQL
{
    /**
     * بررسی و آپدیت جداول دیتابیس
     */
    public static function setup_update()
    {
        $installed_ver = get_option("gf_sms_version");
        if ($installed_ver != GF_MESSAGEWAY::$version || !get_option('gf_sms_installed')) {
            self::gf_sms_create_tables();
        }
    }

    public static function main_table()
    {
        global $wpdb;
        return $wpdb->prefix . "gf_sms_feed";
    }

    public static function sent_table()
    {
        global $wpdb;
        return $wpdb->prefix . "gf_sms_sent";
    }

    public static function verify_table()
    {
        global $wpdb;
        return $wpdb->prefix . "gf_sms_verification";
    }

    /**
     * ایجاد جداول مورد نیاز
     */
    public static function gf_sms_create_tables()
    {
        global $wpdb;
        update_option('gf_sms_installed', '1');
        update_option('gf_sms_version', GF_MESSAGEWAY::$version);

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        }

        $charset_collate = $wpdb->get_charset_collate();

        // 1. جدول تنظیمات فیدها
        $main_table_name = self::main_table();
        $sql_main = "CREATE TABLE $main_table_name (
            id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
            form_id mediumint(8) unsigned NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            meta longtext,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";
        dbDelta($sql_main);

        // 2. جدول گزارش پیامک‌های ارسالی
        $sent_table_name = self::sent_table();
        $sql_sent = "CREATE TABLE $sent_table_name (
            id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
            form_id mediumint(8) unsigned NOT NULL,
            entry_id varchar(50) DEFAULT NULL,
            sender varchar(50) DEFAULT NULL,
            reciever varchar(50) DEFAULT NULL,
            message text,
            date datetime DEFAULT '0000-00-00 00:00:00',
            verify_code varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY form_entry (form_id, entry_id)
        ) $charset_collate;";
        dbDelta($sql_sent);

        // 3. جدول اعتبارسنجی (Verification)
        $verify_table_name = self::verify_table();
        $sql_verify = "CREATE TABLE $verify_table_name (
            id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
            form_id mediumint(8) unsigned NOT NULL,
            entry_id mediumint(8) unsigned DEFAULT NULL,
            mobile varchar(20) NOT NULL,
            code varchar(10) NOT NULL,
            try_num tinyint(2) NOT NULL DEFAULT 0,
            sent_num tinyint(2) NOT NULL DEFAULT 0,
            status tinyint(1) NOT NULL DEFAULT 0,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mobile (mobile),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_verify);
    }

    public static function save_feed($meta, $form_id)
    {
        global $wpdb;
        $table_name = self::main_table();
        $meta = maybe_serialize($meta);
        
        $wpdb->insert(
            $table_name, 
            array('form_id' => $form_id, 'is_active' => 1, 'meta' => $meta), 
            array('%d', '%d', '%s')
        );
        return $wpdb->insert_id;
    }

    public static function update_feed($id, $form_id, $is_active, $meta)
    {
        global $wpdb;
        $table_name = self::main_table();
        $meta = maybe_serialize($meta);
        
        $wpdb->update(
            $table_name, 
            array('form_id' => $form_id, 'is_active' => $is_active, 'meta' => $meta), 
            array('id' => $id), 
            array('%d', '%d', '%s'), 
            array('%d')
        );
    }

    public static function delete_feed($id)
    {
        global $wpdb;
        $table_name = self::main_table();
        $wpdb->delete($table_name, array('id' => $id), array('%d'));
    }

    public static function get_feeds()
    {
        global $wpdb;
        $table_name = self::main_table();
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC", ARRAY_A);
    }

    public static function get_feed($id)
    {
        global $wpdb;
        $table_name = self::main_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id=%d", $id), ARRAY_A);
        if ($row) {
            $row['meta'] = maybe_unserialize($row['meta']);
        }
        return $row;
    }

    public static function get_feed_via_formid($form_id, $is_active = false)
    {
        global $wpdb;
        $table_name = self::main_table();
        $sql = "SELECT * FROM $table_name WHERE form_id=%d";
        if ($is_active) {
            $sql .= " AND is_active=1";
        }
        $results = $wpdb->get_results($wpdb->prepare($sql, $form_id), ARRAY_A);
        
        foreach ($results as &$row) {
            $row['meta'] = maybe_unserialize($row['meta']);
        }
        return $results;
    }

    // --- Sent Methods ---

    public static function save_sms_sent($form_id, $entry_id, $from, $to, $msg, $verify_code = '')
    {
        global $wpdb;
        $sent_table_name = self::sent_table();
        
        $wpdb->insert(
            $sent_table_name,
            array(
                'form_id' => $form_id,
                'entry_id' => $entry_id,
                'sender' => $from,
                'reciever' => $to,
                'message' => $msg,
                'date' => current_time('mysql'),
                'verify_code' => $verify_code
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    // --- Verification Methods ---

    public static function insert_verify($form_id, $entry_id, $mobile, $code, $status, $try_num, $sent_num)
    {
        global $wpdb;
        $verify_table = self::verify_table();
        
        $wpdb->insert(
            $verify_table,
            array(
                'form_id' => $form_id,
                'entry_id' => $entry_id ? $entry_id : 0,
                'mobile' => $mobile,
                'code' => $code,
                'try_num' => $try_num,
                'sent_num' => $sent_num,
                'status' => $status,
                'date_created' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s')
        );
    }

    public static function get_verify($mobile, $form_id)
    {
        global $wpdb;
        $verify_table = self::verify_table();
        // دریافت آخرین رکورد مربوط به این شماره و فرم
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $verify_table WHERE mobile=%s AND form_id=%d ORDER BY id DESC LIMIT 1", $mobile, $form_id), ARRAY_A);
    }
    
    public static function update_verify_status($id, $status)
    {
        global $wpdb;
        $verify_table = self::verify_table();
        $wpdb->update($verify_table, array('status' => $status), array('id' => $id), array('%d'), array('%d'));
    }
    
    public static function update_verify_try($id, $try_num)
    {
        global $wpdb;
        $verify_table = self::verify_table();
        $wpdb->update($verify_table, array('try_num' => $try_num), array('id' => $id), array('%d'), array('%d'));
    }
    
    public static function drop_table()
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . self::main_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::sent_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::verify_table());
    }
}