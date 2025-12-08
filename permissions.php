<?php
/**
 * نظام إدارة الصلاحيات للأدوار المختلفة
 * 
 * الأدوار:
 * - manager: جميع الصلاحيات
 * - admin: إدارة العقارات (إضافة، تعديل، حذف)
 * - user: عرض العقارات فقط
 */

// التأكد من بدء الجلسة
require_once __DIR__ . '/security.php';
ensure_secure_session_started();

// Supported listing modes
const VALID_LISTING_MODES = ['Primary', 'Resale', 'Rental'];

/**
 * فحص ما إذا كان المستخدم مسجل دخول
 */
function is_logged_in() {
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

/**
 * الحصول على دور المستخدم الحالي
 */
function get_user_role() {
    return isset($_SESSION["role"]) ? $_SESSION["role"] : null;
}

/**
 * الحصول على الأنماط المسموح بها حسب الدور
 */
function get_allowed_modes_for_role($role) {
    switch ($role) {
        case 'manager':
            return VALID_LISTING_MODES;
        case 'admin': // Primary & Resale admin
        case 'user': // Primary & Resale user
            return ['Primary', 'Resale'];
        case 'rental_admin':
        case 'rental_user':
            return ['Rental'];
        default:
            return [];
    }
}

/**
 * فحص صلاحية نمط معين للدور
 */
function is_mode_allowed_for_role($mode, $role = null) {
    $role = $role ?? get_user_role();
    if ($role === 'manager') {
        return true;
    }
    if (!in_array($mode, VALID_LISTING_MODES, true)) {
        return false;
    }
    $allowed = get_allowed_modes_for_role($role);
    return in_array($mode, $allowed, true);
}

/**
 * التأكد من أن سياق النمط الحالي صالح ومسموح به للدور
 * يعيد النمط الحالي أو يعيد التوجيه للوحة التحكم مع رسالة خطأ
 */
function require_listing_context(array $allowed_modes = null) {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }

    $mode = $_SESSION['listing_context'] ?? null;
    if ($mode === null || !in_array($mode, VALID_LISTING_MODES, true)) {
        $_SESSION["error_message"] = function_exists('t') ? t('mode_required') : "برجاء اختيار النمط أولاً.";
        header("Location: dashboard.php");
        exit;
    }

    if (!is_mode_allowed_for_role($mode)) {
        $_SESSION["error_message"] = function_exists('t') ? t('mode_not_allowed_for_role') : "ليس لديك صلاحية للوصول إلى هذا النمط.";
        header("Location: dashboard.php");
        exit;
    }

    if ($allowed_modes !== null && !in_array($mode, $allowed_modes, true)) {
        $_SESSION["error_message"] = function_exists('t') ? t('mode_not_allowed') : "هذا النمط غير متاح لهذا الجزء من النظام.";
        header("Location: dashboard.php");
        exit;
    }

    return $mode;
}

/**
 * فحص ما إذا كان المستخدم مدير
 */
function is_manager() {
    return get_user_role() === 'manager';
}

/**
 * فحص ما إذا كان المستخدم مسؤول
 */
function is_admin() {
    return get_user_role() === 'admin';
}

/**
 * فحص ما إذا كان المستخدم مسؤول إيجارات
 */
function is_rental_admin() {
    return get_user_role() === 'rental_admin';
}

/**
 * فحص ما إذا كان المستخدم عادي
 */
function is_user() {
    return get_user_role() === 'user';
}

/**
 * فحص ما إذا كان المستخدم عادي للإيجارات
 */
function is_rental_user() {
    return get_user_role() === 'rental_user';
}

/**
 * فحص صلاحية إدارة الموظفين
 * فقط المدير يمكنه إدارة الموظفين
 */
function can_manage_employees() {
    return is_manager();
}

/**
 * فحص صلاحية إدارة العقارات (إضافة، تعديل، حذف)
 * المدير يمكنه إدارة كل الأنماط
 * المسؤول الأساسي/إعادة البيع فقط في Primary/Resale
 * مسؤول الإيجارات فقط في Rental
 */
function can_manage_properties($mode = null) {
    $mode = $mode ?? ($_SESSION['listing_context'] ?? null);

    if (is_manager()) {
        return true;
    }

    if ($mode === null || !in_array($mode, VALID_LISTING_MODES, true)) {
        return false;
    }

    if (is_admin()) {
        return in_array($mode, ['Primary', 'Resale'], true);
    }

    if (is_rental_admin()) {
        return $mode === 'Rental';
    }

    return false;
}

/**
 * فحص صلاحية حذف العقارات فقط
 * الحذف مسموح للمدير فقط (manager)
 */
function can_delete_properties() {
    return is_manager();
}

/**
 * فحص صلاحية عرض العقارات
 * جميع الأدوار المسموح لها بالنمط الحالي يمكنها العرض
 */
function can_view_properties($mode = null) {
    if (!is_logged_in()) {
        return false;
    }

    $mode = $mode ?? ($_SESSION['listing_context'] ?? null);
    if ($mode === null) {
        return false;
    }

    if (!in_array($mode, VALID_LISTING_MODES, true)) {
        return false;
    }

    if (is_manager()) {
        return true;
    }

    $role = get_user_role();
    return is_mode_allowed_for_role($mode, $role) && ($role === 'admin' || $role === 'user' || $role === 'rental_admin' || $role === 'rental_user');
}

/**
 * فحص صلاحية الوصول لصفحة معينة
 */
function check_permission($required_permission) {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
    
    switch ($required_permission) {
        case 'manage_employees':
            if (!can_manage_employees()) {
                $_SESSION["error_message"] = "ليس لديك صلاحية للوصول إلى هذه الصفحة.";
                header("Location: dashboard.php");
                exit;
            }
            break;
            
        case 'manage_properties': {
            $mode = $_SESSION['listing_context'] ?? null;
            if (!can_manage_properties($mode)) {
                $_SESSION["error_message"] = "ليس لديك صلاحية لإدارة العقارات.";
                header("Location: dashboard.php");
                exit;
            }
            break;
        }
            
        case 'view_properties': {
            $mode = $_SESSION['listing_context'] ?? null;
            if (!can_view_properties($mode)) {
                $_SESSION["error_message"] = "ليس لديك صلاحية لعرض العقارات.";
                header("Location: dashboard.php");
                exit;
            }
            break;
        }
            
        default:
            // صلاحية غير معروفة
            $_SESSION["error_message"] = "صلاحية غير صحيحة.";
            header("Location: dashboard.php");
            exit;
    }
}

/**
 * الحصول على اسم الدور باللغة العربية
 */
function get_role_name_ar($role) {
    switch ($role) {
        case 'manager':
            return 'مدير';
        case 'admin':
            return 'مسؤول (أساسي/إعادة بيع)';
        case 'rental_admin':
            return 'مسؤول الإيجارات';
        case 'user':
            return 'مستخدم (أساسي/إعادة بيع)';
        case 'rental_user':
            return 'مستخدم الإيجارات';
        default:
            return 'غير محدد';
    }
}

/**
 * الحصول على اسم الدور باللغة الإنجليزية
 */
function get_role_name_en($role) {
    switch ($role) {
        case 'manager':
            return 'Manager';
        case 'admin':
            return 'Primary/Resale Admin';
        case 'rental_admin':
            return 'Rental Admin';
        case 'user':
            return 'Primary/Resale User';
        case 'rental_user':
            return 'Rental User';
        default:
            return 'Unknown';
    }
}
?>

