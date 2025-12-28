<?php
if (!defined('ABSPATH')) exit;

class MBP_License
{
    // --- رزرو (قبلی)
    const OPT_KEY       = 'mbp_license_key';
    const OPT_VALID     = 'mbp_license_valid';
    const OPT_LASTCHECK = 'mbp_license_lastcheck';
    const PRODUCT_SLUG  = 'rezerv';

    // --- فاکتور (جدید)
    const OPT_INVOICE_KEY       = 'mbp_invoice_license_key';
    const OPT_INVOICE_VALID     = 'mbp_invoice_license_valid';
    const OPT_INVOICE_LASTCHECK = 'mbp_invoice_license_lastcheck';
    const PRODUCT_SLUG_INVOICE  = 'rezerv_invoice'; // باید در API هم همین productSlug باشد

    const API_URL = 'http://lisense.somee.com/api/licenses';

    private static function normalize($value)
    {
        $value = trim((string)$value);
        if ($value === '') return '';

        if (!preg_match('~^https?://~i', $value)) {
            $value_for_parse = 'http://' . $value;
        } else {
            $value_for_parse = $value;
        }

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

    // =========================================================
    // رزرو (Backwards compatible)
    // =========================================================
    public static function is_valid()
    {
        return self::product_is_valid(self::OPT_KEY, self::OPT_VALID, self::OPT_LASTCHECK, self::PRODUCT_SLUG);
    }

    public static function activate($license_key)
    {
        return self::product_activate(self::OPT_KEY, self::OPT_VALID, self::OPT_LASTCHECK, self::PRODUCT_SLUG, $license_key, 'رزرو');
    }

    public static function deactivate_local()
    {
        update_option(self::OPT_VALID, 0, false);
        update_option(self::OPT_LASTCHECK, time(), false);
    }

    public static function get_booking_key()
    {
        return (string) get_option(self::OPT_KEY, '');
    }

    // =========================================================
    // فاکتور
    // =========================================================
    public static function invoice_is_valid()
    {
        return self::product_is_valid(self::OPT_INVOICE_KEY, self::OPT_INVOICE_VALID, self::OPT_INVOICE_LASTCHECK, self::PRODUCT_SLUG_INVOICE);
    }

    public static function invoice_activate($license_key)
    {
        return self::product_activate(self::OPT_INVOICE_KEY, self::OPT_INVOICE_VALID, self::OPT_INVOICE_LASTCHECK, self::PRODUCT_SLUG_INVOICE, $license_key, 'فاکتور');
    }

    public static function invoice_deactivate_local()
    {
        update_option(self::OPT_INVOICE_VALID, 0, false);
        update_option(self::OPT_INVOICE_LASTCHECK, time(), false);
    }

    public static function get_invoice_key()
    {
        return (string) get_option(self::OPT_INVOICE_KEY, '');
    }

    // =========================================================
    // Core helpers (generic)
    // =========================================================
    private static function product_is_valid($opt_key, $opt_valid, $opt_lastcheck, $product_slug)
    {
        $valid = (bool) get_option($opt_valid, false);
        $last  = (int)  get_option($opt_lastcheck, 0);

        if ($valid && $last && (time() - $last) < 12 * HOUR_IN_SECONDS) {
            return true;
        }

        $key = (string) get_option($opt_key, '');
        if ($key === '') return false;

        $res = self::verify_remote_for_product($key, $product_slug);

        update_option($opt_lastcheck, time(), false);

        if ($res['ok']) {
            update_option($opt_valid, 1, false);
            return true;
        }

        update_option($opt_valid, 0, false);
        return false;
    }

    private static function product_activate($opt_key, $opt_valid, $opt_lastcheck, $product_slug, $license_key, $label_fa)
    {
        $license_key = trim((string)$license_key);
        if ($license_key === '') {
            return array('ok' => false, 'message' => 'کلید لایسنس ' . $label_fa . ' خالی است');
        }

        $res = self::verify_remote_for_product($license_key, $product_slug);

        if (!$res['ok']) {
            update_option($opt_valid, 0, false);
            update_option($opt_lastcheck, time(), false);
            return $res;
        }

        update_option($opt_key, $license_key, false);
        update_option($opt_valid, 1, false);
        update_option($opt_lastcheck, time(), false);

        return array('ok' => true, 'message' => 'لایسنس ' . $label_fa . ' با موفقیت فعال شد ✅');
    }

    private static function verify_remote_for_product($license_key, $product_slug)
    {
        $resp = wp_remote_get(self::API_URL, array('timeout' => 15));

        if (is_wp_error($resp)) {
            return array('ok' => false, 'message' => 'خطا در اتصال به سرور لایسنس: ' . $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);

        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'message' => 'پاسخ نامعتبر از سرور لایسنس (HTTP ' . $code . ')');
        }

        $list = json_decode($body, true);
        if (!is_array($list)) {
            return array('ok' => false, 'message' => 'JSON لایسنس معتبر نیست');
        }

        $siteN = self::this_site_normalized();

        foreach ($list as $row) {
            $k = $row['licenseKey']  ?? '';
            $p = $row['productSlug'] ?? '';
            $d = $row['boundDomain'] ?? '';
            $s = $row['status']      ?? false;

            if ((string)$k !== (string)$license_key) continue;

            if ((string)$p !== (string)$product_slug) {
                return array('ok' => false, 'message' => 'این لایسنس برای محصول دیگری است');
            }

            if (!$s) {
                return array('ok' => false, 'message' => 'این لایسنس غیرفعال است');
            }

            $boundN = self::normalize($d);
            if ($boundN !== $siteN) {
                return array('ok' => false, 'message' => 'این لایسنس برای این دامنه نیست. دامنه ثبت‌شده: ' . $d);
            }

            return array('ok' => true, 'message' => 'معتبر ✅');
        }

        return array('ok' => false, 'message' => 'کلید لایسنس پیدا نشد');
    }
}
