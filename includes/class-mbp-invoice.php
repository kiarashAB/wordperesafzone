<?php
if (!defined('ABSPATH')) exit;

class MBP_Invoice {

    const OPT_SETTINGS = 'mbp_invoice_settings';

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $t1 = $wpdb->prefix . 'mbp_invoices';
        $t2 = $wpdb->prefix . 'mbp_invoice_items';

        $sql1 = "CREATE TABLE $t1 (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_no VARCHAR(40) NOT NULL,
            customer_name VARCHAR(190) NOT NULL,
            mobile VARCHAR(32) NULL,
            email VARCHAR(190) NULL,
            notes TEXT NULL,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            tax DECIMAL(10,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'created',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_no (invoice_no)
        ) $charset;";

        $sql2 = "CREATE TABLE $t2 (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            description VARCHAR(255) NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
    }

    public function __construct() {
        add_action('wp_ajax_mbp_get_invoices', [$this, 'ajax_get_invoices']);
        add_action('wp_ajax_mbp_create_invoice', [$this, 'ajax_create_invoice']);
        add_action('wp_ajax_mbp_delete_invoice', [$this, 'ajax_delete_invoice']);
        add_action('wp_ajax_mbp_update_invoice_status', [$this, 'ajax_update_invoice_status']);
        add_action('wp_ajax_mbp_send_invoice_sms', [$this, 'ajax_send_invoice_sms']);
        add_action('wp_ajax_mbp_save_invoice_settings', [$this, 'ajax_save_invoice_settings']);

        add_action('admin_post_mbp_invoice_print', [$this, 'print_invoice_page']);
    }

    private function must_admin() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی ندارید'], 403);
        }
        check_ajax_referer('mbp_admin_action_nonce', 'nonce');
    }

    private function get_settings() {
        $d = [
            'seller_name' => '',
            'seller_phone' => '',
            'seller_address' => '',
            'footer' => '',
            'date_mode' => 'jalali', // jalali | gregorian
            'sms_on_status' => 0,
            'sms_template' => 'وضعیت فاکتور شما: {status} - شماره: {invoice_no}'
        ];
        $s = get_option(self::OPT_SETTINGS, []);
        if (!is_array($s)) $s = [];
        return array_merge($d, $s);
    }

    private function save_settings($new) {
        $cur = $this->get_settings();
        $cur['seller_name'] = sanitize_text_field($new['seller_name'] ?? '');
        $cur['seller_phone'] = sanitize_text_field($new['seller_phone'] ?? '');
        $cur['seller_address'] = sanitize_textarea_field($new['seller_address'] ?? '');
        $cur['footer'] = sanitize_textarea_field($new['footer'] ?? '');
        $cur['date_mode'] = ($new['date_mode'] ?? 'jalali') === 'gregorian' ? 'gregorian' : 'jalali';
        $cur['sms_on_status'] = !empty($new['sms_on_status']) ? 1 : 0;
        $cur['sms_template'] = sanitize_textarea_field($new['sms_template'] ?? '');
        update_option(self::OPT_SETTINGS, $cur, false);
        return $cur;
    }

    public function ajax_save_invoice_settings() {
        $this->must_admin();
        $s = $this->save_settings($_POST);
        wp_send_json_success(['message' => 'ok', 'settings' => $s]);
    }

    public function ajax_get_invoices() {
        $this->must_admin();

        global $wpdb;
        $t = $wpdb->prefix . 'mbp_invoices';

        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC LIMIT 50");
        $settings = $this->get_settings();

        ob_start();
        ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
            <div style="opacity:.85;font-size:12px;">آخرین 50 فاکتور</div>
        </div>

        <div style="overflow:auto;border:1px solid rgba(255,255,255,.12);border-radius:14px;background:rgba(255,255,255,.04);">
            <table style="width:100%;border-collapse:collapse;min-width:920px;">
                <thead>
                    <tr style="background:rgba(255,255,255,.06);">
                        <th style="padding:10px;text-align:right;">شماره</th>
                        <th style="padding:10px;text-align:right;">مشتری</th>
                        <th style="padding:10px;text-align:right;">تاریخ</th>
                        <th style="padding:10px;text-align:right;">جمع</th>
                        <th style="padding:10px;text-align:right;">وضعیت</th>
                        <th style="padding:10px;text-align:right;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="padding:14px;opacity:.8;">هیچ فاکتوری ثبت نشده</td></tr>
                <?php else: foreach ($rows as $r):
                    $print_url = wp_nonce_url(
                        admin_url('admin-post.php?action=mbp_invoice_print&id=' . intval($r->id)),
                        'mbp_invoice_print_' . intval($r->id),
                        'pnonce'
                    );
                    ?>
                    <tr style="border-top:1px solid rgba(255,255,255,.10);">
                        <td style="padding:10px;"><?php echo esc_html($r->invoice_no); ?></td>
                        <td style="padding:10px;">
                            <div style="font-weight:900;"><?php echo esc_html($r->customer_name); ?></div>
                            <div style="opacity:.75;font-size:12px;">
                                <?php echo esc_html($r->mobile ?: '-'); ?>
                            </div>
                        </td>
                        <td style="padding:10px;opacity:.9;">
                            <?php echo esc_html($this->format_date(strtotime($r->created_at), $settings['date_mode'])); ?>
                        </td>
                        <td style="padding:10px;font-weight:900;">
                            <?php echo esc_html(number_format((float)$r->total)); ?>
                        </td>
                        <td style="padding:10px;">
                            <select class="mbp-inv-status" data-id="<?php echo intval($r->id); ?>"
                                style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);color:#fff;border-radius:10px;padding:6px 10px;">
                                <option value="created" <?php selected($r->status, 'created'); ?>>ایجاد شده</option>
                                <option value="paid" <?php selected($r->status, 'paid'); ?>>پرداخت شده</option>
                                <option value="cancelled" <?php selected($r->status, 'cancelled'); ?>>لغو شده</option>
                            </select>
                        </td>
                        <td style="padding:10px;white-space:nowrap;">
                            <a href="#" class="mbp-btn mbp-inv-print" data-id="<?php echo intval($r->id); ?>" data-url="<?php echo esc_url($print_url); ?>">🖨️ چاپ/PDF</a>
                            <a href="#" class="mbp-btn mbp-inv-sms" data-id="<?php echo intval($r->id); ?>">📩 پیامک</a>
                            <a href="#" class="mbp-btn mbp-delete mbp-inv-del" data-id="<?php echo intval($r->id); ?>">🗑️ حذف</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    public function ajax_create_invoice() {
        $this->must_admin();

        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        if ($customer_name === '') wp_send_json_error(['message' => 'نام مشتری الزامی است']);

        $mobile = sanitize_text_field($_POST['mobile'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        $discount = (float) ($_POST['discount'] ?? 0);
        $tax = (float) ($_POST['tax'] ?? 0);

        $items_json = wp_unslash($_POST['items'] ?? '[]');
        $items = json_decode($items_json, true);
        if (!is_array($items) || empty($items)) wp_send_json_error(['message' => 'آیتم‌ها معتبر نیستند']);

        $subtotal = 0;
        foreach ($items as &$it) {
            $desc = sanitize_text_field($it['description'] ?? '');
            $qty = (int) ($it['qty'] ?? 1);
            $unit = (float) ($it['unit_price'] ?? 0);
            if ($desc === '') continue;
            if ($qty < 1) $qty = 1;
            $line = $qty * $unit;
            $it = ['description' => $desc, 'qty' => $qty, 'unit_price' => $unit, 'line_total' => $line];
            $subtotal += $line;
        }
        unset($it);

        $total = max(0, $subtotal - $discount + $tax);

        global $wpdb;
        $t1 = $wpdb->prefix . 'mbp_invoices';
        $t2 = $wpdb->prefix . 'mbp_invoice_items';

        $invoice_no = 'INV-' . wp_date('Ymd') . '-' . wp_generate_password(6, false, false);
        $now = current_time('mysql');

        $wpdb->insert($t1, [
            'invoice_no' => $invoice_no,
            'customer_name' => $customer_name,
            'mobile' => $mobile,
            'email' => $email,
            'notes' => $notes,
            'discount' => $discount,
            'tax' => $tax,
            'subtotal' => $subtotal,
            'total' => $total,
            'status' => 'created',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $invoice_id = (int) $wpdb->insert_id;
        if (!$invoice_id) wp_send_json_error(['message' => 'خطا در ذخیره فاکتور']);

        foreach ($items as $it) {
            $wpdb->insert($t2, [
                'invoice_id' => $invoice_id,
                'description' => $it['description'],
                'qty' => $it['qty'],
                'unit_price' => $it['unit_price'],
                'line_total' => $it['line_total'],
            ]);
        }

        wp_send_json_success(['message' => 'ok', 'id' => $invoice_id, 'invoice_no' => $invoice_no]);
    }

    public function ajax_delete_invoice() {
        $this->must_admin();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'شناسه نامعتبر']);

        global $wpdb;
        $t1 = $wpdb->prefix . 'mbp_invoices';
        $t2 = $wpdb->prefix . 'mbp_invoice_items';

        $wpdb->delete($t2, ['invoice_id' => $id]);
        $wpdb->delete($t1, ['id' => $id]);

        wp_send_json_success(['message' => 'deleted']);
    }

    public function ajax_update_invoice_status() {
        $this->must_admin();
        $id = (int) ($_POST['id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? 'created');
        if (!$id) wp_send_json_error(['message' => 'شناسه نامعتبر']);

        $allowed = ['created', 'paid', 'cancelled'];
        if (!in_array($status, $allowed, true)) wp_send_json_error(['message' => 'وضعیت نامعتبر']);

        global $wpdb;
        $t = $wpdb->prefix . 'mbp_invoices';

        $wpdb->update($t, [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);

        // اگر فعال باشد: پیامک خودکار
        $settings = $this->get_settings();
        if (!empty($settings['sms_on_status'])) {
            $this->send_sms_for_invoice($id, $status);
        }

        wp_send_json_success(['message' => 'ok']);
    }

    public function ajax_send_invoice_sms() {
        $this->must_admin();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'شناسه نامعتبر']);
        $ok = $this->send_sms_for_invoice($id, null);
        if (!$ok) wp_send_json_error(['message' => 'ارسال پیامک ناموفق']);
        wp_send_json_success(['message' => 'sent']);
    }

    private function send_sms_for_invoice($id, $status_override = null) {
        global $wpdb;
        $t = $wpdb->prefix . 'mbp_invoices';
        $inv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id));
        if (!$inv || empty($inv->mobile)) return false;

        $settings = $this->get_settings();
        $status = $status_override ?: $inv->status;

        $map = ['created' => 'ایجاد شده', 'paid' => 'پرداخت شده', 'cancelled' => 'لغو شده'];
        $status_fa = $map[$status] ?? $status;

        $msg = $settings['sms_template'] ?: 'وضعیت فاکتور شما: {status} - شماره: {invoice_no}';
        $msg = str_replace(
            ['{status}', '{invoice_no}', '{total}'],
            [$status_fa, $inv->invoice_no, number_format((float)$inv->total)],
            $msg
        );

        // 🔧 اینجا باید به کلاس SMS خودت وصل بشه
        // اگر متد/اسم فرق داشت فقط همین قسمت رو تغییر بده
        if (class_exists('MBP_SMS_Manager')) {
            $sms = new MBP_SMS_Manager();
            if (method_exists($sms, 'send_sms')) {
                return (bool) $sms->send_sms($inv->mobile, $msg);
            }
        }

        return false;
    }

    public function print_invoice_page() {
        if (!current_user_can('manage_options')) wp_die('دسترسی ندارید');

        $id = (int) ($_GET['id'] ?? 0);
        $pnonce = sanitize_text_field($_GET['pnonce'] ?? '');
        if (!$id || !wp_verify_nonce($pnonce, 'mbp_invoice_print_' . $id)) {
            wp_die('درخواست نامعتبر');
        }

        global $wpdb;
        $t1 = $wpdb->prefix . 'mbp_invoices';
        $t2 = $wpdb->prefix . 'mbp_invoice_items';

        $inv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t1 WHERE id=%d", $id));
        if (!$inv) wp_die('فاکتور پیدا نشد');

        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t2 WHERE invoice_id=%d ORDER BY id ASC", $id));
        $settings = $this->get_settings();

        $print_link = admin_url('admin-post.php?action=mbp_invoice_print&id=' . $id . '&pnonce=' . urlencode($pnonce));
        $qr_data = $print_link; // QR میره به صفحه چاپ همین فاکتور

        $date_str = $this->format_date(strtotime($inv->created_at), $settings['date_mode']);
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Invoice <?php echo esc_html($inv->invoice_no); ?></title>
            <style>
                body{font-family:tahoma,arial;direction:rtl;margin:0;background:#f3f4f6;color:#111;}
                .wrap{max-width:900px;margin:18px auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;}
                .top{display:flex;justify-content:space-between;gap:12px;padding:18px;border-bottom:1px solid #e5e7eb;}
                .btns{display:flex;gap:8px;flex-wrap:wrap;padding:12px 18px;border-bottom:1px solid #e5e7eb;background:#fafafa;}
                .btn{border:1px solid #e5e7eb;background:#fff;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:800;}
                .content{padding:18px;}
                table{width:100%;border-collapse:collapse;margin-top:12px;}
                th,td{border:1px solid #e5e7eb;padding:10px;text-align:right;font-size:13px;}
                th{background:#f9fafb;}
                .totals{margin-top:12px;display:flex;justify-content:flex-end;}
                .totals .box{min-width:280px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;}
                .totals .row{display:flex;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #e5e7eb;}
                .totals .row:last-child{border-bottom:none;font-weight:900;background:#f9fafb;}
                .footer{margin-top:14px;padding-top:12px;border-top:1px dashed #ddd;font-size:12px;opacity:.9;white-space:pre-wrap;}
                .codes{display:flex;gap:18px;justify-content:flex-end;margin-top:14px;align-items:center;flex-wrap:wrap;}
                #qrcode{width:140px;height:140px;border:1px solid #e5e7eb;border-radius:10px;padding:8px;}
                #barcode{width:280px;max-width:100%;}
                @media print{
                    .btns{display:none;}
                    body{background:#fff;}
                    .wrap{border:none;margin:0;border-radius:0;}
                }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
        </head>
        <body>
            <div class="wrap">
                <div class="btns">
                    <button class="btn" onclick="window.print()">🖨️ چاپ / ذخیره PDF</button>
                    <button class="btn" onclick="window.close()">✖ بستن</button>
                </div>

                <div class="top">
                    <div>
                        <div style="font-weight:900;font-size:18px;"><?php echo esc_html($settings['seller_name'] ?: 'فاکتور'); ?></div>
                        <div style="font-size:12px;opacity:.85;margin-top:6px;">
                            <?php echo esc_html($settings['seller_phone']); ?><br>
                            <?php echo nl2br(esc_html($settings['seller_address'])); ?>
                        </div>
                    </div>
                    <div style="text-align:left;direction:ltr;">
                        <div style="font-weight:900;"><?php echo esc_html($inv->invoice_no); ?></div>
                        <div style="font-size:12px;opacity:.85;margin-top:6px;direction:rtl;text-align:right;">
                            تاریخ: <?php echo esc_html($date_str); ?><br>
                            وضعیت: <?php echo esc_html($inv->status); ?>
                        </div>
                    </div>
                </div>

                <div class="content">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:900;">مشخصات مشتری</div>
                            <div style="font-size:13px;opacity:.9;margin-top:6px;">
                                نام: <?php echo esc_html($inv->customer_name); ?><br>
                                موبایل: <?php echo esc_html($inv->mobile ?: '-'); ?><br>
                                ایمیل: <?php echo esc_html($inv->email ?: '-'); ?>
                            </div>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>شرح</th>
                                <th style="width:90px;">تعداد</th>
                                <th style="width:140px;">قیمت واحد</th>
                                <th style="width:140px;">جمع</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?php echo esc_html($it->description); ?></td>
                                <td><?php echo esc_html($it->qty); ?></td>
                                <td><?php echo esc_html(number_format((float)$it->unit_price)); ?></td>
                                <td><?php echo esc_html(number_format((float)$it->line_total)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="totals">
                        <div class="box">
                            <div class="row"><span>جمع جزء</span><span><?php echo esc_html(number_format((float)$inv->subtotal)); ?></span></div>
                            <div class="row"><span>تخفیف</span><span><?php echo esc_html(number_format((float)$inv->discount)); ?></span></div>
                            <div class="row"><span>مالیات</span><span><?php echo esc_html(number_format((float)$inv->tax)); ?></span></div>
                            <div class="row"><span>جمع کل</span><span><?php echo esc_html(number_format((float)$inv->total)); ?></span></div>
                        </div>
                    </div>

                    <div class="codes">
                        <div id="qrcode"></div>
                        <svg id="barcode"></svg>
                    </div>

                    <?php if (!empty($inv->notes)): ?>
                        <div class="footer"><strong>یادداشت:</strong> <?php echo nl2br(esc_html($inv->notes)); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($settings['footer'])): ?>
                        <div class="footer"><?php echo nl2br(esc_html($settings['footer'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                new QRCode(document.getElementById("qrcode"), {
                    text: <?php echo wp_json_encode($qr_data); ?>,
                    width: 140,
                    height: 140
                });
                JsBarcode("#barcode", <?php echo wp_json_encode($inv->invoice_no); ?>, {
                    displayValue: true,
                    fontSize: 14,
                    height: 60,
                    margin: 0
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    private function format_date($ts, $mode) {
        if ($mode === 'gregorian') return wp_date('Y/m/d H:i', $ts);
        return $this->gregorian_to_jalali_str($ts);
    }

    // ساده و کافی برای نمایش تاریخ شمسی
    private function gregorian_to_jalali_str($ts) {
        $gy = (int) wp_date('Y', $ts);
        $gm = (int) wp_date('n', $ts);
        $gd = (int) wp_date('j', $ts);

        [$jy, $jm, $jd] = $this->g2j($gy, $gm, $gd);
        $time = wp_date('H:i', $ts);
        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $time);
    }

    // الگوریتم تبدیل میلادی به شمسی
    private function g2j($gy, $gm, $gd) {
        $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365*$gy) + (int)(($gy2+3)/4) - (int)(($gy2+99)/100) + (int)(($gy2+399)/400) + $gd + $g_d_m[$gm-1];
        $jy = -1595 + 33*(int)($days/12053);
        $days %= 12053;
        $jy += 4*(int)($days/1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days-1)/365);
            $days = ($days-1)%365;
        }
        $jm = ($days < 186) ? 1 + (int)($days/31) : 7 + (int)(($days-186)/30);
        $jd = 1 + (($days < 186) ? ($days%31) : (($days-186)%30));
        return [$jy, $jm, $jd];
    }

    
}
