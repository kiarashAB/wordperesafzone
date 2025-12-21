<?php
if (!defined('ABSPATH')) exit;

class MBP_License
{
    const OPTION_KEY   = 'mbp_license_key_v1';
    const OPTION_STATE = 'mbp_license_state_v1'; // active|inactive
    const OPTION_INFO  = 'mbp_license_info_v1';  // آرایه اطلاعات (اختیاری)

    // آدرس API شما:
    const API_URL = 'http://lisense.somee.com/api/licenses';

    public static function is_active(): bool
    {
        $state = get_option(self::OPTION_STATE, 'inactive');
        return $state === 'active';
    }

    public static function get_key(): string
    {
        return (string) get_option(self::OPTION_KEY, '');
    }

    public static function set_inactive(string $msg = ''): void
    {
        update_option(self::OPTION_STATE, 'inactive', false);
        if ($msg !== '') {
            update_option(self::OPTION_INFO, array('message' => $msg), false);
        }
    }

    public static function activate(string $license_key): array
    {
        $license_key = trim($license_key);
        if ($license_key === '') {
            return array('ok' => false, 'message' => 'لایسنس خالی است');
        }

        $payload = array(
            // بسته به بک‌اندت ممکنه اسم فیلدها فرق کنه:
            'licenseKey' => $license_key,
            'domain'     => parse_url(home_url(), PHP_URL_HOST),
        );

        $res = wp_remote_post(self::API_URL, array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($payload),
        ));

        if (is_wp_error($res)) {
            return array('ok' => false, 'message' => 'خطا در ارتباط با سرور لایسنس');
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);

        // ⚠️ این بخش رو با خروجی واقعی API خودت هماهنگ کن
        // فرض: اگر HTTP 200 و json['valid'] == true یعنی معتبر
        $valid = false;
        $msg   = 'نامعتبر';

        if ($code === 200 && is_array($json)) {
            $valid = !empty($json['valid']);
            $msg   = $json['message'] ?? ($valid ? 'فعال شد' : 'نامعتبر');
        } else {
            $msg = 'پاسخ نامعتبر از سرور لایسنس';
        }

        if (!$valid) {
            self::set_inactive($msg);
            return array('ok' => false, 'message' => $msg);
        }

        // ذخیره
        update_option(self::OPTION_KEY, $license_key, false);
        update_option(self::OPTION_STATE, 'active', false);
        update_option(self::OPTION_INFO, is_array($json) ? $json : array('message' => $msg), false);

        return array('ok' => true, 'message' => $msg);
    }

    public static function deactivate_local(): void
    {
        // فقط داخل سایت غیرفعال می‌کنه (اختیاری)
        update_option(self::OPTION_STATE, 'inactive', false);
    }

    public static function get_info(): array
    {
        $info = get_option(self::OPTION_INFO, array());
        return is_array($info) ? $info : array();
    }
}
