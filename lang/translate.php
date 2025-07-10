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
    return $_SESSION['lang'];
}
