<?php
/**
 * Plugin Name: blugin-rezerf
 * Plugin URI: #
 * Description: ورژن بتا رزو نوبت دهی
 * Version: 0.7.0
 * Author: kiarash abdollahi
 * Author URI: #
 * License: GPL2
 * Text Domain: my-booking-plugin
 */

if (!defined('ABSPATH')) exit;

define('MBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MBP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MBP_VERSION', '0.7.0');

// لود کلاس‌ها
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-database.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-license.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-core.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-sms-manager.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-payment-gateway.php';
require_once MBP_PLUGIN_DIR . 'elementor/elementor-init.php';

// فعال‌سازی: ساخت جدول‌ها
function mbp_activate_plugin() {
    if (class_exists('MBP_Database')) {
        MBP_Database::create_tables();
    }
    
    // ایجاد صفحات لازم
    mbp_create_pages();
}
register_activation_hook(__FILE__, 'mbp_activate_plugin');

// ایجاد صفحات لازم
function mbp_create_pages() {
    $pages = array(
        'payment-verify' => array(
            'title' => 'تأیید پرداخت',
            'content' => '[mbp_payment_verify]',
            'template' => 'full-width'
        ),
        'my-appointments' => array(
            'title' => 'نوبت‌های من',
            'content' => '[mbp_my_appointments]',
            'template' => 'page'
        )
    );
    
    foreach ($pages as $slug => $page) {
        $existing = get_page_by_path($slug);
        
        if (!$existing) {
            wp_insert_post(array(
                'post_title' => $page['title'],
                'post_name' => $slug,
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'page_template' => $page['template'] ?? ''
            ));
        }
    }
}

// اجرای افزونه (مهم: بعد از آماده شدن وردپرس)
function mbp_run_plugin() {
    $plugin = new MBP_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'mbp_run_plugin');

// آپدیت جداول هنگام آپدیت پلاگین
function mbp_update_tables() {
    if (class_exists('MBP_Database')) {
        MBP_Database::update_tables();
    }
}
add_action('upgrader_process_complete', 'mbp_update_tables');