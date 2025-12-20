<?php
if (!defined('ABSPATH')) exit;

class MBP_License
{
    const OPTION_LICENSE_KEY = 'mbp_license_key_v1';
    const TRANSIENT_STATUS   = 'mbp_license_status_v1';

    // آدرس API شما (همونی که دادی)
    const API_URL = 'http://lisense.somee.com/api/licenses';

    /**
     * گرفتن دامنه سایت برای مقایسه با boundDomain
     */
    public static function get_domain()
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = strtolower((string)$host);
        $host = preg_replace('/^www\./', '', $host);
        return $host;
    }

    public static function get_key()
    {
        return (string) get_option(self::OPTION_LICENSE_KEY, '');
    }

    public static function save_key($key)
    {
        $key = sanitize_text_field($key);
        update_option(self::OPTION_LICENSE_KEY, $key, false);
        delete_transient(self::TRANSIENT_STATUS); // بعد از تغییر، کش پاک شود
    }

    /**
     * چک لایسنس با کش (مثلا 12 ساعت)
     */
    public static function is_valid_cached()
    {
        $cached = get_transient(self::TRANSIENT_STATUS);
        if (is_array($cached) && isset($cached['valid'])) {
            return (bool) $cached['valid'];
        }

        $result = self::check_remote();

        // اگر API قطع بود، بهتره سایت قفل نشه؛
        // ولی تو می‌خوای امنیت بالا → پس اینجا تصمیم با توئه:
        // من پیشفرض: اگر خطای شبکه بود، نامعتبر حساب می‌کنم.
        $valid = (is_array($result) && !empty($result['valid'])) ? true : false;

        set_transient(self::TRANSIENT_STATUS, ['valid' => $valid, 'raw' => $result], 12 * HOUR_IN_SECONDS);
        return $valid;
    }

    /**
     * تماس به API و بررسی licenseKey + domain + status
     * چون API فعلی شما لیست برمی‌گردونه، ما توی اون لیست می‌گردیم.
     */
    public static function check_remote()
    {
        $key = self::get_key();
        if ($key === '') {
            return ['valid' => false, 'reason' => 'no_key'];
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 12,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['valid' => false, 'reason' => 'network_error', 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return ['valid' => false, 'reason' => 'bad_status', 'http' => $code];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return ['valid' => false, 'reason' => 'bad_json'];
        }

        $domain = self::get_domain();

        // توی لیست بگرد
        foreach ($data as $row) {
            $licenseKey  = isset($row['licenseKey']) ? (string)$row['licenseKey'] : '';
            $boundDomain = isset($row['boundDomain']) ? strtolower((string)$row['boundDomain']) : '';
            $status      = isset($row['status']) ? (bool)$row['status'] : false;

            $boundDomain = preg_replace('/^www\./', '', $boundDomain);

            if ($licenseKey === $key && $boundDomain === $domain && $status === true) {
                return ['valid' => true, 'reason' => 'ok'];
            }
        }

        return ['valid' => false, 'reason' => 'not_found_or_mismatch', 'domain' => $domain];
    }
}
