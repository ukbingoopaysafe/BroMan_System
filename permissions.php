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

// Auto-refresh permissions from DB into Session to apply changes immediately
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION['employee_id'])) {
    require_once __DIR__ . '/db.php';
    // Use a fresh connection for this check if generic one isn't available, or simple global $conn
    global $conn;
    
    // Fallback if global conn not set (though usually included before)
    $stmt_refresh = null;
    if ($conn) {
        // Fetch current Role and AllowedModes
        // Try with AllowedModes first
        $sql_refresh = "SELECT Role, AllowedModes FROM employees WHERE EmployeeID = ?";
        $stmt_refresh = $conn->prepare($sql_refresh);
        if (!$stmt_refresh) {
            // Fallback for migrations not run
             $sql_refresh = "SELECT Role FROM employees WHERE EmployeeID = ?";
             $stmt_refresh = $conn->prepare($sql_refresh);
             $has_modes = false;
        } else {
             $has_modes = true;
        }

        if ($stmt_refresh) {
            $stmt_refresh->bind_param("i", $_SESSION['employee_id']);
            if ($stmt_refresh->execute()) {
                $stmt_refresh->store_result();
                if ($stmt_refresh->num_rows == 1) {
                     if ($has_modes) {
                         $stmt_refresh->bind_result($db_role, $db_modes);
                         $stmt_refresh->fetch();
                         $_SESSION['role'] = $db_role;
                         $_SESSION['allowed_modes'] = !empty($db_modes) ? $db_modes : 'Primary,Resale';
                     } else {
                         $stmt_refresh->bind_result($db_role);
                         $stmt_refresh->fetch();
                         $_SESSION['role'] = $db_role;
                         // Session allowed_modes remains as is or recalculated based on role default
                     }
                }
            }
            $stmt_refresh->close();
        }
    }
}

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
 * الحصول على الأنماط المسموح بها للمستخدم الحالي
 * يستخدم الأنماط المخزنة في الجلسة (نظام الصلاحيات المرن)
 */
function get_allowed_modes_for_role($role = null) {
    // المدير دائماً له كل الصلاحيات
    $role = $role ?? get_user_role();
    if ($role === 'manager') {
        return VALID_LISTING_MODES;
    }
    
    // استخدام الأنماط المخزنة في الجلسة (نظام الصلاحيات المرن)
    if (isset($_SESSION['allowed_modes']) && !empty($_SESSION['allowed_modes'])) {
        $modes = explode(',', $_SESSION['allowed_modes']);
        // تصفية الأنماط الصالحة فقط
        return array_values(array_intersect($modes, VALID_LISTING_MODES));
    }
    
    // القيمة الافتراضية إذا لم تكن موجودة في الجلسة
    return ['Primary', 'Resale'];
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
 * فحص ما إذا كان المستخدم عادي
 */
function is_user() {
    return get_user_role() === 'user';
}

/**
 * فحص ما إذا كان المستخدم مسؤول إيجارات (للتوافق مع الكود القديم)
 * @deprecated استخدم is_admin() مع فحص الأنماط المسموحة
 */
function is_rental_admin() {
    return is_admin() && in_array('Rental', get_allowed_modes_for_role());
}

/**
 * فحص ما إذا كان المستخدم عادي للإيجارات (للتوافق مع الكود القديم)
 * @deprecated استخدم is_user() مع فحص الأنماط المسموحة
 */
function is_rental_user() {
    return is_user() && in_array('Rental', get_allowed_modes_for_role());
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

    // المسؤول يمكنه إدارة العقارات في الأنماط المسموح له بها
    if (is_admin()) {
        $allowed = get_allowed_modes_for_role();
        return in_array($mode, $allowed, true);
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
            return 'مسؤول';
        case 'user':
            return 'مستخدم';
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
            return 'Admin';
        case 'user':
            return 'User';
        default:
            return 'Unknown';
    }
}

/**
 * الحصول على أسماء الأنماط المسموحة للعرض
 */
function get_allowed_modes_display($modes_string, $lang = 'ar') {
    if (empty($modes_string)) {
        return $lang === 'ar' ? 'غير محدد' : 'Not set';
    }
    
    $modes = explode(',', $modes_string);
    $display_names = [];
    
    $names_ar = ['Primary' => 'الأساسي', 'Resale' => 'إعادة البيع', 'Rental' => 'الإيجارات'];
    $names_en = ['Primary' => 'Primary', 'Resale' => 'Resale', 'Rental' => 'Rental'];
    
    $names = $lang === 'ar' ? $names_ar : $names_en;
    
    foreach ($modes as $mode) {
        $mode = trim($mode);
        if (isset($names[$mode])) {
            $display_names[] = $names[$mode];
        }
    }
    
    return implode(', ', $display_names);
}
?>

