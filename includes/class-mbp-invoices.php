<?php
if (!defined('ABSPATH')) exit;

class MBP_Invoices
{
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mbp_invoices';
    }

    public static function install() {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            invoice_date DATE NULL,
            customer_name VARCHAR(190) NOT NULL DEFAULT '',
            customer_phone VARCHAR(60) NOT NULL DEFAULT '',
            items LONGTEXT NULL,
            subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
            discount DECIMAL(14,2) NOT NULL DEFAULT 0,
            tax DECIMAL(14,2) NOT NULL DEFAULT 0,
            total DECIMAL(14,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            PRIMARY KEY (id)
        ) {$charset};";

        dbDelta($sql);
    }

    public function __construct() {
        add_action('wp_ajax_mbp_get_invoices', [$this, 'ajax_get_invoices']);
        add_action('wp_ajax_mbp_save_invoice', [$this, 'ajax_save_invoice']);
        add_action('wp_ajax_mbp_delete_invoice', [$this, 'ajax_delete_invoice']);
        add_action('wp_ajax_mbp_print_invoice', [$this, 'ajax_print_invoice']);
    }

    private function must_admin_and_nonce() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی ندارید']);
        }
        check_ajax_referer('mbp_admin_action_nonce', 'nonce');
    }

    public function ajax_get_invoices() {
        $this->must_admin_and_nonce();

        global $wpdb;
        $table = self::table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 200", ARRAY_A);

        ob_start();
        ?>
        <div style="display:grid;grid-template-columns:1.1fr .9fr;gap:14px;align-items:start;flex-wrap:wrap;">
            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:14px;">
                <div style="font-weight:900;margin-bottom:10px;">ساخت فاکتور جدید</div>

                <form id="mbp-invoice-form">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div>
                            <label style="font-size:12px;opacity:.8;display:block;margin-bottom:6px;">نام مشتری</label>
                            <input name="customer_name" type="text" required
                                   style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                        </div>
                        <div>
                            <label style="font-size:12px;opacity:.8;display:block;margin-bottom:6px;">موبایل</label>
                            <input name="customer_phone" type="text"
                                   style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                        </div>
                        <div>
                            <label style="font-size:12px;opacity:.8;display:block;margin-bottom:6px;">تاریخ فاکتور</label>
                            <input name="invoice_date" type="date"
                                   style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                        </div>
                        <div>
                            <label style="font-size:12px;opacity:.8;display:block;margin-bottom:6px;">تخفیف</label>
                            <input name="discount" type="number" value="0" min="0"
                                   style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                        </div>
                        <div>
                            <label style="font-size:12px;opacity:.8;display:block;margin-bottom:6px;">مالیات</label>
                            <input name="tax" type="number" value="0" min="0"
                                   style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                        </div>
                        <div style="grid-column:1/-1">
                            <label style="font-size:12px;opacity:.8;display:block;margin-bottom:6px;">توضیحات</label>
                            <textarea name="notes" rows="2"
                                      style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;"></textarea>
                        </div>
                    </div>

                    <div style="margin-top:12px;font-weight:900;">آیتم‌ها</div>

                    <div style="margin-top:8px;border:1px solid rgba(255,255,255,.12);border-radius:12px;overflow:hidden;">
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                            <tr style="background:rgba(255,255,255,.06);">
                                <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;">عنوان</th>
                                <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;width:90px;">تعداد</th>
                                <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;width:140px;">قیمت</th>
                                <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;width:60px;">حذف</th>
                            </tr>
                            </thead>
                            <tbody id="mbp-invoice-items">
                            <tr>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
                                    <input class="mbp-item-title" type="text" required
                                           style="width:100%;padding:9px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                                </td>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
                                    <input class="mbp-item-qty" type="number" value="1" min="1"
                                           style="width:100%;padding:9px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                                </td>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
                                    <input class="mbp-item-price" type="number" value="0" min="0"
                                           style="width:100%;padding:9px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;">
                                </td>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
                                    <button type="button" class="mbp-inv-del-item"
                                            style="width:100%;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.12);color:#fff;border-radius:10px;padding:8px;cursor:pointer;">×</button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;">
                        <button type="button" id="mbp-inv-add-item"
                                style="border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);color:#fff;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:900;">
                            + افزودن آیتم
                        </button>

                        <button type="submit"
                                style="border:0;background:#fff;color:#111;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:900;">
                            💾 ذخیره فاکتور
                        </button>

                        <div id="mbp-inv-msg" style="font-size:12px;opacity:.8;align-self:center;"></div>
                    </div>
                </form>
            </div>

            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:14px;">
                <div style="font-weight:900;margin-bottom:10px;">لیست فاکتورها</div>

                <div style="overflow:auto;border:1px solid rgba(255,255,255,.10);border-radius:12px;">
                    <table style="width:100%;border-collapse:collapse;min-width:520px;">
                        <thead>
                        <tr style="background:rgba(255,255,255,.06);">
                            <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;">#</th>
                            <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;">مشتری</th>
                            <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;">تاریخ</th>
                            <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;">جمع</th>
                            <th style="text-align:right;padding:10px;font-size:12px;opacity:.85;">عملیات</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="5" style="padding:14px;opacity:.75;">فاکتوری ثبت نشده.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);"><?php echo (int)$r['id']; ?></td>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);"><?php echo esc_html($r['customer_name']); ?></td>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);"><?php echo esc_html($r['invoice_date'] ?: $r['created_at']); ?></td>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);"><?php echo esc_html(number_format((float)$r['total'])); ?></td>
                                <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);white-space:nowrap;">
                                    <button type="button" class="mbp-inv-print" data-id="<?php echo (int)$r['id']; ?>"
                                            style="border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.14);color:#fff;border-radius:10px;padding:8px 10px;cursor:pointer;font-weight:900;">چاپ</button>

                                    <button type="button" class="mbp-inv-del" data-id="<?php echo (int)$r['id']; ?>"
                                            style="border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.12);color:#fff;border-radius:10px;padding:8px 10px;cursor:pointer;font-weight:900;">حذف</button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    public function ajax_save_invoice() {
        $this->must_admin_and_nonce();

        $payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
        $data = json_decode($payload, true);
        if (!is_array($data)) wp_send_json_error(['message' => 'داده نامعتبر است']);

        $name = trim((string)($data['customer_name'] ?? ''));
        if ($name === '') wp_send_json_error(['message' => 'نام مشتری الزامی است']);

        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) wp_send_json_error(['message' => 'حداقل یک آیتم لازم است']);

        $subtotal = 0.0;
        $clean_items = [];

        foreach ($items as $it) {
            $title = trim((string)($it['title'] ?? ''));
            $qty = max(1, (int)($it['qty'] ?? 1));
            $price = max(0, (float)($it['price'] ?? 0));
            if ($title === '') continue;

            $line = $qty * $price;
            $subtotal += $line;

            $clean_items[] = [
                'title' => $title,
                'qty'   => $qty,
                'price' => $price,
                'line'  => $line,
            ];
        }

        if (empty($clean_items)) wp_send_json_error(['message' => 'عنوان آیتم‌ها خالی است']);

        $discount = max(0, (float)($data['discount'] ?? 0));
        $tax = max(0, (float)($data['tax'] ?? 0));
        $total = max(0, ($subtotal - $discount + $tax));

        global $wpdb;
        $table = self::table_name();

        $wpdb->insert($table, [
            'created_at'      => current_time('mysql'),
            'invoice_date'    => !empty($data['invoice_date']) ? $data['invoice_date'] : null,
            'customer_name'   => $name,
            'customer_phone'  => (string)($data['customer_phone'] ?? ''),
            'items'           => wp_json_encode($clean_items, JSON_UNESCAPED_UNICODE),
            'subtotal'        => $subtotal,
            'discount'        => $discount,
            'tax'             => $tax,
            'total'           => $total,
            'notes'           => (string)($data['notes'] ?? ''),
        ]);

        wp_send_json_success(['message' => 'فاکتور ذخیره شد ✅']);
    }

    public function ajax_delete_invoice() {
        $this->must_admin_and_nonce();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) wp_send_json_error(['message' => 'ID نامعتبر']);

        global $wpdb;
        $table = self::table_name();
        $wpdb->delete($table, ['id' => $id]);

        wp_send_json_success(['message' => 'حذف شد ✅']);
    }

    public function ajax_print_invoice() {
        $this->must_admin_and_nonce();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) wp_send_json_error(['message' => 'ID نامعتبر']);

        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        if (!$row) wp_send_json_error(['message' => 'فاکتور پیدا نشد']);

        $items = json_decode((string)$row['items'], true);
        if (!is_array($items)) $items = [];

        ob_start();
        ?>
        <div style="direction:rtl;font-family:tahoma,arial;max-width:820px;margin:0 auto;padding:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #ddd;padding-bottom:10px;margin-bottom:14px;">
                <div style="font-size:18px;font-weight:900;">فاکتور</div>
                <div style="font-size:12px;color:#444;">
                    شماره: <b><?php echo (int)$row['id']; ?></b><br>
                    تاریخ: <b><?php echo esc_html($row['invoice_date'] ?: $row['created_at']); ?></b>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <div><b>مشتری:</b> <?php echo esc_html($row['customer_name']); ?></div>
                <div><b>موبایل:</b> <?php echo esc_html($row['customer_phone']); ?></div>
            </div>

            <table style="width:100%;border-collapse:collapse;">
                <thead>
                <tr>
                    <th style="border:1px solid #ddd;padding:8px;text-align:right;">عنوان</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:right;width:80px;">تعداد</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:right;width:140px;">قیمت</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:right;width:140px;">جمع</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo esc_html($it['title'] ?? ''); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo (int)($it['qty'] ?? 1); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo number_format((float)($it['price'] ?? 0)); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo number_format((float)($it['line'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:12px;display:flex;justify-content:flex-end;">
                <table style="border-collapse:collapse;min-width:280px;">
                    <tr><td style="padding:6px;">جمع جزء</td><td style="padding:6px;text-align:left;"><?php echo number_format((float)$row['subtotal']); ?></td></tr>
                    <tr><td style="padding:6px;">تخفیف</td><td style="padding:6px;text-align:left;"><?php echo number_format((float)$row['discount']); ?></td></tr>
                    <tr><td style="padding:6px;">مالیات</td><td style="padding:6px;text-align:left;"><?php echo number_format((float)$row['tax']); ?></td></tr>
                    <tr><td style="padding:6px;font-weight:900;">مبلغ نهایی</td><td style="padding:6px;text-align:left;font-weight:900;"><?php echo number_format((float)$row['total']); ?></td></tr>
                </table>
            </div>

            <?php if (!empty($row['notes'])): ?>
                <div style="margin-top:12px;border-top:1px dashed #ccc;padding-top:10px;">
                    <b>توضیحات:</b><br>
                    <div><?php echo nl2br(esc_html($row['notes'])); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }
}
