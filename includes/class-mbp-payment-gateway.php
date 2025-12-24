<?php
if (!defined('ABSPATH')) exit;

class MBP_Payment_Gateway {
    
    private $gateway;
    private $settings;
    
    public function __construct($gateway = 'zarinpal') {
        $this->gateway = $gateway;
        $this->settings = $this->get_gateway_settings();
    }
    
    // درخواست پرداخت
    public function request_payment($appointment_id, $amount, $description, $callback_url, $additional_data = array()) {
        global $wpdb;
        
        // بررسی اطلاعات رزرو
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mbp_appointments WHERE id = %d",
            $appointment_id
        ));
        
        if (!$appointment) {
            return array('success' => false, 'message' => 'رزرو پیدا نشد');
        }
        
        // تولید شناسه یکتا برای تراکنش
        $transaction_id = $this->generate_transaction_id();
        
        switch($this->gateway) {
            case 'zarinpal':
                return $this->zarinpal_request($appointment_id, $amount, $description, $callback_url, $transaction_id, $additional_data);
            case 'idpay':
                return $this->idpay_request($appointment_id, $amount, $description, $callback_url, $transaction_id, $additional_data);
            case 'nextpay':
                return $this->nextpay_request($appointment_id, $amount, $description, $callback_url, $transaction_id, $additional_data);
            default:
                return array('success' => false, 'message' => 'درگاه پرداخت پشتیبانی نمی‌شود');
        }
    }
    
    // تأیید پرداخت
    public function verify_payment($transaction_id, $status, $additional_data = array()) {
        global $wpdb;
        
        // پیدا کردن پرداخت
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mbp_payments WHERE transaction_id = %s",
            $transaction_id
        ));
        
        if (!$payment) {
            return array('success' => false, 'message' => 'تراکنش پیدا نشد');
        }
        
        switch($this->gateway) {
            case 'zarinpal':
                return $this->zarinpal_verify($payment, $status, $additional_data);
            case 'idpay':
                return $this->idpay_verify($payment, $status, $additional_data);
            case 'nextpay':
                return $this->nextpay_verify($payment, $status, $additional_data);
            default:
                return array('success' => false, 'message' => 'درگاه پرداخت پشتیبانی نمی‌شود');
        }
    }
    
    // درخواست پرداخت زرین‌پال
    private function zarinpal_request($appointment_id, $amount, $description, $callback_url, $transaction_id, $additional_data) {
        $merchant_id = $this->settings['zarinpal_merchant_id'] ?? '';
        
        if (empty($merchant_id)) {
            return array('success' => false, 'message' => 'مرچنت کد زرین‌پال تنظیم نشده است');
        }
        
        $data = array(
            'merchant_id'  => $merchant_id,
            'amount'       => $amount * 10, // تبدیل به ریال
            'description'  => $description,
            'callback_url' => $callback_url,
            'metadata'     => array(
                'appointment_id' => $appointment_id,
                'transaction_id' => $transaction_id,
                'email' => $additional_data['email'] ?? '',
                'mobile' => $additional_data['phone'] ?? ''
            )
        );
        
        $response = wp_remote_post('https://api.zarinpal.com/pg/v4/payment/request.json', array(
            'body'    => json_encode($data),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'خطا در ارتباط با درگاه پرداخت');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body['data']['code'] == 100) {
            // ذخیره اطلاعات پرداخت
            $this->save_payment_request($appointment_id, $amount, $transaction_id, $body['data']['authority']);
            
            return array(
                'success' => true,
                'payment_url' => 'https://www.zarinpal.com/pg/StartPay/' . $body['data']['authority'],
                'authority' => $body['data']['authority'],
                'transaction_id' => $transaction_id
            );
        }
        
        $error_message = $this->get_zarinpal_error($body['data']['code']);
        return array('success' => false, 'message' => $error_message);
    }
    
    // تأیید پرداخت زرین‌پال
    private function zarinpal_verify($payment, $status, $additional_data) {
        if ($status != 'OK') {
            $this->update_payment_status($payment->id, 'failed', array('status' => 'user_cancelled'));
            return array('success' => false, 'message' => 'پرداخت توسط کاربر لغو شد');
        }
        
        $authority = $additional_data['Authority'] ?? '';
        $merchant_id = $this->settings['zarinpal_merchant_id'] ?? '';
        
        if (empty($authority)) {
            return array('success' => false, 'message' => 'شناسه تراکنش نامعتبر است');
        }
        
        $data = array(
            'merchant_id' => $merchant_id,
            'authority'   => $authority,
            'amount'      => $payment->amount * 10 // ریال
        );
        
        $response = wp_remote_post('https://api.zarinpal.com/pg/v4/payment/verify.json', array(
            'body'    => json_encode($data),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'خطا در ارتباط با درگاه پرداخت');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body['data']['code'] == 100 || $body['data']['code'] == 101) {
            // آپدیت وضعیت پرداخت
            $this->update_payment_status($payment->id, 'completed', array(
                'ref_id' => $body['data']['ref_id'],
                'card_pan' => $body['data']['card_pan'] ?? '',
                'fee' => $body['data']['fee'] ?? 0,
                'payment_data' => json_encode($body['data'])
            ));
            
            // آپدیت وضعیت رزرو
            $this->update_appointment_payment_status($payment->appointment_id, 'paid');
            
            // ارسال پیامک
            $this->send_payment_sms($payment->appointment_id, $body['data']['ref_id']);
            
            return array(
                'success' => true,
                'ref_id' => $body['data']['ref_id'],
                'amount' => $body['data']['amount'] / 10, // تبدیل به تومان
                'card_pan' => $body['data']['card_pan'] ?? ''
            );
        }
        
        $error_message = $this->get_zarinpal_error($body['data']['code']);
        $this->update_payment_status($payment->id, 'failed', array('error' => $error_message));
        
        return array('success' => false, 'message' => $error_message);
    }
    
    // درخواست پرداخت آیدی پی
    private function idpay_request($appointment_id, $amount, $description, $callback_url, $transaction_id, $additional_data) {
        $api_key = $this->settings['idpay_api_key'] ?? '';
        
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'API Key آیدی پی تنظیم نشده است');
        }
        
        $data = array(
            'order_id' => $transaction_id,
            'amount'   => $amount * 10, // ریال
            'name'     => $additional_data['name'] ?? '',
            'phone'    => $additional_data['phone'] ?? '',
            'mail'     => $additional_data['email'] ?? '',
            'desc'     => $description,
            'callback' => $callback_url
        );
        
        $response = wp_remote_post('https://api.idpay.ir/v1.1/payment', array(
            'body'    => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-KEY' => $api_key,
                'X-SANDBOX' => $this->settings['idpay_sandbox'] ?? 0
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'خطا در ارتباط با درگاه پرداخت');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['id']) && isset($body['link'])) {
            // ذخیره اطلاعات پرداخت
            $this->save_payment_request($appointment_id, $amount, $transaction_id, $body['id']);
            
            return array(
                'success' => true,
                'payment_url' => $body['link'],
                'authority' => $body['id'],
                'transaction_id' => $transaction_id
            );
        }
        
        $error_message = $body['error_message'] ?? 'خطای ناشناخته از درگاه پرداخت';
        return array('success' => false, 'message' => $error_message);
    }
    
    // بقیه متدهای درگاه‌ها...
    
    // متدهای کمکی
    
    private function generate_transaction_id() {
        return uniqid('mbp_') . '_' . time();
    }
    
    private function save_payment_request($appointment_id, $amount, $transaction_id, $authority) {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_payments';
        
        return $wpdb->insert($table, array(
            'appointment_id' => $appointment_id,
            'amount' => $amount,
            'transaction_id' => $transaction_id,
            'payment_gateway' => $this->gateway,
            'status' => 'pending',
            'payment_data' => json_encode(array('authority' => $authority))
        ));
    }
    
    private function update_payment_status($payment_id, $status, $additional_data = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_payments';
        
        $data = array('status' => $status);
        
        if (isset($additional_data['ref_id'])) {
            $data['ref_id'] = $additional_data['ref_id'];
        }
        
        if (isset($additional_data['payment_data'])) {
            $data['payment_data'] = $additional_data['payment_data'];
        }
        
        return $wpdb->update($table, $data, array('id' => $payment_id));
    }
    
    private function update_appointment_payment_status($appointment_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'mbp_appointments';
        
        return $wpdb->update($table, 
            array('payment_status' => $status),
            array('id' => $appointment_id)
        );
    }
    
    private function send_payment_sms($appointment_id, $ref_id) {
        global $wpdb;
        
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, s.name as service_name 
             FROM {$wpdb->prefix}mbp_appointments a 
             LEFT JOIN {$wpdb->prefix}mbp_services s ON a.service_id = s.id 
             WHERE a.id = %d",
            $appointment_id
        ));
        
        if ($appointment && $appointment->customer_phone) {
            $sms = new MBP_SMS_Manager();
            $sms->send_payment_confirmation($appointment->customer_phone, array(
                'amount' => number_format($appointment->price ?? 0),
                'ref_id' => $ref_id,
                'date' => date('Y/m/d H:i')
            ));
        }
    }
    
    private function get_gateway_settings() {
        return array(
            'zarinpal_merchant_id' => get_option('mbp_zarinpal_merchant_id', ''),
            'idpay_api_key' => get_option('mbp_idpay_api_key', ''),
            'idpay_sandbox' => get_option('mbp_idpay_sandbox', 0),
            'nextpay_api_key' => get_option('mbp_nextpay_api_key', '')
        );
    }
    
    private function get_zarinpal_error($code) {
        $errors = array(
            -9  => 'خطای اعتبار سنجی',
            -10 => 'آی پی یا مرچنت کد پذیرنده صحیح نیست',
            -11 => 'مرچنت کد فعال نیست',
            -12 => 'تلاش بیش از حد در یک بازه زمانی کوتاه',
            -15 => 'ترمینال شما به حالت تعلیق درآمده است',
            -16 => 'سطح تایید پذیرنده پایین تر از سطح نقره ای است',
            -30 => 'اجازه دسترسی به تسویه اشتراکی شناور ندارید',
            -31 => 'حساب بانکی تسویه را به پنل اضافه کنید',
            -32 => 'مبلغ پرداختی از مبلغ کل تراکنش بیشتر است',
            -33 => 'درصد های وارد شده صحیح نیست',
            -34 => 'مبلغ از کل تراکنش بیشتر است',
            -35 => 'تعداد افراد دریافت کننده تسویه بیش از حد مجاز است',
            -40 => 'پارامترهای اضافی نامعتبر',
            -50 => 'مبلغ پرداخت شده با مقدار وریفای متفاوت است',
            -51 => 'پرداخت ناموفق',
            -52 => 'خطای غیرمنتظره',
            -53 => 'اتوریتی برای این مرچنت کد نیست',
            -54 => 'اتوریتی نامعتبر است'
        );
        
        return $errors[$code] ?? 'خطای ناشناخته از درگاه پرداخت';
    }
}