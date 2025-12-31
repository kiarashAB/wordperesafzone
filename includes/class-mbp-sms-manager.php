<?php
if (!defined('ABSPATH')) exit;

class MBP_SMS_Manager {

    private $settings = [];
    private $api_key = '';
    private $sender  = '';
    private $custom_url = '';

    public function __construct() {
        $this->settings   = self::get_settings_array();
        $this->api_key    = (string) ($this->settings['api_key'] ?? '');
        $this->sender     = (string) ($this->settings['sender_number'] ?? '');
        $this->custom_url = (string) ($this->settings['custom_url'] ?? '');
    }

    /**
     * ارسال پیامک عمومی (فقط پنل سفارشی)
     */
    public function send($phone, $message, $type = 'general') {
        $phone   = $this->normalize_phone((string)$phone);
        $message = trim((string)$message);

        if (!$this->api_key || !$this->custom_url || $phone === '' || $message === '') {
            $this->log_sms($phone, $message, $type, false, 'missing_fields_or_settings', 0);
            return false;
        }

        $result = $this->send_via_custom_json($phone, $message);

        $this->log_sms(
            $phone,
            $message,
            $type,
            (bool)$result['ok'],
            $result['error'] ? $result['error'] : $result['body'],
            (int)$result['http_code']
        );

        return (bool)$result['ok'];
    }

    // ارسال تأیید رزرو
    public function send_booking_confirmation($phone, $appointment_data) {
        if (!$this->is_enabled('booking')) return false;

        $message = "✅ رزرو شما ثبت شد\n";
        $message .= "👤 نام: {$appointment_data['name']}\n";
        $message .= "📅 تاریخ: {$appointment_data['date']}\n";
        $message .= "🕐 ساعت: {$appointment_data['time']}\n";
        $message .= "💼 خدمت: {$appointment_data['service']}\n";
        $message .= "🔢 کد رهگیری: {$appointment_data['tracking_code']}\n";
        $message .= "با تشکر از اعتماد شما";

        return $this->send($phone, $message, 'booking');
    }

    // ارسال تأیید پرداخت
    public function send_payment_confirmation($phone, $payment_data) {
        if (!$this->is_enabled('payment')) return false;

        $message = "✅ پرداخت موفق\n";
        $message .= "💰 مبلغ: " . number_format((float)$payment_data['amount']) . " تومان\n";
        $message .= "🔢 شماره پیگیری: {$payment_data['ref_id']}\n";
        $message .= "📅 تاریخ: {$payment_data['date']}\n";
        $message .= "با تشکر از پرداخت شما";

        return $this->send($phone, $message, 'payment');
    }

    // ارسال یادآوری
    public function send_reminder($phone, $appointment_data) {
        if (!$this->is_enabled('reminder')) return false;

        $message = "⏰ یادآوری نوبت\n";
        $message .= "فردا ساعت {$appointment_data['time']} نوبت شماست\n";
        $message .= "💼 خدمت: {$appointment_data['service']}\n";
        $message .= "📍 آدرس: {$appointment_data['location']}\n";
        $message .= "لطفا سر وقت حاضر شوید";

        return $this->send($phone, $message, 'reminder');
    }

    // ارسال کد تأیید
    public function send_verification_code($phone, $code) {
        $message = "🔐 کد تأیید شما: {$code}\n";
        $message .= "این کد ۵ دقیقه اعتبار دارد";
        return $this->send($phone, $message, 'verification');
    }

    /**
     * ✅ دقیقاً مطابق عکس:
     * POST {custom_url}
     * Headers:
     *   Content-Type: application/json
     *   x-api-key: {api_key}   (قابل تغییر از تنظیمات)
     * JSON Body:
     *   { SendNumber, Mobile, Message } (قابل تغییر از تنظیمات)
     */
    private function send_via_custom_json($phone, $message) {
        $url = $this->custom_url;

        $hdr_name = !empty($this->settings['custom_header_name']) ? (string)$this->settings['custom_header_name'] : 'x-api-key';
        $k_send   = !empty($this->settings['custom_key_sendnumber']) ? (string)$this->settings['custom_key_sendnumber'] : 'SendNumber';
        $k_mobile = !empty($this->settings['custom_key_mobile'])     ? (string)$this->settings['custom_key_mobile']     : 'Mobile';
        $k_msg    = !empty($this->settings['custom_key_message'])    ? (string)$this->settings['custom_key_message']    : 'Message';

        $payload = [
            $k_send   => (string)$this->sender,
            $k_mobile => (string)$phone,
            $k_msg    => (string)$message,
        ];

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                $hdr_name      => $this->api_key,
            ],
            'body'        => wp_json_encode($payload),
            'timeout'     => 30,
            'data_format' => 'body',
        ];

        $res = wp_remote_post($url, $args);

        if (is_wp_error($res)) {
            return ['ok'=>false,'http_code'=>0,'body'=>'','error'=>$res->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        // معیار موفقیت: HTTP 2xx
        return [
            'ok'        => ($code >= 200 && $code < 300),
            'http_code' => $code,
            'body'      => $body,
            'error'     => '',
        ];
    }

    /**
     * فرمت شماره طبق تنظیمات:
     * - 0   => 09xxxxxxxxx
     * - 98  => 989xxxxxxxxx
     * - raw => همون عدد خام
     *
     * پیش‌فرض برای API شما: 0 (مثل عکس که Mobile با 0935... بود)
     */
    private function normalize_phone($phone) {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        if ($digits === '') return '';

        $fmt = !empty($this->settings['phone_format']) ? (string)$this->settings['phone_format'] : '0';

        if ($fmt === 'raw') {
            return $digits;
        }

        if ($fmt === '98') {
            if (strpos($digits, '0098') === 0) $digits = substr($digits, 2); // 0098 -> 98
            if ($digits[0] === '0') $digits = ltrim($digits, '0');
            if (strpos($digits, '98') !== 0) $digits = '98' . $digits;
            return $digits;
        }

        // fmt = 0
        if (strpos($digits, '0098') === 0) $digits = substr($digits, 4);
        if (strpos($digits, '98') === 0)   $digits = '0' . substr($digits, 2);
        if ($digits[0] !== '0') $digits = '0' . $digits;
        return $digits;
    }

    private function is_enabled($type) {
        switch ($type) {
            case 'booking':  return !empty($this->settings['enable_booking_sms']);
            case 'payment':  return !empty($this->settings['enable_payment_sms']);
            case 'reminder': return !empty($this->settings['enable_reminder_sms']);
            default:         return true;
        }
    }

    private function log_sms($phone, $message, $type, $status, $response = '', $http_code = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_logs';

        $wpdb->insert($table, [
            'phone'      => (string)$phone,
            'message'    => (string)$message,
            'type'       => (string)$type,
            'status'     => $status ? 1 : 0,
            'http_code'  => (int)$http_code,
            'response'   => is_string($response) ? $response : wp_json_encode($response),
            'created_at' => current_time('mysql'),
        ]);
    }

    // -----------------------
    // DB Settings
    // -----------------------

    public static function get_settings_array(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';

        $row = $wpdb->get_row("SELECT * FROM $table WHERE id=1 LIMIT 1", ARRAY_A);
        if (!is_array($row)) $row = [];

        $defaults = [
            'id' => 1,
            'api_key' => '',
            'sender_number' => '',
            'custom_url' => '',

            'custom_header_name' => 'x-api-key',
            'custom_key_sendnumber' => 'SendNumber',
            'custom_key_mobile'     => 'Mobile',
            'custom_key_message'    => 'Message',

            'phone_format' => '0',

            'enable_booking_sms' => 0,
            'enable_payment_sms' => 0,
            'enable_reminder_sms' => 0,
        ];

        return array_merge($defaults, $row);
    }

    public static function save_settings($data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';

        $current = self::get_settings_array();
        $allowed = array_keys($current);

        $payload = [];

        foreach ($allowed as $k) {
            if ($k === 'id') continue;

            $v = isset($data[$k]) ? $data[$k] : $current[$k];

            if ($k === 'custom_url') {
                $payload[$k] = esc_url_raw((string)$v);
            } elseif (in_array($k, ['enable_booking_sms','enable_payment_sms','enable_reminder_sms'], true)) {
                $payload[$k] = (int) (!!$v);
            } else {
                $payload[$k] = sanitize_text_field((string)$v);
            }
        }

        $exists = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE id=1");
        if ($exists) {
            $wpdb->update($table, $payload, ['id' => 1]);
        } else {
            $payload['id'] = 1;
            $wpdb->insert($table, $payload);
        }

        return true;
    }
}
