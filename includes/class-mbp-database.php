<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MBP_Database {

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول رزروها (آپدیت شده)
        $table_appointments = $wpdb->prefix . 'mbp_appointments';
        $sql1 = "CREATE TABLE $table_appointments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime NOT NULL,
            service_id mediumint(9) NOT NULL DEFAULT 0,
            customer_name tinytext NOT NULL,
            customer_email varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
            sms_sent tinyint(1) NOT NULL DEFAULT 0,
            tracking_code varchar(50) NOT NULL DEFAULT '',
            notes text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY time (time),
            KEY payment_status (payment_status),
            KEY service_id (service_id)
        ) $charset_collate;";

        // جدول خدمات
        $table_services = $wpdb->prefix . 'mbp_services';
        $sql2 = "CREATE TABLE $table_services (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            duration int NOT NULL DEFAULT 30 COMMENT 'مدت زمان به دقیقه',
            price decimal(10,2) NOT NULL DEFAULT '0.00',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // جدول پرداخت‌ها
        $table_payments = $wpdb->prefix . 'mbp_payments';
        $sql3 = "CREATE TABLE $table_payments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            appointment_id mediumint(9) NOT NULL,
            amount decimal(10,2) NOT NULL,
            transaction_id varchar(100),
            payment_gateway varchar(50),
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_data text,
            ref_id varchar(100),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY appointment_id (appointment_id),
            KEY transaction_id (transaction_id),
            KEY status (status),
            FOREIGN KEY (appointment_id) REFERENCES $table_appointments(id) ON DELETE CASCADE
        ) $charset_collate;";

        // جدول تنظیمات SMS
        $table_sms_settings = $wpdb->prefix . 'mbp_sms_settings';
        $sql4 = "CREATE TABLE $table_sms_settings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gateway varchar(50) NOT NULL DEFAULT 'kavenegar',
            api_key varchar(255),
            sender_number varchar(20),
            enable_booking_sms tinyint(1) NOT NULL DEFAULT 1,
            enable_payment_sms tinyint(1) NOT NULL DEFAULT 1,
            enable_reminder_sms tinyint(1) NOT NULL DEFAULT 0,
            reminder_hours int NOT NULL DEFAULT 24,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // جدول لاگ پیامک‌ها
        $table_sms_logs = $wpdb->prefix . 'mbp_sms_logs';
        $sql5 = "CREATE TABLE $table_sms_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            message text NOT NULL,
            type varchar(50) NOT NULL COMMENT 'booking, payment, reminder, etc',
            status tinyint(1) NOT NULL DEFAULT 0,
            response text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY phone (phone),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // اجرای جداول
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );
        dbDelta( $sql5 );
        
        // درج خدمات پیش‌فرض
        self::insert_default_services();
        
        // درج تنظیمات پیش‌فرض SMS
        self::insert_default_sms_settings();
    }
    
    private static function insert_default_services() {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';
        
        $services = array(
            array(
                'name' => 'مشاوره اولیه',
                'description' => 'جلسه مشاوره ۳۰ دقیقه‌ای',
                'duration' => 30,
                'price' => 0.00
            ),
            array(
                'name' => 'جلسه درمانی',
                'description' => 'جلسه درمانی ۴۵ دقیقه‌ای',
                'duration' => 45,
                'price' => 150000
            ),
            array(
                'name' => 'جلسه ویژه',
                'description' => 'جلسه ۶۰ دقیقه‌ای با گزارش کامل',
                'duration' => 60,
                'price' => 250000
            )
        );
        
        foreach ($services as $service) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE name = %s", 
                $service['name']
            ));
            
            if (!$exists) {
                $wpdb->insert($table, $service);
            }
        }
    }
    
    private static function insert_default_sms_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';
        
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        if (!$exists) {
            $wpdb->insert($table, array(
                'gateway' => 'kavenegar',
                'enable_booking_sms' => 1,
                'enable_payment_sms' => 1,
                'enable_reminder_sms' => 1,
                'reminder_hours' => 24
            ));
        }
    }
    
    // متد برای آپدیت جداول موجود
    public static function update_tables() {
        global $wpdb;
        
        $table_appointments = $wpdb->prefix . 'mbp_appointments';
        
        // بررسی وجود ستون‌های جدید
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_appointments");
        $column_names = array();
        foreach ($columns as $column) {
            $column_names[] = $column->Field;
        }
        
        // اضافه کردن ستون‌های جدید اگر وجود ندارند
        if (!in_array('customer_phone', $column_names)) {
            $wpdb->query("ALTER TABLE $table_appointments ADD COLUMN customer_phone varchar(20) NOT NULL DEFAULT ''");
        }
        
        if (!in_array('payment_status', $column_names)) {
            $wpdb->query("ALTER TABLE $table_appointments ADD COLUMN payment_status varchar(20) NOT NULL DEFAULT 'unpaid'");
        }
        
        if (!in_array('sms_sent', $column_names)) {
            $wpdb->query("ALTER TABLE $table_appointments ADD COLUMN sms_sent tinyint(1) NOT NULL DEFAULT 0");
        }
        
        if (!in_array('tracking_code', $column_names)) {
            $wpdb->query("ALTER TABLE $table_appointments ADD COLUMN tracking_code varchar(50) NOT NULL DEFAULT ''");
        }
        
        if (!in_array('notes', $column_names)) {
            $wpdb->query("ALTER TABLE $table_appointments ADD COLUMN notes text");
        }
    }
}