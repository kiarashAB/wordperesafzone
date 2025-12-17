<?php
/**
 * Plugin Name: blugin-rezerf
 * Plugin URI: #
 * Description: ورژن بتا رزو نوبت دهی
 * Version: 0.5.4
 * Author: kiarash abdollahi
 * Author URI: #
 * License: GPL2
 * Text Domain: my-booking-plugin
 */

 
if ( ! defined( 'ABSPATH' ) ) exit;

// مسیر اصلی افزونه
define( 'MBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// لود کلاس‌ها
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-database.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-core.php';
require_once MBP_PLUGIN_DIR . 'elementor/elementor-init.php';




// فعال‌سازی: ساخت جدول‌ها
function mbp_activate_plugin() {
    MBP_Database::create_tables();
}
register_activation_hook( __FILE__, 'mbp_activate_plugin' );

// اجرای افزونه
function mbp_run_plugin() {
    $plugin = new MBP_Core();
    $plugin->run();
}
mbp_run_plugin();

add_action('init', function () {

    $puc_path = plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
    if (!file_exists($puc_path)) return;

    require_once $puc_path;

    // بهتر: URL بدون .git
    $repo_url = 'https://github.com/kiarashAB/wordperesafzone/';

    // بهترین حالت: اسلاگ = اسم پوشه افزونه داخل wp-content/plugins/
    $slug = basename(dirname(__FILE__));

    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $repo_url,
        __FILE__,
        $slug
    );

    // اگر می‌خوای از Release Asset (zip) استفاده کنی
    $updateChecker->getVcsApi()->enableReleaseAssets();
});
