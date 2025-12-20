<?php
/**
 * Plugin Name: blugin-rezerf
 * Plugin URI: #
 * Description: ورژن بتا رزرو نوبت دهی
 * Version: 0.6.0
 * Author: kiarash abdollahi
 * License: GPL2
 * Text Domain: my-booking-plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once MBP_PLUGIN_DIR . 'includes/class-mbp-database.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-core.php';
require_once MBP_PLUGIN_DIR . 'elementor/elementor-init.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-license.php';

add_action('admin_init', function () {
    if (!current_user_can('activate_plugins')) return;

    // اگر لایسنس معتبر نیست
    if (!MBP_License::is_valid_cached()) {

        // افزونه را غیرفعال کن
        deactivate_plugins(plugin_basename(__FILE__));

        // نذار پیام "Plugin activated" بیاد
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }

        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>
            افزونه به دلیل لایسنس نامعتبر غیرفعال شد. 
            <a href="'.esc_url(admin_url('admin.php?page=mbp-license')).'">فعال‌سازی</a>
            </p></div>';
        });
    }
});


/* Activation */
function mbp_activate_plugin() {
    MBP_Database::create_tables();
}
register_activation_hook( __FILE__, 'mbp_activate_plugin' );

/* Run plugin */
function mbp_run_plugin() {
    // اجازه بده ادمین همیشه بتونه صفحه لایسنس رو ببینه
    $is_admin = is_admin() && current_user_can('manage_options');

    // اگر لایسنس معتبر نبود، افزونه اجرا نشه
    if (!MBP_License::is_valid_cached()) {

        // یک پیام برای ادمین
        if ($is_admin) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>
                افزونه رزرو فعال نیست. لطفاً لایسنس را وارد کنید: 
                <a href="'.esc_url(admin_url('admin.php?page=mbp-license')).'">صفحه لایسنس</a>
                </p></div>';
            });
        }

        return; // <<< این یعنی MBP_Core اجرا نشه
    }

    $plugin = new MBP_Core();
    $plugin->run();
}
mbp_run_plugin();


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

    $key = esc_attr(MBP_License::get_key());
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


/* ===============================
   GitHub Auto Update
================================ */
add_action('init', function () {

    $puc_path = plugin_dir_path(__FILE__) . 'includes/plugin-update-checker-5.6/plugin-update-checker.php';
    if ( ! file_exists($puc_path) ) {
        return;
    }

    require_once $puc_path;

    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/kiarashAB/wordperesafzone',
        __FILE__,
        'blugin-rezerf'
    );

    // استفاده از GitHub Releases
    $updateChecker->getVcsApi()->enableReleaseAssets();
});
