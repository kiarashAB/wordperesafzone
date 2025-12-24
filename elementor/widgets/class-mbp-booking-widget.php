<?php
if (!defined('ABSPATH'))
    exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class MBP_Booking_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'mbp_booking_form';
    }
    public function get_title()
    {
        return 'جدول رزرو نوبت';
    }
    public function get_icon()
    {
        return 'eicon-calendar';
    }
    public function get_categories()
    {
        return ['mbp-widgets'];
    }

    protected function register_controls()
    {
        $this->start_controls_section('content_section', [
            'label' => 'تنظیمات جدول',
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('title', [
            'label' => 'عنوان بالای جدول',
            'type' => Controls_Manager::TEXT,
            'default' => 'زمان‌بندی نوبت‌ها',
        ]);

        $this->add_control('show_form', [
            'label' => 'نمایش فرم رزرو زیر جدول',
            'type' => Controls_Manager::SWITCHER,
            'label_on' => 'بله',
            'label_off' => 'خیر',
            'return_value' => '1',
            'default' => '1',
        ]);

        $this->add_control('extra_class', [
            'label' => 'کلاس اضافی',
            'type' => Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->end_controls_section();

        // ما یک wrapper داخلی به اسم .mbp-skin می‌سازیم
        $sel = '{{WRAPPER}} .mbp-skin';

        $this->start_controls_section('style_colors_section', [
            'label' => 'استایل (رنگ‌ها)',
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('mbp_bg', [
            'label' => 'پس‌زمینه',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-bg: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_text', [
            'label' => 'رنگ متن',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-text: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_border', [
            'label' => 'رنگ کادر',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-border: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_btn_bg', [
            'label' => 'پس‌زمینه دکمه',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-btn-bg: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_btn_text', [
            'label' => 'رنگ متن دکمه',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-btn-text: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_btn_border', [
            'label' => 'بوردر دکمه',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-btn-border: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_free_bg', [
            'label' => 'پس‌زمینه خانه خالی',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-free-bg: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_free_text', [
            'label' => 'متن خانه خالی',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-free-text: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_booked_bg', [
            'label' => 'پس‌زمینه خانه پر',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-booked-bg: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_booked_text', [
            'label' => 'متن خانه پر',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-booked-text: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        // بخش تایپوگرافی
        $this->start_controls_section('typography_section', [
            'label' => 'تایپوگرافی',
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'label' => 'فونت عنوان',
            'selector' => '{{WRAPPER}} .mbp-el-title',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'form_labels_typography',
            'label' => 'فونت لیبلهای فرم',
            'selector' => '{{WRAPPER}} .mbp-skin .mbp-label',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'form_inputs_typography',
            'label' => 'فونت فیلدهای فرم',
            'selector' => '{{WRAPPER}} .mbp-skin .mbp-input, {{WRAPPER}} .mbp-skin .mbp-select',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'form_button_typography',
            'label' => 'فونت دکمه فرم',
            'selector' => '{{WRAPPER}} .mbp-skin .mbp-submit',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'table_headers_typography',
            'label' => 'فونت سرتیتر جدول',
            'selector' => '{{WRAPPER}} .mbp-skin .mbp-public-schedule th',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'table_cells_typography',
            'label' => 'فونت خانههای جدول',
            'selector' => '{{WRAPPER}} .mbp-skin .mbp-public-schedule td',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'toolbar_typography',
            'label' => 'فونت نوار ابزار',
            'selector' => '{{WRAPPER}} .mbp-skin .mbp-public-toolbar',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_layout_section', [
            'label' => 'استایل (چیدمان)',
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('wrapper_padding', [
            'label' => 'پدینگ کلی ویجت',
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .mbp-widget-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('title_color', [
            'label' => 'رنگ عنوان',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mbp-el-title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_btn_bg_hover', [
            'label' => 'پس‌زمینه دکمه (Hover)',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-btn-bg-hover: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_input_bg', [
            'label' => 'پس‌زمینه فیلدها',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-input-bg: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_input_border', [
            'label' => 'بوردر فیلدها',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-input-border: {{VALUE}};',
            ],
        ]);

        $this->add_control('mbp_focus', [
            'label' => 'رنگ فوکوس فیلدها',
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                $sel => '--mbp-focus: {{VALUE}};',
            ],
          
        ]);


        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .mbp-el-title',
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'wrap_border',
            'selector' => '{{WRAPPER}} .mbp-widget-wrap',
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'wrap_shadow',
            'selector' => '{{WRAPPER}} .mbp-widget-wrap',
        ]);


        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $title = $settings['title'] ?? '';
        $show_form = $settings['show_form'] ?? '1';
        $extra = $settings['extra_class'] ?? '';

        echo '<div class="mbp-widget-wrap ' . esc_attr($extra) . '">';

        if (!empty($title)) {
            echo '<h3 class="mbp-el-title" style="text-align:right;margin:0 0 10px;">' . esc_html($title) . '</h3>';
        }

        // IMPORTANT: .mbp-skin wrapper برای اعمال متغیرها
        echo '<div class="mbp-skin">';
        echo do_shortcode('[mbp_public_schedule show_form="' . esc_attr($show_form) . '" skin="mbp-skin" class="' . esc_attr($extra) . '"]');
        echo '</div>';

        echo '</div>';
    }
}
