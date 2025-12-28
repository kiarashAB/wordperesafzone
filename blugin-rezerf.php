<?php
/**
 * Plugin Name: blugin-rezerf
 * Plugin URI: #
 * Description: ورژن بتا رزو نوبت دهی
 * Version: 0.8.7
 * Author: kiarash abdollahi
 * Author URI: #
 * License: GPL2
 * Text Domain: my-booking-plugin
 */

if (!defined('ABSPATH')) exit;

define('MBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MBP_PLUGIN_URL', plugin_dir_url(__FILE__));
if ( ! function_exists('mbp_get_plugin_version') ) {
  function mbp_get_plugin_version() {
    if ( ! function_exists('get_file_data') ) {
      require_once ABSPATH . 'wp-includes/functions.php';
    }
    $data = get_file_data(__FILE__, array('Version' => 'Version'), 'plugin');
    return $data['Version'] ?? '0.0.0';
  }
}
define('MBP_VERSION', mbp_get_plugin_version());


// لود کلاس‌ها
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-database.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-license.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-core.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-sms-manager.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-payment-gateway.php';
require_once MBP_PLUGIN_DIR . 'elementor/elementor-init.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-invoice.php';
require_once MBP_PLUGIN_DIR . 'includes/class-mbp-invoice-license.php';



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

register_activation_hook(__FILE__, function () {
    if (class_exists('MBP_Invoices')) {
        MBP_Invoices::install();
    } else {
        require_once plugin_dir_path(__FILE__) . 'includes/class-mbp-invoices.php';
        MBP_Invoices::install();
    }
});


function mbp_run_plugin() {
    $plugin = new MBP_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'mbp_run_plugin');

// اجرای افزونه (مهم: بعد از آماده شدن وردپرس)
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