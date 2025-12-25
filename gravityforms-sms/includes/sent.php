<?php
/**
 * نمایش لیست پیامک‌های ارسال شده
 * @package    Gravity Forms SMS - MsgWay
 * @author     Ready Studio <info@readystudio.ir>
 * @license    GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

// بارگذاری کلاس پایه WP_List_Table اگر موجود نباشد
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class GF_SMS_Sent_List_Table extends WP_List_Table
{
    /**
     * سازنده کلاس
     */
    function __construct()
    {
        global $status, $page;
        parent::__construct(array(
            'singular' => __('پیامک', 'GF_SMS'),
            'plural'   => __('پیامک‌ها', 'GF_SMS'),
            'ajax'     => false
        ));
    }

    /**
     * پیام پیش‌فرض در صورت نبود داده
     */
    function no_items()
    {
        _e('هیچ پیامکی یافت نشد.', 'GF_SMS');
    }

    /**
     * رندر کردن ستون‌های پیش‌فرض
     */
    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'form_id':
                $form = RGFormsModel::get_form_meta($item[$column_name]);
                return !empty($form) ? '<a href="' . admin_url('admin.php?page=gf_edit_forms&id=' . $item[$column_name]) . '">' . esc_html($form['title']) . '</a>' : __('فرم حذف شده', 'GF_SMS');
            
            case 'entry_id':
                // لینک به جزئیات ورودی اگر فرم و ورودی وجود داشته باشد
                if (!empty($item['form_id']) && !empty($item['entry_id'])) {
                    return '<a href="' . admin_url('admin.php?page=gf_entries&view=entry&id=' . $item['form_id'] . '&lid=' . $item['entry_id']) . '">' . $item[$column_name] . '</a>';
                }
                return $item[$column_name];

            case 'sender':
                return '<span style="font-family:monospace; direction:ltr; unicode-bidi:embed;">' . esc_html($item[$column_name]) . '</span>';
            
            case 'reciever':
                return '<span style="font-family:monospace; direction:ltr; unicode-bidi:embed;">' . esc_html($item[$column_name]) . '</span>';
            
            case 'message':
                // نمایش خلاصه پیام و تولتیپ کامل
                $msg = esc_html($item[$column_name]);
                $short_msg = mb_substr($msg, 0, 80) . (mb_strlen($msg) > 80 ? '...' : '');
                return '<span title="' . esc_attr($msg) . '">' . $short_msg . '</span>';
            
            case 'date':
                return '<span dir="ltr">' . date_i18n('Y/m/d H:i', strtotime($item[$column_name])) . '</span>';
            
            default:
                return print_r($item, true); // برای دیباگ
        }
    }

    /**
     * تعریف ستون‌های جدول
     */
    function get_columns()
    {
        return array(
            'cb'       => '<input type="checkbox" />',
            'form_id'  => __('فرم مربوطه', 'GF_SMS'),
            'reciever' => __('گیرنده', 'GF_SMS'), // "reciever" در دیتابیس قدیمی اینگونه نامگذاری شده
            'sender'   => __('فرستنده', 'GF_SMS'),
            'message'  => __('متن پیام', 'GF_SMS'),
            'date'     => __('تاریخ ارسال', 'GF_SMS'),
        );
    }

    /**
     * ستون‌های قابل سورت
     */
    function get_sortable_columns()
    {
        return array(
            'form_id'  => array('form_id', false),
            'date'     => array('date', true), // true یعنی پیش‌فرض نزولی
            'reciever' => array('reciever', false),
        );
    }

    /**
     * ستون چک‌باکس برای عملیات گروهی
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
        );
    }

    /**
     * تعریف عملیات گروهی (Bulk Actions)
     */
    function get_bulk_actions()
    {
        return array(
            'delete' => __('حذف', 'GF_SMS')
        );
    }

    /**
     * اجرای عملیات گروهی
     */
    function process_bulk_action()
    {
        if ('delete' === $this->current_action()) {
            // بررسی نانس امنیتی
            // (چون در صفحه استاندارد وردپرس هستیم، خود WP_List_Table نانس را هندل می‌کند اما چک کردن ورودی‌ها خوب است)
            
            if (isset($_REQUEST['bulk-delete']) && is_array($_REQUEST['bulk-delete'])) {
                $ids = array_map('intval', $_REQUEST['bulk-delete']);
                global $wpdb;
                $table_name = GF_MESSAGEWAY_SQL::sent_table();
                
                // حذف ایمن
                if (!empty($ids)) {
                    $ids_sql = implode(',', $ids);
                    $wpdb->query("DELETE FROM $table_name WHERE id IN($ids_sql)");
                    
                    echo '<div class="updated notice is-dismissible"><p>' . sprintf(__('%d پیامک حذف شد.', 'GF_SMS'), count($ids)) . '</p></div>';
                }
            }
        }
    }

    /**
     * آماده‌سازی آیتم‌ها برای نمایش (کوئری اصلی)
     */
    function prepare_items()
    {
        global $wpdb;
        $table_name = GF_MESSAGEWAY_SQL::sent_table();
        
        // اجرای عملیات بالک
        $this->process_bulk_action();

        // تنظیمات صفحه‌بندی
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // ساخت کوئری
        $query = "SELECT * FROM $table_name";
        $where = "WHERE 1=1";

        // جستجو
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            // جستجو در متن، گیرنده یا فرستنده
            $where .= $wpdb->prepare(" AND (message LIKE %s OR reciever LIKE %s OR sender LIKE %s)", $search, $search, $search);
        }

        // مرتب‌سازی
        $orderby = !empty($_REQUEST['orderby']) ? esc_sql($_REQUEST['orderby']) : 'date';
        $order = !empty($_REQUEST['order']) ? esc_sql($_REQUEST['order']) : 'DESC';

        // شمارش کل آیتم‌ها (برای صفحه‌بندی)
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");

        // دریافت داده‌ها
        $this->items = $wpdb->get_results("$query $where ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", ARRAY_A);

        // تنظیم آرگومان‌های صفحه‌بندی
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
    }
}

/**
 * کلاس پوششی برای نمایش صفحه
 */
class GF_MESSAGEWAY_Pro_Sent
{
    public static function table()
    {
        // ایجاد نمونه از جدول
        $table = new GF_SMS_Sent_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap gf_browser_gecko ready-studio-wrap">
            
            <h1 class="wp-heading-inline">
                <?php _e("گزارش پیامک‌های ارسالی", "GF_SMS"); ?>
            </h1>
            
            <!-- برندینگ کوچک بالا -->
            <span style="font-size: 12px; color: #666; margin-right: 10px;">
                (MsgWay Logs by <a href="https://readystudio.ir" target="_blank" style="text-decoration:none;">Ready Studio</a>)
            </span>
            
            <hr class="wp-header-end">

            <!-- فرم جستجو و جدول -->
            <form id="sms-sent-filter" method="get">
                <!-- پارامترهای مخفی برای حفظ صفحه جاری -->
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <input type="hidden" name="view" value="sent" />
                
                <?php $table->search_box(__('جستجو در گزارشات', 'GF_SMS'), 'search_id'); ?>
                <?php $table->display(); ?>
            </form>
            
            <!-- فوتر برندینگ -->
            <div style="margin-top: 30px; text-align: center; border-top: 1px solid #ddd; padding-top: 15px; color: #888;">
                <p>
                    <?php _e("طراحی و توسعه افزونه توسط", "GF_SMS"); ?> 
                    <a href="https://readystudio.ir" target="_blank" style="text-decoration:none; color: #0073aa; font-weight: bold;">Ready Studio</a>
                </p>
            </div>
        </div>
        
        <style>
            /* استایل‌های جزئی برای زیباتر شدن جدول */
            .column-form_id { width: 15%; }
            .column-sender { width: 10%; }
            .column-reciever { width: 10%; }
            .column-date { width: 15%; direction: ltr; }
            .column-message { width: 45%; }
            @media screen and (max-width: 782px) {
                .column-form_id, .column-date { width: auto; }
            }
        </style>
        <?php
    }
}