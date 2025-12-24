<?php
if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Core\Schemes\Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class MBP_Elementor_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'mbp-booking-form';
    }

    public function get_title()
    {
        return 'فرم رزرو نوبت';
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
        // بخش محتوا
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'تنظیمات محتوا',
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'form_title',
            [
                'label' => 'عنوان فرم',
                'type' => Controls_Manager::TEXT,
                'default' => 'فرم رزرو نوبت',
                'placeholder' => 'عنوان فرم را وارد کنید',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => 'نمایش عنوان',
                'type' => Controls_Manager::SWITCHER,
                'label_on' => 'نمایش',
                'label_off' => 'مخفی',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // بخش استایل عنوان
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => 'استایل عنوان',
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => 'تایپوگرافی عنوان',
                'selector' => '{{WRAPPER}} .mbp-form-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => 'رنگ عنوان',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-form-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_margin',
            [
                'label' => 'فاصله عنوان',
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .mbp-form-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // بخش استایل لیبلها
        $this->start_controls_section(
            'labels_style_section',
            [
                'label' => 'استایل لیبلها',
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'labels_typography',
                'label' => 'تایپوگرافی لیبلها',
                'selector' => '{{WRAPPER}} .mbp-label',
            ]
        );

        $this->add_control(
            'labels_color',
            [
                'label' => 'رنگ لیبلها',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // بخش استایل فیلدها
        $this->start_controls_section(
            'fields_style_section',
            [
                'label' => 'استایل فیلدها',
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'fields_typography',
                'label' => 'تایپوگرافی فیلدها',
                'selector' => '{{WRAPPER}} .mbp-input, {{WRAPPER}} .mbp-select',
            ]
        );

        $this->add_control(
            'fields_text_color',
            [
                'label' => 'رنگ متن فیلدها',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-input' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mbp-select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'fields_background_color',
            [
                'label' => 'رنگ پس‌زمینه فیلدها',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-input' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .mbp-select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'fields_border',
                'label' => 'حاشیه فیلدها',
                'selector' => '{{WRAPPER}} .mbp-input, {{WRAPPER}} .mbp-select',
            ]
        );

        $this->add_responsive_control(
            'fields_border_radius',
            [
                'label' => 'گردی حاشیه فیلدها',
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mbp-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .mbp-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'fields_padding',
            [
                'label' => 'فاصله داخلی فیلدها',
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .mbp-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .mbp-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // بخش استایل دکمه
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => 'استایل دکمه',
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => 'تایپوگرافی دکمه',
                'selector' => '{{WRAPPER}} .mbp-submit',
            ]
        );

        $this->start_controls_tabs('button_style_tabs');

        // حالت عادی دکمه
        $this->start_controls_tab(
            'button_normal_tab',
            [
                'label' => 'عادی',
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => 'رنگ متن دکمه',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-submit' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => 'رنگ پس‌زمینه دکمه',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-submit' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        // حالت هاور دکمه
        $this->start_controls_tab(
            'button_hover_tab',
            [
                'label' => 'هاور',
            ]
        );

        $this->add_control(
            'button_hover_text_color',
            [
                'label' => 'رنگ متن دکمه (هاور)',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-submit:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background_color',
            [
                'label' => 'رنگ پس‌زمینه دکمه (هاور)',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-submit:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'label' => 'حاشیه دکمه',
                'selector' => '{{WRAPPER}} .mbp-submit',
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => 'گردی حاشیه دکمه',
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mbp-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => 'فاصله داخلی دکمه',
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .mbp-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'label' => 'سایه دکمه',
                'selector' => '{{WRAPPER}} .mbp-submit',
            ]
        );

        $this->end_controls_section();

        // بخش استایل کلی فرم
        $this->start_controls_section(
            'form_style_section',
            [
                'label' => 'استایل کلی فرم',
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'form_background_color',
            [
                'label' => 'رنگ پس‌زمینه فرم',
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mbp-form' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'label' => 'حاشیه فرم',
                'selector' => '{{WRAPPER}} .mbp-form',
            ]
        );

        $this->add_responsive_control(
            'form_border_radius',
            [
                'label' => 'گردی حاشیه فرم',
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mbp-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'form_padding',
            [
                'label' => 'فاصله داخلی فرم',
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .mbp-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'form_box_shadow',
                'label' => 'سایه فرم',
                'selector' => '{{WRAPPER}} .mbp-form',
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        
        // بررسی لایسنس
        if (!class_exists('MBP_Core')) {
            echo '<div style="padding:20px;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;color:#991b1b;">افزونه رزرو نوبت فعال نیست.</div>';
            return;
        }

        $mbp_core = new MBP_Core();
        
        // شروع خروجی
        echo '<div class="mbp-elementor-widget">';
        
        // نمایش عنوان
        if ($settings['show_title'] === 'yes' && !empty($settings['form_title'])) {
            echo '<h3 class="mbp-form-title">' . esc_html($settings['form_title']) . '</h3>';
        }
        
        // نمایش فرم رزرو
        echo $mbp_core->render_booking_form();
        
        echo '</div>';
    }

    protected function content_template()
    {
        ?>
        <# if ( settings.show_title === 'yes' && settings.form_title ) { #>
            <h3 class="mbp-form-title">{{{ settings.form_title }}}</h3>
        <# } #>
        <div style="padding:20px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;text-align:center;color:#6b7280;">
            <div style="font-size:16px;font-weight:600;margin-bottom:10px;">پیش‌نمایش فرم رزرو</div>
            <div style="font-size:14px;">فرم رزرو در حالت ویرایش نمایش داده نمی‌شود</div>
        </div>
        <?php
    }
}