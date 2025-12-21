<?php
/**
 * Plugin Name: blugin-rezerf
 * Plugin URI: #
 * Description: ورژن بتا رزو نوبت دهی
 * Version: 0.6.1
 * Author: kiarash abdollahi
 * Author URI: #
 * License: GPL2
 * Text Domain: my-booking-plugin
 */

if (!defined('ABSPATH')) exit;

// مسیر اصلی افزونه
define('MBP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// لود کلاس‌ها
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-database.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-core.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-license.php';
require_once MBP_PLUGIN_DIR . 'elementor/elementor-init.php';


// فعال‌سازی: ساخت جدول‌ها
function mbp_activate_plugin() {
    MBP_Database::create_tables();
}
register_activation_hook(__FILE__, 'mbp_activate_plugin');

/**
 * صفحه لایسنس در ادمین
 * (این بخش مشکلی ندارد چون در admin_menu اجرا می‌شود)
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'mbp-bookings',
        'License',
        'لایسنس',
        'manage_options',
        'mbp-license',
        'mbp_license_page'
    );
});

function mbp_license_page()
{
    if (!current_user_can('manage_options')) wp_die('دسترسی ندارید');

    if (isset($_POST['mbp_license_key'])) {
        check_admin_referer('mbp_save_license');
        MBP_License::save_key($_POST['mbp_license_key']);
        echo '<div class="notice notice-success"><p>لایسنس ذخیره شد.</p></div>';
    }

    $key   = esc_attr(MBP_License::get_key());
    $valid = MBP_License::is_valid_cached();

    echo '<div class="wrap" style="direction:rtl;max-width:700px">';
    echo '<h1>فعال‌سازی افزونه</h1>';
    echo '<p>وضعیت: ' . ($valid ? '<strong style="color:green">فعال ✅</strong>' : '<strong style="color:red">نامعتبر ❌</strong>') . '</p>';

    echo '<form method="post">';
    wp_nonce_field('mbp_save_license');
    echo '<input type="text" name="mbp_license_key" value="'.$key.'" style="width:100%;max-width:420px;direction:ltr;padding:8px" placeholder="LICENSE-KEY">';
    echo '<p><button class="button button-primary" type="submit">ذخیره</button></p>';
    echo '</form>';
    echo '</div>';
}

/**
 * ✅ اجرای افزونه را بنداز روی init (یا plugins_loaded)
 * تا wp_get_current_user و بقیه pluggable ها آماده باشند
 */
add_action('init', function () {

    // اگر لایسنس معتبر نیست، افزونه را "قفل" کن (اجرا نکن)
    if (!MBP_License::is_valid_cached()) {

        // فقط برای ادمین پیام بگذار
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>
                افزونه رزرو فعال نیست. لطفاً لایسنس را وارد کنید:
                <a href="'.esc_url(admin_url('admin.php?page=mbp-license')).'">صفحه لایسنس</a>
                </p></div>';
            });
        }

        // ❌ هیچ چیزی از MBP_Core اجرا نشود
        return;
    }

    // ✅ اگر معتبر بود، افزونه اجرا شود
    $plugin = new MBP_Core();
    $plugin->run();
});
