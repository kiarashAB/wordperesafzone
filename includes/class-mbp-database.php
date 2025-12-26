<?php
if (!defined('ABSPATH'))
    exit;

class MBP_Database
{
    /**
     * ایجاد تمامی جداول مورد نیاز افزونه
     */
    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ۱. جدول رزروها (Appointments)
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
            KEY time (time)
        ) $charset_collate;";
        dbDelta($sql1);

        // ۲. جدول دفترچه تلفن (Phonebook) - دارای قفل شماره تکراری
        $table_phonebook = $wpdb->prefix . 'mbp_phonebook';
        $sql2 = "CREATE TABLE $table_phonebook (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY phone (phone)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql2);

        // ۳. جدول خدمات (Services)
        $table_services = $wpdb->prefix . 'mbp_services';
        $sql3 = "CREATE TABLE $table_services (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            duration int NOT NULL DEFAULT 30,
            price decimal(10,2) NOT NULL DEFAULT '0.00',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql3);

        // ۴. جدول پرداخت‌ها (Payments)
        $table_payments = $wpdb->prefix . 'mbp_payments';
        $sql4 = "CREATE TABLE $table_payments (
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
            FOREIGN KEY (appointment_id) REFERENCES $table_appointments(id) ON DELETE CASCADE
        ) $charset_collate;";
        dbDelta($sql4);

        // ۵. جدول تنظیمات SMS
        $table_sms_settings = $wpdb->prefix . 'mbp_sms_settings';
        $sql5 = "CREATE TABLE $table_sms_settings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gateway varchar(50) NOT NULL DEFAULT 'kavenegar',
            api_key varchar(255),
            sender_number varchar(20),
            enable_booking_sms tinyint(1) NOT NULL DEFAULT 1,
            enable_payment_sms tinyint(1) NOT NULL DEFAULT 1,
            enable_reminder_sms tinyint(1) NOT NULL DEFAULT 0,
            reminder_hours int NOT NULL DEFAULT 24,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql5);

        // ۶. جدول لاگ پیامک‌ها
        $table_sms_logs = $wpdb->prefix . 'mbp_sms_logs';
        $sql6 = "CREATE TABLE $table_sms_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            message text NOT NULL,
            type varchar(50) NOT NULL,
            status tinyint(1) NOT NULL DEFAULT 0,
            response text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql6);

        // درج مقادیر پیش‌فرض
        self::insert_default_services();
        self::insert_default_sms_settings();
    }

    /**
     * درج خدمات پیش‌فرض
     */
    private static function insert_default_services()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';
        $services = array(
            array('name' => 'مشاوره اولیه', 'duration' => 30, 'price' => 0.00),
            array('name' => 'جلسه درمانی', 'duration' => 45, 'price' => 150000)
        );

        foreach ($services as $service) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE name = %s", $service['name']));
            if (!$exists) {
                $wpdb->insert($table, $service);
            }
        }
    }

    /**
     * درج تنظیمات پیش‌فرض پیامک
     */
    private static function insert_default_sms_settings()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if (!$exists) {
            $wpdb->insert($table, array(
                'gateway' => 'kavenegar',
                'enable_booking_sms' => 1,
                'enable_payment_sms' => 1
            ));
        }
    }

    /**
     * آپدیت ستون‌های احتمالی در نسخه‌های جدید
     */
    public static function update_tables()
    {
        global $wpdb;
        $table_appointments = $wpdb->prefix . 'mbp_appointments';
        $column = $wpdb->get_results("SHOW COLUMNS FROM $table_appointments LIKE 'customer_phone'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE $table_appointments ADD COLUMN customer_phone varchar(20) NOT NULL DEFAULT ''");
        }
    }
}