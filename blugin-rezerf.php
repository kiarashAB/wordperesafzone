<?php
/**
 * Plugin Name: blugin-rezerf
 * Plugin URI: #
 * Description: ورژن بتا رزو نوبت دهی
 * Version: 0.6.2
 * Author: kiarash abdollahi
 * Author URI: #
 * License: GPL2
 * Text Domain: my-booking-plugin
 */

if (!defined('ABSPATH')) exit;

define('MBP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// لود کلاس‌ها
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-database.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-license.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-core.php';
require_once MBP_PLUGIN_DIR . 'elementor/elementor-init.php';

// فعال‌سازی: ساخت جدول‌ها
function mbp_activate_plugin() {
    if (class_exists('MBP_Database')) {
        MBP_Database::create_tables();
    }
}
register_activation_hook(__FILE__, 'mbp_activate_plugin');

// اجرای افزونه (مهم: بعد از آماده شدن وردپرس)
function mbp_run_plugin() {
    $plugin = new MBP_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'mbp_run_plugin');
