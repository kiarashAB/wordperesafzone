<?php
if (!defined('ABSPATH')) exit;

class MBP_Invoice_License
{
    const OPT_KEY       = 'mbp_invoice_license_key';
    const OPT_VALID     = 'mbp_invoice_license_valid';
    const OPT_LASTCHECK = 'mbp_invoice_license_lastcheck';

    // اینو مطابق API خودت بگذار
    const PRODUCT_SLUG  = 'invoice'; // مثلا: 'factor' یا هر چی تو دیتابیس/API داری
    const API_URL       = 'http://lisense.somee.com/api/licenses';

    private static function normalize($value)
    {
        $value = trim((string)$value);
        if ($value === '') return '';

        if (!preg_match('~^https?://~i', $value)) $value_for_parse = 'http://' . $value;
        else $value_for_parse = $value;

        $parts = wp_parse_url($value_for_parse);
        $host  = isset($parts['host']) ? strtolower($parts['host']) : '';
        $path  = isset($parts['path']) ? trim($parts['path']) : '';

        $host = preg_replace('~^www\.~i', '', $host);
        $path = rtrim($path, '/');

        if ($host === '') {
            $value = strtolower($value);
            $value = preg_replace('~^https?://~i', '', $value);
            $value = preg_replace('~^www\.~i', '', $value);
            $value = rtrim($value, '/');
            return $value;
        }

        return $host . ($path ? $path : '');
    }

    private static function this_site_normalized()
    {
        return self::normalize(site_url('/'));
    }

    public static function is_valid()
    {
        $valid = (bool) get_option(self::OPT_VALID, false);
        $last  = (int)  get_option(self::OPT_LASTCHECK, 0);

        if ($valid && $last && (time() - $last) < 12 * HOUR_IN_SECONDS) return true;

        $key = (string) get_option(self::OPT_KEY, '');
        if ($key === '') return false;

        $res = self::verify_remote($key);

        update_option(self::OPT_LASTCHECK, time(), false);

        if ($res['ok']) {
            update_option(self::OPT_VALID, 1, false);
            return true;
        }

        update_option(self::OPT_VALID, 0, false);
        return false;
    }

    public static function deactivate_local()
    {
        update_option(self::OPT_VALID, 0, false);
        update_option(self::OPT_LASTCHECK, time(), false);
    }

    public static function activate($license_key)
    {
        $license_key = trim((string)$license_key);
        if ($license_key === '') {
            return ['ok' => false, 'message' => 'کلید لایسنس فاکتور خالی است'];
        }

        $res = self::verify_remote($license_key);

        if (!$res['ok']) {
            update_option(self::OPT_VALID, 0, false);
            update_option(self::OPT_LASTCHECK, time(), false);
            return $res;
        }

        update_option(self::OPT_KEY, $license_key, false);
        update_option(self::OPT_VALID, 1, false);
        update_option(self::OPT_LASTCHECK, time(), false);

        return ['ok' => true, 'message' => 'لایسنس فاکتور با موفقیت فعال شد ✅'];
    }

    private static function verify_remote($license_key)
    {
        $resp = wp_remote_get(self::API_URL, ['timeout' => 15]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => 'خطا در اتصال به سرور لایسنس: ' . $resp->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);

        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از سرور لایسنس (HTTP ' . $code . ')'];
        }

        $list = json_decode($body, true);
        if (!is_array($list)) {
            return ['ok' => false, 'message' => 'JSON لایسنس معتبر نیست'];
        }

        $siteN = self::this_site_normalized();

        foreach ($list as $row) {
            $k = $row['licenseKey']  ?? '';
            $p = $row['productSlug'] ?? '';
            $d = $row['boundDomain'] ?? '';
            $s = $row['status']      ?? false;

            if ((string)$k !== (string)$license_key) continue;

            if ((string)$p !== self::PRODUCT_SLUG) {
                return ['ok' => false, 'message' => 'این لایسنس برای محصول دیگری است'];
            }

            if (!$s) {
                return ['ok' => false, 'message' => 'این لایسنس غیرفعال است'];
            }

            $boundN = self::normalize($d);
            if ($boundN !== $siteN) {
                return ['ok' => false, 'message' => 'این لایسنس برای این دامنه نیست. دامنه ثبت‌شده: ' . $d];
            }

            return ['ok' => true, 'message' => 'معتبر ✅'];
        }

        return ['ok' => false, 'message' => 'کلید لایسنس پیدا نشد'];
    }
}
