<?php
/**
 * Plugin Name: blugin-rezerf
 * Plugin URI: #
<<<<<<< HEAD
 * Description: ورژن بتا رزرو نوبت دهی
=======
 * Description: ورژن بتا رزو نوبت دهی
>>>>>>> 95e52bfcda831ff3e49afe5fdeec67a761220c48
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

/* Activation */
function mbp_activate_plugin() {
    MBP_Database::create_tables();
}
register_activation_hook( __FILE__, 'mbp_activate_plugin' );

/* Run plugin */
function mbp_run_plugin() {
    $plugin = new MBP_Core();
    $plugin->run();
}
mbp_run_plugin();

/* ===============================
   GitHub Auto Update
================================ */
add_action('init', function () {

    $puc_path = plugin_dir_path(__FILE__) . 'lib/plugin-update-checker-5.6/plugin-update-checker.php';
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
