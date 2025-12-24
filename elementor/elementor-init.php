<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('plugins_loaded', function () {

    
    if ( ! did_action('elementor/loaded') ) return;

    // ثبت دسته ویجت
    add_action('elementor/elements/categories_registered', function($elements_manager){
        $elements_manager->add_category(
            'mbp-widgets',
            [
                'title' => 'افزونه رزرو نوبت',
                'icon' => 'fa fa-calendar',
            ]
        );
    });

    // ویجت رو ثبت کن
    add_action('elementor/widgets/register', function($widgets_manager){

        require_once MBP_PLUGIN_DIR . 'elementor/widgets/class-mbp-booking-widget.php';
        $widgets_manager->register( new \MBP_Booking_Widget() );

    });
});