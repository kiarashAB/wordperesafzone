<?php
if (!defined('ABSPATH')) exit;

class MBP_Invoice
{
    public function __construct()
    {
        add_action('wp_ajax_mbp_get_invoices', array($this, 'ajax_get_invoices'));
    }

    public function ajax_get_invoices()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید'));
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mbp_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'درخواست نامعتبر است'));
        }

        if (!MBP_License::invoice_is_valid()) {
            wp_send_json_success(array(
                'html' => $this->render_license_required_box()
            ));
        }

        if (!class_exists('WooCommerce')) {
            wp_send_json_success(array(
                'html' => '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                            <div style="font-weight:800;margin-bottom:8px;">ووکامرس فعال نیست</div>
                            <div style="color:#6b7280;font-size:13px;">برای نمایش فاکتورها، افزونه WooCommerce باید فعال باشد.</div>
                        </div>'
            ));
        }

        $orders = wc_get_orders(array(
            'limit'   => 20,
            'orderby' => 'date',
            'order'   => 'DESC',
        ));

        ob_start();
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;">
                <div>
                    <div style="font-weight:900;font-size:16px;">فاکتورها (سفارش‌های ووکامرس)</div>
                    <div style="color:#6b7280;font-size:13px;margin-top:4px;">فعلاً به‌صورت لیست سفارش‌هاست؛ مرحله بعدی چاپ/ساخت فاکتور رو اضافه می‌کنیم.</div>
                </div>
                <button type="button" class="button" id="mbp-refresh-invoices">🔄 بروزرسانی</button>
            </div>

            <div style="overflow:auto;">
                <table class="widefat striped" style="min-width:900px;">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>تاریخ</th>
                            <th>مشتری</th>
                            <th>وضعیت</th>
                            <th>مبلغ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)) : ?>
                        <tr><td colspan="6" style="color:#6b7280;">هیچ سفارشی یافت نشد.</td></tr>
                    <?php else : ?>
                        <?php foreach ($orders as $order) :
                            /** @var WC_Order $order */
                            $order_id = $order->get_id();
                            $date     = $order->get_date_created() ? $order->get_date_created()->date_i18n('Y/m/d H:i') : '-';
                            $name     = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                            $name     = $name ? $name : '—';
                            $status   = wc_get_order_status_name($order->get_status());
                            $total    = $order->get_formatted_order_total();

                            $edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                            ?>
                            <tr>
                                <td>#<?php echo esc_html($order_id); ?></td>
                                <td><?php echo esc_html($date); ?></td>
                                <td><?php echo esc_html($name); ?></td>
                                <td><?php echo esc_html($status); ?></td>
                                <td><?php echo wp_kses_post($total); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url($edit_url); ?>" target="_blank">مشاهده سفارش</a>
                                    <button class="button button-small" type="button" disabled title="مرحله بعدی اضافه می‌شود">چاپ فاکتور</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        jQuery(function($){
            $('#mbp-refresh-invoices').on('click', function(){
                $.post(ajaxurl, {
                    action: 'mbp_get_invoices',
                    nonce: '<?php echo esc_js(wp_create_nonce('mbp_admin_action_nonce')); ?>'
                }, function(resp){
                    if(resp && resp.success && resp.data && resp.data.html){
                        $('#mbp-invoices-container').html(resp.data.html);
                    }
                });
            });
        });
        </script>
        <?php

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    private function render_license_required_box()
    {
        $msg = 'برای استفاده از بخش فاکتور، باید لایسنس مخصوص فاکتور فعال باشد.';
        return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                    <div style="font-weight:900;margin-bottom:6px;">🔒 بخش فاکتور غیرفعال است</div>
                    <div style="color:#6b7280;font-size:13px;">' . esc_html($msg) . '</div>
                </div>';
    }
}
