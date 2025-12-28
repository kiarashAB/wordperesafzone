<?php
if (!defined('ABSPATH'))
    exit;

class MBP_Core
{
    const OPTION_SCHEDULE_SETTINGS = 'mbp_schedule_settings_v1';
    const OPTION_TIME_SLOTS = 'mbp_time_slots_v1';
    const OPTION_PAYMENT_SETTINGS = 'mbp_payment_settings_v1';
    const OPTION_GENERAL_SETTINGS = 'mbp_general_settings_v1';

    public function __construct()
    {
        // Admin Menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Shortcodes
        add_shortcode('my_booking_form', array($this, 'render_booking_form'));
        add_shortcode('mbp_public_schedule', array($this, 'render_public_schedule'));
        add_shortcode('mbp_services_list', array($this, 'render_services_list'));
        add_shortcode('mbp_payment_verify', array($this, 'render_payment_verify_page'));
        add_shortcode('mbp_my_appointments', array($this, 'render_my_appointments'));

        // Submit booking (logged-in + guest)
        add_action('wp_ajax_mbp_submit_booking', array($this, 'handle_booking_submit'));
        add_action('wp_ajax_nopriv_mbp_submit_booking', array($this, 'handle_booking_submit'));

        // Public schedule week ajax (front)
        add_action('wp_ajax_mbp_public_get_schedule_week', array($this, 'ajax_public_get_schedule_week'));
        add_action('wp_ajax_nopriv_mbp_public_get_schedule_week', array($this, 'ajax_public_get_schedule_week'));

        // Front assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Fullscreen dashboard page
        add_action('admin_post_mbp_dashboard_app', array($this, 'render_dashboard_app_page'));

        // Admin actions (AJAX)
        add_action('wp_ajax_mbp_admin_approve_booking', array($this, 'admin_approve_booking'));
        add_action('wp_ajax_mbp_admin_delete_booking', array($this, 'admin_delete_booking'));
        add_action('wp_ajax_mbp_admin_cancel_booking', array($this, 'admin_cancel_booking'));

        // Schedule week ajax (admin)
        add_action('wp_ajax_mbp_get_schedule_week', array($this, 'ajax_get_schedule_week'));

        // Time slots (admin)
        add_action('wp_ajax_mbp_get_time_slots', array($this, 'ajax_get_time_slots'));
        add_action('wp_ajax_mbp_save_time_slots', array($this, 'ajax_save_time_slots'));

        // License AJAX (admin)
        add_action('wp_ajax_mbp_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_mbp_deactivate_license_local', array($this, 'ajax_deactivate_license_local'));
        add_action('wp_ajax_mbp_activate_invoice_license', [$this, 'ajax_activate_invoice_license']);
        add_action('wp_ajax_mbp_deactivate_invoice_license_local', [$this, 'ajax_deactivate_invoice_license_local']);


        // Services AJAX
        add_action('wp_ajax_mbp_get_services', array($this, 'ajax_get_services'));
        add_action('wp_ajax_mbp_save_service', array($this, 'ajax_save_service'));
        add_action('wp_ajax_mbp_delete_service', array($this, 'ajax_delete_service'));
        add_action('wp_ajax_mbp_toggle_service', array($this, 'ajax_toggle_service'));

        // SMS AJAX
        add_action('wp_ajax_mbp_get_sms_settings', array($this, 'ajax_get_sms_settings'));
        add_action('wp_ajax_mbp_send_custom_sms', array($this, 'ajax_send_custom_sms'));

        // Payment AJAX
        add_action('wp_ajax_mbp_get_payment_settings', array($this, 'ajax_get_payment_settings'));
        add_action('wp_ajax_mbp_save_payment_settings', array($this, 'ajax_save_payment_settings'));
        add_action('wp_ajax_mbp_initiate_payment', array($this, 'ajax_initiate_payment'));

        // Invoices AJAX
        add_action('wp_ajax_mbp_get_invoices', array($this, 'ajax_get_invoices'));
        add_action('wp_ajax_mbp_create_invoice', array($this, 'ajax_create_invoice'));
        add_action('wp_ajax_mbp_update_invoice_status', array($this, 'ajax_update_invoice_status'));

        // User appointments AJAX
        add_action('wp_ajax_mbp_get_user_appointments', array($this, 'ajax_get_user_appointments'));
        add_action('wp_ajax_mbp_cancel_user_appointment', array($this, 'ajax_cancel_user_appointment'));

        // Payment verification endpoint
        add_action('template_redirect', array($this, 'handle_payment_callback'));

        // Cron jobs
        add_action('mbp_check_expired_payments', array($this, 'check_expired_payments'));
        add_action('mbp_send_reminders', array($this, 'send_appointment_reminders'));

        add_action('wp_ajax_mbp_send_mass_sms', array($this, 'ajax_send_mass_sms'));

        // Schedule cron jobs
        if (!wp_next_scheduled('mbp_check_expired_payments')) {
            wp_schedule_event(time(), 'hourly', 'mbp_check_expired_payments');
        }

        if (!wp_next_scheduled('mbp_send_reminders')) {
            wp_schedule_event(time(), 'daily', 'mbp_send_reminders');
        }

        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        add_action('wp_ajax_mbp_invoice_create', [$this, 'ajax_invoice_create']);
        add_action('wp_ajax_mbp_get_invoices', [$this, 'ajax_get_invoices']);
        add_action('wp_ajax_mbp_invoice_delete', [$this, 'ajax_invoice_delete']);
        add_action('wp_ajax_mbp_invoice_print', [$this, 'ajax_invoice_print']);

        // Elementor support
        add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widgets'));
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_categories'));
        require_once MBP_PLUGIN_DIR . 'includes/class-mbp-invoice.php';

        // ✅ خیلی مهم: این new باعث میشه هوک‌های Invoice فعال بشن
        $this->invoice = new MBP_Invoice();
    }

    public function run()
    {
    }

    // =========================
    // ELEMENTOR SUPPORT
    // =========================
    public function register_elementor_widgets()
    {
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Include widget file
        require_once plugin_dir_path(__FILE__) . 'class-mbp-elementor-widget.php';

        // Register widget
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new MBP_Elementor_Widget());
    }

    public function add_elementor_widget_categories($elements_manager)
    {
        $elements_manager->add_category(
            'mbp-widgets',
            [
                'title' => 'افزونه رزرو نوبت',
                'icon' => 'fa fa-calendar',
            ]
        );
    }


    // Admin assets if needed

    // =========================
    // SCHEDULE AJAX
    // =========================
    public function ajax_get_schedule_week()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $week_start = isset($_POST['week_start']) ? sanitize_text_field($_POST['week_start']) : wp_date('Y-m-d');

        // اعتبارسنجی تاریخ
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            wp_send_json_error(array('message' => 'تاریخ نامعتبر است'));
        }

        $settings = $this->schedule_settings();

        $tz = wp_timezone();
        $ws = new DateTime($week_start . ' 00:00:00', $tz);
        $we = clone $ws;
        $we->modify('+6 day');

        $appointments_week = $this->get_appointments_for_range($ws->format('Y-m-d'), $we->format('Y-m-d'));
        $schedule_html = $this->render_schedule_grid_html($appointments_week, $week_start, $settings);

        wp_send_json_success(array('html' => $schedule_html));
    }

    // =========================
    // LICENSE HELPERS
    // =========================
    private function license_is_ok()
    {
        if (!class_exists('MBP_License'))
            return false;
        if (!method_exists('MBP_License', 'is_valid'))
            return false;
        return (bool) MBP_License::is_valid();
    }

    private function render_license_required_box($context = 'front')
    {
        $msg = ($context === 'admin')
            ? 'برای استفاده از امکانات افزونه، ابتدا لایسنس را فعال کنید.'
            : 'برای نمایش جدول/فرم رزرو، افزونه باید فعال‌سازی شود.';

        return '
        <div style="direction:rtl;margin:12px 0;padding:12px;border:1px solid rgba(214,54,56,.35);background:rgba(214,54,56,.08);border-radius:12px;">
            <div style="font-weight:900;margin-bottom:6px;color:#7a1112;">نیاز به فعال‌سازی ❌</div>
            <div style="color:#374151;line-height:1.9;">' . esc_html($msg) . '</div>
        </div>';
    }

    // =========================
    // LICENSE AJAX
    // =========================

    public function ajax_activate_invoice_license()
    {
        check_ajax_referer('mbp_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی ندارید']);
        }

        $key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        $res = MBP_Invoice_License::activate($key);

        if (!empty($res['ok'])) {
            wp_send_json_success(['message' => $res['message'] ?? 'فعال شد ✅']);
        }

        wp_send_json_error(['message' => $res['message'] ?? 'ناموفق']);
    }

    public function ajax_deactivate_invoice_license_local()
    {
        check_ajax_referer('mbp_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی ندارید']);
        }

        MBP_Invoice_License::deactivate_local();
        wp_send_json_success(['message' => 'لایسنس فاکتور (محلی) غیرفعال شد']);
    }


    public function ajax_activate_license()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mbp_license_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        if (!class_exists('MBP_License') || !method_exists('MBP_License', 'activate')) {
            wp_send_json_error(array('message' => 'کلاس/متد لایسنس وجود ندارد'));
        }

        $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        if ($key === '') {
            wp_send_json_error(array('message' => 'لایسنس خالی است'));
        }

        $result = MBP_License::activate($key);

        if (!is_array($result) || empty($result['ok'])) {
            $m = is_array($result) && !empty($result['message']) ? $result['message'] : 'لایسنس نامعتبر است';
            wp_send_json_error(array('message' => $m));
        }

        wp_send_json_success(array('message' => $result['message'] ?? 'فعال شد ✅'));
    }

    public function ajax_deactivate_license_local()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mbp_license_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        if (class_exists('MBP_License') && method_exists('MBP_License', 'deactivate_local')) {
            MBP_License::deactivate_local();
        }

        wp_send_json_success(array('message' => 'غیرفعال شد'));
    }

    // =========================
    // Front JS + CSS
    // =========================
    public function enqueue_assets()
    {
        // CSS اصلی
        $css = <<<CSS
.mbp-skin{
  --mbp-bg:#fff; --mbp-text:#111827; --mbp-border:#e5e7eb; --mbp-cell-border:#f1f5f9; --mbp-head-bg:#fff;
  --mbp-free-bg:#ecfdf5; --mbp-free-text:#065f46;
  --mbp-booked-bg:#fef2f2; --mbp-booked-text:#991b1b;
  --mbp-btn-bg:#f9fafb; --mbp-btn-text:#111827; --mbp-btn-border:#d1d5db;
  --mbp-btn-bg-hover:#f3f4f6; --mbp-btn-text-hover:#111827; --mbp-btn-border-hover:#cbd5e1;
  --mbp-input-bg:#fff; --mbp-input-text:#111827; --mbp-input-border:#d1d5db; --mbp-input-border-hover:#cbd5e1;
  --mbp-focus:#60a5fa;
  --mbp-paid-bg:#d1fae5; --mbp-paid-text:#065f46;
  --mbp-unpaid-bg:#fee2e2; --mbp-unpaid-text:#991b1b;
  --mbp-warning-bg:#fef3c7; --mbp-warning-text:#92400e;
}

.mbp-public-wrap{ margin:12px 0; direction:rtl; }
.mbp-public-toolbar{
  display:flex; gap:10px; align-items:center; justify-content:center;
  padding:10px; border:1px solid var(--mbp-border); border-radius:12px; background:var(--mbp-bg); color:var(--mbp-text);
}
.mbp-public-title strong{ font-weight:900; }

.mbp-public-scroll{ overflow:auto; margin-top:10px; border:1px solid var(--mbp-border); border-radius:12px; background:var(--mbp-bg); }
table.mbp-public-schedule{ width:100%; border-collapse:separate; border-spacing:0; min-width:900px; }

.mbp-public-schedule th,.mbp-public-schedule td{
  border-bottom:1px solid var(--mbp-cell-border); border-left:1px solid var(--mbp-cell-border);
  padding:10px; text-align:center; vertical-align:middle; background:var(--mbp-bg); color:var(--mbp-text);
}

.mbp-public-corner,.mbp-public-time{ position:sticky; right:0; background:var(--mbp-head-bg); font-weight:900; z-index:2; }
.mbp-public-schedule thead th{ position:sticky; top:0; background:var(--mbp-head-bg); z-index:3; }

.mbp-public-cell{ transition:.14s ease; }
.mbp-public-cell.free{ background:var(--mbp-free-bg); color:var(--mbp-free-text); cursor:pointer; font-weight:900; }
.mbp-public-cell.booked{ background:var(--mbp-booked-bg); color:var(--mbp-booked-text); font-weight:900; }

.mbp-form-title{ margin:0 0 10px 0; font-weight:900; }
.mbp-field{ margin:0 0 12px 0; }
.mbp-label{ display:block; margin-bottom:6px; font-weight:800; font-size:13px; }
.mbp-input, .mbp-select{
  width:100%; padding:10px; border:1px solid var(--mbp-input-border); border-radius:10px;
  background:var(--mbp-input-bg); color:var(--mbp-input-text); box-sizing:border-box; outline:none; transition:.16s ease;
}
.mbp-ltr{ direction:ltr; text-align:left; }

.mbp-submit{
  display:inline-flex; align-items:center; justify-content:center; padding:10px 16px; border-radius:10px;
  border:1px solid var(--mbp-btn-border); color:#fff; background:#5c5c5c; cursor:pointer; font-weight:900; transition:.18s ease;
}
.mbp-submit:hover{ background:#454545; transform:translateY(-1px); }
.mbp-submit:active{ transform:translateY(0); }

.mbp-submit-primary{
  background:#2563eb; border-color:#2563eb;
}
.mbp-submit-primary:hover{
  background:#1d4ed8;
}

.mbp-submit-success{
  background:#10b981; border-color:#10b981;
}
.mbp-submit-success:hover{
  background:#0da271;
}

.mbp-payment-btn{
  display:inline-flex; align-items:center; gap:8px; padding:12px 24px; border-radius:10px;
  background:linear-gradient(135deg, #10b981, #059669); color:white; font-weight:900; text-decoration:none;
  border:none; cursor:pointer; transition:all 0.3s ease; box-shadow:0 4px 12px rgba(16, 185, 129, 0.3);
}
.mbp-payment-btn:hover{
  transform:translateY(-2px); box-shadow:0 6px 18px rgba(16, 185, 129, 0.4); color:white;
}

#mbp-result.mbp-result{ margin-top:12px; padding:10px; border-radius:10px; border:1px solid #c3c4c7; background:#fff; color:#111827; }
#mbp-result.mbp-result-success{ border-color: rgba(0,163,42,.45); background:rgba(0,163,42,.05); }
#mbp-result.mbp-result-error{ border-color: rgba(214,54,56,.55); background:rgba(214,54,56,.05); }
#mbp-result.mbp-result-warning{ border-color: rgba(245,158,11,.55); background:rgba(245,158,11,.05); }
#mbp-result.mbp-result-info{ border-color: rgba(59,130,246,.55); background:rgba(59,130,246,.05); }

.mbp-services-grid{
  display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; margin:20px 0;
}
.mbp-service-card{
  border:1px solid var(--mbp-border); border-radius:12px; padding:16px; background:var(--mbp-bg);
  transition:transform 0.2s ease, box-shadow 0.2s ease;
}
.mbp-service-card:hover{
  transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,0.1);
}
.mbp-service-title{
  font-size:18px; font-weight:900; margin:0 0 8px 0; color:var(--mbp-text);
}
.mbp-service-description{
  color:#6b7280; font-size:14px; line-height:1.6; margin:0 0 12px 0;
}
.mbp-service-price{
  font-size:20px; font-weight:900; color:#10b981; margin:0 0 12px 0;
}
.mbp-service-duration{
  display:inline-flex; align-items:center; gap:4px; padding:4px 10px; background:#f3f4f6;
  border-radius:20px; font-size:12px; color:#6b7280;
}

.mbp-appointments-list{
  display:flex; flex-direction:column; gap:12px;
}
.mbp-appointment-card{
  border:1px solid var(--mbp-border); border-radius:12px; padding:16px; background:var(--mbp-bg);
}
.mbp-appointment-header{
  display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;
}
.mbp-appointment-status{
  padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;
}
.mbp-appointment-status.pending{ background:#fef3c7; color:#92400e; }
.mbp-appointment-status.approved{ background:#d1fae5; color:#065f46; }
.mbp-appointment-status.cancelled{ background:#fee2e2; color:#991b1b; }
.mbp-appointment-status.paid{ background:#dbeafe; color:#1e40af; }
.mbp-appointment-status.unpaid{ background:#fee2e2; color:#991b1b; }
.mbp-appointment-details{
  display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;
}
.mbp-appointment-detail{
  display:flex; flex-direction:column; gap:4px;
}
.mbp-appointment-label{
  font-size:12px; color:#6b7280;
}
.mbp-appointment-value{
  font-weight:700; color:var(--mbp-text);
}
.mbp-appointment-actions{
  display:flex; gap:8px; margin-top:16px;
}

.loading-spinner{
  display:inline-block; width:20px; height:20px; border:3px solid rgba(59,130,246,0.3);
  border-radius:50%; border-top-color:#3b82f6; animation:spin 1s linear infinite;
}
@keyframes spin{
  to{ transform:rotate(360deg); }
}
CSS;

        wp_register_style('mbp-front-inline', false);
        wp_enqueue_style('mbp-front-inline');
        wp_add_inline_style('mbp-front-inline', $css);

        // JavaScript
        wp_enqueue_script('jquery');

        $ajax_url = esc_js(admin_url('admin-ajax.php'));
        $nonce = wp_create_nonce('mbp_booking_nonce');

        $inline_script = <<<JS
jQuery(function($){
  const ajax = '{$ajax_url}';
  const mbp_nonce = '{$nonce}';

  // فرم رزرو
  $(document).on('submit', '#mbp-booking-form', function(e){
    e.preventDefault();
    var \$form = $(this);
    var submitBtn = \$form.find('.mbp-submit');
    var originalText = submitBtn.text();
    
    submitBtn.prop('disabled', true);
    submitBtn.html('<span class="loading-spinner"></span> در حال ثبت...');
    $('#mbp-result').remove();

    var formData = \$form.serialize();

    $.post(ajax, formData)
      .done(function(res){
        if(res && res.success){
          var msg = res.data.message || 'ثبت شد';
          var cls = 'mbp-result-success';
          
          // اگر نیاز به پرداخت دارد
          if(res.data.needs_payment && res.data.payment_url){
            msg += '<br><br><a href="' + res.data.payment_url + '" class="mbp-payment-btn">پرداخت آنلاین</a>';
            cls = 'mbp-result-warning';
          }
          
          \$form.after('<div id="mbp-result" class="mbp-result ' + cls + '">' + msg + '</div>');
          
          // اگر پرداخت نیاز نیست، فرم ریست شود
          if(!res.data.needs_payment){
            \$form[0].reset();
          }
        }else{
          var msg = (res && res.data && res.data.message) ? res.data.message : 'خطا در ثبت';
          \$form.after('<div id="mbp-result" class="mbp-result mbp-result-error">' + msg + '</div>');
        }
      })
      .fail(function(){
        \$form.after('<div id="mbp-result" class="mbp-result mbp-result-error">خطا در ارسال فرم</div>');
      })
      .always(function(){
        submitBtn.prop('disabled', false);
        submitBtn.text(originalText);
      });
  });

  // کلیک روی خانه خالی جدول
  $(document).on('click', '.mbp-public-cell.free', function(){
    var day  = $(this).data('day');
    var slot = $(this).data('slot');
    $('#mbp-date').val(day);
    $('#mbp-slot').val(slot);
    var form = $('#mbp-booking-form');
    if(form.length){
      $('html, body').animate({ scrollTop: form.offset().top - 80 }, 400);
    }
  });

  // تغییر خدمت
  $(document).on('change', '#mbp-service-id', function(){
    var serviceId = $(this).val();
    if(serviceId){
      $.post(ajax, {
        action: 'mbp_get_service_price',
        service_id: serviceId,
        nonce: mbp_nonce
      })
      .done(function(res){
        if(res && res.success && res.data.price){
          $('#mbp-service-price').show().find('.price-amount').text(res.data.price.toLocaleString());
        }
      });
    }
  });

  // حذف نوبت کاربر
  $(document).on('click', '.mbp-cancel-appointment', function(e){
    e.preventDefault();
    if(!confirm('آیا از لغو نوبت مطمئن هستید؟')) return;
    
    var \$btn = $(this);
    var appointmentId = \$btn.data('id');
    var originalText = \$btn.text();
    
    \$btn.prop('disabled', true);
    \$btn.text('در حال لغو...');
    
    $.post(ajax, {
      action: 'mbp_cancel_user_appointment',
      appointment_id: appointmentId,
      nonce: mbp_nonce
    })
    .done(function(res){
      if(res && res.success){
        \$btn.closest('.mbp-appointment-card').fadeOut(300, function(){
          $(this).remove();
        });
      }else{
        alert(res.data.message || 'خطا در لغو نوبت');
        \$btn.prop('disabled', false);
        \$btn.text(originalText);
      }
    })
    .fail(function(){
      alert('خطا در ارتباط با سرور');
      \$btn.prop('disabled', false);
      \$btn.text(originalText);
    });
  });

  // شروع پرداخت
  $(document).on('click', '.mbp-initiate-payment', function(e){
    e.preventDefault();
    var \$btn = $(this);
    var appointmentId = \$btn.data('id');
    var originalText = \$btn.text();
    
    \$btn.prop('disabled', true);
    \$btn.html('<span class="loading-spinner"></span> در حال اتصال به درگاه...');
    
    $.post(ajax, {
      action: 'mbp_initiate_payment',
      appointment_id: appointmentId,
      nonce: mbp_nonce
    })
    .done(function(res){
      if(res && res.success && res.data.payment_url){
        window.location.href = res.data.payment_url;
      }else{
        alert(res.data.message || 'خطا در اتصال به درگاه پرداخت');
        \$btn.prop('disabled', false);
        \$btn.text(originalText);
      }
    })
    .fail(function(){
      alert('خطا در ارتباط با سرور');
      \$btn.prop('disabled', false);
      \$btn.text(originalText);
    });
  });
});
JS;

        wp_add_inline_script('jquery', $inline_script);
    }

    // =========================
    // Admin Menu
    // =========================
    public function add_admin_menu()
    {
        add_menu_page(
            __('Booking Management', 'my-booking-plugin'),
            __('پنل رزرو نوبت', 'my-booking-plugin'),
            'manage_options',
            'mbp-bookings',
            array($this, 'admin_page_content'),
            'dashicons-calendar-alt',
            6
        );

        add_submenu_page(
            'mbp-bookings',
            'Dashboard',
            'ورود به پنل',
            'manage_options',
            'mbp-dashboard-redirect',
            array($this, 'dashboard_redirect_page')
        );


    }

    public function dashboard_redirect_page()
    {
        if (!$this->license_is_ok()) {
            wp_redirect(admin_url('admin.php?page=mbp-bookings'));
            exit;
        }

        wp_redirect(admin_url('admin-post.php?action=mbp_dashboard_app'));
        exit;
    }

    // =========================
    // ADMIN PAGES
    // =========================
    public function admin_page_content()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی ندارید');
        }

        $license_ok = $this->license_is_ok();
        $invoice_license_ok = $this->invoice_license_is_ok();

        $dashboard_url = admin_url('admin-post.php?action=mbp_dashboard_app');
        $nonce = wp_create_nonce('mbp_license_nonce');

        echo '<div class="wrap" style="direction:rtl;">';
        echo '<h1 style="margin-bottom:14px;">' . esc_html__('پنل مدیریت رزرو', 'my-booking-plugin') . '</h1>';

        // کارت اصلی رزرو
        echo '
    <div style="
        max-width: 720px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;
        box-shadow: 0 8px 22px rgba(0,0,0,.06);
        margin-bottom:14px;
    ">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;opacity:.85;margin-bottom:6px;">افزونه</div>
                <div style="font-size:18px;font-weight:900;">افزونه رزرو نوبت</div>
            </div>';

        if ($license_ok) {
            echo '<div style="
            padding:6px 10px;border-radius:999px;background:rgba(0,163,42,.12);
            border:1px solid rgba(0,163,42,.25);color:#0a5a22;font-weight:800;font-size:12px;white-space:nowrap;
        ">فعال ✅</div>';
        } else {
            echo '<div style="
            padding:6px 10px;border-radius:999px;background:rgba(214,54,56,.10);
            border:1px solid rgba(214,54,56,.25);color:#7a1112;font-weight:800;font-size:12px;white-space:nowrap;
        ">نیاز به فعال‌سازی ❌</div>';
        }

        echo '</div>

        <div style="margin-top:12px;line-height:1.9;color:#374151;">
            از اینجا می‌تونی وارد <strong>پنل تمام صفحه</strong> بشی و رزروها رو مدیریت کنی.
        </div>

        <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">';

        if ($license_ok) {
            echo '<a href="' . esc_url($dashboard_url) . '" class="button button-primary" style="font-weight:800;padding:6px 14px;">
                ورود به پنل مدیریت
              </a>
              <button type="button" id="mbp-license-deactivate" class="button" style="font-weight:800;padding:6px 14px;">
                غیرفعال‌سازی (محلی)
              </button>';
        } else {
            echo '<button type="button" id="mbp-license-open" class="button button-primary" style="font-weight:800;padding:6px 14px;">
                فعال‌سازی / لایسنس
              </button>';
        }

        echo '</div>

        <div style="margin-top:10px;font-size:12px;opacity:.75;">
            نکته: برای نمایش جدول رزرو در سایت از شورتکد <code>[mbp_public_schedule]</code> استفاده کن.
        </div>
    </div>';

        // کارت فاکتور (ووکامرس)
        echo '
    <div style="
        max-width: 720px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;
        box-shadow: 0 8px 22px rgba(0,0,0,.06);
    ">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;opacity:.85;margin-bottom:6px;">ماژول</div>
                <div style="font-size:18px;font-weight:900;">فاکتور (ویژه ووکامرس)</div>
            </div>';

        if ($invoice_license_ok) {
            echo '<div style="
            padding:6px 10px;border-radius:999px;background:rgba(0,163,42,.12);
            border:1px solid rgba(0,163,42,.25);color:#0a5a22;font-weight:800;font-size:12px;white-space:nowrap;
        ">فعال ✅</div>';
        } else {
            echo '<div style="
            padding:6px 10px;border-radius:999px;background:rgba(214,54,56,.10);
            border:1px solid rgba(214,54,56,.25);color:#7a1112;font-weight:800;font-size:12px;white-space:nowrap;
        ">غیرفعال / نیاز به لایسنس ❌</div>';
        }

        echo '</div>

        <div style="margin-top:12px;line-height:1.9;color:#374151;">
            این بخش برای ساخت/مدیریت فاکتورهای مرتبط با سفارش‌های <strong>ووکامرس</strong> استفاده می‌شود.
        </div>

        <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">';

        if ($invoice_license_ok) {
            echo '<button type="button" id="mbp-invoice-license-deactivate" class="button" style="font-weight:800;padding:6px 14px;">
                غیرفعال‌سازی لایسنس فاکتور (محلی)
              </button>';
        } else {
            echo '<button type="button" id="mbp-invoice-license-open" class="button button-primary" style="font-weight:800;padding:6px 14px;">
                فعال‌سازی / لایسنس فاکتور
              </button>';
        }

        echo '</div>

        <div style="margin-top:10px;font-size:12px;opacity:.75;">
            نکته: تا وقتی لایسنس فاکتور فعال نشود، تب فاکتورها باید غیرفعال/کم‌رنگ باشد.
        </div>
    </div>';

        // مودال لایسنس رزرو
        echo '
    <div id="mbp-license-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;">
        <div style="width:min(520px,92vw);margin:10vh auto;background:#fff;border-radius:14px;padding:14px;box-shadow:0 10px 30px rgba(0,0,0,.25);">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div style="font-weight:900;">فعال‌سازی لایسنس رزرو</div>
                <button type="button" class="button" id="mbp-license-close">بستن</button>
            </div>

            <div style="margin-top:12px;">
                <label style="font-weight:800;display:block;margin-bottom:6px;">کد لایسنس رزرو</label>
                <input id="mbp-license-key" type="text" class="regular-text" style="width:100%;" placeholder="XXXX-XXXX-XXXX">
                <div style="font-size:12px;opacity:.75;margin-top:6px;">دامنه این سایت به صورت خودکار بررسی می‌شود.</div>
            </div>

            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="button" class="button button-primary" id="mbp-license-activate">فعال‌سازی</button>
                <span id="mbp-license-msg" style="font-size:12px;color:#6b7280;"></span>
            </div>
        </div>
    </div>';

        // مودال لایسنس فاکتور
        echo '
    <div id="mbp-invoice-license-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;">
        <div style="width:min(520px,92vw);margin:10vh auto;background:#fff;border-radius:14px;padding:14px;box-shadow:0 10px 30px rgba(0,0,0,.25);">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div style="font-weight:900;">فعال‌سازی لایسنس فاکتور</div>
                <button type="button" class="button" id="mbp-invoice-license-close">بستن</button>
            </div>

            <div style="margin-top:12px;">
                <label style="font-weight:800;display:block;margin-bottom:6px;">کد لایسنس فاکتور</label>
                <input id="mbp-invoice-license-key" type="text" class="regular-text" style="width:100%;" placeholder="XXXX-XXXX-XXXX">
                <div style="font-size:12px;opacity:.75;margin-top:6px;">دامنه این سایت به صورت خودکار بررسی می‌شود.</div>
            </div>

            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="button" class="button button-primary" id="mbp-invoice-license-activate">فعال‌سازی</button>
                <span id="mbp-invoice-license-msg" style="font-size:12px;color:#6b7280;"></span>
            </div>
        </div>
    </div>';

        // اسکریپت (هر دو لایسنس)
        echo '
    <script>
    (function(){
        const ajaxUrl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';
        const nonce   = ' . wp_json_encode($nonce) . ';

        async function post(action, extra){
            const fd = new FormData();
            fd.append("action", action);
            fd.append("nonce", nonce);
            if(extra){
                Object.keys(extra).forEach(k => fd.append(k, extra[k]));
            }
            const res = await fetch(ajaxUrl, { method:"POST", body: fd });
            return await res.json();
        }

        // ====== رزرو ======
        const openBtn   = document.getElementById("mbp-license-open");
        const modal     = document.getElementById("mbp-license-modal");
        const closeBtn  = document.getElementById("mbp-license-close");
        const keyInp    = document.getElementById("mbp-license-key");
        const actBtn    = document.getElementById("mbp-license-activate");
        const msgEl     = document.getElementById("mbp-license-msg");
        const deactBtn  = document.getElementById("mbp-license-deactivate");

        function openModal(){
            if(!modal) return;
            modal.style.display = "block";
            if(keyInp) keyInp.focus();
            if(msgEl) msgEl.textContent = "";
        }
        function closeModal(){
            if(!modal) return;
            modal.style.display = "none";
        }

        if(openBtn) openBtn.addEventListener("click", openModal);
        if(closeBtn) closeBtn.addEventListener("click", closeModal);
        if(modal) modal.addEventListener("click", (e)=>{ if(e.target === modal) closeModal(); });

        if(actBtn){
            actBtn.addEventListener("click", async ()=>{
                const key = (keyInp && keyInp.value ? keyInp.value : "").trim();
                if(!key){
                    if(msgEl) msgEl.textContent = "لایسنس را وارد کن";
                    return;
                }
                if(msgEl) msgEl.textContent = "در حال بررسی...";
                actBtn.disabled = true;

                try{
                    const data = await post("mbp_activate_license", { license_key: key });
                    if(!data.success){
                        if(msgEl) msgEl.textContent = (data && data.data && data.data.message) ? data.data.message : "ناموفق";
                        actBtn.disabled = false;
                        return;
                    }
                    if(msgEl) msgEl.textContent = (data && data.data && data.data.message) ? data.data.message : "فعال شد ✅";
                    setTimeout(()=> location.reload(), 700);
                }catch(e){
                    if(msgEl) msgEl.textContent = "خطای شبکه";
                    actBtn.disabled = false;
                }
            });
        }

        if(deactBtn){
            deactBtn.addEventListener("click", async ()=>{
                if(!confirm("لایسنس رزرو روی همین سایت غیرفعال شود؟")) return;
                try{
                    await post("mbp_deactivate_license_local", {});
                }catch(e){}
                location.reload();
            });
        }

        // ====== فاکتور ======
        const invOpenBtn  = document.getElementById("mbp-invoice-license-open");
        const invModal    = document.getElementById("mbp-invoice-license-modal");
        const invCloseBtn = document.getElementById("mbp-invoice-license-close");
        const invKeyInp   = document.getElementById("mbp-invoice-license-key");
        const invActBtn   = document.getElementById("mbp-invoice-license-activate");
        const invMsgEl    = document.getElementById("mbp-invoice-license-msg");
        const invDeactBtn = document.getElementById("mbp-invoice-license-deactivate");

        function invOpenModal(){
            if(!invModal) return;
            invModal.style.display = "block";
            if(invKeyInp) invKeyInp.focus();
            if(invMsgEl) invMsgEl.textContent = "";
        }
        function invCloseModal(){
            if(!invModal) return;
            invModal.style.display = "none";
        }

        if(invOpenBtn) invOpenBtn.addEventListener("click", invOpenModal);
        if(invCloseBtn) invCloseBtn.addEventListener("click", invCloseModal);
        if(invModal) invModal.addEventListener("click", (e)=>{ if(e.target === invModal) invCloseModal(); });

        if(invActBtn){
            invActBtn.addEventListener("click", async ()=>{
                const key = (invKeyInp && invKeyInp.value ? invKeyInp.value : "").trim();
                if(!key){
                    if(invMsgEl) invMsgEl.textContent = "لایسنس فاکتور را وارد کن";
                    return;
                }
                if(invMsgEl) invMsgEl.textContent = "در حال بررسی...";
                invActBtn.disabled = true;

                try{
                    const data = await post("mbp_activate_invoice_license", { license_key: key });
                    if(!data.success){
                        if(invMsgEl) invMsgEl.textContent = (data && data.data && data.data.message) ? data.data.message : "ناموفق";
                        invActBtn.disabled = false;
                        return;
                    }
                    if(invMsgEl) invMsgEl.textContent = (data && data.data && data.data.message) ? data.data.message : "لایسنس فاکتور فعال شد ✅";
                    setTimeout(()=> location.reload(), 700);
                }catch(e){
                    if(invMsgEl) invMsgEl.textContent = "خطای شبکه";
                    invActBtn.disabled = false;
                }
            });
        }

        if(invDeactBtn){
            invDeactBtn.addEventListener("click", async ()=>{
                if(!confirm("لایسنس فاکتور روی همین سایت غیرفعال شود؟")) return;
                try{
                    await post("mbp_deactivate_invoice_license_local", {});
                }catch(e){}
                location.reload();
            });
        }

    })();
    </script>';

        echo '</div>';
    }

    public function render_payment_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی ندارید');
        }

        if (!$this->license_is_ok()) {
            echo $this->render_license_required_box('admin');
            return;
        }

        echo '<div class="wrap" style="direction:rtl;">';
        echo '<h1>تنظیمات درگاه پرداخت</h1>';

        echo '
        <div style="max-width:800px;margin-top:20px;">
            <div id="mbp-payment-settings-container">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
                    <div style="font-size:14px;color:#6b7280;margin-bottom:20px;">
                        در حال بارگذاری تنظیمات درگاه پرداخت...
                    </div>
                </div>
            </div>
        </div>
        ';

        // JavaScript برای بارگذاری تنظیمات
        echo '
        <script>
        jQuery(function($){
            function loadPaymentSettings(){
                $.post(ajaxurl, {
                    action: "mbp_get_payment_settings",
                    nonce: "' . wp_create_nonce('mbp_admin_action_nonce') . '"
                }, function(response){
                    if(response.success){
                        $("#mbp-payment-settings-container").html(response.data.html);
                    }
                });
            }
            
            loadPaymentSettings();
        });
        </script>
        ';
        echo '</div>';
    }

    public function invoice_license_is_ok()
    {
        return class_exists('MBP_Invoice_License') && MBP_Invoice_License::is_valid();
    }
    //کد فاکتور
    public function render_invoices_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی ندارید');
        }

        // ✅ اینجا چک لایسنس فاکتور
        if (!MBP_License::invoice_is_valid()) {
            echo $this->render_license_required_box('admin');
            return;
        }

        echo '<div class="wrap" style="direction:rtl;">';
        echo '<h1>مدیریت فاکتورها</h1>';

        // ادامه کدهای خودت...
    }

    public function admin_approve_booking(): void
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(array('message' => 'دسترسی ندارید'));

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id)
            wp_send_json_error(array('message' => 'شناسه نامعتبر است'));

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';

        $ok = $wpdb->update(
            $table,
            array('status' => 'approved'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($ok === false)
            wp_send_json_error(array('message' => 'خطا در تایید'));

        // ارسال پیامک تأیید
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if ($appointment && $appointment->customer_phone) {
            $sms = new MBP_SMS_Manager();
            $sms->send_booking_confirmation($appointment->customer_phone, array(
                'name' => $appointment->customer_name,
                'date' => $this->fa_date_from_timestamp(strtotime($appointment->time), 'Y/m/d', true),
                'time' => date('H:i', strtotime($appointment->time)),
                'service' => $this->get_service_name($appointment->service_id),
                'tracking_code' => $appointment->tracking_code
            ));
        }

        wp_send_json_success(array('message' => 'تایید شد', 'id' => $id));
    }

    public function admin_delete_booking()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(array('message' => 'دسترسی ندارید'));

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id)
            wp_send_json_error(array('message' => 'شناسه نامعتبر است'));

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';

        $ok = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($ok === false)
            wp_send_json_error(array('message' => 'حذف انجام نشد'));

        wp_send_json_success(array('message' => 'حذف شد', 'id' => $id));
    }

    public function admin_cancel_booking()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(array('message' => 'دسترسی ندارید'));

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id)
            wp_send_json_error(array('message' => 'شناسه نامعتبر است'));

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';

        $ok = $wpdb->update(
            $table,
            array('status' => 'cancelled'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($ok === false)
            wp_send_json_error(array('message' => 'خطا در لغو'));

        wp_send_json_success(array('message' => 'لغو شد', 'id' => $id));
    }


    // =========================
    // SERVICES AJAX
    // =========================
    // در کلاس MBP_Core، متد ajax_get_services رو آپدیت می‌کنیم:

    public function ajax_get_services()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';
        $services = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");

        // دریافت تنظیمات SMS
        $sms_settings = MBP_SMS_Manager::get_settings();

        // دریافت تنظیمات پرداخت
        $payment_settings = array(
            'default_gateway' => get_option('mbp_default_gateway', 'zarinpal'),
            'zarinpal_merchant_id' => get_option('mbp_zarinpal_merchant_id', ''),
            'idpay_api_key' => get_option('mbp_idpay_api_key', ''),
            'idpay_sandbox' => get_option('mbp_idpay_sandbox', 0),
            'nextpay_api_key' => get_option('mbp_nextpay_api_key', '')
        );

        ob_start();
        ?>
        <div style="max-width:1200px;">

            <!-- تب‌های تنظیمات -->
            <div style="margin-bottom:20px;border-bottom:1.5px solid #424242ff;">
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="button" class="button mbp-settings-tab active" data-tab="services">
                        مدیریت خدمات
                    </button>
                    <button type="button" class="button mbp-settings-tab" data-tab="sms">
                        تنظیمات پیامک
                    </button>
                    <button type="button" class="button mbp-settings-tab" data-tab="payment">
                        تنظیمات درگاه پرداخت
                    </button>
                    <button type="button" class="button mbp-settings-tab" data-tab="general">
                        تنظیمات عمومی
                    </button>
                </div>
            </div>

            <!-- محتوای تب‌ها -->
            <div id="mbp-services-tab-content">

                <!-- تب خدمات -->
                <div class="mbp-tab-pane active" id="tab-services">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h3 style="margin:0;">مدیریت خدمات</h3>
                        <button type="button" id="mbp-add-service" class="button button-primary" style="font-weight:800;">
                            + افزودن خدمت جدید
                        </button>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>نام خدمت</th>
                                <th>توضیحات</th>
                                <th width="100">مدت زمان (دقیقه)</th>
                                <th width="120">قیمت (تومان)</th>
                                <th width="100">وضعیت</th>
                                <th width="150">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($services)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:20px;">
                                        هیچ خدمتی تعریف نشده است.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($services as $service): ?>
                                    <tr data-service-id="<?php echo esc_attr($service->id); ?>">
                                        <td><?php echo esc_html($service->id); ?></td>
                                        <td>
                                            <strong><?php echo esc_html($service->name); ?></strong>
                                        </td>
                                        <td><?php echo esc_html($service->description); ?></td>
                                        <td><?php echo esc_html($service->duration); ?></td>
                                        <td>
                                            <?php echo number_format($service->price); ?>
                                        </td>
                                        <td>
                                            <span class="service-status <?php echo $service->is_active ? 'active' : 'inactive'; ?>">
                                                <?php echo $service->is_active ? 'فعال' : 'غیرفعال'; ?>
                                            </span>
                                        </td>
                                        <td style="width: 300px; display: flex; gap: 10px;">
                                            <button type="button" class="button button-small button-primary mbp-edit-service"
                                                data-id="<?php echo esc_attr($service->id); ?>">
                                                ویرایش
                                            </button>
                                            <button type="button" class="button button-small button-primary mbp-toggle-service"
                                                data-id="<?php echo esc_attr($service->id); ?>"
                                                data-status="<?php echo $service->is_active; ?>">
                                                <?php echo $service->is_active ? 'غیرفعال' : 'فعال'; ?>
                                            </button>
                                            <button type="button"
                                                class="button button-small button-primary button-link-delete mbp-delete-service"
                                                data-id="<?php echo esc_attr($service->id); ?>">
                                                حذف
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- تب تنظیمات پیامک -->
                <div class="mbp-tab-pane" id="tab-sms">
                    <div style="max-width:800px;">
                        <h3 style="margin-top:0;">مدیریت اطلاع‌رسانی و مخاطبین</h3>

                        <div
                            style="background:rgba(255, 255, 255, .06); border:1px solid rgba(75, 75, 75, 0.8); border-radius:10px; padding:20px; margin-bottom:20px;">
                            <h4 style="margin-top:0; color:#fff;">دفترچه تلفن (مشتریان اخیر)</h4>
                            <div style="max-height: 250px; overflow-y: auto;">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>نام مشتری</th>
                                            <th>شماره موبایل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        global $wpdb;
                                        $table_name = $wpdb->prefix . 'mbp_phonebook';

                                        // چک کردن اینکه آیا جدول اصلاً وجود دارد؟
                                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

                                        if (!$table_exists) {
                                            echo '<tr><td colspan="2" style="color:red;">خطا: جدول mbp_phonebook در دیتابیس یافت نشد!</td></tr>';
                                        } else {
                                            $results = $wpdb->get_results("SELECT name, phone FROM $table_name ORDER BY id DESC");

                                            if (!empty($results)) {
                                                foreach ($results as $row): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($row->name); ?></td>
                                                        <td><?php echo esc_html($row->phone); ?></td>
                                                    </tr>
                                                <?php endforeach;
                                            } else {
                                                echo '<tr><td colspan="2" style="text-align:center;">دفترچه تلفن خالی است. (هنوز شماره‌ای ثبت نشده)</td></tr>';
                                            }

                                        }

                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            style="background:rgba(255, 255, 255, .06); border:1px solid rgba(75, 75, 75, 0.8); border-radius:10px; padding:20px;">
                            <h4 style="margin-top:0; color:#fff;">ارسال پیامک گروهی</h4>
                            <div style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px;">متن پیامک برای همه مخاطبین:</label>
                                <textarea id="mass-sms-text" rows="5"
                                    style="width:100%; background:rgba(0,0,0,0.2); color:#fff; border:1px solid #555; border-radius:5px; padding:10px;"
                                    placeholder="پیام خود را اینجا بنویسید..."></textarea>
                            </div>
                            <button type="button" id="mbp-send-mass-sms" class="button button-primary"
                                style="background:#2271b1 !important;">
                                <span class="dashicons dashicons-paper-plane" style="vertical-align: middle;"></span>
                                ارسال به تمام شماره‌ها
                            </button>
                            <span id="mass-sms-status" style="margin-right:15px; font-size:12px;"></span>
                        </div>
                    </div>
                </div>
                <!-- تب تنظیمات درگاه پرداخت -->
                <div class="mbp-tab-pane" id="tab-payment">
                    <div style="max-width:600px;">
                        <h3 style="margin-top:0;">تنظیمات درگاه پرداخت</h3>

                        <form id="mbp-payment-settings-form">

                            <div
                                style="background:rgba(255, 255, 255, .06);border:1px solid #5f5f5fad;border-radius:10px;padding:20px">
                                <h4 style="margin-top:0;">تنظیمات زرین‌پال</h4>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:block;margin-bottom:5px;">مرچنت کد (Merchant
                                        ID)</label>
                                    <input type="text" id="mbp-zarinpal-merchant-id" name="zarinpal_merchant_id"
                                        value="<?php echo esc_attr($payment_settings['zarinpal_merchant_id']); ?>"
                                        class="regular-text" style="width:100%;"
                                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                    <p style="font-size:12px;color:#6b7280;margin-top:5px;">
                                        Merchant ID را از پنل زرین‌پال دریافت کنید
                                    </p>
                                </div>
                            </div>

                            <div
                                style="background:rgba(255, 255, 255, .06);border:1px solid #5f5f5fad;border-radius:10px;padding:20px">
                                <h4 style="margin-top:0;">تنظیمات آیدی پی</h4>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:block;margin-bottom:5px;">API Key</label>
                                    <input type="text" id="mbp-idpay-api-key" name="idpay_api_key"
                                        value="<?php echo esc_attr($payment_settings['idpay_api_key']); ?>" class="regular-text"
                                        style="width:100%;" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </div>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                                        <input type="checkbox" id="mbp-idpay-sandbox" name="idpay_sandbox" value="1" <?php checked($payment_settings['idpay_sandbox'], 1); ?>>
                                        حالت تست (Sandbox)
                                    </label>
                                    <p style="font-size:12px;color:#6b7280;margin-top:5px;margin-right:24px;">
                                        در حالت تست، پرداخت‌ها واقعی نیستند
                                    </p>
                                </div>
                            </div>

                            <div
                                style="background:rgba(255, 255, 255, .06);border:1px solid #5f5f5fad;border-radius:10px;padding:20px">
                                <h4 style="margin-top:0;">تنظیمات نکست پی</h4>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:block;margin-bottom:5px;">API Key</label>
                                    <input type="text" id="mbp-nextpay-api-key" name="nextpay_api_key"
                                        value="<?php echo esc_attr($payment_settings['nextpay_api_key']); ?>"
                                        class="regular-text" style="width:100%;">
                                </div>
                            </div>

                            <div style="margin-top:20px;">
                                <button type="submit" class="button button-primary" id="mbp-payment-save">
                                    ذخیره تنظیمات درگاه
                                </button>
                                <span id="mbp-payment-message" style="margin-right:15px;font-size:12px;"></span>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- تب تنظیمات عمومی -->
                <div class="mbp-tab-pane" id="tab-general">
                    <div style="max-width:600px;">
                        <h3 style="margin-top:0;">تنظیمات عمومی</h3>

                        <form id="mbp-general-settings-form">
                            <div
                                style="background:rgba(255, 255, 255, .06);border:1px solid #5f5f5fad;border-radius:10px;padding:20px">
                                <h4 style="margin-top:0;">تنظیمات رزرو</h4>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:block;margin-bottom:5px;">زمان تأیید خودکار
                                        (ساعت)</label>
                                    <input type="number" id="mbp-auto-approve-hours" name="auto_approve_hours"
                                        value="<?php echo esc_attr(get_option('mbp_auto_approve_hours', 0)); ?>"
                                        class="regular-text" style="width:100px;">
                                    <p style="font-size:12px;color:#6b7280;margin-top:5px;">
                                        اگر عددی بیشتر از 0 وارد کنید، رزروها بعد از این مدت به صورت خودکار تأیید می‌شوند (0 =
                                        غیرفعال)
                                    </p>
                                </div>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:block;margin-bottom:5px;">زمان لغو خودکار
                                        (ساعت)</label>
                                    <input type="number" id="mbp-auto-cancel-hours" name="auto_cancel_hours"
                                        value="<?php echo esc_attr(get_option('mbp_auto_cancel_hours', 0)); ?>"
                                        class="regular-text" style="width:100px;">
                                    <p style="font-size:12px;color:#6b7280;margin-top:5px;">
                                        رزروهای پرداخت نشده بعد از این مدت لغو می‌شوند (0 = غیرفعال)
                                    </p>
                                </div>
                            </div>

                            <div
                                style="background:rgba(255, 255, 255, .06);border:1px solid #5f5f5fad;border-radius:10px;padding:20px">
                                <h4 style="margin-top:0;">تنظیمات نمایش</h4>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:block;margin-bottom:5px;">تعداد روزهای قابل رزرو در
                                        آینده</label>
                                    <input type="number" id="mbp-future-days-limit" name="future_days_limit"
                                        value="<?php echo esc_attr(get_option('mbp_future_days_limit', 30)); ?>"
                                        class="regular-text" style="width:100px;" min="1" max="365">
                                    <p style="font-size:12px;color:#6b7280;margin-top:5px;">
                                        کاربران فقط می‌توانند تا این تعداد روز در آینده رزرو کنند
                                    </p>
                                </div>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                                        <input type="checkbox" id="mbp-show-calendar" name="show_calendar" value="1" <?php checked(get_option('mbp_show_calendar', 1), 1); ?>>
                                        نمایش تقویم در فرم رزرو
                                    </label>
                                </div>

                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                                        <input type="checkbox" id="mbp-require-login" name="require_login" value="1" <?php checked(get_option('mbp_require_login', 0), 1); ?>>
                                        نیاز به ورود برای رزرو
                                    </label>
                                </div>
                            </div>

                            <div style="margin-top:20px;">
                                <button type="submit" class="button button-primary" id="mbp-general-save">
                                    ذخیره تنظیمات عمومی
                                </button>
                                <span id="mbp-general-message" style="margin-right:15px;font-size:12px;"></span>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- Modal برای افزودن/ویرایش خدمت -->
            <div id="mbp-service-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;">
                <div
                    style="width:min(520px,92vw);margin:10vh auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,.25);">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:20px;">
                        <div style="font-weight:900;font-size:16px;" id="mbp-service-modal-title">افزودن خدمت جدید</div>
                        <button type="button" class="button" id="mbp-service-modal-close">بستن</button>
                    </div>

                    <form id="mbp-service-form">
                        <input type="hidden" id="mbp-service-id" name="id" value="">

                        <div style="margin-bottom:15px;">
                            <label style="font-weight:800;display:block;margin-bottom:5px;">نام خدمت *</label>
                            <input type="text" id="mbp-service-name" name="name" class="regular-text" style="width:100%;"
                                required>
                        </div>

                        <div style="margin-bottom:15px;">
                            <label style="font-weight:800;display:block;margin-bottom:5px;">توضیحات</label>
                            <textarea id="mbp-service-description" name="description" class="regular-text"
                                style="width:100%;height:80px;"></textarea>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
                            <div>
                                <label style="font-weight:800;display:block;margin-bottom:5px;">مدت زمان (دقیقه)</label>
                                <input type="number" id="mbp-service-duration" name="duration" class="regular-text"
                                    style="width:100%;" value="30" min="5" step="5">
                            </div>
                            <div>
                                <label style="font-weight:800;display:block;margin-bottom:5px;">قیمت (تومان)</label>
                                <input type="number" id="mbp-service-price" name="price" class="regular-text"
                                    style="width:100%;" value="0" min="0" step="1000">
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;align-items:center;margin-top:20px;">
                            <button type="submit" class="button button-primary" id="mbp-service-submit">ذخیره</button>
                            <button type="button" class="button" id="mbp-service-cancel">انصراف</button>
                            <span id="mbp-service-message" style="font-size:12px;color:#6b7280;"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <style>
            .mbp-tab-pane {
                display: none;
            }

            .mbp-tab-pane.active {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .button-primary {
                padding: 10px;
                border-radius: 10px;
                background: rgba(255, 255, 255, .06);
                border: 1px solid rgba(255, 255, 255, .12);
                color: #ccccccff;
                font-family: inherit;
                cursor: pointer;
                transition: all 0.5s;
            }

            .button-primary:hover {
                background: #2271b1;
                color: white;
                border-color: #2271b1;
            }

            .mbp-settings-tab {
                padding: 10px;
                border-radius: 10px 10px 0 0;
                background: rgba(255, 255, 255, .06);
                border: 1px solid rgba(255, 255, 255, .12);
                color: #ccccccff;
                font-family: inherit;
                cursor: pointer;
            }

            .mbp-settings-tab.active {
                background: #2271b1;
                color: white;
                border-color: #2271b1;
            }

            .service-status {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 700;
            }

            .service-status.active {
                background: rgba(34, 197, 94, .2);
                color: #22c55e;
            }

            .service-status.inactive {
                background: rgba(239, 68, 68, .2);
                color: #ef4444;
            }

            .regular-text {
                padding: 10px;
                width: 300px;
                border-radius: 10px;
                background: rgba(0, 0, 0, 0.2);
                color: #fff;
                border: 1px solid #555;
            }

            .regular-text:focus {
                outline: 1px solid #0084ffff;
            }
        </style>

        <script>
            jQuery(function ($) {
                const nonce = '<?php echo wp_create_nonce("mbp_admin_action_nonce"); ?>';

                // مدیریت تب‌ها
                $('.mbp-settings-tab').on('click', function () {
                    const tab = $(this).data('tab');

                    // آپدیت دکمه‌های تب
                    $('.mbp-settings-tab').removeClass('active');
                    $(this).addClass('active');

                    // آپدیت محتوا
                    $('.mbp-tab-pane').removeClass('active');
                    $('#tab-' + tab).addClass('active');
                });

                // مدیریت خدمات
                // باز کردن مدال برای افزودن خدمت
                $('#mbp-add-service').on('click', function () {
                    $('#mbp-service-modal-title').text('افزودن خدمت جدید');
                    $('#mbp-service-form')[0].reset();
                    $('#mbp-service-id').val('');
                    $('#mbp-service-modal').show();
                });

                // ویرایش خدمت
                $(document).on('click', '.mbp-edit-service', function () {
                    const serviceId = $(this).data('id');
                    const row = $(this).closest('tr');

                    $('#mbp-service-modal-title').text('ویرایش خدمت');
                    $('#mbp-service-id').val(serviceId);
                    $('#mbp-service-name').val(row.find('td:nth-child(2) strong').text());
                    $('#mbp-service-description').val(row.find('td:nth-child(3)').text());
                    $('#mbp-service-duration').val(row.find('td:nth-child(4)').text());
                    $('#mbp-service-price').val(row.find('td:nth-child(5)').text().replace(/,/g, ''));

                    $('#mbp-service-modal').show();
                });

                // بستن مدال
                $('#mbp-service-modal-close, #mbp-service-cancel').on('click', function () {
                    $('#mbp-service-modal').hide();
                });

                // ارسال پیامک گروهی
                $(document).on('click', '#mbp-send-mass-sms', function () {
                    const message = $('#mass-sms-text').val();
                    const btn = $(this);
                    const status = $('#mass-sms-status');

                    if (!message) {
                        alert('لطفاً متن پیام را وارد کنید');
                        return;
                    }

                    if (!confirm('آیا مطمئن هستید که می‌خواهید این پیام را برای تمام مخاطبین لیست ارسال کنید؟')) {
                        return;
                    }

                    btn.prop('disabled', true).text('در حال ارسال...');

                    $.post(ajaxurl, {
                        action: 'mbp_send_mass_sms',
                        message: message,
                        nonce: nonce
                    }, function (response) {
                        if (response.success) {
                            status.text('ارسال با موفقیت انجام شد').css('color', '#10b981');
                            $('#mass-sms-text').val('');
                        } else {
                            status.text('خطا در ارسال').css('color', '#ef4444');
                        }
                    }).always(function () {
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-paper-plane"></span> ارسال به تمام شماره‌ها');
                    });
                });

                // ارسال فرم خدمت
                $('#mbp-service-form').on('submit', function (e) {
                    e.preventDefault();

                    const submitBtn = $('#mbp-service-submit');
                    const originalText = submitBtn.text();
                    const messageEl = $('#mbp-service-message');

                    submitBtn.prop('disabled', true);
                    submitBtn.text('در حال ذخیره...');
                    messageEl.text('');

                    const formData = $(this).serialize() + '&action=mbp_save_service&nonce=' + nonce;

                    $.post(ajaxurl, formData, function (response) {
                        if (response.success) {
                            messageEl.text('خدمت با موفقیت ذخیره شد').css('color', '#10b981');
                            setTimeout(function () {
                                $('#mbp-service-modal').hide();
                                // رفرش تب خدمات
                                $('.mbp-settings-tab[data-tab="services"]').click();
                            }, 1000);
                        } else {
                            messageEl.text(response.data.message || 'خطا در ذخیره').css('color', '#ef4444');
                        }
                    }).fail(function () {
                        messageEl.text('خطا در ارتباط با سرور').css('color', '#ef4444');
                    }).always(function () {
                        submitBtn.prop('disabled', false);
                        submitBtn.text(originalText);
                    });
                });

                // فعال/غیرفعال کردن خدمت
                $(document).on('click', '.mbp-toggle-service', function () {
                    const serviceId = $(this).data('id');
                    const currentStatus = $(this).data('status');
                    const button = $(this);

                    $.post(ajaxurl, {
                        action: 'mbp_toggle_service',
                        id: serviceId,
                        nonce: nonce
                    }, function (response) {
                        if (response.success) {
                            const newStatus = response.data.new_status;
                            button.data('status', newStatus);
                            button.text(newStatus ? 'غیرفعال' : 'فعال');

                            const statusSpan = button.closest('tr').find('.service-status');
                            statusSpan.removeClass('active inactive').addClass(newStatus ? 'active' : 'inactive');
                            statusSpan.text(newStatus ? 'فعال' : 'غیرفعال');
                        }
                    });
                });

                // حذف خدمت
                $(document).on('click', '.mbp-delete-service', function () {
                    if (!confirm('آیا از حذف این خدمت مطمئن هستید؟')) return;

                    const serviceId = $(this).data('id');
                    const button = $(this);

                    $.post(ajaxurl, {
                        action: 'mbp_delete_service',
                        id: serviceId,
                        nonce: nonce
                    }, function (response) {
                        if (response.success) {
                            button.closest('tr').fadeOut(300, function () {
                                $(this).remove();
                            });
                        }
                    });
                });

                // ذخیره تنظیمات پیامک

                // تست پیامک


                // ذخیره تنظیمات درگاه پرداخت
                $('#mbp-payment-settings-form').on('submit', function (e) {
                    e.preventDefault();

                    const submitBtn = $('#mbp-payment-save');
                    const originalText = submitBtn.text();
                    const messageEl = $('#mbp-payment-message');

                    submitBtn.prop('disabled', true);
                    submitBtn.text('در حال ذخیره...');
                    messageEl.text('').css('color', '');

                    const formData = $(this).serialize() + '&action=mbp_save_payment_settings&nonce=' + nonce;

                    $.post(ajaxurl, formData, function (response) {
                        if (response.success) {
                            messageEl.text('تنظیمات با موفقیت ذخیره شد').css('color', '#10b981');
                        } else {
                            messageEl.text(response.data.message || 'خطا در ذخیره').css('color', '#ef4444');
                        }
                    }).fail(function () {
                        messageEl.text('خطا در ارتباط با سرور').css('color', '#ef4444');
                    }).always(function () {
                        submitBtn.prop('disabled', false);
                        submitBtn.text(originalText);
                    });
                });

                // ذخیره تنظیمات عمومی
                $('#mbp-general-settings-form').on('submit', function (e) {
                    e.preventDefault();

                    const submitBtn = $('#mbp-general-save');
                    const originalText = submitBtn.text();
                    const messageEl = $('#mbp-general-message');

                    submitBtn.prop('disabled', true);
                    submitBtn.text('در حال ذخیره...');
                    messageEl.text('').css('color', '');

                    const formData = $(this).serialize() + '&action=mbp_save_general_settings&nonce=' + nonce;

                    $.post(ajaxurl, formData, function (response) {
                        if (response.success) {
                            messageEl.text('تنظیمات با موفقیت ذخیره شد').css('color', '#10b981');
                        } else {
                            messageEl.text(response.data.message || 'خطا در ذخیره').css('color', '#ef4444');
                        }
                    }).fail(function () {
                        messageEl.text('خطا در ارتباط با سرور').css('color', '#ef4444');
                    }).always(function () {
                        submitBtn.prop('disabled', false);
                        submitBtn.text(originalText);
                    });
                });
            });
        </script>
        <?php

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
    // در متد enqueue_admin_assets استایل اضافه کنید:

    public function ajax_send_mass_sms()
    {
        check_ajax_referer('mbp_admin_action_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'عدم دسترسی'));
        }

        $message = sanitize_textarea_field($_POST['message']);

        global $wpdb;
        // استخراج شماره‌های منحصر به فرد از جدول رزروها
        $phones = $wpdb->get_col("SELECT DISTINCT customer_phone FROM {$wpdb->prefix}mbp_bookings");

        if (empty($phones)) {
            wp_send_json_error(array('message' => 'شماره‌ای برای ارسال یافت نشد'));
        }

        // استفاده از کلاس SMS Manager که در پروژه داری برای ارسال
        $sms_manager = new MBP_SMS_Manager(); // یا هر متدی که برای ارسال داری

        foreach ($phones as $phone) {
            // اینجا متد ارسال پیامک خودت را صدا بزن
            // $sms_manager->send_direct_sms($phone, $message);
        }

        wp_send_json_success();
    }
    // حتما این اکشن را در Constructor کلاس ثبت کن:
// add_action('wp_ajax_mbp_send_mass_sms', array($this, 'ajax_send_mass_sms'));

    public function ajax_save_service()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 30;
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;

        if (empty($name)) {
            wp_send_json_error(array('message' => 'نام خدمت الزامی است'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';

        $data = array(
            'name' => $name,
            'description' => $description,
            'duration' => $duration,
            'price' => $price
        );

        $format = array('%s', '%s', '%d', '%f');

        if ($id > 0) {
            // آپدیت
            $result = $wpdb->update($table, $data, array('id' => $id), $format, array('%d'));
            $message = 'خدمت با موفقیت به‌روزرسانی شد';
        } else {
            // درج جدید
            $result = $wpdb->insert($table, $data, $format);
            $message = 'خدمت جدید با موفقیت ایجاد شد';
        }

        if ($result === false) {
            wp_send_json_error(array('message' => 'خطا در ذخیره خدمت'));
        }

        wp_send_json_success(array('message' => $message));
    }

    public function ajax_delete_service()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id)
            wp_send_json_error(array('message' => 'شناسه نامعتبر است'));

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';

        // بررسی اینکه خدمت در رزروها استفاده نشده باشد
        $used = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mbp_appointments WHERE service_id = %d",
            $id
        ));

        if ($used > 0) {
            wp_send_json_error(array('message' => 'این خدمت در رزروها استفاده شده و قابل حذف نیست'));
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result === false) {
            wp_send_json_error(array('message' => 'خطا در حذف خدمت'));
        }

        wp_send_json_success(array('message' => 'خدمت با موفقیت حذف شد'));
    }

    public function ajax_toggle_service()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id)
            wp_send_json_error(array('message' => 'شناسه نامعتبر است'));

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';

        // دریافت وضعیت فعلی
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM $table WHERE id = %d",
            $id
        ));

        $new_status = $current_status ? 0 : 1;

        $result = $wpdb->update(
            $table,
            array('is_active' => $new_status),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'خطا در تغییر وضعیت'));
        }

        wp_send_json_success(array(
            'message' => 'وضعیت خدمت تغییر کرد',
            'new_status' => $new_status
        ));
    }

    // =========================
    // SMS AJAX
    // =========================
    public function ajax_get_sms_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';
        $settings = $wpdb->get_row("SELECT * FROM $table LIMIT 1");

        if (!$settings) {
            $settings = (object) array(
                'gateway' => 'kavenegar',
                'api_key' => '',
                'sender_number' => '',
                'enable_booking_sms' => 1,
                'enable_payment_sms' => 1,
                'enable_reminder_sms' => 0,
                'reminder_hours' => 24
            );
        }

        ob_start();
        ?>
        <div style="max-width:600px; background:rgba(255, 255, 255, .06);">
            <form id="mbp-sms-settings-form">
                <div
                    style="background:rgba(255, 255, 255, .06);border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;">تنظیمات کلی</h3>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:block;margin-bottom:5px;">درگاه پیامک</label>
                        <select id="mbp-sms-gateway" name="gateway" class="regular-text" style="width:100%;">
                            <option value="kavenegar" <?php selected($settings->gateway, 'kavenegar'); ?>>کاوه‌نگار</option>
                            <option value="ghasedak" <?php selected($settings->gateway, 'ghasedak'); ?>>قاصدک</option>
                            <option value="melipayamak" <?php selected($settings->gateway, 'melipayamak'); ?>>ملی پیامک</option>
                        </select>
                    </div>

                    <div style="margin-bottom:15px;">
                        <div class="mbp-card">
                            <h3>افزودن به دفترچه تلفن</h3>
                            <form id="add-to-phonebook">
                                <input type="text" name="contact_name" placeholder="نام کاربر" required>
                                <input type="text" name="contact_phone" placeholder="شماره موبایل" required>
                                <button type="submit" class="button button-primary">ذخیره در دفترچه</button>
                            </form>
                        </div>
                        <div class="mbp-card" style="margin-top: 20px;">
                            <h3>ارسال پیامک به همه مخاطبین</h3>
                            <textarea id="mass-sms-content" rows="5" style="width: 100%;"
                                placeholder="متن پیامک خود را اینجا بنویسید..."></textarea>
                            <button id="send-mass-sms" class="button button-primary"
                                style="margin-top: 10px; background: #27ae60;">ارسال برای همه</button>
                        </div>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:block;margin-bottom:5px;">شماره فرستنده</label>
                        <input type="text" id="mbp-sms-sender" name="sender_number"
                            value="<?php echo esc_attr($settings->sender_number); ?>" class="regular-text" style="width:100%;"
                            placeholder="مانند: 10004346">
                        <p style="font-size:12px;color:#6b7280;margin-top:5px;">
                            شماره فرستنده اختصاصی شما از سرویس پیامک
                        </p>
                    </div>
                </div>

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;">تنظیمات ارسال</h3>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                            <input type="checkbox" id="mbp-enable-booking-sms" name="enable_booking_sms" value="1" <?php checked($settings->enable_booking_sms, 1); ?>>
                            ارسال پیامک تأیید رزرو
                        </label>
                        <p style="font-size:12px;color:#6b7280;margin-top:5px;margin-right:24px;">
                            پس از ثبت رزرو، پیامک تأیید برای کاربر ارسال می‌شود
                        </p>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                            <input type="checkbox" id="mbp-enable-payment-sms" name="enable_payment_sms" value="1" <?php checked($settings->enable_payment_sms, 1); ?>>
                            ارسال پیامک تأیید پرداخت
                        </label>
                        <p style="font-size:12px;color:#6b7280;margin-top:5px;margin-right:24px;">
                            پس از پرداخت موفق، پیامک تأیید برای کاربر ارسال می‌شود
                        </p>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                            <input type="checkbox" id="mbp-enable-reminder-sms" name="enable_reminder_sms" value="1" <?php checked($settings->enable_reminder_sms, 1); ?>>
                            ارسال پیامک یادآوری
                        </label>
                        <div style="margin-right:24px;">
                            <div style="margin-top:10px;">
                                <label style="font-weight:600;display:block;margin-bottom:5px;">ساعت‌های قبل از نوبت برای
                                    یادآوری</label>
                                <input type="number" id="mbp-reminder-hours" name="reminder_hours"
                                    value="<?php echo esc_attr($settings->reminder_hours); ?>" class="small-text" min="1"
                                    max="168" style="width:80px;">
                                <span style="font-size:12px;color:#6b7280;">ساعت</span>
                            </div>
                            <p style="font-size:12px;color:#6b7280;margin-top:5px;">
                                قبل از زمان نوبت، پیامک یادآوری برای کاربر ارسال می‌شود
                            </p>
                        </div>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="button button-primary" id="mbp-sms-save">
                        ذخیره تنظیمات
                    </button>

                    <button type="button" class="button" id="mbp-test-sms" style="margin-right:10px;">
                        تست پیامک
                    </button>

                    <span id="mbp-sms-message" style="margin-right:15px;font-size:12px;"></span>
                </div>
            </form>
        </div>

        <script>
            jQuery(function ($) {
                const nonce = '<?php echo wp_create_nonce("mbp_admin_action_nonce"); ?>';

                // ذخیره تنظیمات
                $('#mbp-sms-settings-form').on('submit', function (e) {
                    e.preventDefault();

                    const submitBtn = $('#mbp-sms-save');
                    const originalText = submitBtn.text();
                    const messageEl = $('#mbp-sms-message');

                    submitBtn.prop('disabled', true);
                    submitBtn.text('در حال ذخیره...');
                    messageEl.text('').css('color', '');

                    const formData = $(this).serialize() + '&action=mbp_save_sms_settings&nonce=' + nonce;

                    $.post(ajaxurl, formData, function (response) {
                        if (response.success) {
                            messageEl.text('تنظیمات با موفقیت ذخیره شد').css('color', '#10b981');
                        } else {
                            messageEl.text(response.data.message || 'خطا در ذخیره').css('color', '#ef4444');
                        }
                    }).fail(function () {
                        messageEl.text('خطا در ارتباط با سرور').css('color', '#ef4444');
                    }).always(function () {
                        submitBtn.prop('disabled', false);
                        submitBtn.text(originalText);
                    });
                });

                // تست پیامک
                $('#mbp-test-sms').on('click', function () {
                    const phone = prompt('شماره موبایل برای تست پیامک را وارد کنید:');
                    if (!phone || !/^09[0-9]{9}$/.test(phone)) {
                        alert('شماره موبایل معتبر وارد کنید');
                        return;
                    }

                    const button = $(this);
                    const originalText = button.text();

                    button.prop('disabled', true);
                    button.text('در حال ارسال...');
                    $.post(ajaxurl, {
                        action: 'mbp_test_sms',
                        phone: phone,
                        nonce: nonce
                    }, function (response) {
                        if (response.success) {
                            alert('پیامک تست با موفقیت ارسال شد');
                        } else {
                            alert(response.data.message || 'خطا در ارسال پیامک');
                        }
                    }).fail(function () {
                        alert('خطا در ارتباط با سرور');
                    }).always(function () {
                        button.prop('disabled', false);
                        button.text(originalText);
                    });
                });
            });
        </script>
        <?php

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    public function ajax_save_sms_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $gateway = isset($_POST['gateway']) ? sanitize_text_field($_POST['gateway']) : 'kavenegar';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $sender_number = isset($_POST['sender_number']) ? sanitize_text_field($_POST['sender_number']) : '';
        $enable_booking_sms = isset($_POST['enable_booking_sms']) ? 1 : 0;
        $enable_payment_sms = isset($_POST['enable_payment_sms']) ? 1 : 0;
        $enable_reminder_sms = isset($_POST['enable_reminder_sms']) ? 1 : 0;
        $reminder_hours = isset($_POST['reminder_hours']) ? intval($_POST['reminder_hours']) : 24;

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';

        $data = array(
            'gateway' => $gateway,
            'api_key' => $api_key,
            'sender_number' => $sender_number,
            'enable_booking_sms' => $enable_booking_sms,
            'enable_payment_sms' => $enable_payment_sms,
            'enable_reminder_sms' => $enable_reminder_sms,
            'reminder_hours' => $reminder_hours
        );

        $format = array('%s', '%s', '%s', '%d', '%d', '%d', '%d');

        $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        if ($exists) {
            $result = $wpdb->update($table, $data, array('id' => 1), $format, array('%d'));
        } else {
            $result = $wpdb->insert($table, $data, $format);
        }

        if ($result === false) {
            wp_send_json_error(array('message' => 'خطا در ذخیره تنظیمات'));
        }

        wp_send_json_success(array('message' => 'تنظیمات پیامک با موفقیت ذخیره شد'));
    }

    public function ajax_test_sms()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

        if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
            wp_send_json_error(array('message' => 'شماره موبایل معتبر نیست'));
        }

        try {
            $sms = new MBP_SMS_Manager();
            $result = $sms->send($phone, '✅ تست سرویس پیامک افزونه رزرو\nاین پیام برای تست ارسال شده است.', 'test');

            if ($result) {
                wp_send_json_success(array('message' => 'پیامک تست با موفقیت ارسال شد'));
            } else {
                wp_send_json_error(array('message' => 'خطا در ارسال پیامک. لطفا تنظیمات را بررسی کنید.'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'خطا: ' . $e->getMessage()));
        }
    }

    // =========================
    // PAYMENT AJAX
    // =========================
    public function ajax_get_payment_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $zarinpal_merchant_id = get_option('mbp_zarinpal_merchant_id', '');
        $idpay_api_key = get_option('mbp_idpay_api_key', '');
        $idpay_sandbox = get_option('mbp_idpay_sandbox', 0);
        $nextpay_api_key = get_option('mbp_nextpay_api_key', '');
        $default_gateway = get_option('mbp_default_gateway', 'zarinpal');

        ob_start();
        ?>
        <div style="max-width:600px;">
            <form id="mbp-payment-settings-form">
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;">درگاه پیش‌فرض</h3>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:block;margin-bottom:5px;">انتخاب درگاه پرداخت</label>
                        <select id="mbp-default-gateway" name="default_gateway" class="regular-text" style="width:100%;">
                            <option value="zarinpal" <?php selected($default_gateway, 'zarinpal'); ?>>زرین‌پال</option>
                            <option value="idpay" <?php selected($default_gateway, 'idpay'); ?>>آیدی پی</option>
                            <option value="nextpay" <?php selected($default_gateway, 'nextpay'); ?>>نکست پی</option>
                        </select>
                    </div>
                </div>

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;">تنظیمات زرین‌پال</h3>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:block;margin-bottom:5px;">مرچنت کد (Merchant ID)</label>
                        <input type="text" id="mbp-zarinpal-merchant-id" name="zarinpal_merchant_id"
                            value="<?php echo esc_attr($zarinpal_merchant_id); ?>" class="regular-text" style="width:100%;"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        <p style="font-size:12px;color:#6b7280;margin-top:5px;">
                            Merchant ID را از پنل زرین‌پال دریافت کنید
                        </p>
                    </div>
                </div>

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;">تنظیمات آیدی پی</h3>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:block;margin-bottom:5px;">API Key</label>
                        <input type="text" id="mbp-idpay-api-key" name="idpay_api_key"
                            value="<?php echo esc_attr($idpay_api_key); ?>" class="regular-text" style="width:100%;"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                            <input type="checkbox" id="mbp-idpay-sandbox" name="idpay_sandbox" value="1" <?php checked($idpay_sandbox, 1); ?>>
                            حالت تست (Sandbox)
                        </label>
                        <p style="font-size:12px;color:#6b7280;margin-top:5px;margin-right:24px;">
                            در حالت تست، پرداخت‌ها واقعی نیستند
                        </p>
                    </div>
                </div>

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;">تنظیمات نکست پی</h3>

                    <div style="margin-bottom:15px;">
                        <label style="font-weight:800;display:block;margin-bottom:5px;">API Key</label>
                        <input type="text" id="mbp-nextpay-api-key" name="nextpay_api_key"
                            value="<?php echo esc_attr($nextpay_api_key); ?>" class="regular-text" style="width:100%;">
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="button button-primary" id="mbp-payment-save">
                        ذخیره تنظیمات
                    </button>
                    <span id="mbp-payment-message" style="margin-right:15px;font-size:12px;"></span>
                </div>
            </form>
        </div>

        <script>
            jQuery(function ($) {
                const nonce = '<?php echo wp_create_nonce("mbp_admin_action_nonce"); ?>';

                // ذخیره تنظیمات
                $('#mbp-payment-settings-form').on('submit', function (e) {
                    e.preventDefault();

                    const submitBtn = $('#mbp-payment-save');
                    const originalText = submitBtn.text();
                    const messageEl = $('#mbp-payment-message');

                    submitBtn.prop('disabled', true);
                    submitBtn.text('در حال ذخیره...');
                    messageEl.text('').css('color', '');

                    const formData = $(this).serialize() + '&action=mbp_save_payment_settings&nonce=' + nonce;

                    $.post(ajaxurl, formData, function (response) {
                        if (response.success) {
                            messageEl.text('تنظیمات با موفقیت ذخیره شد').css('color', '#10b981');
                        } else {
                            messageEl.text(response.data.message || 'خطا در ذخیره').css('color', '#ef4444');
                        }
                    }).fail(function () {
                        messageEl.text('خطا در ارتباط با سرور').css('color', '#ef4444');
                    }).always(function () {
                        submitBtn.prop('disabled', false);
                        submitBtn.text(originalText);
                    });
                });
            });
        </script>
        <?php

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    public function ajax_save_payment_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $default_gateway = isset($_POST['default_gateway']) ? sanitize_text_field($_POST['default_gateway']) : 'zarinpal';
        $zarinpal_merchant_id = isset($_POST['zarinpal_merchant_id']) ? sanitize_text_field($_POST['zarinpal_merchant_id']) : '';
        $idpay_api_key = isset($_POST['idpay_api_key']) ? sanitize_text_field($_POST['idpay_api_key']) : '';
        $idpay_sandbox = isset($_POST['idpay_sandbox']) ? 1 : 0;
        $nextpay_api_key = isset($_POST['nextpay_api_key']) ? sanitize_text_field($_POST['nextpay_api_key']) : '';

        update_option('mbp_default_gateway', $default_gateway);
        update_option('mbp_zarinpal_merchant_id', $zarinpal_merchant_id);
        update_option('mbp_idpay_api_key', $idpay_api_key);
        update_option('mbp_idpay_sandbox', $idpay_sandbox);
        update_option('mbp_nextpay_api_key', $nextpay_api_key);

        wp_send_json_success(array('message' => 'تنظیمات درگاه پرداخت با موفقیت ذخیره شد'));
    }

    // =========================
    // INVOICES AJAX
    // =========================
    public function ajax_get_invoices()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        global $wpdb;
        $appointments_table = $wpdb->prefix . 'mbp_appointments';
        $services_table = $wpdb->prefix . 'mbp_services';
        $payments_table = $wpdb->prefix . 'mbp_payments';

        // دریافت فاکتورها (رزروهای پرداخت شده)
        $invoices = $wpdb->get_results(
            "SELECT a.*, s.name as service_name, s.price as service_price, p.amount as paid_amount, p.ref_id, p.created_at as payment_date
             FROM $appointments_table a 
             LEFT JOIN $services_table s ON a.service_id = s.id 
             LEFT JOIN $payments_table p ON a.id = p.appointment_id AND p.status = 'completed'
             WHERE a.payment_status = 'paid'
             ORDER BY a.created_at DESC"
        );

        ob_start();
        ?>
        <div style="max-width:1200px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;">لیست فاکتورها</h3>
                <div style="display:flex;gap:10px;">
                    <button type="button" id="mbp-export-invoices" class="button" style="font-weight:800;">
                        📊 خروجی Excel
                    </button>
                    <button type="button" id="mbp-create-manual-invoice" class="button button-primary" style="font-weight:800;">
                        + ایجاد فاکتور دستی
                    </button>
                </div>
            </div>

            <?php if (empty($invoices)): ?>
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:40px;text-align:center;">
                    <div style="font-size:16px;color:#6b7280;margin-bottom:15px;">هیچ فاکتوری یافت نشد</div>
                    <div style="font-size:14px;color:#9ca3af;">فاکتورها بعد از پرداخت موفق رزروها ایجاد میشوند</div>
                </div>
            <?php else: ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="80">شماره فاکتور</th>
                                <th>مشتری</th>
                                <th>خدمت</th>
                                <th width="120">مبلغ</th>
                                <th width="120">تاریخ پرداخت</th>
                                <th width="100">شماره پیگیری</th>
                                <th width="100">وضعیت</th>
                                <th width="120">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo esc_html($invoice->id); ?></strong>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?php echo esc_html($invoice->customer_name); ?></div>
                                        <div style="font-size:12px;color:#6b7280;"><?php echo esc_html($invoice->customer_email); ?>
                                        </div>
                                        <?php if ($invoice->customer_phone): ?>
                                            <div style="font-size:12px;color:#6b7280;"><?php echo esc_html($invoice->customer_phone); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?php echo esc_html($invoice->service_name ?: 'خدمت عمومی'); ?>
                                        </div>
                                        <div style="font-size:12px;color:#6b7280;">
                                            <?php echo $this->fa_date_from_timestamp(strtotime($invoice->time), 'Y/m/d', true); ?>
                                            -
                                            <?php echo date('H:i', strtotime($invoice->time)); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;color:#059669;">
                                            <?php echo number_format($invoice->paid_amount ?: $invoice->service_price); ?> تومان
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($invoice->payment_date): ?>
                                            <?php echo $this->fa_date_from_timestamp(strtotime($invoice->payment_date), 'Y/m/d H:i', true); ?>
                                        <?php else: ?>
                                            <span style="color:#6b7280;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invoice->ref_id): ?>
                                            <code style="font-size:11px;"><?php echo esc_html($invoice->ref_id); ?></code>
                                        <?php else: ?>
                                            <span style="color:#6b7280;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="invoice-status paid"
                                            style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:rgba(34,197,94,.2);color:#22c55e;">
                                            پرداخت شده
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                            <button type="button" class="button button-small mbp-view-invoice"
                                                data-id="<?php echo esc_attr($invoice->id); ?>" title="مشاهده فاکتور">
                                                👁️
                                            </button>
                                            <button type="button" class="button button-small mbp-print-invoice"
                                                data-id="<?php echo esc_attr($invoice->id); ?>" title="چاپ فاکتور">
                                                🖨️
                                            </button>
                                            <button type="button" class="button button-small mbp-send-invoice"
                                                data-id="<?php echo esc_attr($invoice->id); ?>" title="ارسال ایمیل">
                                                📧
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:20px;padding:15px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
                        <div style="font-size:14px;color:#6b7280;">
                            مجموع <?php echo count($invoices); ?> فاکتور
                        </div>
                        <div style="font-size:16px;font-weight:700;color:#059669;">
                            کل درآمد: <?php
                            $total_income = array_sum(array_map(function ($inv) {
                                return $inv->paid_amount ?: $inv->service_price;
                            }, $invoices));
                            echo number_format($total_income);
                            ?> تومان
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal برای مشاهده فاکتور -->
        <div id="mbp-invoice-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;">
            <div
                style="width:min(800px,92vw);margin:5vh auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,.25);max-height:90vh;overflow-y:auto;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:20px;">
                    <div style="font-weight:900;font-size:16px;" id="mbp-invoice-modal-title">مشاهده فاکتور</div>
                    <button type="button" class="button" id="mbp-invoice-modal-close">بستن</button>
                </div>

                <div id="mbp-invoice-content">
                    <!-- محتوای فاکتور اینجا لود میشود -->
                </div>
            </div>
        </div>

        <script>
            jQuery(function ($) {
                const nonce = '<?php echo wp_create_nonce("mbp_admin_action_nonce"); ?>';

                // مشاهده فاکتور
                $(document).on('click', '.mbp-view-invoice', function () {
                    const invoiceId = $(this).data('id');
                    $('#mbp-invoice-content').html('<div style="text-align:center;padding:20px;">در حال بارگذاری...</div>');
                    $('#mbp-invoice-modal').show();

                    $.post(ajaxurl, {
                        action: 'mbp_get_invoice_details',
                        invoice_id: invoiceId,
                        nonce: nonce
                    }, function (response) {
                        if (response.success) {
                            $('#mbp-invoice-content').html(response.data.html);
                        } else {
                            $('#mbp-invoice-content').html('<div style="color:#ef4444;text-align:center;padding:20px;">خطا در بارگذاری فاکتور</div>');
                        }
                    });
                });

                // چاپ فاکتور
                $(document).on('click', '.mbp-print-invoice', function () {
                    const invoiceId = $(this).data('id');
                    window.open(ajaxurl + '?action=mbp_print_invoice&invoice_id=' + invoiceId + '&nonce=' + nonce, '_blank');
                });

                // ارسال ایمیل فاکتور
                $(document).on('click', '.mbp-send-invoice', function () {
                    const invoiceId = $(this).data('id');
                    const button = $(this);
                    const originalText = button.text();

                    button.prop('disabled', true);
                    button.text('در حال ارسال...');

                    $.post(ajaxurl, {
                        action: 'mbp_send_invoice_email',
                        invoice_id: invoiceId,
                        nonce: nonce
                    }, function (response) {
                        if (response.success) {
                            alert('فاکتور با موفقیت ارسال شد');
                        } else {
                            alert(response.data.message || 'خطا در ارسال فاکتور');
                        }
                    }).always(function () {
                        button.prop('disabled', false);
                        button.text(originalText);
                    });
                });

                // بستن مدال
                $('#mbp-invoice-modal-close').on('click', function () {
                    $('#mbp-invoice-modal').hide();
                });

                // خروجی Excel
                $('#mbp-export-invoices').on('click', function () {
                    window.location.href = ajaxurl + '?action=mbp_export_invoices&nonce=' + nonce;
                });
            });
        </script>
        <?php

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    public function ajax_create_invoice()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        // پیاده‌سازی ایجاد فاکتور دستی
        wp_send_json_success(array('message' => 'فاکتور ایجاد شد'));
    }

    public function ajax_update_invoice_status()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        // پیاده‌سازی آپدیت وضعیت فاکتور
        wp_send_json_success(array('message' => 'وضعیت فاکتور آپدیت شد'));
    }

    public function ajax_initiate_payment()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'برای پرداخت باید وارد شوید'));
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        if (!$appointment_id) {
            wp_send_json_error(array('message' => 'شناسه نوبت نامعتبر است'));
        }

        // بررسی مالکیت نوبت
        $user_id = get_current_user_id();
        $user_email = wp_get_current_user()->user_email;

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND customer_email = %s",
            $appointment_id,
            $user_email
        ));

        if (!$appointment) {
            wp_send_json_error(array('message' => 'نوبت پیدا نشد یا متعلق به شما نیست'));
        }

        // بررسی وضعیت پرداخت
        if ($appointment->payment_status === 'paid') {
            wp_send_json_error(array('message' => 'این نوبت قبلاً پرداخت شده است'));
        }

        // دریافت اطلاعات خدمت
        $service = $this->get_service($appointment->service_id);
        if (!$service) {
            wp_send_json_error(array('message' => 'خدمت مربوطه پیدا نشد'));
        }

        $amount = floatval($service->price);
        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'این خدمت رایگان است'));
        }

        // شروع پرداخت
        $default_gateway = get_option('mbp_default_gateway', 'zarinpal');
        $payment = new MBP_Payment_Gateway($default_gateway);

        $callback_url = home_url('/payment-verify/');
        $description = "پرداخت خدمت {$service->name} - کد رزرو: {$appointment_id}";

        $result = $payment->request_payment(
            $appointment_id,
            $amount,
            $description,
            $callback_url,
            array(
                'email' => $appointment->customer_email,
                'phone' => $appointment->customer_phone,
                'name' => $appointment->customer_name
            )
        );

        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['message']));
        }

        wp_send_json_success(array(
            'message' => 'در حال اتصال به درگاه پرداخت...',
            'payment_url' => $result['payment_url']
        ));
    }

    // =========================
    // USER APPOINTMENTS AJAX
    // =========================
    public function ajax_get_user_appointments()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'لطفاً ابتدا وارد شوید'));
        }

        $user_email = wp_get_current_user()->user_email;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        global $wpdb;
        $appointments_table = $wpdb->prefix . 'mbp_appointments';
        $services_table = $wpdb->prefix . 'mbp_services';

        // تعداد کل
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE customer_email = %s",
            $user_email
        ));

        // دریافت نوبت‌ها
        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.name as service_name, s.price as service_price 
             FROM $appointments_table a 
             LEFT JOIN $services_table s ON a.service_id = s.id 
             WHERE a.customer_email = %s 
             ORDER BY a.time DESC 
             LIMIT %d OFFSET %d",
            $user_email,
            $per_page,
            $offset
        ));

        ob_start();

        if (empty($appointments)) {
            echo '<div style="text-align:center;padding:40px 20px;color:#6b7280;">
                    <p style="font-size:16px;margin-bottom:10px;">هنوز نوبتی ثبت نکرده‌اید</p>
                    <a href="' . home_url() . '" class="button button-primary">رزرو نوبت جدید</a>
                  </div>';
        } else {
            echo '<div class="mbp-appointments-list">';

            foreach ($appointments as $appointment) {
                $service_name = $appointment->service_name ?: 'خدمت عمومی';
                $service_price = $appointment->service_price ? number_format($appointment->service_price) : 'رایگان';

                $status_text = '';
                switch ($appointment->status) {
                    case 'pending':
                        $status_text = 'در انتظار تأیید';
                        break;
                    case 'approved':
                        $status_text = 'تأیید شده';
                        break;
                    case 'cancelled':
                        $status_text = 'لغو شده';
                        break;
                    default:
                        $status_text = $appointment->status;
                }

                $payment_status_text = '';
                switch ($appointment->payment_status) {
                    case 'paid':
                        $payment_status_text = 'پرداخت شده';
                        break;
                    case 'unpaid':
                        $payment_status_text = 'پرداخت نشده';
                        break;
                    case 'pending':
                        $payment_status_text = 'در انتظار پرداخت';
                        break;
                    default:
                        $payment_status_text = $appointment->payment_status;
                }

                echo '<div class="mbp-appointment-card">';
                echo '<div class="mbp-appointment-header">';
                echo '<div>';
                echo '<span class="mbp-appointment-status ' . esc_attr($appointment->status) . '">' . esc_html($status_text) . '</span>';
                echo ' <span class="mbp-appointment-status ' . esc_attr($appointment->payment_status) . '">' . esc_html($payment_status_text) . '</span>';
                echo '</div>';
                echo '<div style="font-size:12px;color:#6b7280;">کد رهگیری: ' . esc_html($appointment->tracking_code ?: $appointment->id) . '</div>';
                echo '</div>';

                echo '<div class="mbp-appointment-details">';
                echo '<div class="mbp-appointment-detail">';
                echo '<div class="mbp-appointment-label">خدمت</div>';
                echo '<div class="mbp-appointment-value">' . esc_html($service_name) . '</div>';
                echo '</div>';

                echo '<div class="mbp-appointment-detail">';
                echo '<div class="mbp-appointment-label">تاریخ و زمان</div>';
                echo '<div class="mbp-appointment-value">' . $this->fa_date_from_timestamp(strtotime($appointment->time), 'Y/m/d', true) . ' - ' . date('H:i', strtotime($appointment->time)) . '</div>';
                echo '</div>';

                echo '<div class="mbp-appointment-detail">';
                echo '<div class="mbp-appointment-label">مبلغ</div>';
                echo '<div class="mbp-appointment-value">' . esc_html($service_price) . ' تومان</div>';
                echo '</div>';

                echo '<div class="mbp-appointment-detail">';
                echo '<div class="mbp-appointment-label">تاریخ ثبت</div>';
                echo '<div class="mbp-appointment-value">' . $this->fa_date_from_timestamp(strtotime($appointment->created_at), 'Y/m/d H:i', true) . '</div>';
                echo '</div>';
                echo '</div>';

                echo '<div class="mbp-appointment-actions">';

                // اگر پرداخت نشده و خدمت پولی است
                if ($appointment->payment_status === 'unpaid' && $appointment->service_price > 0) {
                    echo '<button class="mbp-submit mbp-submit-primary mbp-initiate-payment" data-id="' . esc_attr($appointment->id) . '">پرداخت آنلاین</button>';
                }

                // اگر هنوز تأیید نشده یا در انتظار است
                if ($appointment->status === 'pending') {
                    echo '<button class="mbp-submit mbp-cancel-appointment" data-id="' . esc_attr($appointment->id) . '">لغو نوبت</button>';
                }

                echo '</div>';
                echo '</div>';
            }

            echo '</div>';

            // صفحه‌بندی
            if ($total > $per_page) {
                $total_pages = ceil($total / $per_page);

                echo '<div style="display:flex;justify-content:center;gap:10px;margin-top:20px;">';

                if ($page > 1) {
                    echo '<button class="button mbp-appointments-prev" data-page="' . ($page - 1) . '">قبلی</button>';
                }

                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == $page) {
                        echo '<span class="button" style="background:#3b82f6;color:white;">' . $i . '</span>';
                    } else {
                        echo '<button class="button mbp-appointments-page" data-page="' . $i . '">' . $i . '</button>';
                    }
                }

                if ($page < $total_pages) {
                    echo '<button class="button mbp-appointments-next" data-page="' . ($page + 1) . '">بعدی</button>';
                }

                echo '</div>';
            }
        }

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html, 'total' => $total));
    }

    public function ajax_cancel_user_appointment()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'لطفاً ابتدا وارد شوید'));
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        if (!$appointment_id) {
            wp_send_json_error(array('message' => 'شناسه نوبت نامعتبر است'));
        }

        // بررسی مالکیت
        $user_email = wp_get_current_user()->user_email;

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND customer_email = %s",
            $appointment_id,
            $user_email
        ));

        if (!$appointment) {
            wp_send_json_error(array('message' => 'نوبت پیدا نشد یا متعلق به شما نیست'));
        }

        // فقط نوبت‌های در انتظار قابل لغو هستند
        if ($appointment->status !== 'pending') {
            wp_send_json_error(array('message' => 'فقط نوبت‌های در انتظار تأیید قابل لغو هستند'));
        }

        // لغو نوبت
        $result = $wpdb->update(
            $table,
            array('status' => 'cancelled'),
            array('id' => $appointment_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'خطا در لغو نوبت'));
        }

        wp_send_json_success(array('message' => 'نوبت با موفقیت لغو شد'));
    }

    // =========================
    // PAYMENT VERIFICATION
    // =========================
    public function handle_payment_callback()
    {
        if (strpos($_SERVER['REQUEST_URI'], '/payment-verify/') === false) {
            return;
        }

        $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : get_option('mbp_default_gateway', 'zarinpal');

        $payment = new MBP_Payment_Gateway($gateway);

        $result = $payment->verify_payment(
            $_GET['Authority'] ?? $_GET['id'] ?? '',
            $_GET['Status'] ?? 'NOK',
            $_GET
        );

        // نمایش نتیجه
        $this->render_payment_result($result);
        exit;
    }

    private function render_payment_result($result)
    {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>نتیجه پرداخت - <?php bloginfo('name'); ?></title>
            <style>
                body {
                    font-family: Tahoma, Arial, sans-serif;
                    background: #f9fafb;
                    margin: 0;
                    padding: 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    direction: rtl;
                }

                .payment-result {
                    background: white;
                    border-radius: 12px;
                    padding: 30px;
                    max-width: 500px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                }

                .success {
                    border-top: 4px solid #10b981;
                }

                .error {
                    border-top: 4px solid #ef4444;
                }

                .icon {
                    font-size: 48px;
                    margin-bottom: 20px;
                }

                .success .icon {
                    color: #10b981;
                }

                .error .icon {
                    color: #ef4444;
                }

                h2 {
                    margin: 0 0 15px 0;
                    color: #111827;
                }

                p {
                    color: #6b7280;
                    line-height: 1.6;
                    margin-bottom: 20px;
                }

                .details {
                    background: #f9fafb;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 20px 0;
                    text-align: right;
                }

                .detail-item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    font-size: 14px;
                }

                .detail-label {
                    color: #6b7280;
                }

                .detail-value {
                    color: #111827;
                    font-weight: 600;
                }

                .buttons {
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                    margin-top: 20px;
                }

                .button {
                    padding: 10px 20px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    transition: all 0.3s;
                    border: none;
                    cursor: pointer;
                }

                .button-primary {
                    background: #3b82f6;
                    color: white;
                }

                .button-primary:hover {
                    background: #2563eb;
                }

                .button-secondary {
                    background: #e5e7eb;
                    color: #374151;
                }

                .button-secondary:hover {
                    background: #d1d5db;
                }
            </style>
        </head>

        <body>
            <div class="payment-result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                <div class="icon">
                    <?php echo $result['success'] ? '✅' : '❌'; ?>
                </div>

                <h2>
                    <?php echo $result['success'] ? 'پرداخت موفق' : 'پرداخت ناموفق'; ?>
                </h2>

                <p>
                    <?php echo $result['message']; ?>
                </p>

                <?php if ($result['success'] && isset($result['ref_id'])): ?>
                    <div class="details">
                        <div class="detail-item">
                            <span class="detail-label">شماره پیگیری:</span>
                            <span class="detail-value"><?php echo esc_html($result['ref_id']); ?></span>
                        </div>
                        <?php if (isset($result['amount'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">مبلغ پرداختی:</span>
                                <span class="detail-value"><?php echo number_format($result['amount']); ?> تومان</span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($result['card_pan'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">شماره کارت:</span>
                                <span class="detail-value"><?php echo esc_html($result['card_pan']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="buttons">
                    <a href="<?php echo home_url('/my-appointments/'); ?>" class="button button-primary">
                        مشاهده نوبت‌های من
                    </a>
                    <a href="<?php echo home_url(); ?>" class="button button-secondary">
                        بازگشت به صفحه اصلی
                    </a>
                </div>
            </div>
        </body>

        </html>
        <?php
    }

    public function render_payment_verify_page()
    {
        // این صفحه توسط handle_payment_callback مدیریت می‌شود
        return '<div style="text-align:center;padding:40px 20px;">
                   در حال بررسی وضعیت پرداخت...
                </div>';
    }

    // =========================
    // PUBLIC SHORTCODES
    // =========================
    public function render_services_list($atts = array())
    {
        if (!$this->license_is_ok()) {
            return $this->render_license_required_box('front');
        }

        $atts = shortcode_atts(array(
            'columns' => '3',
            'show_price' => '1',
            'show_duration' => '1',
            'class' => '',
        ), $atts);

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';
        $services = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY price ASC");

        if (empty($services)) {
            return '<div style="text-align:center;padding:20px;color:#6b7280;">هیچ خدمتی تعریف نشده است.</div>';
        }

        ob_start();
        ?>
        <div class="mbp-services-grid" style="grid-template-columns: repeat(<?php echo esc_attr($atts['columns']); ?>, 1fr);">
            <?php foreach ($services as $service): ?>
                <div class="mbp-service-card">
                    <h3 class="mbp-service-title"><?php echo esc_html($service->name); ?></h3>

                    <?php if (!empty($service->description)): ?>
                        <div class="mbp-service-description">
                            <?php echo nl2br(esc_html($service->description)); ?>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;">
                        <?php if ($atts['show_price'] === '1'): ?>
                            <div class="mbp-service-price">
                                <?php echo number_format($service->price); ?> تومان
                            </div>
                        <?php endif; ?>

                        <?php if ($atts['show_duration'] === '1'): ?>
                            <div class="mbp-service-duration">
                                ⏱️ <?php echo esc_html($service->duration); ?> دقیقه
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($service->price > 0): ?>
                        <div style="margin-top:12px;">
                            <button type="button" class="mbp-submit mbp-submit-primary" style="width:100%;"
                                onclick="selectService(<?php echo $service->id; ?>)">
                                انتخاب این خدمت
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            function selectService(serviceId) {
                // ذخیره انتخاب کاربر
                localStorage.setItem('mbp_selected_service', serviceId);

                // انتقال به صفحه رزرو
                window.location.href = '<?php echo home_url(); ?>';
            }
        </script>
        <?php

        return ob_get_clean();
    }

    public function render_my_appointments($atts = array())
    {
        if (!is_user_logged_in()) {
            return '<div style="text-align:center;padding:40px 20px;">
                       <p style="margin-bottom:15px;">برای مشاهده نوبت‌های خود باید وارد شوید</p>
                       <a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">ورود به حساب کاربری</a>
                    </div>';
        }

        ob_start();
        ?>
        <div class="mbp-my-appointments">
            <h2 style="margin-top:0;margin-bottom:20px;">نوبت‌های من</h2>

            <div id="mbp-appointments-container">
                <div style="text-align:center;padding:20px;color:#6b7280;">
                    در حال بارگذاری نوبت‌ها...
                </div>
            </div>
        </div>

        <script>
            jQuery(function ($) {
                function loadAppointments(page = 1) {
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'mbp_get_user_appointments',
                        page: page,
                        nonce: '<?php echo wp_create_nonce('mbp_booking_nonce'); ?>'
                    }, function (response) {
                        if (response.success) {
                            $('#mbp-appointments-container').html(response.data.html);
                        }
                    });
                }

                // بارگذاری اولیه
                loadAppointments();

                // مدیریت صفحه‌بندی
                $(document).on('click', '.mbp-appointments-page, .mbp-appointments-prev, .mbp-appointments-next', function () {
                    const page = $(this).data('page');
                    loadAppointments(page);
                });
            });
        </script>
        <?php

        return ob_get_clean();
    }

    // =========================
    // UPDATED FORM SHORTCODE
    // =========================
    public function render_booking_form($atts = array())
    {
        if (!$this->license_is_ok()) {
            return $this->render_license_required_box('front');
        }

        // دریافت خدمات فعال
        global $wpdb;
        $services_table = $wpdb->prefix . 'mbp_services';
        $services = $wpdb->get_results("SELECT * FROM $services_table WHERE is_active = 1 ORDER BY price ASC");

        $slots = $this->get_time_slots();

        ob_start(); ?>
        <div class="">


            <form id="mbp-booking-form" class="mbp-form" method="post">
                <div style="display:flex;justify-content:space-around;gap:12px;flex-wrap:wrap;">

                    <p class="mbp-field" style="min-width:260px;flex:1;">
                        <label for="mbp-name"
                            class="mbp-label"><?php esc_html_e('نام و نام خانوادگی:', 'my-booking-plugin'); ?></label>
                        <input type="text" dir="rtl" id="mbp-name" name="customer_name" required class="mbp-input mbp-ltr"
                            style="border-radius: 10px;">
                    </p>

                    <p class="mbp-field" style="min-width:260px;flex:1;">
                        <label for="mbp-email" class="mbp-label"><?php esc_html_e('ایمیل:', 'my-booking-plugin'); ?></label>
                        <input type="email" id="mbp-email" name="customer_email" required class="mbp-input mbp-ltr"
                            style="border-radius: 10px;">
                    </p>

                    <p class="mbp-field" style="min-width:260px;flex:1;">
                        <label for="mbp-phone"
                            class="mbp-label"><?php esc_html_e('شماره موبایل:', 'my-booking-plugin'); ?></label>
                        <input type="tel" id="mbp-phone" name="customer_phone" required class="mbp-input mbp-ltr"
                            style="border-radius: 10px;" pattern="^09[0-9]{9}$" placeholder="09123456789">
                    </p>
                </div>

                <div style="display:flex;justify-content:space-around;gap:12px;flex-wrap:wrap;">

                    <p class="mbp-field" style="min-width:260px;flex:1;">
                        <label for="mbp-service-id" class="mbp-label"><?php esc_html_e('خدمت:', 'my-booking-plugin'); ?></label>
                        <select id="mbp-service-id" name="service_id" required class="mbp-select">
                            <option value=""><?php esc_html_e('انتخاب کنید', 'my-booking-plugin'); ?></option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo esc_attr($service->id); ?>"
                                    data-price="<?php echo esc_attr($service->price); ?>">
                                    <?php echo esc_html($service->name); ?>
                                    <?php if ($service->price > 0): ?>
                                        (<?php echo number_format($service->price); ?> تومان)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p class="mbp-field" style="min-width:260px;flex:1;">
                        <label for="mbp-date" class="mbp-label"><?php esc_html_e('تاریخ:', 'my-booking-plugin'); ?></label>
                        <input type="text" id="mbp-date" name="date" required class="mbp-input" style="border-radius: 10px;"
                            readonly placeholder="رو جدولانتخاب کنید">
                    </p>
                    <p class="mbp-field" style="min-width:260px;flex:1;">
                        <label for="mbp-slot" class="mbp-label"><?php esc_html_e('ساعت:', 'my-booking-plugin'); ?></label>
                        <select id="mbp-slot" name="slot" required class="mbp-select">
                            <option value=""><?php esc_html_e('انتخاب کنید', 'my-booking-plugin'); ?></option>
                            <?php foreach ($slots as $s): ?>
                                <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <div id="mbp-service-price"
                    style="display:none;margin:15px 0;padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                    <strong>مبلغ قابل پرداخت: </strong>
                    <span class="price-amount" style="color:#059669;font-weight:900;">0</span>
                    <span>تومان</span>
                </div>

                <input type="hidden" name="action" value="mbp_submit_booking">
                <?php wp_nonce_field('mbp_booking_submit_action', 'mbp_booking_nonce_field'); ?>

                <button class="mbp-submit mbp-submit-primary"><?php esc_html_e('ثبت رزرو', 'my-booking-plugin'); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================
    // UPDATED BOOKING SUBMIT
    // =========================
    public function handle_booking_submit()
    {
        global $wpdb;

        // ۱. امنیت و لایسنس
        if (!$this->license_is_ok())
            wp_send_json_error(array('message' => 'افزونه فعال نیست.'));
        check_ajax_referer('mbp_booking_submit_action', 'mbp_booking_nonce_field');

        // ۲. دریافت و ضدعفونی داده‌ها
        $name = sanitize_text_field($_POST['customer_name']);
        $email = sanitize_email($_POST['customer_email']);
        $phone = sanitize_text_field($_POST['customer_phone']);
        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date']);
        $slot = sanitize_text_field($_POST['slot']);

        // ۳. اعتبارسنجی فیلدها
        if (empty($name) || empty($phone) || empty($date) || empty($slot) || $service_id === 0) {
            wp_send_json_error(array('message' => 'اطلاعات فرم ناقص است.'));
        }
        if (!preg_match('/^09[0-9]{9}$/', $phone)) {
            wp_send_json_error(array('message' => 'شماره موبایل معتبر نیست.'));
        }

        // ۴. بررسی تکراری نبودن زمان رزرو
        $dt = date('Y-m-d H:i:s', strtotime($date . ' ' . $slot));
        $table_app = $wpdb->prefix . 'mbp_appointments';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_app WHERE time = %s AND status != 'cancelled'", $dt));
        if ($exists > 0)
            wp_send_json_error(array('message' => 'این زمان قبلاً رزرو شده است.'));

        // ۵. دریافت اطلاعات خدمت
        $service = $this->get_service($service_id);
        if (!$service)
            wp_send_json_error(array('message' => 'خدمت انتخاب شده موجود نیست.'));

        $tracking_code = substr(md5(uniqid()), 0, 8);
        $payment_status = ($service->price > 0) ? 'unpaid' : 'paid';

        // ۶. ذخیره در جدول اصلی رزروها
        $ok = $wpdb->insert($table_app, array(
            'time' => $dt,
            'service_id' => $service_id,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'status' => 'pending',
            'payment_status' => $payment_status,
            'tracking_code' => $tracking_code,
            'created_at' => current_time('mysql')
        ));

        if (!$ok)
            wp_send_json_error(array('message' => 'خطا در ثبت رزرو در دیتابیس.'));
        $appointment_id = $wpdb->insert_id;

        // ۷. ذخیره در دفترچه تلفن (مهم: قبل از هرگونه ارسال Success)
        $table_pb = $wpdb->prefix . 'mbp_phonebook';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_pb (name, phone) VALUES (%s, %s) 
         ON DUPLICATE KEY UPDATE name = VALUES(name)",
            $name,
            $phone
        ));

        // ۸. ارسال پیامک تأیید به مشتری و ادمین
        $sms = new MBP_SMS_Manager();
        if ($phone) {
            $sms->send_booking_confirmation($phone, array(
                'name' => $name,
                'date' => $this->fa_date_from_timestamp(strtotime($date), 'Y/m/d', true),
                'time' => $slot,
                'service' => $service->name,
                'tracking_code' => $tracking_code
            ));
        }
        // اطلاع به ادمین (اگر متدش را ساخته‌اید)
        // $sms->send_admin_notification($name, $service->name, "$date $slot");

        // ۹. مدیریت پرداخت (اگر نیاز بود)
        if ($service->price > 0) {
            $default_gateway = get_option('mbp_default_gateway', 'zarinpal');
            $payment = new MBP_Payment_Gateway($default_gateway);
            $callback_url = home_url('/payment-verify/');

            $payment_result = $payment->request_payment(
                $appointment_id,
                $service->price,
                "رزرو {$service->name} - کد: {$tracking_code}",
                $callback_url,
                array('email' => $email, 'phone' => $phone, 'name' => $name)
            );

            if ($payment_result && $payment_result['success']) {
                wp_send_json_success(array(
                    'message' => 'رزرو ثبت شد. در حال انتقال به درگاه پرداخت...',
                    'needs_payment' => true,
                    'payment_url' => $payment_result['payment_url'],
                    'tracking_code' => $tracking_code
                ));
                return; // خروج بعد از موفقیت
            }
        }

        // ۱۰. خروجی نهایی برای خدمات رایگان یا اگر پرداخت به هر دلیلی مستقیم نشد
        wp_send_json_success(array(
            'message' => 'رزرو با موفقیت انجام شد.',
            'tracking_code' => $tracking_code,
            'needs_payment' => false
        ));
    }
    // =========================
    // CRON JOBS
    // =========================
    public function check_expired_payments()
    {
        global $wpdb;

        // پیدا کردن پرداخت‌های pending که بیش از 24 ساعت گذشته
        $payments_table = $wpdb->prefix . 'mbp_payments';
        $appointments_table = $wpdb->prefix . 'mbp_appointments';

        $expired_payments = $wpdb->get_results(
            "SELECT p.* FROM $payments_table p 
             WHERE p.status = 'pending' 
             AND p.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        foreach ($expired_payments as $payment) {
            // آپدیت وضعیت پرداخت به failed
            $wpdb->update(
                $payments_table,
                array('status' => 'failed'),
                array('id' => $payment->id),
                array('%s'),
                array('%d')
            );

            // آپدیت وضعیت رزرو به cancelled
            $wpdb->update(
                $appointments_table,
                array('status' => 'cancelled'),
                array('id' => $payment->appointment_id),
                array('%s'),
                array('%d')
            );
        }
    }

    public function send_appointment_reminders()
    {
        global $wpdb;

        $sms_settings = MBP_SMS_Manager::get_settings();

        if (!$sms_settings || !$sms_settings->enable_reminder_sms) {
            return;
        }

        $reminder_hours = $sms_settings->reminder_hours ?: 24;

        $appointments_table = $wpdb->prefix . 'mbp_appointments';
        $services_table = $wpdb->prefix . 'mbp_services';

        // پیدا کردن نوبت‌های تأیید شده که تا X ساعت دیگر زمان دارند
        $reminder_time = date('Y-m-d H:i:s', strtotime("+$reminder_hours hours"));

        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, s.name as service_name 
                 FROM $appointments_table a 
                 LEFT JOIN $services_table s ON a.service_id = s.id 
                 WHERE a.status = 'approved' 
                 AND a.time BETWEEN NOW() AND %s 
                 AND a.sms_sent = 0",
                $reminder_time
            )
        );

        foreach ($appointments as $appointment) {
            if ($appointment->customer_phone) {
                $sms = new MBP_SMS_Manager();

                $result = $sms->send_reminder($appointment->customer_phone, array(
                    'time' => date('H:i', strtotime($appointment->time)),
                    'service' => $appointment->service_name,
                    'location' => 'مطب' // این قسمت می‌تواند از تنظیمات لود شود
                ));

                // علامت زدن ارسال شده
                if ($result) {
                    $wpdb->update(
                        $appointments_table,
                        array('sms_sent' => 1),
                        array('id' => $appointment->id),
                        array('%d'),
                        array('%d')
                    );
                }
            }
        }
    }

    // =========================
    // HELPERS
    // =========================
    private function get_service($service_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_services';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND is_active = 1",
            $service_id
        ));
    }

    private function get_service_name($service_id)
    {
        $service = $this->get_service($service_id);
        return $service ? $service->name : 'خدمت عمومی';
    }

    // ... بقیه متدهای موجود (fa_digits, gregorian_to_jalali, fa_date_from_timestamp, fa_weekday_from_timestamp, get_time_slots, etc)

    private function fa_digits($str)
    {
        $en = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $fa = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        return str_replace($en, $fa, (string) $str);
    }

    private function gregorian_to_jalali($gy, $gm, $gd)
    {
        $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy)
            + (int) (($gy2 + 3) / 4)
            - (int) (($gy2 + 99) / 100)
            + (int) (($gy2 + 399) / 400)
            + $gd + $g_d_m[$gm - 1];

        $jy = -1595 + (33 * (int) ($days / 12053));
        $days %= 12053;
        $jy += 4 * (int) ($days / 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        $jm = ($days < 186) ? 1 + (int) ($days / 31) : 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

        return array($jy, $jm, $jd);
    }

    private function fa_date_from_timestamp($timestamp, $format = 'Y/m/d', $use_fa_digits = true)
    {
        $timestamp = (int) $timestamp;
        $gy = (int) wp_date('Y', $timestamp);
        $gm = (int) wp_date('n', $timestamp);
        $gd = (int) wp_date('j', $timestamp);

        list($jy, $jm, $jd) = $this->gregorian_to_jalali($gy, $gm, $gd);

        $jm2 = str_pad((string) $jm, 2, '0', STR_PAD_LEFT);
        $jd2 = str_pad((string) $jd, 2, '0', STR_PAD_LEFT);

        $out = ($format === 'Y-m-d') ? "{$jy}-{$jm2}-{$jd2}" : "{$jy}/{$jm2}/{$jd2}";
        return $use_fa_digits ? $this->fa_digits($out) : $out;
    }

    private function fa_weekday_from_timestamp($timestamp)
    {
        $timestamp = (int) $timestamp;
        $w = (int) wp_date('w', $timestamp);
        $weekday_fa = array('یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه');
        return $weekday_fa[$w] ?? '';
    }

    private function get_time_slots()
    {
        $default = array('09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00');

        $opt = get_option(self::OPTION_TIME_SLOTS, null);
        if (!is_array($opt) || empty($opt)) {
            return $default;
        }

        $clean = array();
        foreach ($opt as $t) {
            $t = trim((string) $t);
            if (preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $t)) {
                $clean[] = $t;
            }
        }

        $clean = array_values(array_unique($clean));
        usort($clean, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        return !empty($clean) ? $clean : $default;
    }

    // بعد از متد get_time_slots() و قبل از متد get_appointments_for_range() اضافه کنید:

    // =========================
// Schedule settings
// =========================
    private function schedule_settings()
    {
        $defaults = array('week_start' => 'saturday');
        $opt = get_option(self::OPTION_SCHEDULE_SETTINGS, array());
        if (!is_array($opt))
            $opt = array();
        return array_merge($defaults, $opt);
    }

    private function get_appointments_for_range($start_ymd, $end_ymd)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';
        $start = $start_ymd . ' 00:00:00';
        $end = $end_ymd . ' 23:59:59';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE time BETWEEN %s AND %s ORDER BY time ASC",
            $start,
            $end
        );
        return $wpdb->get_results($sql);
    }
    // این متد را بعد از متد get_appointments_for_range() اضافه کنید:

    // =========================
// Admin schedule HTML
// =========================
    private function render_schedule_grid_html($appointments, $week_start_ymd, $settings)
    {
        $tz = wp_timezone();
        $week_start = new DateTime($week_start_ymd . ' 00:00:00', $tz);

        $days = array();
        for ($i = 0; $i < 7; $i++) {
            $d = clone $week_start;
            $d->modify("+{$i} day");
            $days[] = $d;
        }

        $slots = $this->get_time_slots();

        $index = array();
        foreach ((array) $appointments as $a) {
            if (empty($a->time))
                continue;
            $dt = new DateTime($a->time, $tz);
            $dayKey = $dt->format('Y-m-d');
            $timeKey = $dt->format('H:i');
            if (!isset($index[$dayKey]))
                $index[$dayKey] = array();
            if (!isset($index[$dayKey][$timeKey]))
                $index[$dayKey][$timeKey] = array();
            $index[$dayKey][$timeKey][] = $a;
        }

        ob_start(); ?>
        <div class="mbp-schedule-wrap" data-week-start="<?php echo esc_attr($week_start_ymd); ?>">
            <div class="mbp-schedule-toolbar">
                <button class="mbp-nav" data-week-nav="-7">هفته قبل</button>
                <div class="mbp-week-title">
                    شروع جدول از:
                    <strong><?php echo esc_html($this->fa_date_from_timestamp($week_start->getTimestamp(), 'Y/m/d', true)); ?></strong>
                </div>
                <button class="mbp-nav" data-week-nav="7">هفته بعد</button>
            </div>

            <div class="mbp-schedule-scroll">
                <table class="mbp-schedule">
                    <thead>
                        <tr>
                            <th class="mbp-corner">ساعت</th>
                            <?php foreach ($days as $d): ?>
                                <th>
                                    <div class="mbp-day">
                                        <div class="mbp-day-name">
                                            <?php echo esc_html($this->fa_weekday_from_timestamp($d->getTimestamp())); ?>
                                        </div>
                                        <div class="mbp-day-date">
                                            <?php echo esc_html($this->fa_date_from_timestamp($d->getTimestamp(), 'Y/m/d', true)); ?>
                                        </div>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td class="mbp-time"><?php echo esc_html($slot); ?></td>

                                <?php foreach ($days as $d): ?>
                                    <?php
                                    $dayKey = $d->format('Y-m-d');
                                    $cellAppointments = $index[$dayKey][$slot] ?? array();
                                    ?>
                                    <td class="mbp-cell <?php echo empty($cellAppointments) ? 'empty' : 'has'; ?>"
                                        data-day="<?php echo esc_attr($dayKey); ?>" data-slot="<?php echo esc_attr($slot); ?>">

                                        <?php if (empty($cellAppointments)): ?>
                                            <div class="mbp-empty">—</div>
                                        <?php else: ?>
                                            <?php foreach ($cellAppointments as $a): ?>
                                                <?php
                                                $status = $a->status ?: 'pending';
                                                $is_approved = ($status === 'approved');
                                                ?>
                                                <div class="mbp-booking-card" data-id="<?php echo esc_attr($a->id); ?>">
                                                    <div class="mbp-booking-head">
                                                        <span
                                                            class="<?php echo $is_approved ? 'mbp-status-approved' : 'mbp-status-pending'; ?>">
                                                            <?php echo esc_html(ucfirst($status)); ?>
                                                        </span>
                                                        <span class="mbp-id">#<?php echo esc_html($this->fa_digits($a->id)); ?></span>
                                                    </div>

                                                    <div class="mbp-booking-body">
                                                        <div class="mbp-name"><?php echo esc_html($a->customer_name); ?></div>
                                                        <div class="mbp-email"><?php echo esc_html($a->customer_email); ?></div>
                                                        <?php if (!empty($a->customer_phone)): ?>
                                                            <div class="mbp-phone"><?php echo esc_html($a->customer_phone); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($a->tracking_code)): ?>
                                                            <div class="mbp-tracking">کد: <?php echo esc_html($a->tracking_code); ?></div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="mbp-booking-actions">
                                                        <?php if (!$is_approved): ?>
                                                            <button class="mbp-btn mbp-approve"
                                                                data-id="<?php echo esc_attr($a->id); ?>">تایید</button>
                                                        <?php endif; ?>
                                                        <?php if ($status !== 'cancelled'): ?>
                                                            <button class="mbp-btn mbp-cancel"
                                                                data-id="<?php echo esc_attr($a->id); ?>">لغو</button>
                                                        <?php endif; ?>
                                                        <button class="mbp-btn mbp-delete"
                                                            data-id="<?php echo esc_attr($a->id); ?>">حذف</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    // =========================
    // DASHBOARD PAGE
    // =========================



    public function render_dashboard_app_page()
    {
        if (!current_user_can('manage_options'))
            wp_die('دسترسی ندارید');

        if (!$this->license_is_ok()) {
            wp_die('لایسنس فعال نیست. ابتدا از صفحه پنل رزرو، لایسنس را فعال کنید.');
        }

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';
        $appointments_all = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY `time` DESC");
        $count_total = is_array($appointments_all) ? count($appointments_all) : 0;

        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('mbp_admin_action_nonce');

        $settings = $this->schedule_settings();
        $week_start_ymd = wp_date('Y-m-d');

        $tz = wp_timezone();
        $ws = new DateTime($week_start_ymd . ' 00:00:00', $tz);
        $we = clone $ws;
        $we->modify('+6 day');

        $appointments_week = $this->get_appointments_for_range($ws->format('Y-m-d'), $we->format('Y-m-d'));
        $schedule_html = $this->render_schedule_grid_html($appointments_week, $week_start_ymd, $settings);

        // آمار جدید
        $count_pending = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        $count_approved = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'");
        $count_paid = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE payment_status = 'paid'");

        // درآمد کل
        $total_income = $wpdb->get_var(
            "SELECT SUM(p.amount) 
         FROM {$wpdb->prefix}mbp_payments p 
         WHERE p.status = 'completed'"
        ) ?: 0;

        $invoice_enabled = $this->invoice_license_is_ok();

        $invoice_class = 'item' . ($invoice_enabled ? '' : ' disabled');
        $invoice_attr = $invoice_enabled ? 'data-view="invoices"' : '';
        $invoice_title = $invoice_enabled ? '' : 'برای فعال‌سازی فاکتور باید لایسنس فاکتور تهیه کنید';
        $invoice_lock = $invoice_enabled ? '' : ' 🔒';





        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"
                type="text/css" />
            <title>افزونه رزرو</title>

            <style>
                html,
                body {
                    height: 100%;
                    margin: 0;
                    font-family: Vazirmatn, Tahoma, Arial, sans-serif;
                }

                body {
                    background: #0b1220;
                    color: #fff;
                    overflow: hidden;
                }

                .mbp-shell {
                    height: 100%;
                    display: grid;
                    grid-template-columns: 280px 1fr;
                }

                .mbp-side {
                    background: #101828;
                    padding: 14px;
                    border-left: 1px solid rgba(255, 255, 255, .08);
                    position: relative;
                }

                .mbp-main {
                    background: #0b1220;
                    position: relative;
                }

                .mbp-side .item.disabled {
                    opacity: .45;
                    pointer-events: none;
                    filter: grayscale(1);
                }


                .mbp-topbar {
                    height: 54px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0 14px;
                    background: rgba(255, 255, 255, .04);
                    border-bottom: 1px solid rgba(255, 255, 255, .08);
                }

                .mbp-canvas {
                    height: calc(100% - 54px);
                    padding: 14px;
                    overflow: auto;
                }

                /* Invoice Highlights */
                .mbp-hl-title {
                    margin: 0 0 12px 0;
                    font-size: 18px;
                    font-weight: 900;
                }

                .mbp-hl-sub {
                    opacity: .8;
                    margin-bottom: 16px;
                    line-height: 1.8;
                    font-size: 13px;
                }

                .mbp-highlights {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                    gap: 14px;
                }

                .mbp-highlight {
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .12);
                    border-radius: 14px;
                    padding: 16px;
                    transition: .2s;
                }

                .mbp-highlight:hover {
                    transform: translateY(-3px);
                    background: rgba(255, 255, 255, .08);
                }

                .mbp-highlight-top {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 10px;
                }

                .mbp-highlight-icon {
                    width: 38px;
                    height: 38px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(59, 130, 246, .22);
                    border: 1px solid rgba(59, 130, 246, .25);
                    font-size: 18px;
                }

                .mbp-highlight-badge {
                    font-size: 11px;
                    font-weight: 900;
                    padding: 3px 10px;
                    border-radius: 999px;
                    background: rgba(34, 197, 94, .14);
                    border: 1px solid rgba(34, 197, 94, .25);
                    color: #a7f3d0;
                }

                .mbp-highlight-text {
                    font-weight: 900;
                    font-size: 14px;
                    line-height: 1.7;
                }

                .mbp-highlight-desc {
                    opacity: .78;
                    font-size: 12px;
                    line-height: 1.8;
                    margin-top: 6px;
                }


                .btn {
                    background: #fff;
                    color: #111;
                    border: 0;
                    border-radius: 10px;
                    padding: 8px 12px;
                    cursor: pointer;
                    font-weight: 800;
                    text-decoration: none;
                }

                a.item {
                    display: block;
                    padding: 10px;
                    border-radius: 10px;
                    color: #fff;
                    text-decoration: none;
                    opacity: .9;
                    margin-bottom: 6px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                a.item:hover,
                a.item.active {
                    background: rgba(255, 255, 255, .12);
                    opacity: 1;
                    transform: translateX(-5px);
                }

                .cards {
                    display: flex;
                    gap: 16px;
                    flex-wrap: wrap;
                }

                .card {
                    flex: 1;
                    min-width: 220px;
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .12);
                    border-radius: 14px;
                    padding: 12px;
                }

                .card .title {
                    opacity: .85;
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }

                .card .num {
                    font-size: 26px;
                    font-weight: 900;
                    margin-top: 6px;
                }

                .mbp-status-pending {
                    color: #dba617;
                    font-weight: 800;
                }

                .mbp-status-approved {
                    color: #00a32a;
                    font-weight: 800;
                }

                .mbp-btn {
                    border: 1px solid rgba(255, 255, 255, .18);
                    background: rgba(255, 255, 255, .08);
                    color: #e5e7eb;
                    padding: 6px 10px;
                    border-radius: 10px;
                    cursor: pointer;
                    font-family: Vazirmatn, sans-serif;
                    margin-left: 6px;
                    transition: all 0.3s ease;
                }

                .mbp-btn:hover {
                    background: rgba(255, 255, 255, .14);
                    transform: translateY(-2px);
                }

                .mbp-btn.mbp-delete {
                    border-color: rgba(214, 54, 56, .55);
                }

                .mbp-btn.mbp-delete:hover {
                    background: rgba(214, 54, 56, .15);
                }

                .mbp-btn.mbp-approve {
                    border-color: rgba(0, 163, 42, .55);
                }

                .mbp-btn.mbp-approve:hover {
                    background: rgba(0, 163, 42, .15);
                }

                .toast {
                    position: fixed;
                    left: 16px;
                    bottom: 16px;
                    background: rgba(0, 0, 0, .55);
                    border: 1px solid rgba(255, 255, 255, .12);
                    color: #fff;
                    padding: 10px 12px;
                    border-radius: 12px;
                    opacity: 0;
                    transform: translateY(6px);
                    transition: .2s;
                    pointer-events: none;
                    font-size: 12px;
                    z-index: 10000;
                }

                .toast.show {
                    opacity: 1;
                    transform: translateY(0);
                }

                /* Schedule */
                .mbp-schedule-wrap {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .mbp-schedule-toolbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    padding: 10px;
                    border-radius: 14px;
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .12);
                }

                .mbp-nav {
                    border: 1px solid rgba(255, 255, 255, .18);
                    background: rgba(255, 255, 255, .08);
                    color: #e5e7eb;
                    padding: 8px 12px;
                    border-radius: 10px;
                    cursor: pointer;
                    font-family: Vazirmatn, sans-serif;
                    font-weight: 900;
                    transition: all 0.3s ease;
                }

                .mbp-nav:hover {
                    background: rgba(255, 255, 255, .14);
                    transform: translateY(-2px);
                }

                .mbp-schedule-scroll {
                    border-radius: 14px;
                    border: 1px solid rgba(255, 255, 255, .12);
                    background: rgba(255, 255, 255, .04);
                    overflow: auto;
                    max-height: calc(100vh - 190px);
                }

                table.mbp-schedule {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                    min-width: 1100px;
                }

                .mbp-schedule th,
                .mbp-schedule td {
                    border-bottom: 1px solid rgba(255, 255, 255, .10);
                    border-left: 1px solid rgba(255, 255, 255, .08);
                    padding: 10px;
                    vertical-align: top;
                    color: #cbd5e1;
                    background: rgba(255, 255, 255, .03);
                }

                .mbp-schedule thead th {
                    position: sticky;
                    top: 0;
                    z-index: 5;
                    background: rgba(16, 24, 40, .98);
                }

                .mbp-corner {
                    position: sticky;
                    right: 0;
                    z-index: 6;
                    background: rgba(16, 24, 40, .98);
                }

                .mbp-time {
                    position: sticky;
                    right: 0;
                    z-index: 4;
                    background: rgba(16, 24, 40, .75);
                    font-weight: 900;
                    white-space: nowrap;
                }

                .mbp-day-name {
                    font-weight: 900;
                }

                .mbp-day-date {
                    opacity: .75;
                    font-size: 12px;
                    margin-top: 4px;
                }

                .mbp-cell.empty {
                    opacity: .65;
                }

                .mbp-empty {
                    text-align: center;
                    padding: 10px 0;
                }

                .mbp-booking-card {
                    border: 1px solid rgba(255, 255, 255, .12);
                    background: rgba(255, 255, 255, .06);
                    border-radius: 12px;
                    padding: 10px;
                    margin-bottom: 10px;
                }

                .mbp-booking-head {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .mbp-id {
                    opacity: .8;
                    font-size: 12px;
                }

                .mbp-booking-body {
                    margin-top: 8px;
                    font-size: 12px;
                    opacity: .95;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .mbp-booking-actions {
                    margin-top: 10px;
                    white-space: nowrap;
                }

                /* جدید: کارت‌های آمار */
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 16px;
                    margin-bottom: 24px;
                }

                .stat-card {
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .12);
                    border-radius: 14px;
                    padding: 16px;
                    transition: all 0.3s ease;
                }

                .stat-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                }

                .stat-card .num {
                    font-size: 32px;
                    font-weight: 900;
                    margin: 8px 0;
                }

                .stat-card .label {
                    font-size: 14px;
                    opacity: .8;
                }

                .stat-card.income {
                    border-color: rgba(16, 185, 129, .3);
                }

                .stat-card.income .num {
                    color: #10b981;
                }

                .stat-card.pending {
                    border-color: rgba(245, 158, 11, .3);
                }

                .stat-card.pending .num {
                    color: #f59e0b;
                }

                .stat-card.approved {
                    border-color: rgba(34, 197, 94, .3);
                }

                .stat-card.approved .num {
                    color: #22c55e;
                }

                .stat-card.total {
                    border-color: rgba(59, 130, 246, .3);
                }

                .stat-card.total .num {
                    color: #3b82f6;
                }

                /* Slots page */
                .panel {
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .12);
                    border-radius: 14px;
                    padding: 12px;
                    max-width: 720px;
                }

                .panel h3 {
                    margin: 0 0 10px 0;
                    font-size: 14px;
                }

                .slots-ta {
                    width: 100%;
                    min-height: 220px;
                    resize: vertical;
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .16);
                    border-radius: 12px;
                    padding: 12px;
                    color: #fff;
                    font-family: Vazirmatn, sans-serif;
                    box-sizing: border-box;
                    font-size: 14px;
                }

                .cf-btn {
                    border: 0;
                    border-radius: 10px;
                    padding: 10px 12px;
                    cursor: pointer;
                    font-family: Vazirmatn, sans-serif;
                    font-weight: 900;
                    transition: all 0.3s ease;
                }

                .cf-btn:hover {
                    transform: translateY(-2px);
                }

                .cf-btn.primary {
                    background: #fff;
                    color: #111;
                }

                .cf-btn.primary:hover {
                    background: #f3f4f6;
                }

                .cf-btn.ghost {
                    background: rgba(255, 255, 255, .08);
                    color: #fff;
                    border: 1px solid rgba(255, 255, 255, .16);
                }

                .cf-btn.ghost:hover {
                    background: rgba(255, 255, 255, .14);
                }

                .hint {
                    font-size: 12px;
                    opacity: .8;
                    margin-top: 8px;
                }

                /* Services page */
                .services-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: rgba(255, 255, 255, .03);
                    border-radius: 12px;
                    overflow: hidden;
                }

                .services-table th,
                .services-table td {
                    border: 1px solid rgba(255, 255, 255, .12);
                    padding: 12px;
                    text-align: right;
                }

                .services-table th {
                    background: rgba(255, 255, 255, .08);
                    font-weight: 900;
                }

                .service-actions {
                    display: flex;
                    gap: 8px;
                }

                .service-status {
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 700;
                    display: inline-block;
                }

                .service-status.active {
                    background: rgba(34, 197, 94, .2);
                    color: #22c55e;
                }

                .service-status.inactive {
                    background: rgba(239, 68, 68, .2);
                    color: #ef4444;
                }

                /* Tab styles */
                .mbp-settings-tabs {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid rgba(255, 255, 255, .12);
                }

                .mbp-settings-tab {
                    padding: 8px 16px;
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .12);
                    border-radius: 8px;
                    color: #cbd5e1;
                    cursor: pointer;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }

                .mbp-settings-tab:hover {
                    background: rgba(255, 255, 255, .10);
                    transform: translateY(-2px);
                }

                .mbp-settings-tab.active {
                    background: rgba(59, 130, 246, .3);
                    color: #3b82f6;
                    border-color: rgba(59, 130, 246, .5);
                }

                .mbp-tab-pane {
                    display: none;
                    animation: fadeIn 0.3s ease;
                }

                .mbp-tab-pane.active {
                    display: block;
                }

                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                /* Form styles */
                .mbp-form-group {
                    margin-bottom: 20px;
                }

                .mbp-form-label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 700;
                    color: #cbd5e1;
                }

                .mbp-form-input {
                    width: 100%;
                    padding: 10px 12px;
                    background: rgba(255, 255, 255, .06);
                    border: 1px solid rgba(255, 255, 255, .16);
                    border-radius: 8px;
                    color: #fff;
                    font-family: Vazirmatn, sans-serif;
                    font-size: 14px;
                    transition: all 0.3s ease;
                }

                .mbp-form-input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, .2);
                }

                .mbp-form-checkbox {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 10px;
                }

                .mbp-form-checkbox input {
                    width: 18px;
                    height: 18px;
                }

                /* Service Modal */
                .mbp-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, .7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    animation: fadeIn 0.2s ease;
                }

                .mbp-modal {
                    background: #1f2937;
                    border-radius: 16px;
                    padding: 24px;
                    width: 90%;
                    max-width: 500px;
                    max-height: 90vh;
                    overflow-y: auto;
                    animation: slideUp 0.3s ease;
                }

                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .mbp-modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid rgba(255, 255, 255, .12);
                }

                .mbp-modal-title {
                    font-size: 18px;
                    font-weight: 900;
                    color: #fff;
                    margin: 0;
                }

                .mbp-modal-close {
                    background: rgba(255, 255, 255, .08);
                    border: none;
                    color: #cbd5e1;
                    width: 36px;
                    height: 36px;
                    border-radius: 8px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 18px;
                    transition: all 0.3s ease;
                }

                .mbp-modal-close:hover {
                    background: rgba(255, 255, 255, .14);
                    transform: rotate(90deg);
                }

                /* Loading spinner */
                .mbp-loading {
                    display: inline-block;
                    width: 20px;
                    height: 20px;
                    border: 3px solid rgba(255, 255, 255, .1);
                    border-radius: 50%;
                    border-top-color: #3b82f6;
                    animation: spin 1s ease-in-out infinite;
                }

                @keyframes spin {
                    to {
                        transform: rotate(360deg);
                    }
                }

                /* Responsive */
                @media (max-width: 768px) {
                    .mbp-shell {
                        grid-template-columns: 1fr;
                    }

                    .mbp-side {
                        display: none;
                    }

                    .stats-grid {
                        grid-template-columns: 1fr;
                    }

                    .mbp-settings-tabs {
                        flex-direction: column;
                    }

                    .service-actions {
                        flex-direction: column;
                        gap: 5px;
                    }
                }
            </style>

            <script>
                window.MBP_AJAX_URL = <?php echo wp_json_encode($ajax_url); ?>;
                window.MBP_ADMIN_NONCE = <?php echo wp_json_encode($nonce); ?>;
                window.MBP_WEEK_START = <?php echo wp_json_encode($week_start_ymd); ?>;
            </script>
            <script src="<?php echo plugin_dir_url(__FILE__) . 'invoices-functions.js'; ?>"></script>
        </head>

        <body>
            <div class="mbp-shell">
                <aside class="mbp-side">
                    <div style="font-weight:900;margin-bottom:10px;padding:10px;border-bottom:1px solid rgba(255,255,255,.12);">
                        افزونه رزرو
                    </div>
                    <a class="item active" href="#" data-view="dashboard">
                        📊 داشبورد
                    </a>
                    <a class="item" href="#" data-view="schedule">
                        📅 جدول رزرو (هفتگی)
                    </a>
                    <a class="item" href="#" data-view="custom_fields">
                        ⏰ ساعت‌های رزرو
                    </a>
                    <a class="item" href="#" data-view="services">
                        ⚙️ خدمات و تنظیمات
                    </a>
                    <a class="item <?php echo $invoice_enabled ? '' : 'disabled'; ?>" href="#" <?php echo $invoice_enabled ? 'data-view="invoices"' : ''; ?>
                        title="<?php echo $invoice_enabled ? '' : 'برای فعال‌سازی فاکتور باید لایسنس فاکتور وارد شود'; ?>">
                        🧾 فاکتورها <?php echo $invoice_enabled ? '' : '🔒'; ?>
                    </a>


                </aside>

                <main class="mbp-main">
                    <div class="mbp-topbar">
                        <div style="font-weight:800;display:flex;align-items:center;gap:10px;">
                            <span>افزونه رزرو</span>
                            <span
                                style="font-size:12px;opacity:.7;background:rgba(59,130,246,.2);padding:2px 8px;border-radius:10px;">
                                پنل مدیریت
                            </span>
                        </div>
                        <div>
                            <a class="btn" href="<?php echo esc_url(admin_url('admin.php?page=mbp-bookings')); ?>">
                                خروج / بازگشت
                            </a>
                        </div>
                    </div>

                    <div id="tpl-dashboard" style="display:none">
                        <h2 style="margin-top:0;margin-bottom:20px;">📊 داشبورد</h2>

                        <div class="stats-grid">
                            <div class="stat-card total">
                                <div class="label">کل رزروها</div>
                                <div class="num" id="mbp-total"><?php echo esc_html($this->fa_digits($count_total)); ?></div>
                            </div>

                            <div class="stat-card income">
                                <div class="label">درآمد کل</div>
                                <div class="num" id="mbp-income">
                                    <?php echo esc_html($this->fa_digits(number_format($total_income))); ?> تومان
                                </div>
                            </div>

                            <div class="stat-card pending">
                                <div class="label">در انتظار تأیید</div>
                                <div class="num" id="mbp-pending"><?php echo esc_html($this->fa_digits($count_pending)); ?>
                                </div>
                            </div>

                            <div class="stat-card approved">
                                <div class="label">تأیید شده</div>
                                <div class="num" id="mbp-approved"><?php echo esc_html($this->fa_digits($count_approved)); ?>
                                </div>
                            </div>
                        </div>

                        <div class="cards">
                            <div class="card">
                                <div class="title">💰 آمار پرداخت‌ها</div>
                                <div class="num"><?php echo esc_html($this->fa_digits($count_paid)); ?></div>
                                <div style="font-size:12px;opacity:.8;margin-top:4px;">پرداخت‌های موفق</div>
                            </div>
                            <div class="card">
                                <div class="title">⚙️ خدمات فعال</div>
                                <div class="num" id="mbp-active-services"><?php
                                $services_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mbp_services WHERE is_active = 1");
                                echo esc_html($this->fa_digits($services_count));
                                ?></div>
                                <div style="font-size:12px;opacity:.8;margin-top:4px;">خدمت قابل رزرو</div>
                            </div>
                        </div>
                    </div>

                    <div id="tpl-schedule" style="display:none">
                        <h2 style="margin-top:0;margin-bottom:20px;">📅 جدول زمان‌بندی رزروها</h2>
                        <div id="mbp-schedule-root">
                            <?php echo $schedule_html; ?>
                        </div>
                    </div>

                    <div id="tpl-services" style="display:none">
                        <h2 style="margin-top:0;margin-bottom:20px;">⚙️ خدمات و تنظیمات</h2>
                        <div id="mbp-services-container">
                            <div
                                style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:30px;">
                                <div style="text-align:center;color:#cbd5e1;">
                                    <div style="margin-bottom:15px;">
                                        <div class="mbp-loading" style="width:40px;height:40px;margin:0 auto 15px;"></div>
                                        <div>در حال بارگذاری خدمات و تنظیمات...</div>
                                    </div>
                                    <div style="font-size:12px;opacity:.7;">
                                        اگر بارگذاری طول کشید، صفحه را رفرش کنید
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tpl-custom_fields" style="display:none">
                        <h2 style="margin-top:0;margin-bottom:20px;">⏰ تنظیم ساعت‌های قابل رزرو</h2>
                        <div class="panel">
                            <h3>هر خط یک ساعت (HH:MM)</h3>
                            <div class="hint">مثال: 09:00 یا 14:30</div>
                            <textarea id="mbp-slots-text" class="slots-ta"
                                placeholder="09:00&#10;09:30&#10;10:00&#10;10:30&#10;11:00&#10;11:30&#10;12:00"></textarea>

                            <div style="display:flex;gap:10px;margin-top:20px;">
                                <button class="cf-btn primary" id="mbp-slots-save">💾 ذخیره</button>
                                <button class="cf-btn ghost" id="mbp-slots-load">🔄 بارگذاری مجدد</button>
                            </div>

                            <div class="hint">
                                این ساعت‌ها ردیف‌های جدول هفتگی را می‌سازند و در فرم رزرو هم استفاده می‌شوند.
                            </div>
                        </div>
                    </div>

                    <div class="mbp-canvas" id="mbp-view"></div>
                </main>
            </div>

            <div class="toast" id="mbp-toast">ذخیره شد</div>

            <!-- Templates -->
            <div id="tpl-invoices" style="display:none">
                <div style="max-width:1100px;">
                    <h2 class="mbp-hl-title">🧾 ویژگی‌های کلیدی فاکتور</h2>
                    <div class="mbp-hl-sub">
                        این بخش فعلاً به صورت معرفی امکانات نمایش داده می‌شود (Highlights).<br>
                        بعداً اگر خواستی، همین‌جا فاکتور ساز و لیست فاکتور هم اضافه می‌کنیم.
                    </div>

                    <div class="mbp-highlights">

                        <div class="mbp-highlight">
                            <div class="mbp-highlight-top">
                                <div class="mbp-highlight-icon">⚙️</div>
                                <div class="mbp-highlight-badge">highlights</div>
                            </div>
                            <div class="mbp-highlight-text">امکان سفارشی‌سازی فاکتور</div>
                            <div class="mbp-highlight-desc">قالب، رنگ، لوگو، متن‌ها و آیتم‌ها قابل شخصی‌سازی هستند.</div>
                        </div>

                        <div class="mbp-highlight">
                            <div class="mbp-highlight-top">
                                <div class="mbp-highlight-icon">📅</div>
                                <div class="mbp-highlight-badge">highlights</div>
                            </div>
                            <div class="mbp-highlight-text">پشتیبانی از تاریخ شمسی و میلادی</div>
                            <div class="mbp-highlight-desc">نمایش تاریخ فاکتور با هر دو فرمت برای راحتی مشتری و مدیر.</div>
                        </div>

                        <div class="mbp-highlight">
                            <div class="mbp-highlight-top">
                                <div class="mbp-highlight-icon">🖨️</div>
                                <div class="mbp-highlight-badge">highlights</div>
                            </div>
                            <div class="mbp-highlight-text">امکان چاپ و ذخیره به PDF</div>
                            <div class="mbp-highlight-desc">چاپ مستقیم از پنل و خروجی PDF برای ارسال یا آرشیو.</div>
                        </div>

                        <div class="mbp-highlight">
                            <div class="mbp-highlight-top">
                                <div class="mbp-highlight-icon">📩</div>
                                <div class="mbp-highlight-badge">highlights</div>
                            </div>
                            <div class="mbp-highlight-text">ارسال پیامک وضعیت سفارش</div>
                            <div class="mbp-highlight-desc">ارسال خودکار یا دستی پیامک وضعیت و لینک فاکتور به مشتری.</div>
                        </div>

                        <div class="mbp-highlight">
                            <div class="mbp-highlight-top">
                                <div class="mbp-highlight-icon">🔳</div>
                                <div class="mbp-highlight-badge">highlights</div>
                            </div>
                            <div class="mbp-highlight-text">پشتیبانی از بارکد و QR Code</div>
                            <div class="mbp-highlight-desc">برای اعتبارسنجی سریع فاکتور یا هدایت به صفحه پرداخت/جزئیات.</div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- Invoice List -->
            <div
                style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:14px;">
                <div style="font-weight:900;margin-bottom:10px;">لیست فاکتورها</div>
                <div id="mbp-invoices-list">
                    <div style="text-align:center;color:#cbd5e1;padding:30px;">
                        <div class="mbp-loading" style="width:40px;height:40px;margin:0 auto 15px;"></div>
                        <div>در حال بارگذاری...</div>
                    </div>
                </div>
            </div>

            </div>
            </div>


            <script>
                (function () {
                    function ready(fn) {
                        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
                        else fn();
                    }

                    ready(function () {
                        console.log('🔧 Dashboard loaded successfully');
                        console.log('🔗 AJAX URL:', window.MBP_AJAX_URL);
                        console.log('🔑 Nonce:', window.MBP_ADMIN_NONCE ? 'Valid' : 'Missing');

                        const view = document.getElementById('mbp-view');
                        const items = Array.from(document.querySelectorAll('a.item'));

                        function hardFail(msg, err) {
                            console.error('❌ Error:', msg, err || '');
                            const target = view || document.body;
                            target.innerHTML = `
                            <div style="margin:14px;padding:20px;border:1px solid rgba(255,80,80,.5);background:rgba(255,80,80,.12);border-radius:12px;color:#fff;">
                                <div style="font-weight:900;margin-bottom:10px;font-size:16px;">⚠️ خطا در اجرای پنل</div>
                                <div style="font-size:14px;opacity:.95;margin-bottom:15px;">${msg}</div>
                                ${err ? `<pre style="direction:ltr;text-align:left;white-space:pre-wrap;font-size:11px;opacity:.9;margin-top:10px;background:rgba(0,0,0,.2);padding:10px;border-radius:6px;">${String(err.stack || err)}</pre>` : ''}
                                <button onclick="location.reload()" style="margin-top:15px;padding:8px 16px;background:#3b82f6;color:white;border:none;border-radius:6px;cursor:pointer;">
                                    🔄 رفرش صفحه
                                </button>
                            </div>`;
                        }

                        try {
                            if (!view) throw new Error('#mbp-view پیدا نشد');
                            if (!items.length) throw new Error('منوها (a.item) پیدا نشدند');

                            function setActive(el) {
                                console.log('🎯 Setting active tab:', el.dataset.view);
                                items.forEach(i => i.classList.remove('active'));
                                el.classList.add('active');
                            }

                            function mount(tplId) {
                                console.log('📦 Mounting template:', tplId);
                                const tpl = document.getElementById(tplId);
                                if (!tpl) {
                                    view.innerHTML = '<div style="padding:20px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:12px;text-align:center;color:#cbd5e1;">قالب پیدا نشد</div>';
                                    return;
                                }
                                view.innerHTML = tpl.innerHTML;
                                console.log('✅ Template mounted:', tplId);
                            }

                            function toast(msg, type = 'success') {
                                const t = document.getElementById('mbp-toast');
                                if (!t) return;

                                t.textContent = msg;
                                t.style.background = type === 'error' ? 'rgba(239,68,68,.8)' : 'rgba(0,0,0,.8)';
                                t.style.borderColor = type === 'error' ? 'rgba(239,68,68,.5)' : 'rgba(255,255,255,.12)';

                                t.classList.add('show');
                                setTimeout(() => t.classList.remove('show'), 3000);
                            }

                            function render(name) {
                                console.log('🔄 Rendering view:', name);

                                if (name === 'dashboard') {
                                    mount('tpl-dashboard');
                                } else if (name === 'schedule') {
                                    mount('tpl-schedule');
                                } else if (name === 'services') {
                                    mount('tpl-services');
                                    setTimeout(loadServices, 100);
                                } else if (name === 'custom_fields') {
                                    mount('tpl-custom_fields');
                                    setTimeout(initSlots, 100);
                                } else if (name === 'invoices') {
                                    mount('tpl-invoices');
                                } else {
                                    view.innerHTML = `
            <h2 style="margin-top:0">${name}</h2>
            <div style="opacity:.85;padding:20px;text-align:center;">بزودی...</div>
        `;
                                }
                            }


                            // مدیریت کلیک روی تب‌ها
                            items.forEach(a => {
                                a.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    setActive(a);
                                    localStorage.setItem('mbp_active_view', a.dataset.view);
                                    render(a.dataset.view);
                                });
                            });

                            // تابع‌های کمکی برای آپدیت UI
                            function updateTotal(delta) {
                                const el = document.querySelector('#mbp-view #mbp-total') || document.getElementById('mbp-total');
                                if (!el) return;

                                const fa_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                                const en_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

                                let current_fa = el.textContent || '0';
                                let current_en = current_fa.replace(/[۰-۹]/g, d => en_digits[fa_digits.indexOf(d)]);

                                const n = parseInt(current_en, 10) || 0;
                                const next_n = Math.max(0, n + delta);

                                let next_fa = String(next_n).replace(/[0-9]/g, d => fa_digits[parseInt(d, 10)]);
                                el.textContent = next_fa;
                            }

                            // ==================== Event Listeners برای رزروها ====================

                            // تایید رزرو
                            document.addEventListener('click', function (e) {
                                if (e.target.classList.contains('mbp-approve') || e.target.closest('.mbp-approve')) {
                                    e.preventDefault();
                                    const btn = e.target.classList.contains('mbp-approve') ? e.target : e.target.closest('.mbp-approve');
                                    const appointmentId = btn.dataset.id;
                                    approveBooking(appointmentId, btn);
                                }

                                // لغو رزرو
                                if (e.target.classList.contains('mbp-cancel') || e.target.closest('.mbp-cancel')) {
                                    e.preventDefault();
                                    const btn = e.target.classList.contains('mbp-cancel') ? e.target : e.target.closest('.mbp-cancel');
                                    const appointmentId = btn.dataset.id;
                                    cancelBooking(appointmentId, btn);
                                }

                                // حذف رزرو
                                if (e.target.classList.contains('mbp-delete') || e.target.closest('.mbp-delete')) {
                                    e.preventDefault();
                                    const btn = e.target.classList.contains('mbp-delete') ? e.target : e.target.closest('.mbp-delete');
                                    const appointmentId = btn.dataset.id;
                                    deleteBooking(appointmentId, btn);
                                }

                                // ناوبری هفتگی
                                if (e.target.classList.contains('mbp-nav') || e.target.closest('.mbp-nav')) {
                                    e.preventDefault();
                                    const btn = e.target.classList.contains('mbp-nav') ? e.target : e.target.closest('.mbp-nav');
                                    const days = parseInt(btn.dataset.weekNav);
                                    navigateWeek(days);
                                }
                            });

                            // تابعهای مدیریت رزرو
                            async function approveBooking(appointmentId, button) {
                                if (!appointmentId) return;

                                const originalText = button.innerHTML;
                                button.innerHTML = '<span class="mbp-loading" style="width:14px;height:14px;"></span>';
                                button.disabled = true;

                                try {
                                    const formData = new FormData();
                                    formData.append('action', 'mbp_admin_approve_booking');
                                    formData.append('id', appointmentId);
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('✅ رزرو تایید شد');

                                        // آپدیت UI
                                        const card = button.closest('.mbp-booking-card');
                                        if (card) {
                                            const statusEl = card.querySelector('.mbp-status-pending');
                                            if (statusEl) {
                                                statusEl.className = 'mbp-status-approved';
                                                statusEl.textContent = 'Approved';
                                            }
                                            button.remove(); // حذف دکمه تایید
                                        }

                                        updateTotal(0); // رفرش آمار
                                    } else {
                                        toast(data.data?.message || 'خطا در تایید رزرو', 'error');
                                        button.innerHTML = originalText;
                                        button.disabled = false;
                                    }
                                } catch (error) {
                                    console.error('Approve booking error:', error);
                                    toast('خطای شبکه در تایید رزرو', 'error');
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }
                            }

                            async function cancelBooking(appointmentId, button) {
                                if (!appointmentId || !confirm('آیا از لغو این رزرو مطمئن هستید؟')) return;

                                const originalText = button.innerHTML;
                                button.innerHTML = '<span class="mbp-loading" style="width:14px;height:14px;"></span>';
                                button.disabled = true;

                                try {
                                    const formData = new FormData();
                                    formData.append('action', 'mbp_admin_cancel_booking');
                                    formData.append('id', appointmentId);
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('⚠️ رزرو لغو شد');

                                        const card = button.closest('.mbp-booking-card');
                                        if (card) {
                                            card.style.opacity = '0.5';
                                            card.style.filter = 'grayscale(1)';
                                        }
                                    } else {
                                        toast(data.data?.message || 'خطا در لغو رزرو', 'error');
                                        button.innerHTML = originalText;
                                        button.disabled = false;
                                    }
                                } catch (error) {
                                    console.error('Cancel booking error:', error);
                                    toast('خطای شبکه در لغو رزرو', 'error');
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }
                            }

                            async function deleteBooking(appointmentId, button) {
                                if (!appointmentId || !confirm('⚠️ آیا از حذف این رزرو مطمئن هستید؟ این عمل قابل بازگشت نیست.')) return;

                                const originalText = button.innerHTML;
                                button.innerHTML = '<span class="mbp-loading" style="width:14px;height:14px;"></span>';
                                button.disabled = true;

                                try {
                                    const formData = new FormData();
                                    formData.append('action', 'mbp_admin_delete_booking');
                                    formData.append('id', appointmentId);
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('🗑️ رزرو حذف شد');

                                        const card = button.closest('.mbp-booking-card');
                                        if (card) {
                                            card.style.animation = 'fadeOut 0.3s ease';
                                            setTimeout(() => card.remove(), 300);
                                        }

                                        updateTotal(-1);
                                    } else {
                                        toast(data.data?.message || 'خطا در حذف رزرو', 'error');
                                        button.innerHTML = originalText;
                                        button.disabled = false;
                                    }
                                } catch (error) {
                                    console.error('Delete booking error:', error);
                                    toast('خطای شبکه در حذف رزرو', 'error');
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }
                            }

                            async function navigateWeek(days) {
                                const scheduleWrap = document.querySelector('.mbp-schedule-wrap');
                                if (!scheduleWrap) return;

                                const currentWeekStart = scheduleWrap.dataset.weekStart;
                                const currentDate = new Date(currentWeekStart);
                                currentDate.setDate(currentDate.getDate() + days);

                                const newWeekStart = currentDate.toISOString().split('T')[0];

                                try {
                                    const formData = new FormData();
                                    formData.append('action', 'mbp_get_schedule_week');
                                    formData.append('week_start', newWeekStart);
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success && data.data.html) {
                                        document.getElementById('mbp-schedule-root').innerHTML = data.data.html;
                                    }
                                } catch (error) {
                                    console.error('Navigate week error:', error);
                                    toast('خطا در بارگذاری هفته', 'error');
                                }
                            }

                            // بارگذاری اولیه
                            const initialView = localStorage.getItem('mbp_active_view') || 'dashboard';
                            const initialItem = document.querySelector(`a.item[data-view="${initialView}"]`) || document.querySelector('a.item.active');

                            if (initialItem) {
                                setActive(initialItem);
                                render(initialItem.dataset.view);
                            } else {
                                render('dashboard');
                            }

                            // ==================== تابع‌های AJAX ====================

                            async function loadServices() {
                                console.log('🔄 Loading services...');
                                const container = document.querySelector('#mbp-view #mbp-services-container');
                                if (!container) {
                                    console.error('❌ Services container not found');
                                    return;
                                }

                                container.innerHTML = `
                                <div style="text-align:center;color:#cbd5e1;padding:40px;">
                                    <div class="mbp-loading" style="width:40px;height:40px;margin:0 auto 15px;"></div>
                                    <div>در حال بارگذاری خدمات و تنظیمات...</div>
                                </div>
                            `;

                                try {
                                    const fd = new FormData();
                                    fd.append('action', 'mbp_get_services');
                                    fd.append('nonce', window.MBP_ADMIN_NONCE);

                                    console.log('📤 Sending AJAX request...');

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: fd
                                    });

                                    console.log('📥 Response status:', response.status, response.statusText);

                                    if (!response.ok) {
                                        throw new Error(`HTTP error! status: ${response.status} ${response.statusText}`);
                                    }

                                    const data = await response.json();
                                    console.log('📊 Response data:', data);

                                    if (data.success && data.data && data.data.html) {
                                        console.log('✅ Services loaded successfully');
                                        container.innerHTML = data.data.html;

                                        // فعال‌سازی event listenerها بعد از لود HTML
                                        setTimeout(initServicesEvents, 50);

                                    } else {
                                        const errorMsg = data.data?.message || 'خطای نامشخص';
                                        console.error('❌ Service load error:', errorMsg);

                                        container.innerHTML = `
                                        <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:30px;color:#ef4444;text-align:center;">
                                            <div style="font-size:18px;font-weight:900;margin-bottom:10px;">⚠️ خطا</div>
                                            <div style="margin-bottom:20px;">${errorMsg}</div>
                                            <button onclick="loadServices()" style="padding:10px 20px;background:#3b82f6;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                                                🔄 تلاش مجدد
                                            </button>
                                        </div>
                                    `;
                                    }

                                } catch (error) {
                                    console.error('❌ Network error:', error);

                                    container.innerHTML = `
                                    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:30px;color:#ef4444;text-align:center;">
                                        <div style="font-size:18px;font-weight:900;margin-bottom:10px;">🌐 خطای شبکه</div>
                                        <div style="margin-bottom:20px;font-size:14px;">${error.message}</div>
                                        <div style="display:flex;gap:10px;justify-content:center;">
                                            <button onclick="loadServices()" style="padding:10px 20px;background:#3b82f6;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                                                🔄 تلاش مجدد
                                            </button>
                                            <button onclick="render('dashboard')" style="padding:10px 20px;background:rgba(255,255,255,.1);color:#cbd5e1;border:1px solid rgba(255,255,255,.2);border-radius:8px;cursor:pointer;font-weight:600;">
                                                ← بازگشت
                                            </button>
                                        </div>
                                    </div>
                                `;
                                }
                            }

                            function initServicesEvents() {
                                console.log('🔧 Initializing services events...');

                                // ========== مدیریت تب‌های داخلی ==========
                                const tabButtons = document.querySelectorAll('.mbp-settings-tab');
                                tabButtons.forEach(tab => {
                                    tab.addEventListener('click', function (e) {
                                        e.preventDefault();
                                        const tabName = this.dataset.tab;
                                        console.log('📌 Inner tab clicked:', tabName);

                                        // حذف active از همه
                                        tabButtons.forEach(t => t.classList.remove('active'));
                                        // اضافه کردن active به تب کلیک شده
                                        this.classList.add('active');

                                        // مخفی کردن همه paneها
                                        document.querySelectorAll('.mbp-tab-pane').forEach(pane => {
                                            pane.classList.remove('active');
                                        });

                                        // نمایش pane مربوطه
                                        const pane = document.getElementById('tab-' + tabName);
                                        if (pane) {
                                            pane.classList.add('active');
                                        }
                                    });
                                });

                                // ========== مدیریت فرم خدمات ==========
                                const serviceForm = document.getElementById('mbp-service-form');
                                if (serviceForm) {
                                    serviceForm.addEventListener('submit', function (e) {
                                        e.preventDefault();
                                        saveService(this);
                                    });
                                }

                                // ========== دکمه افزودن خدمت ==========
                                const addServiceBtn = document.getElementById('mbp-add-service');
                                if (addServiceBtn) {
                                    addServiceBtn.addEventListener('click', function () {
                                        openServiceModal();
                                    });
                                }

                                // ========== دکمه‌های ویرایش ==========
                                document.addEventListener('click', function (e) {
                                    // ویرایش خدمت
                                    if (e.target.classList.contains('mbp-edit-service') || e.target.closest('.mbp-edit-service')) {
                                        e.preventDefault();
                                        const btn = e.target.classList.contains('mbp-edit-service') ? e.target : e.target.closest('.mbp-edit-service');
                                        const serviceId = btn.dataset.id;
                                        editService(serviceId);
                                    }

                                    // فعال/غیرفعال کردن خدمت
                                    if (e.target.classList.contains('mbp-toggle-service') || e.target.closest('.mbp-toggle-service')) {
                                        e.preventDefault();
                                        const btn = e.target.classList.contains('mbp-toggle-service') ? e.target : e.target.closest('.mbp-toggle-service');
                                        const serviceId = btn.dataset.id;
                                        toggleService(serviceId, btn);
                                    }

                                    // حذف خدمت
                                    if (e.target.classList.contains('mbp-delete-service') || e.target.closest('.mbp-delete-service')) {
                                        e.preventDefault();
                                        const btn = e.target.classList.contains('mbp-delete-service') ? e.target : e.target.closest('.mbp-delete-service');
                                        const serviceId = btn.dataset.id;
                                        deleteService(serviceId, btn);
                                    }
                                });

                                // ========== فرم تنظیمات پیامک ==========
                                const smsForm = document.getElementById('mbp-sms-settings-form');
                                if (smsForm) {
                                    smsForm.addEventListener('submit', function (e) {
                                        e.preventDefault();
                                        saveSMSSettings(this);
                                    });
                                }

                                // ========== فرم تنظیمات درگاه ==========
                                const paymentForm = document.getElementById('mbp-payment-settings-form');
                                if (paymentForm) {
                                    paymentForm.addEventListener('submit', function (e) {
                                        e.preventDefault();
                                        savePaymentSettings(this);
                                    });
                                }

                                // ========== فرم تنظیمات عمومی ==========
                                const generalForm = document.getElementById('mbp-general-settings-form');
                                if (generalForm) {
                                    generalForm.addEventListener('submit', function (e) {
                                        e.preventDefault();
                                        saveGeneralSettings(this);
                                    });
                                }

                                // ========== تست پیامک ==========
                                const testSmsBtn = document.getElementById('mbp-test-sms');
                                if (testSmsBtn) {
                                    testSmsBtn.addEventListener('click', testSMS);
                                }

                                console.log('✅ Services events initialized');
                            }

                            // ==================== تابع‌های کمکی خدمات ====================

                            function openServiceModal(serviceData = null) {
                                const modalHTML = `
                                <div class="mbp-modal-overlay" id="mbp-service-modal">
                                    <div class="mbp-modal">
                                        <div class="mbp-modal-header">
                                            <h3 class="mbp-modal-title">${serviceData ? 'ویرایش خدمت' : 'افزودن خدمت جدید'}</h3>
                                            <button class="mbp-modal-close" onclick="closeServiceModal()">×</button>
                                        </div>
                                        <form id="mbp-service-modal-form">
                                            <input type="hidden" name="id" value="${serviceData?.id || ''}">
                                            <div class="mbp-form-group">
                                                <label class="mbp-form-label">نام خدمت *</label>
                                                <input type="text" name="name" class="mbp-form-input" value="${serviceData?.name || ''}" required>
                                            </div>
                                            <div class="mbp-form-group">
                                                <label class="mbp-form-label">توضیحات</label>
                                                <textarea name="description" class="mbp-form-input" rows="3">${serviceData?.description || ''}</textarea>
                                            </div>
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
                                                <div class="mbp-form-group">
                                                    <label class="mbp-form-label">مدت زمان (دقیقه)</label>
                                                    <input type="number" name="duration" class="mbp-form-input" value="${serviceData?.duration || 30}" min="5" step="5">
                                                </div>
                                                <div class="mbp-form-group">
                                                    <label class="mbp-form-label">قیمت (تومان)</label>
                                                    <input type="number" name="price" class="mbp-form-input" value="${serviceData?.price || 0}" min="0" step="1000">
                                                </div>
                                            </div>
                                            <div style="display:flex;gap:10px;margin-top:20px;">
                                                <button type="submit" class="cf-btn primary" style="flex:1;">💾 ذخیره</button>
                                                <button type="button" class="cf-btn ghost" onclick="closeServiceModal()">لغو</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            `;

                                document.body.insertAdjacentHTML('beforeend', modalHTML);

                                // اضافه کردن event listener به فرم
                                const form = document.getElementById('mbp-service-modal-form');
                                form.addEventListener('submit', function (e) {
                                    e.preventDefault();
                                    saveServiceFromModal(this);
                                });
                            }

                            function closeServiceModal() {
                                const modal = document.getElementById('mbp-service-modal');
                                if (modal) {
                                    modal.style.animation = 'fadeOut 0.2s ease';
                                    setTimeout(() => modal.remove(), 200);
                                }
                            }

                            async function saveServiceFromModal(form) {
                                const submitBtn = form.querySelector('button[type="submit"]');
                                const originalText = submitBtn.innerHTML;
                                submitBtn.innerHTML = '<span class="mbp-loading" style="width:16px;height:16px;display:inline-block;margin-left:5px;"></span> در حال ذخیره...';
                                submitBtn.disabled = true;

                                try {
                                    const formData = new FormData(form);
                                    formData.append('action', 'mbp_save_service');
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('✅ خدمت با موفقیت ذخیره شد');
                                        closeServiceModal();
                                        // رفرش لیست خدمات
                                        setTimeout(loadServices, 500);
                                    } else {
                                        toast(data.data?.message || 'خطا در ذخیره خدمت', 'error');
                                    }
                                } catch (error) {
                                    toast('خطای شبکه در ذخیره خدمت', 'error');
                                    console.error('Save service error:', error);
                                } finally {
                                    submitBtn.innerHTML = originalText;
                                    submitBtn.disabled = false;
                                }
                            }

                            function editService(serviceId) {
                                const row = document.querySelector(`tr[data-service-id="${serviceId}"]`);
                                if (!row) return;

                                const serviceData = {
                                    id: serviceId,
                                    name: row.querySelector('td:nth-child(2) strong')?.textContent || '',
                                    description: row.querySelector('td:nth-child(3)')?.textContent || '',
                                    duration: parseInt(row.querySelector('td:nth-child(4)')?.textContent || 30),
                                    price: parseInt((row.querySelector('td:nth-child(5)')?.textContent || '0').replace(/,/g, ''))
                                };

                                openServiceModal(serviceData);
                            }

                            async function toggleService(serviceId, button) {
                                const originalText = button.innerHTML;
                                button.innerHTML = '<span class="mbp-loading" style="width:14px;height:14px;display:inline-block;margin-left:5px;"></span>';
                                button.disabled = true;

                                try {
                                    const formData = new FormData();
                                    formData.append('action', 'mbp_toggle_service');
                                    formData.append('id', serviceId);
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        const newStatus = data.data.new_status;
                                        button.dataset.status = newStatus;
                                        button.textContent = newStatus ? 'غیرفعال' : 'فعال';

                                        const statusSpan = button.closest('tr').querySelector('.service-status');
                                        statusSpan.classList.remove('active', 'inactive');
                                        statusSpan.classList.add(newStatus ? 'active' : 'inactive');
                                        statusSpan.textContent = newStatus ? 'فعال' : 'غیرفعال';

                                        toast(newStatus ? '✅ خدمت فعال شد' : '⚠️ خدمت غیرفعال شد');
                                    } else {
                                        toast(data.data?.message || 'خطا در تغییر وضعیت', 'error');
                                    }
                                } catch (error) {
                                    toast('خطای شبکه در تغییر وضعیت', 'error');
                                    console.error('Toggle service error:', error);
                                } finally {
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }
                            }

                            async function deleteService(serviceId, button) {
                                if (!confirm('⚠️ آیا از حذف این خدمت مطمئن هستید؟ این عمل قابل بازگشت نیست.')) {
                                    return;
                                }

                                const originalText = button.innerHTML;
                                button.innerHTML = '<span class="mbp-loading" style="width:14px;height:14px;display:inline-block;margin-left:5px;"></span>';
                                button.disabled = true;

                                try {
                                    const formData = new FormData();
                                    formData.append('action', 'mbp_delete_service');
                                    formData.append('id', serviceId);
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('🗑️ خدمت با موفقیت حذف شد');
                                        button.closest('tr').style.opacity = '0.5';
                                        setTimeout(() => {
                                            button.closest('tr').remove();
                                        }, 300);
                                    } else {
                                        toast(data.data?.message || 'خطا در حذف خدمت', 'error');
                                    }
                                } catch (error) {
                                    toast('خطای شبکه در حذف خدمت', 'error');
                                    console.error('Delete service error:', error);
                                } finally {
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }
                            }

                            // ==================== تابع‌های تنظیمات ====================

                            async function saveSMSSettings(form) {
                                const submitBtn = form.querySelector('#mbp-sms-save');
                                const originalText = submitBtn.innerHTML;
                                submitBtn.innerHTML = '<span class="mbp-loading" style="width:16px;height:16px;display:inline-block;margin-left:5px;"></span> در حال ذخیره...';
                                submitBtn.disabled = true;

                                try {
                                    const formData = new FormData(form);
                                    formData.append('action', 'mbp_save_sms_settings');
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('✅ تنظیمات پیامک ذخیره شد');
                                    } else {
                                        toast(data.data?.message || 'خطا در ذخیره تنظیمات', 'error');
                                    }
                                } catch (error) {
                                    toast('خطای شبکه در ذخیره تنظیمات', 'error');
                                    console.error('Save SMS settings error:', error);
                                } finally {
                                    submitBtn.innerHTML = originalText;
                                    submitBtn.disabled = false;
                                }
                            }

                            async function savePaymentSettings(form) {
                                const submitBtn = form.querySelector('#mbp-payment-save');
                                const originalText = submitBtn.innerHTML;
                                submitBtn.innerHTML = '<span class="mbp-loading" style="width:16px;height:16px;display:inline-block;margin-left:5px;"></span> در حال ذخیره...';
                                submitBtn.disabled = true;

                                try {
                                    const formData = new FormData(form);
                                    formData.append('action', 'mbp_save_payment_settings');
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('✅ تنظیمات درگاه پرداخت ذخیره شد');
                                    } else {
                                        toast(data.data?.message || 'خطا در ذخیره تنظیمات', 'error');
                                    }
                                } catch (error) {
                                    toast('خطای شبکه در ذخیره تنظیمات', 'error');
                                    console.error('Save payment settings error:', error);
                                } finally {
                                    submitBtn.innerHTML = originalText;
                                    submitBtn.disabled = false;
                                }
                            }

                            async function saveGeneralSettings(form) {
                                const submitBtn = form.querySelector('#mbp-general-save');
                                const originalText = submitBtn.innerHTML;
                                submitBtn.innerHTML = '<span class="mbp-loading" style="width:16px;height:16px;display:inline-block;margin-left:5px;"></span> در حال ذخیره...';
                                submitBtn.disabled = true;

                                try {
                                    const formData = new FormData(form);
                                    formData.append('action', 'mbp_save_general_settings');
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        toast('✅ تنظیمات عمومی ذخیره شد');
                                    } else {
                                        toast(data.data?.message || 'خطا در ذخیره تنظیمات', 'error');
                                    }
                                } catch (error) {
                                    toast('خطای شبکه در ذخیره تنظیمات', 'error');
                                    console.error('Save general settings error:', error);
                                } finally {
                                    submitBtn.innerHTML = originalText;
                                    submitBtn.disabled = false;
                                }
                            }

                            async function testSMS() {
                                const phone = prompt('شماره موبایل برای تست پیامک را وارد کنید:');
                                if (!phone || !/^09[0-9]{9}$/.test(phone)) {
                                    alert('⚠️ شماره موبایل معتبر وارد کنید (مثال: 09123456789)');
                                    return;
                                }

                                const button = document.getElementById('mbp-test-sms');
                                const originalText = button.innerHTML;
                                button.innerHTML = '<span class="mbp-loading" style="width:14px;height:14px;display:inline-block;margin-left:5px;"></span> در حال ارسال...';
                                button.disabled = true;

                                try {
                                    const formData = new FormData();
                                    formData.append('action', 'mbp_test_sms');
                                    formData.append('phone', phone);
                                    formData.append('nonce', window.MBP_ADMIN_NONCE);

                                    const response = await fetch(window.MBP_AJAX_URL, {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        alert('✅ پیامک تست با موفقیت ارسال شد');
                                    } else {
                                        alert('❌ ' + (data.data?.message || 'خطا در ارسال پیامک'));
                                    }
                                } catch (error) {
                                    alert('❌ خطای شبکه در ارسال پیامک');
                                    console.error('Test SMS error:', error);
                                } finally {
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }
                            }

                            // ==================== تابع time slots ====================

                            async function initSlots() {
                                const ta = document.querySelector('#mbp-view #mbp-slots-text');
                                const btnSave = document.querySelector('#mbp-view #mbp-slots-save');
                                const btnLoad = document.querySelector('#mbp-view #mbp-slots-load');
                                if (!ta || !btnSave || !btnLoad) return;

                                async function load() {
                                    const fd = new FormData();
                                    fd.append('action', 'mbp_get_time_slots');
                                    fd.append('nonce', window.MBP_ADMIN_NONCE);

                                    btnSave.disabled = true;
                                    btnLoad.disabled = true;

                                    try {
                                        const res = await fetch(window.MBP_AJAX_URL, {
                                            method: "POST",
                                            body: fd
                                        });

                                        if (!res.ok) {
                                            throw new Error(`HTTP error! status: ${res.status}`);
                                        }

                                        const data = await res.json();

                                        if (!data.success) {
                                            toast(data?.data?.message || 'خطا در دریافت اطلاعات', 'error');
                                            return;
                                        }

                                        if (data.data && data.data.slots) {
                                            ta.value = data.data.slots.join("\n");
                                        } else {
                                            ta.value = "09:00\n09:30\n10:00\n10:30\n11:00\n11:30\n12:00";
                                        }
                                    } catch (err) {
                                        console.error('Error loading slots:', err);
                                        toast('خطای شبکه در بارگذاری اسلات‌ها', 'error');
                                        ta.value = "09:00\n09:30\n10:00\n10:30\n11:00\n11:30\n12:00";
                                    } finally {
                                        btnSave.disabled = false;
                                        btnLoad.disabled = false;
                                    }
                                }

                                btnLoad.onclick = (e) => {
                                    e.preventDefault();
                                    load();
                                };

                                btnSave.onclick = async (e) => {
                                    e.preventDefault();
                                    btnSave.disabled = true;
                                    const fd = new FormData();
                                    fd.append('action', 'mbp_save_time_slots');
                                    fd.append('nonce', window.MBP_ADMIN_NONCE);
                                    fd.append('slots_text', ta.value || '');

                                    try {
                                        const res = await fetch(window.MBP_AJAX_URL, {
                                            method: "POST",
                                            body: fd
                                        });

                                        if (!res.ok) {
                                            throw new Error(`HTTP error! status: ${res.status}`);
                                        }

                                        const data = await res.json();

                                        if (!data.success) {
                                            toast(data?.data?.message || 'خطا در ذخیره', 'error');
                                            return;
                                        }

                                        toast('✅ ذخیره شد');
                                        load(); // بارگذاری مجدد
                                    } catch (err) {
                                        console.error('Error saving slots:', err);
                                        toast('خطای شبکه در ذخیره اسلات‌ها', 'error');
                                    } finally {
                                        btnSave.disabled = false;
                                    }
                                };

                                load(); // بارگذاری اولیه
                            }

                        } catch (err) {
                            hardFail('اسکریپت پنل کرش کرد. Console را هم چک کن.', err);
                        }
                    });
                })();
            </script>

        </body>

        </html>
        <?php
        exit;
    }

    public function ajax_get_time_slots()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        $nonce = '';
        if (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        } elseif (isset($_GET['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        // دریافت time slots
        $slots = $this->get_time_slots();

        wp_send_json_success(array('slots' => $slots));
    }

    public function ajax_save_time_slots()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        $nonce = '';
        if (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        } elseif (isset($_GET['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $raw = isset($_POST['slots_text']) ? wp_unslash($_POST['slots_text']) : '';
        $lines = preg_split("/\r\n|\n|\r/", (string) $raw);

        $slots = array();
        foreach ($lines as $line) {
            $t = trim((string) $line);
            if ($t === '')
                continue;
            if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $t))
                continue;
            $slots[] = $t;
        }

        $slots = array_values(array_unique($slots));
        usort($slots, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        if (empty($slots)) {
            wp_send_json_error(array('message' => 'حداقل یک ساعت معتبر وارد کنید مثل 09:00'));
        }

        update_option(self::OPTION_TIME_SLOTS, $slots, false);
        wp_send_json_success(array(
            'message' => 'ذخیره شد',
            'slots' => $slots
        ));
    }


    public function ajax_public_get_schedule_week()
    {
        if (!$this->license_is_ok()) {
            wp_send_json_error(array('message' => 'لایسنس فعال نیست'));
        }

        $week_start = isset($_POST['week_start']) ? sanitize_text_field($_POST['week_start']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            wp_send_json_error(array('message' => 'تاریخ نامعتبر است'));
        }

        $skin = isset($_POST['skin']) ? sanitize_text_field($_POST['skin']) : '';
        $class = isset($_POST['class']) ? sanitize_text_field($_POST['class']) : '';

        $tz = wp_timezone();
        $ws = new DateTime($week_start . ' 00:00:00', $tz);
        $we = clone $ws;
        $we->modify('+6 day');

        $appointments = $this->get_appointments_for_range($ws->format('Y-m-d'), $we->format('Y-m-d'));
        $html = $this->render_public_schedule_html($appointments, $ws->format('Y-m-d'), $skin, $class);

        wp_send_json_success(array('html' => $html, 'week_start' => $ws->format('Y-m-d')));
    }

    private function render_public_schedule_html($appointments, $week_start_ymd, $skin = '', $extra_class = '')
    {
        $tz = wp_timezone();
        $week_start = new DateTime($week_start_ymd . ' 00:00:00', $tz);

        $days = array();
        for ($i = 0; $i < 7; $i++) {
            $d = clone $week_start;
            $d->modify("+{$i} day");
            $days[] = $d;
        }

        $slots = $this->get_time_slots();

        $index = array();
        foreach ((array) $appointments as $a) {
            if (empty($a->time))
                continue;
            $dt = new DateTime($a->time, $tz);
            $dayKey = $dt->format('Y-m-d');
            $timeKey = $dt->format('H:i');
            if (!isset($index[$dayKey]))
                $index[$dayKey] = array();
            $index[$dayKey][$timeKey] = true;
        }

        ob_start(); ?>
        <div class="mbp-public-wrap" data-week-start="<?php echo esc_attr($week_start_ymd); ?>">
            <div class="mbp-public-toolbar">
                <div class="mbp-public-title">
                    شروع جدول از:
                    <strong><?php echo esc_html($this->fa_date_from_timestamp($week_start->getTimestamp(), 'Y/m/d', true)); ?></strong>
                </div>
            </div>

            <div class="mbp-public-scroll">
                <table class="mbp-public-schedule">
                    <thead>
                        <tr>
                            <th class="mbp-public-corner">ساعت</th>
                            <?php foreach ($days as $d): ?>
                                <th>
                                    <div class="mbp-public-day">
                                        <div class="mbp-public-day-name">
                                            <?php echo esc_html($this->fa_weekday_from_timestamp($d->getTimestamp())); ?>
                                        </div>
                                        <div class="mbp-public-day-date">
                                            <?php echo esc_html($this->fa_date_from_timestamp($d->getTimestamp(), 'Y/m/d', true)); ?>
                                        </div>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td class="mbp-public-time"><?php echo esc_html($slot); ?></td>

                                <?php foreach ($days as $d):
                                    $dayKey = $d->format('Y-m-d');
                                    $isBooked = !empty($index[$dayKey][$slot]);
                                    ?>
                                    <td class="mbp-public-cell <?php echo $isBooked ? 'booked' : 'free'; ?>"
                                        data-day="<?php echo esc_attr($dayKey); ?>" data-slot="<?php echo esc_attr($slot); ?>">
                                        <?php echo $isBooked ? 'پر' : 'خالی'; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================
// PUBLIC SCHEDULE SHORTCODE - MAIN ENTRY POINT
// =========================
    public function render_public_schedule($atts = array())
    {
        if (!$this->license_is_ok()) {
            return $this->render_license_required_box('front');
        }

        // پردازش پارامترهای شورتکد
        $atts = shortcode_atts(array(
            'show_form' => '0',
            'skin' => 'mbp-skin',
            'class' => '',
            'week_start' => '',
        ), $atts, 'mbp_public_schedule');

        // دریافت تاریخ شروع هفته
        $week_start_ymd = $atts['week_start'];
        if (empty($week_start_ymd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start_ymd)) {
            $week_start_ymd = wp_date('Y-m-d');
        }

        // دریافت رزروهای هفته
        $tz = wp_timezone();
        $ws = new DateTime($week_start_ymd . ' 00:00:00', $tz);
        $we = clone $ws;
        $we->modify('+6 day');

        $appointments = $this->get_appointments_for_range($ws->format('Y-m-d'), $we->format('Y-m-d'));

        // استفاده از تابع render_public_schedule_html که از قبل دارید
        $schedule_html = $this->render_public_schedule_html(
            $appointments,
            $week_start_ymd,
            $atts['skin'],
            $atts['class']
        );

        // اضافه کردن فرم رزرو (اگر show_form=1 باشد)
        $output = $schedule_html;

        if ($atts['show_form'] === '1') {
            $output .= '<div style="margin-top:30px;padding:20px;border:1px solid var(--mbp-border);border-radius:12px;background:var(--mbp-bg);">';
            $output .= '<h3 style="margin-top:0;">رزرو نوبت</h3>';
            $output .= $this->render_booking_form();
            $output .= '</div>';
        }

        // اضافه کردن اسکریپت ناوبری
        $ajax_url = esc_js(admin_url('admin-ajax.php'));
        $nonce = wp_create_nonce('mbp_booking_nonce');

        $output .= '
    <script>
    jQuery(function($) {
        // ناوبری هفته
        $(document).on("click", ".mbp-nav-week", function() {
            const delta = parseInt($(this).data("delta"), 10);
            const wrap = $(this).closest(".mbp-public-wrap");
            const currentWeek = wrap.data("week-start");
            const skin = wrap.data("skin") || "";
            const extra = wrap.data("extra") || "";
            
            const currentDate = new Date(currentWeek + "T00:00:00");
            currentDate.setDate(currentDate.getDate() + delta);
            const newWeek = currentDate.toISOString().split("T")[0];
            
            // بارگذاری هفته جدید
            $.post("' . $ajax_url . '", {
                action: "mbp_public_get_schedule_week",
                week_start: newWeek,
                skin: skin,
                class: extra,
                nonce: "' . $nonce . '"
            }, function(response) {
                if(response.success){
                    wrap.replaceWith(response.data.html);
                }
            });
        });
        
        // برگشت به امروز
        $(document).on("click", ".mbp-nav-today", function() {
            const wrap = $(this).closest(".mbp-public-wrap");
            const skin = wrap.data("skin") || "";
            const extra = wrap.data("extra") || "";
            const today = new Date().toISOString().split("T")[0];
            
            $.post("' . $ajax_url . '", {
                action: "mbp_public_get_schedule_week",
                week_start: today,
                skin: skin,
                class: extra,
                nonce: "' . $nonce . '"
            }, function(response) {
                if(response.success){
                    wrap.replaceWith(response.data.html);
                }
            });
        });
        
        // کلیک روی خانه خالی
        $(document).on("click", ".mbp-public-cell.free", function() {
            const day = $(this).data("day");
            const slot = $(this).data("slot");
            
            // پر کردن فرم رزرو (اگر در صفحه وجود دارد)
            if ($("#mbp-date").length) {
                $("#mbp-date").val(day);
                $("#mbp-slot").val(slot);
                
                // اسکرول به فرم
                $("html, body").animate({
                    scrollTop: $("#mbp-booking-form").offset().top - 100
                }, 500);
            }
        });
    });
    </script>
    ';

        return $output;
    }
    // =========================
    // EXISTING METHODS (کامنت برای خلاصه)
    // =========================

    // این متدها از قبل وجود دارند و کدشان طولانی است:
    // - ajax_get_time_slots()
    // - ajax_save_time_slots()
    // - ajax_public_get_schedule_week()
    // - ajax_get_schedule_week()
    // - render_public_schedule()
    // - render_public_schedule_html()
    // - get_appointments_for_range()
    // - render_schedule_grid_html()
    // - schedule_settings()
    // و چندین متد کمکی دیگر

    // Admin assets
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'mbp-') === false) {
            return;
        }

        // استایل برای تب‌ها
        $admin_css = '
    .mbp-settings-tabs {
        margin: 20px 0;
    }
    .mbp-tabs-nav {
        display: flex;
        gap: 5px;
        border-bottom: 2px solid #ccd0d4;
        margin-bottom: 20px;
    }
    .mbp-tab-btn {
        padding: 8px 16px;
        background: #f0f0f1;
        border: 1px solid #ccd0d4;
        border-bottom: none;
        border-radius: 4px 4px 0 0;
        cursor: pointer;
        font-weight: 600;
        color: #3c434a;
        text-decoration: none;
    }
    .mbp-tab-btn:hover {
        background: #e6e6e6;
    }
    .mbp-tab-btn.active {
        background: #fff;
        color: #2271b1;إ
        position: relative;
        top: 2px;
        margin-bottom: -2px;
    }
    .mbp-tab-content {
        display: none;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 0 4px 4px 4px;
        padding: 20px;
    }
    .mbp-tab-content.active {
        display: block;
    }
    .service-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
    }
    .service-status.active {
        background: #d1fae5;
        color: #065f46;
    }
    .service-status.inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    ';

        wp_add_inline_style('wp-admin', $admin_css);
    }

}