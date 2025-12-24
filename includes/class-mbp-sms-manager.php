<?php
if (!defined('ABSPATH')) exit;

class MBP_SMS_Manager {
    
    private $gateway;
    private $api_key;
    private $sender;
    
    public function __construct() {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';
        $settings = $wpdb->get_row("SELECT * FROM $table LIMIT 1");
        
        if ($settings) {
            $this->gateway = $settings->gateway ?: 'kavenegar';
            $this->api_key = $settings->api_key ?: '';
            $this->sender = $settings->sender_number ?: '';
        } else {
            $this->gateway = 'kavenegar';
            $this->api_key = '';
            $this->sender = '';
        }
    }
    
    // ارسال پیامک عمومی
    public function send($phone, $message, $type = 'general') {
        if (!$this->api_key || empty($phone) || empty($message)) {
            return false;
        }
        
        // حذف صفر اول اگر با 0 شروع شده
        $phone = ltrim($phone, '0');
        
        // اگر شماره با 98 شروع نشده، اضافه کن
        if (substr($phone, 0, 2) !== '98') {
            $phone = '98' . $phone;
        }
        
        $result = false;
        
        switch($this->gateway) {
            case 'kavenegar':
                $result = $this->send_via_kavenegar($phone, $message);
                break;
            case 'ghasedak':
                $result = $this->send_via_ghasedak($phone, $message);
                break;
            case 'melipayamak':
                $result = $this->send_via_melipayamak($phone, $message);
                break;
        }
        
        // ذخیره لاگ
        $this->log_sms($phone, $message, $type, $result);
        
        return $result;
    }
    
    // ارسال تأیید رزرو
    public function send_booking_confirmation($phone, $appointment_data) {
        if (!$this->is_enabled('booking')) {
            return false;
        }
        
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
        if (!$this->is_enabled('payment')) {
            return false;
        }
        
        $message = "✅ پرداخت موفق\n";
        $message .= "💰 مبلغ: " . number_format($payment_data['amount']) . " تومان\n";
        $message .= "🔢 شماره پیگیری: {$payment_data['ref_id']}\n";
        $message .= "📅 تاریخ: {$payment_data['date']}\n";
        $message .= "با تشکر از پرداخت شما";
        
        return $this->send($phone, $message, 'payment');
    }
    
    // ارسال یادآوری
    public function send_reminder($phone, $appointment_data) {
        if (!$this->is_enabled('reminder')) {
            return false;
        }
        
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
    
    // ارسال از طریق کاوه‌نگار
    private function send_via_kavenegar($phone, $message) {
        $url = "https://api.kavenegar.com/v1/{$this->api_key}/sms/send.json";
        
        $args = array(
            'body' => array(
                'receptor' => $phone,
                'sender'   => $this->sender,
                'message'  => $message
            ),
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return isset($body['return']['status']) && $body['return']['status'] == 200;
    }
    
    // ارسال از طریق قاصدک
    private function send_via_ghasedak($phone, $message) {
        $url = "http://api.ghasedaksms.com/v2/sms/send/simple";
        
        $args = array(
            'headers' => array(
                'apikey' => $this->api_key
            ),
            'body' => array(
                'message' => $message,
                'receptor' => $phone,
                'linenumber' => $this->sender
            ),
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return isset($body['result']['code']) && $body['result']['code'] == 200;
    }
    
    // ارسال از طریق ملی پیامک
    private function send_via_melipayamak($phone, $message) {
        $url = "https://rest.payamak-panel.com/api/SendSMS/SendSMS";
        
        $args = array(
            'body' => array(
                'username' => $this->api_key,
                'password' => '', // اگر نیاز باشد
                'to' => $phone,
                'from' => $this->sender,
                'text' => $message,
                'isFlash' => false
            ),
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return isset($body['RetStatus']) && $body['RetStatus'] == 1;
    }
    
    // بررسی فعال بودن نوع پیامک
    private function is_enabled($type) {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';
        $settings = $wpdb->get_row("SELECT * FROM $table LIMIT 1");
        
        if (!$settings) return false;
        
        switch($type) {
            case 'booking':
                return (bool) $settings->enable_booking_sms;
            case 'payment':
                return (bool) $settings->enable_payment_sms;
            case 'reminder':
                return (bool) $settings->enable_reminder_sms;
            default:
                return true;
        }
    }
    
    // ذخیره لاگ پیامک
    private function log_sms($phone, $message, $type, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_logs';
        
        $wpdb->insert($table, array(
            'phone' => $phone,
            'message' => $message,
            'type' => $type,
            'status' => $status ? 1 : 0,
            'response' => json_encode($status)
        ));
    }
    
    // دریافت تنظیمات
    public static function get_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';
        return $wpdb->get_row("SELECT * FROM $table LIMIT 1");
    }
    
    // ذخیره تنظیمات
    public static function save_settings($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_sms_settings';
        
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        if ($exists) {
            $wpdb->update($table, $data, array('id' => 1));
        } else {
            $wpdb->insert($table, $data);
        }
        
        return true;
    }
}