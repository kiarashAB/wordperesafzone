<?php
if (!defined('ABSPATH'))
    exit;

class MBP_Core
{
    const OPTION_SCHEDULE_SETTINGS = 'mbp_schedule_settings_v1';
    const OPTION_TIME_SLOTS = 'mbp_time_slots_v1';

    public function __construct()
    {
        // Admin Menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Shortcodes
        add_shortcode('my_booking_form', array($this, 'render_booking_form'));
        add_shortcode('mbp_public_schedule', array($this, 'render_public_schedule'));

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

        // Schedule week ajax (admin)
        add_action('wp_ajax_mbp_get_schedule_week', array($this, 'ajax_get_schedule_week'));

        // Time slots (admin)
        add_action('wp_ajax_mbp_get_time_slots', array($this, 'ajax_get_time_slots'));
        add_action('wp_ajax_mbp_save_time_slots', array($this, 'ajax_save_time_slots'));
    }

    public function run()
    {
    }

    /**
     * Front JS + CSS
     * - hover for buttons
     * - form styles (NO inline style anymore)
     * - keeps skin/class across week navigation Ajax
     */
    public function enqueue_assets()
    {
        // ---------- CSS ----------
        $css = <<<CSS
/* ========= MBP Front Skin Defaults (optional) ========= */
.mbp-skin{
  --mbp-bg: #ffffff;
  --mbp-text: #111827;
  --mbp-border: #e5e7eb;
  --mbp-cell-border: #f1f5f9;
  --mbp-head-bg: #ffffff;

  --mbp-free-bg: #ecfdf5;
  --mbp-free-text: #065f46;

  --mbp-booked-bg: #fef2f2;
  --mbp-booked-text: #991b1b;

  --mbp-btn-bg: #f9fafb;
  --mbp-btn-text: #111827;
  --mbp-btn-border: #d1d5db;

  --mbp-btn-bg-hover: #f3f4f6;
  --mbp-btn-text-hover: #111827;
  --mbp-btn-border-hover: #cbd5e1;

  --mbp-input-bg: #ffffff;
  --mbp-input-text: #111827;
  --mbp-input-border: #d1d5db;
  --mbp-input-border-hover: #cbd5e1;

  --mbp-focus: #60a5fa;
}

/* ========= Public Schedule ========= */
.mbp-public-wrap{ margin:12px 0; direction:rtl; }

.mbp-public-toolbar{
  display:flex; gap:10px; align-items:center; justify-content:space-between;
  padding:10px;
  border:1px solid var(--mbp-border, #e5e7eb);
  border-radius:12px;
  background:var(--mbp-bg, #ffffff);
  color: var(--mbp-text, #111827);
}

.mbp-public-title strong{ font-weight:900; }

.mbp-public-nav{
  padding:8px 12px;
  border-radius:20px;
  cursor:pointer;
  border:1px solid var(--mbp-btn-border, #d1d5db);
  background:var(--mbp-btn-bg, #f9fafb);
  color: var(--mbp-btn-text, #111827);
  font-weight:900;
  transition: .16s ease;
}

.mbp-public-nav:hover{
  background: var(--mbp-btn-bg-hover, var(--mbp-btn-bg, #f3f4f6));
  color: var(--mbp-btn-text-hover, var(--mbp-btn-text, #111827));
  border-color: var(--mbp-btn-border-hover, var(--mbp-btn-border, #cbd5e1));
  transform: translateY(-1px);
}

.mbp-public-nav:active{ transform: translateY(0); }

.mbp-public-nav:disabled{
  opacity:.6;
  cursor:not-allowed;
  transform:none;
}

.mbp-public-nav:focus-visible{
  outline: 2px solid var(--mbp-focus, #60a5fa);
  outline-offset: 2px;
}

.mbp-public-scroll{
  overflow:auto;
  margin-top:10px;
  border:1px solid var(--mbp-border, #e5e7eb);
  border-radius:12px;
  background:var(--mbp-bg, #ffffff);
}

table.mbp-public-schedule{ width:100%; border-collapse:separate; border-spacing:0; min-width:900px; }

.mbp-public-schedule th,.mbp-public-schedule td{
  border-bottom:1px solid var(--mbp-cell-border, #f1f5f9);
  border-left:1px solid var(--mbp-cell-border, #f1f5f9);
  padding:10px;
  text-align:center;
  vertical-align:middle;
  background: var(--mbp-bg, #ffffff);
  color: var(--mbp-text, #111827);
}

.mbp-public-corner,.mbp-public-time{
  position:sticky;
  right:0;
  background:var(--mbp-head-bg, #ffffff);
  font-weight:900;
  z-index:2;
}

.mbp-public-schedule thead th{
  position:sticky;
  top:0;
  background:var(--mbp-head-bg, #ffffff);
  z-index:3;
}

.mbp-public-cell{
  transition: .14s ease;
}

.mbp-public-cell.free{
  background: var(--mbp-free-bg, #ecfdf5);
  color: var(--mbp-free-text, #065f46);
  cursor:pointer;
  font-weight:900;
}

.mbp-public-cell.booked{
  background: var(--mbp-booked-bg, #fef2f2);
  color: var(--mbp-booked-text, #991b1b);
  font-weight:900;
}

.mbp-public-cell.free:hover{
  filter: brightness(0.97);
  transform: translateY(-1px);
}

.mbp-public-cell.free:active{
  transform: translateY(0);
}

.mbp-public-day-date{ font-size:12px; opacity:.75; margin-top:4px; }

/* ========= Booking Form ========= */
.mbp-form-wrap{
  direction: rtl;
  margin-top: 14px;
  padding: 12px;
  border: 1px solid var(--mbp-border, #e5e7eb);
  border-radius: 12px;
  background: var(--mbp-bg, #ffffff);
  color: var(--mbp-text, #111827);
}

.mbp-form-title{
  margin:0 0 10px 0;
  font-weight:900;
  color: var(--mbp-text, #111827);
}

.mbp-field{ margin:0 0 12px 0; }

.mbp-label{
  display:block;
  margin-bottom:6px;
  font-weight:800;
  font-size:13px;
}

.mbp-input, .mbp-select{
  width:100%;
  padding:10px;
  border:1px solid var(--mbp-input-border, var(--mbp-border, #d1d5db));
  border-radius:10px;
  background: var(--mbp-input-bg, #ffffff);
  color: var(--mbp-input-text, var(--mbp-text, #111827));
  box-sizing:border-box;
  outline:none;
  transition: .16s ease;
}

.mbp-input:hover, .mbp-select:hover{
  border-color: var(--mbp-input-border-hover, var(--mbp-input-border, #cbd5e1));
}

.mbp-input:focus, .mbp-select:focus,
.mbp-input:focus-visible, .mbp-select:focus-visible{
  border-color: var(--mbp-focus, #60a5fa);
  outline: 2px solid var(--mbp-focus, #60a5fa);
  outline-offset: 1px;
}

.mbp-ltr{ direction:ltr; text-align:left; }

.mbp-submit{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:10px 16px;
  border-radius:10px;
  border:1px solid var(--mbp-btn-border, #d1d5db);
  color:  #fff;
  background:#5c5c5cff;
  cursor:pointer;
  font-weight:900;
  transition: .18s ease;
}

.mbp-submit:hover{
  background: #454545ff
  border-color: var(--mbp-btn-border-hover, var(--mbp-btn-border, #d1d5db));
  transform: translateY(-1px);
}

.mbp-submit:active{ transform: translateY(0); }

.mbp-submit:focus-visible{
  outline: 2px solid var(#60a5fa);
  outline-offset: 2px;
}

/* ========= Result Message ========= */
#mbp-result.mbp-result{
  margin-top:12px;
  padding:10px;
  border-radius:10px;
  border:1px solid var(--mbp-border, #c3c4c7);
  background: var(--mbp-bg, #fff);
  color: var(--mbp-text, #111827);
}
#mbp-result.mbp-result-success{
  border-color: rgba(0,163,42,.45);
}
#mbp-result.mbp-result-error{
  border-color: rgba(214,54,56,.55);
}
CSS;

        wp_register_style('mbp-front-inline', false);
        wp_enqueue_style('mbp-front-inline');
        wp_add_inline_style('mbp-front-inline', $css);

        // ---------- JS ----------
        $ajax_url = esc_js(admin_url('admin-ajax.php'));

        $inline_script = <<<JS
jQuery(function($){
  const ajax = '{$ajax_url}';

  // Submit booking form
  $(document).on('submit', '#mbp-booking-form', function(e){
    e.preventDefault();
    var \$form = $(this);
    var data = \$form.serialize();
    $('#mbp-result').remove();

    $.post(ajax, data)
      .done(function(res){
        var msg = (res && res.data && res.data.message) ? res.data.message : 'ثبت شد';
        var cls = (res && res.success) ? 'mbp-result-success' : 'mbp-result-error';
        \$form.after('<div id="mbp-result" class="mbp-result '+cls+'">'+ msg +'</div>');
        if(res && res.success){ \$form[0].reset(); }
      })
      .fail(function(){
        \$form.after('<div id="mbp-result" class="mbp-result mbp-result-error">خطا در ارسال فرم</div>');
      });
  });

  // Click free cell -> fill form
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

  // Public week navigation (keeps skin/class)
  $(document).on('click', '.mbp-public-nav', function(){
    var \$btn  = $(this);
    var \$wrap = \$btn.closest('.mbp-public-wrap');
    var current = \$wrap.attr('data-week-start');
    var delta = parseInt(\$btn.attr('data-week-nav'), 10) || 0;
    if(!current) return;

    var d = new Date(current + 'T00:00:00');
    d.setDate(d.getDate() + delta);
    var next = d.toISOString().slice(0,10);

    // keep skin/class from data attrs
    var skin  = \$wrap.attr('data-skin') || '';
    var extra = \$wrap.attr('data-extra') || '';

    \$btn.prop('disabled', true);

    var fd = new FormData();
    fd.append('action', 'mbp_public_get_schedule_week');
    fd.append('week_start', next);
    fd.append('skin', skin);
    fd.append('class', extra);

    fetch(ajax, { method:'POST', body: fd })
      .then(r => r.json())
      .then(function(res){
        if(!res.success){
          alert((res && res.data && res.data.message) ? res.data.message : 'خطا');
          return;
        }
        \$wrap.replaceWith(res.data.html);
      })
      .catch(function(){
        alert('خطای شبکه');
      })
      .finally(function(){
        // اگر replaceWith انجام شده باشه، این دکمه دیگه وجود نداره
        if(\$btn.closest('body').length){
          \$btn.prop('disabled', false);
        }
      });
  });
});
JS;

        // safest: attach to jquery (no empty src script tag)
        wp_enqueue_script('jquery');
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
        wp_redirect(admin_url('admin-post.php?action=mbp_dashboard_app'));
        exit;
    }

    // =========================
    // Admin AJAX approve/delete
    // =========================
    public function admin_approve_booking()
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

    // =========================
    // Helpers: digits / jalali
    // =========================
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
        $w = (int) wp_date('w', $timestamp); // 0=Sun ... 6=Sat
        $weekday_fa = array('یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه');
        return $weekday_fa[$w] ?? '';
    }

    // =========================
    // Time slots
    // =========================
    

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

        // اعتبارسنجی دقیق ساعت و دقیقه (09:00)
        if (preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $t)) {
            $clean[] = $t;
        }
    }

    $clean = array_values(array_unique($clean));

    // مرتب‌سازی بر اساس زمان واقعی
    usort($clean, function ($a, $b) {
        return strtotime($a) - strtotime($b);
    });

    return !empty($clean) ? $clean : $default;
}

public function ajax_get_time_slots()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'دسترسی ندارید'));
    }

    // nonce ممکنه با GET یا POST بیاد
    $nonce = '';
    if (isset($_POST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    } elseif (isset($_GET['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
    }

    if (!$nonce || !wp_verify_nonce($nonce, 'mbp_admin_action_nonce')) {
        wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
    }

    wp_send_json_success(array(
        'slots' => $this->get_time_slots()
    ));
}

public function ajax_save_time_slots()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'دسترسی ندارید'));
    }

    // nonce ممکنه با GET یا POST بیاد
    $nonce = '';
    if (isset($_POST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    } elseif (isset($_GET['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
    }

    if (!$nonce || !wp_verify_nonce($nonce, 'mbp_admin_action_nonce')) {
        wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
    }

    $raw   = isset($_POST['slots_text']) ? wp_unslash($_POST['slots_text']) : '';
    $lines = preg_split("/\r\n|\n|\r/", (string) $raw);

    $slots = array();
    foreach ($lines as $line) {
        $t = trim((string) $line);
        if ($t === '') {
            continue;
        }

        // اعتبارسنجی دقیق (09:00)
        if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $t)) {
            continue;
        }

        $slots[] = $t;
    }

    $slots = array_values(array_unique($slots));

    // مرتب‌سازی بر اساس زمان واقعی
    usort($slots, function ($a, $b) {
        return strtotime($a) - strtotime($b);
    });

    if (empty($slots)) {
        wp_send_json_error(array('message' => 'حداقل یک ساعت معتبر وارد کنید مثل 09:00'));
    }

    update_option(self::OPTION_TIME_SLOTS, $slots, false);

    wp_send_json_success(array(
        'message' => 'ذخیره شد',
        'slots'   => $slots
    ));
}

    // =========================
    // Schedule settings
    // =========================
    private function schedule_settings()
    {
        $defaults = array(
            'week_start' => 'saturday', // saturday | monday
        );
        $opt = get_option(self::OPTION_SCHEDULE_SETTINGS, array());
        if (!is_array($opt))
            $opt = array();
        return array_merge($defaults, $opt);
    }

    private function get_week_start_ymd($settings, $base_ymd = null)
    {
        $tz = wp_timezone();
        $dt = $base_ymd ? new DateTime($base_ymd . ' 00:00:00', $tz) : new DateTime('now', $tz);

        $w = (int) $dt->format('w'); // 0=Sun, 1=Mon, ..., 6=Sat

        if ($settings['week_start'] === 'monday') {
            $diff = ($w === 0) ? 6 : ($w - 1);
            $dt->modify("-{$diff} day");
        } else {
            if ($w === 0) {
                $dt->modify('-1 day');
            } else {
                $diff = ($w - 6);
                $dt->modify("-{$diff} day");
            }
        }
        return $dt->format('Y-m-d');
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
        if (is_array($appointments)) {
            foreach ($appointments as $a) {
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
        }

        ob_start();
        ?>
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
                                            <?php echo esc_html($this->fa_weekday_from_timestamp($d->getTimestamp())); ?></div>
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
                                                    </div>

                                                    <div class="mbp-booking-actions">
                                                        <?php if (!$is_approved): ?>
                                                            <button class="mbp-btn mbp-approve"
                                                                data-id="<?php echo esc_attr($a->id); ?>">تایید</button>
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

    public function ajax_get_schedule_week()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است'));
        }

        $week_start = isset($_POST['week_start']) ? sanitize_text_field($_POST['week_start']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            wp_send_json_error(array('message' => 'تاریخ نامعتبر است'));
        }

        $settings = $this->schedule_settings();

        $tz = wp_timezone();
        $ws = new DateTime($week_start . ' 00:00:00', $tz);
        $we = clone $ws;
        $we->modify('+6 day');

        $appointments = $this->get_appointments_for_range($ws->format('Y-m-d'), $we->format('Y-m-d'));
        $html = $this->render_schedule_grid_html($appointments, $ws->format('Y-m-d'), $settings);

        wp_send_json_success(array(
            'html' => $html,
            'week_start' => $ws->format('Y-m-d'),
        ));
    }

    // =========================
    // PUBLIC SCHEDULE (FRONT)
    // =========================
    public function render_public_schedule($atts = array())
    {
        $atts = shortcode_atts(array(
            'show_form' => '1',
            'skin' => '',
            'class' => '',
        ), $atts);

        $skin = trim((string) $atts['skin']);
        $extra = trim((string) $atts['class']);

        // Wrapper classes (برای اینکه فرم هم از همون متغیرها استفاده کنه)
        $wrap_classes = array();
        foreach (preg_split('/\s+/', $skin) as $c) {
            $c = trim($c);
            if ($c !== '')
                $wrap_classes[] = sanitize_html_class($c);
        }
        foreach (preg_split('/\s+/', $extra) as $c) {
            $c = trim($c);
            if ($c !== '')
                $wrap_classes[] = sanitize_html_class($c);
        }
        $wrap_class_attr = implode(' ', array_unique($wrap_classes));

        $settings = $this->schedule_settings();
        $week_start_ymd = wp_date('Y-m-d'); // امروز = اولین روز جدول

        $tz = wp_timezone();
        $ws = new DateTime($week_start_ymd . ' 00:00:00', $tz);
        $we = clone $ws;
        $we->modify('+6 day');

        $appointments = $this->get_appointments_for_range($ws->format('Y-m-d'), $we->format('Y-m-d'));

        ob_start();
        if ($wrap_class_attr !== '') {
            echo '<div class="' . esc_attr($wrap_class_attr) . '">';
        }

        echo $this->render_public_schedule_html($appointments, $week_start_ymd, $skin, $extra);

        if ($atts['show_form'] === '1') {
            echo '<div style="margin-top:18px;">' . $this->render_booking_form(array()) . '</div>';
        }

        if ($wrap_class_attr !== '') {
            echo '</div>';
        }

        return ob_get_clean();
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

        // index [Y-m-d][H:i] => true
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

        $skin = sanitize_text_field((string) $skin);
        $extra_class = sanitize_text_field((string) $extra_class);

        ob_start();
        ?>
        <div class="mbp-public-wrap" data-week-start="<?php echo esc_attr($week_start_ymd); ?>"
            data-skin="<?php echo esc_attr($skin); ?>" data-extra="<?php echo esc_attr($extra_class); ?>">
            <div class="mbp-public-toolbar" style="display: flex; justify-content: center; align-items: center;">
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
                                            <?php echo esc_html($this->fa_weekday_from_timestamp($d->getTimestamp())); ?></div>
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

    public function ajax_public_get_schedule_week()
    {
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

        wp_send_json_success(array(
            'html' => $html,
            'week_start' => $ws->format('Y-m-d'),
        ));
    }

    // =========================
    // FORM SHORTCODE (UPDATED: NO INLINE STYLES)
    // =========================
    public function render_booking_form($atts = array())
    {
        $slots = $this->get_time_slots();

        ob_start();
        ?>
        <div class="">
            <h3 class="mbp-form-title"><?php esc_html_e('فرم رزرو', 'my-booking-plugin'); ?></h3>

            <form id="mbp-booking-form" class="mbp-form" method="post">
                <div style="display: flex; justify-content: space-around;">

                    <p class="mbp-field">
                        <label for="mbp-name"
                            class="mbp-label"><?php esc_html_e('نام و نام خا‌نوادگی:', 'my-booking-plugin'); ?></label>
                        <input type="text" dir="rtl" id="mbp-name" name="customer_name" required class="mbp-input mbp-ltr"
                            style="border-radius: 10px; border: solid 1.5px #cbd5e1; background-color: #dbdbdbff;">
                    </p>

                    <p class="mbp-field">
                        <label for="mbp-email" class="mbp-label"><?php esc_html_e('ایمیل:', 'my-booking-plugin'); ?></label>
                        <input type="email" id="mbp-email" name="customer_email" required class="mbp-input mbp-ltr"
                            style="border-radius: 10px; border: solid 1.5px #cbd5e1; background-color: #dbdbdbff;">
                    </p>
                </div>

                <div style="display: flex; justify-content: space-around;">

                    <p class="mbp-field">
                        <label for="mbp-date" class="mbp-label"><?php esc_html_e('تاریخ:', 'my-booking-plugin'); ?></label>
                        <input type="date" id="mbp-date" name="date" required class="mbp-input mbp-ltr "
                            style="border-radius: 10px; border: solid 1.5px #cbd5e1; background-color: #dbdbdbff;">
                    </p>

                    <p class="mbp-field">
                        <label for="mbp-slot" class="mbp-label"><?php esc_html_e('ساعت:', 'my-booking-plugin'); ?></label>
                        <select id="mbp-slot" name="slot" required class="mbp-select">
                            <option value=""><?php esc_html_e('انتخاب کنید', 'my-booking-plugin'); ?></option>
                            <?php foreach ($slots as $s): ?>
                                <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <input type="hidden" name="action" value="mbp_submit_booking">
                <?php wp_nonce_field('mbp_booking_submit_action', 'mbp_booking_nonce_field'); ?>

                <button class="mbp-submit">
                    <?php esc_html_e('ثبت', 'my-booking-plugin'); ?>
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_booking_submit()
    {
        if (
            !isset($_POST['mbp_booking_nonce_field']) ||
            !wp_verify_nonce($_POST['mbp_booking_nonce_field'], 'mbp_booking_submit_action')
        ) {
            wp_send_json_error(array('message' => 'Nonce نامعتبر است.'));
        }

        $name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
        $email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $slot = isset($_POST['slot']) ? sanitize_text_field($_POST['slot']) : '';
        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;

        if ($name === '' || $email === '' || !is_email($email) || $date === '' || $slot === '') {
            wp_send_json_error(array('message' => 'اطلاعات فرم ناقص یا ایمیل نامعتبر است.'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => 'تاریخ نامعتبر است.'));
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $slot)) {
            wp_send_json_error(array('message' => 'ساعت نامعتبر است.'));
        }

        $allowed = $this->get_time_slots();
        if (!in_array($slot, $allowed, true)) {
            wp_send_json_error(array('message' => 'این ساعت در لیست ساعت‌های مجاز نیست.'));
        }

        $dt = $date . ' ' . $slot . ':00';
        $dt = date('Y-m-d H:i:s', strtotime($dt));



        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';

        // ---- جلوگیری از رزرو تکراری (روز/ساعت) ----
        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
         WHERE time = %s 
           AND status IN ('pending','approved')",
                $dt
            )
        );

        if ($exists > 0) {
            wp_send_json_error(array('message' => 'این زمان قبلاً رزرو شده است. لطفاً یک ساعت دیگر انتخاب کنید.'));
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'time' => $dt,
                'service_id' => $service_id,
                'customer_name' => $name,
                'customer_email' => $email,
                'status' => 'pending',
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );

        if (!$ok) {
            wp_send_json_error(array('message' => 'خطا در ذخیره‌سازی. لطفاً دوباره تلاش کنید.'));
        }

        wp_send_json_success(array('message' => 'رزرو با موفقیت ثبت شد و در انتظار تایید است.'));
    }

    // =========================
    // Dashboard page (همون کد خودت)
    // =========================
    public function render_dashboard_app_page()
    {
        if (!current_user_can('manage_options'))
            wp_die('دسترسی ندارید');

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';
        $appointments_all = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY `time` DESC");
        $count_total = is_array($appointments_all) ? count($appointments_all) : 0;

        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('mbp_admin_action_nonce');

        $settings = $this->schedule_settings();
        $week_start_ymd = wp_date('Y-m-d'); // امروز = اولین روز جدول


        $tz = wp_timezone();
        $ws = new DateTime($week_start_ymd . ' 00:00:00', $tz);
        $we = clone $ws;
        $we->modify('+6 day');

        $appointments_week = $this->get_appointments_for_range($ws->format('Y-m-d'), $we->format('Y-m-d'));
        $schedule_html = $this->render_schedule_grid_html($appointments_week, $week_start_ymd, $settings);
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
                }

                a.item:hover,
                a.item.active {
                    background: rgba(255, 255, 255, .12);
                    opacity: 1;
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
                }

                .mbp-btn:hover {
                    background: rgba(255, 255, 255, .14);
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
                }

                .cf-btn {
                    border: 0;
                    border-radius: 10px;
                    padding: 10px 12px;
                    cursor: pointer;
                    font-family: Vazirmatn, sans-serif;
                    font-weight: 900;
                }

                .cf-btn.primary {
                    background: #fff;
                    color: #111;
                }

                .cf-btn.ghost {
                    background: rgba(255, 255, 255, .08);
                    color: #fff;
                    border: 1px solid rgba(255, 255, 255, .16);
                }

                .hint {
                    font-size: 12px;
                    opacity: .8;
                    margin-top: 8px;
                }
            </style>

            <script>
                window.MBP_AJAX_URL = <?php echo wp_json_encode($ajax_url); ?>;
                window.MBP_ADMIN_NONCE = <?php echo wp_json_encode($nonce); ?>;
                window.MBP_WEEK_START = <?php echo wp_json_encode($week_start_ymd); ?>;
            </script>
        </head>

        <body>
            <div class="mbp-shell">
                <aside class="mbp-side">
                    <div style="font-weight:900;margin-bottom:10px;">افزونه رزرو</div>
                    <a class="item active" href="#" data-view="dashboard">داشبورد</a>
                    <a class="item" href="#" data-view="schedule">جدول رزرو (هفتگی)</a>
                    <a class="item" href="#" data-view="custom_fields">ساعت‌های رزرو</a>
                    <a class="item" href="#" data-view="services">خدمات</a>
                    <a class="item" href="#" data-view="settings">تنظیمات</a>
                </aside>

                <main class="mbp-main">
                    <div class="mbp-topbar">
                        <div style="font-weight:800;">افزونه رزرو</div>
                        <div>
                            <a class="btn" href="<?php echo esc_url(admin_url('admin.php?page=mbp-bookings')); ?>">خروج /
                                بازگشت</a>
                        </div>
                    </div>

                    <div id="tpl-dashboard" style="display:none">
                        <h2 style="margin-top:0">داشبورد</h2>
                        <div class="cards">
                            <div class="card">
                                <div class="title">تعداد رزروها</div>
                                <div class="num" id="mbp-total"><?php echo esc_html($this->fa_digits($count_total)); ?></div>
                            </div>
                            <div class="card">
                                <div class="title">درآمد ماه</div>
                                <div class="num">۰</div>
                            </div>
                        </div>
                    </div>

                    <div id="tpl-schedule" style="display:none">
                        <h2 style="margin-top:0">جدول زمان‌بندی رزروها</h2>
                        <div id="mbp-schedule-root">
                            <?php echo $schedule_html; ?>
                        </div>
                    </div>

                    <div id="tpl-custom-fields" style="display:none">
                        <h2 style="margin-top:0">تنظیم ساعت‌های قابل رزرو</h2>
                        <div class="panel">
                            <h3>هر خط یک ساعت (HH:MM)</h3>
                            <div class="hint">مثال: 09:00 یا 14:30</div>
                            <textarea id="mbp-slots-text" class="slots-ta"></textarea>

                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button class="cf-btn primary" id="mbp-slots-save">ذخیره</button>
                                <button class="cf-btn ghost" id="mbp-slots-load">ریست</button>
                            </div>

                            <div class="hint">این ساعت‌ها ردیف‌های جدول هفتگی را می‌سازند و در فرم رزرو هم استفاده می‌شوند.
                            </div>
                        </div>
                    </div>

                    <div class="mbp-canvas" id="mbp-view"></div>
                </main>
            </div>

            <div class="toast" id="mbp-toast">ذخیره شد</div>

            <script>
                (function () {
                    function ready(fn) {
                        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
                        else fn();
                    }

                    ready(function () {
                        const view = document.getElementById('mbp-view');
                        const items = Array.from(document.querySelectorAll('a.item'));

                        function hardFail(msg, err) {
                            console.error(msg, err || '');
                            const target = view || document.body;
                            target.innerHTML = `
                <div style="margin:14px;padding:14px;border:1px solid rgba(255,80,80,.5);background:rgba(255,80,80,.12);border-radius:12px;color:#fff;">
                  <div style="font-weight:900;margin-bottom:6px;">خطا در اجرای پنل</div>
                  <div style="font-size:12px;opacity:.95">${msg}</div>
                  ${err ? `<pre style="direction:ltr;text-align:left;white-space:pre-wrap;font-size:11px;opacity:.9;margin-top:10px;">${String(err.stack || err)}</pre>` : ''}
                </div>`;
                        }

                        try {
                            if (!view) throw new Error('#mbp-view پیدا نشد');
                            if (!items.length) throw new Error('منوها (a.item) پیدا نشدن');

                            function setActive(el) {
                                items.forEach(i => i.classList.remove('active'));
                                el.classList.add('active');
                            }

                            function mount(tplId) {
                                const tpl = document.getElementById(tplId);
                                if (!tpl) {
                                    view.innerHTML = '<div style="padding:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:12px;">قالب پیدا نشد</div>';
                                    return;
                                }
                                view.innerHTML = tpl.innerHTML;
                            }

                            function toast(msg) {
                                const t = document.getElementById('mbp-toast');
                                if (!t) return;
                                t.textContent = msg || 'انجام شد';
                                t.classList.add('show');
                                setTimeout(() => t.classList.remove('show'), 1400);
                            }

                            function render(name) {
                                if (name === 'dashboard') mount('tpl-dashboard');
                                else if (name === 'schedule') mount('tpl-schedule');
                                else if (name === 'custom_fields') { mount('tpl-custom-fields'); initSlots(); }
                                else view.innerHTML = `<h2 style="margin-top:0">${name}</h2><div style="opacity:.85">بزودی...</div>`;
                            }

                            items.forEach(a => {
                                a.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    setActive(a);
                                    localStorage.setItem('mbp_active_view', a.dataset.view);
                                    render(a.dataset.view);
                                });
                            });

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

                            function updateApprovedEverywhere(id) {
                                document.querySelectorAll(`.mbp-booking-card[data-id="${id}"]`).forEach(card => {
                                    const st = card.querySelector('.mbp-status-pending, .mbp-status-approved');
                                    if (st) {
                                        st.className = 'mbp-status-approved';
                                        st.textContent = 'Approved';
                                    }
                                    card.querySelectorAll('.mbp-approve').forEach(b => {
                                        b.style.transition = '0.3s';
                                        b.style.opacity = '0';
                                        setTimeout(() => b.remove(), 300);
                                    });
                                });
                            }

                            function removeEverywhere(id) {
                                document.querySelectorAll(`.mbp-booking-card[data-id="${id}"]`).forEach(card => {
                                    card.style.transition = '0.3s';
                                    card.style.opacity = '0';
                                    card.style.transform = 'scale(0.9)';
                                    setTimeout(() => card.remove(), 300);
                                });
                            }

                            document.addEventListener('click', async (e) => {
                                const approveBtn = e.target.closest('.mbp-approve');
                                const deleteBtn = e.target.closest('.mbp-delete');
                                if (!approveBtn && !deleteBtn) return;

                                const btn = approveBtn || deleteBtn;
                                const id = btn.dataset.id;
                                if (!id) return;

                                if (deleteBtn && !confirm('رزرو حذف شود؟')) return;

                                const action = approveBtn ? 'mbp_admin_approve_booking' : 'mbp_admin_delete_booking';

                                const fd = new FormData();
                                fd.append('action', action);
                                fd.append('id', id);
                                fd.append('nonce', window.MBP_ADMIN_NONCE);

                                btn.disabled = true;
                                btn.style.opacity = '0.6';

                                try {
                                    const res = await fetch(window.MBP_AJAX_URL, { method: 'POST', body: fd });
                                    const data = await res.json();

                                    if (!data.success) {
                                        alert(data?.data?.message || 'خطا');
                                    } else {
                                        if (approveBtn) {
                                            updateApprovedEverywhere(id);
                                            toast('تایید شد ✅');
                                        } else {
                                            removeEverywhere(id);
                                            updateTotal(-1);
                                            toast('حذف شد 🗑️');
                                        }
                                    }
                                } catch (err) {
                                    alert('خطای شبکه');
                                } finally {
                                    if (btn.closest('body')) {
                                        btn.disabled = false;
                                        btn.style.opacity = '1';
                                    }
                                }
                            });

                            document.addEventListener('click', async (e) => {
                                const nav = e.target.closest('.mbp-nav');
                                if (!nav) return;

                                const wrap = document.querySelector('#mbp-view .mbp-schedule-wrap');
                                const root = document.querySelector('#mbp-view #mbp-schedule-root');
                                if (!wrap || !root) return;

                                const delta = parseInt(nav.getAttribute('data-week-nav') || '0', 10) || 0;
                                const current = wrap.getAttribute('data-week-start');
                                if (!current) return;

                                const d = new Date(current + 'T00:00:00');
                                d.setDate(d.getDate() + delta);
                                const next = d.toISOString().slice(0, 10);

                                document.querySelectorAll('.mbp-nav').forEach(b => {
                                    b.disabled = true;
                                    b.style.opacity = '0.6';
                                });

                                const fd = new FormData();
                                fd.append('action', 'mbp_get_schedule_week');
                                fd.append('nonce', window.MBP_ADMIN_NONCE);
                                fd.append('week_start', next);

                                try {
                                    const res = await fetch(window.MBP_AJAX_URL, { method: 'POST', body: fd });
                                    const data = await res.json();
                                    if (!data.success) {
                                        alert(data?.data?.message || 'خطا');
                                        return;
                                    }
                                    root.innerHTML = data.data.html;
                                } catch (err) {
                                    alert('خطای شبکه');
                                } finally {
                                    document.querySelectorAll('.mbp-nav').forEach(b => {
                                        b.disabled = false;
                                        b.style.opacity = '1';
                                    });
                                }
                            });

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
                                        const res = await fetch(window.MBP_AJAX_URL, { method: 'POST', body: fd });
                                        const data = await res.json();
                                        if (!data.success) { alert(data?.data?.message || 'خطا'); return; }
                                        ta.value = (data.data.slots || []).join("\n");
                                    } catch (err) {
                                        alert('خطای شبکه در بارگذاری اسلات‌ها');
                                    } finally {
                                        btnSave.disabled = false;
                                        btnLoad.disabled = false;
                                    }
                                }

                                btnLoad.onclick = (e) => { e.preventDefault(); load(); };

                                btnSave.onclick = async (e) => {
                                    e.preventDefault();
                                    btnSave.disabled = true;
                                    const fd = new FormData();
                                    fd.append('action', 'mbp_save_time_slots');
                                    fd.append('nonce', window.MBP_ADMIN_NONCE);
                                    fd.append('slots_text', ta.value || '');

                                    try {
                                        const res = await fetch(window.MBP_AJAX_URL, { method: 'POST', body: fd });
                                        const data = await res.json();
                                        if (!data.success) { alert(data?.data?.message || 'خطا'); return; }
                                        toast('ذخیره شد ✅');
                                        load();
                                    } catch (err) {
                                        alert('خطای شبکه در ذخیره اسلات‌ها');
                                    } finally {
                                        btnSave.disabled = false;
                                    }
                                };

                                load();
                            }

                            const initialView = localStorage.getItem('mbp_active_view') || 'dashboard';
                            const initialItem = document.querySelector(`a.item[data-view="${initialView}"]`) || document.querySelector('a.item.active');

                            if (initialItem) {
                                setActive(initialItem);
                                render(initialItem.dataset.view);
                            } else {
                                render('dashboard');
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

   public function admin_page_content()
{
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید');
    }

    $dashboard_url = admin_url('admin-post.php?action=mbp_dashboard_app');
    $license_url   = admin_url('admin.php?page=mbp-license'); // اگر صفحه لایسنس داری

    // وضعیت لایسنس (اگر کلاسش هست)
    $license_ok = null;
    if (class_exists('MBP_License')) {
        $license_ok = MBP_License::is_valid();
    }

    echo '<div class="wrap" style="direction:rtl;">';
    echo '<h1 style="margin-bottom:14px;">' . esc_html__('پنل مدیریت رزرو', 'my-booking-plugin') . '</h1>';

    // کارت/باکس
    echo '
    <div style="
        max-width: 720px;
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:14px;
        padding:16px;
        box-shadow: 0 8px 22px rgba(0,0,0,.06);
    ">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <div>
                <div style="font-size:14px; opacity:.85; margin-bottom:6px;">افزونه</div>
                <div style="font-size:18px; font-weight:900;">افزونه رزرو نوبت</div>
            </div>';

    // نشان وضعیت
    if ($license_ok === true) {
        echo '<div style="
            padding:6px 10px;
            border-radius:999px;
            background:rgba(0,163,42,.12);
            border:1px solid rgba(0,163,42,.25);
            color:#0a5a22;
            font-weight:800;
            font-size:12px;
            white-space:nowrap;
        ">فعال ✅</div>';
    } elseif ($license_ok === false) {
        echo '<div style="
            padding:6px 10px;
            border-radius:999px;
            background:rgba(214,54,56,.10);
            border:1px solid rgba(214,54,56,.25);
            color:#7a1112;
            font-weight:800;
            font-size:12px;
            white-space:nowrap;
        ">نیاز به فعال‌سازی ❌</div>';
    }

    echo '
        </div>

        <div style="margin-top:12px; line-height:1.9; color:#374151;">
            از اینجا می‌تونی وارد <strong>پنل تمام صفحه</strong> بشی و رزروها رو مدیریت کنی.
        </div>

        <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
            <a href="' . esc_url($dashboard_url) . '" class="button button-primary" style="font-weight:800; padding:6px 14px;">
                ورود به پنل مدیریت
            </a>';

    // دکمه فعال سازی/لایسنس (اختیاری)
    if ($license_ok === false) {
        echo '
            <a href="' . esc_url($license_url) . '" class="button" style="font-weight:800; padding:6px 14px;">
                فعال‌سازی / لایسنس
            </a>';
    } else {
        echo '
            <a href="' . esc_url($license_url) . '" class="button" style="font-weight:800; padding:6px 14px;">
                مدیریت لایسنس
            </a>';
    }

    echo '
        </div>

        <div style="margin-top:10px; font-size:12px; opacity:.75;">
            نکته: برای نمایش جدول رزرو در سایت از شورتکد <code>[mbp_public_schedule]</code> استفاده کن.
        </div>
    </div>';

    echo '</div>';
}

}
