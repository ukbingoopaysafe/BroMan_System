<?php
// lang/translate.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default language if not set
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ar'; // Default to Arabic as requested
}

// Handle language change request
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    if ($lang == 'en' || $lang == 'ar') {
        $_SESSION['lang'] = $lang;
        // Redirect back to the same page without the lang parameter to avoid issues on refresh/form submission
        $current_page = basename($_SERVER['PHP_SELF']);
        $query_params = $_GET;
        unset($query_params['lang']);
        $redirect_url = $current_page . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
        header("Location: " . $redirect_url);
        exit();
    }
}

// Load the selected language file
$lang_file = __DIR__ . '/' . $_SESSION['lang'] . '.php';
if (file_exists($lang_file)) {
    $translations = include($lang_file);
} else {
    // Fallback to Arabic if language file not found
    $translations = include(__DIR__ . '/ar.php');
}

// Translation function
function t($key)
{
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : $key; // Return key if translation not found
}

// Function to get current language direction
function get_direction()
{
    return ($_SESSION['lang'] == 'ar') ? 'rtl' : 'ltr';
}

// Function to get current language code
function get_lang_code()
{
    return $_SESSION['lang'] ?? 'ar';
}

// Universal number formatting helper that removes trailing zeros only if decimals exist
if (!function_exists('formatSmartNumber')) {
    function formatSmartNumber($num, $max_decimals = 2) {
        if ($num === null || $num === '' || !is_numeric($num)) {
            return '';
        }
        $formatted = number_format(floatval($num), $max_decimals, '.', ',');
        if (strpos($formatted, '.') !== false) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }
        return $formatted;
    }
}

// Universal Arabic years formatter with correct grammar/pluralization
if (!function_exists('formatArabicYears')) {
    function formatArabicYears($years_raw) {
        if ($years_raw === null || $years_raw === '' || !is_numeric($years_raw)) {
            return '';
        }
        $years = floatval($years_raw);
        $has_fraction = (fmod($years, 1.0) !== 0.0);
        $formatted_num = formatSmartNumber($years, $has_fraction ? 1 : 0);

        if (get_lang_code() === 'ar') {
            if ($has_fraction) {
                return $formatted_num . ' سنة';
            }
            $int_val = intval($years);
            if ($int_val === 1) {
                return 'سنة واحدة';
            } elseif ($int_val === 2) {
                return 'سنتان';
            } elseif ($int_val >= 3 && $int_val <= 10) {
                return $formatted_num . ' سنوات';
            } else {
                return $formatted_num . ' سنة';
            }
        } else {
            return $formatted_num . ' ' . (($years == 1) ? t('year') : t('years'));
        }
    }
}

