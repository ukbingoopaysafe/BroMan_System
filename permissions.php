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
 * فحص صلاحية إدارة الموظفين
 * فقط المدير يمكنه إدارة الموظفين
 */
function can_manage_employees() {
    return is_manager();
}

/**
 * فحص صلاحية إدارة العقارات (إضافة، تعديل، حذف)
 * المدير والمسؤول يمكنهما إدارة العقارات
 */
function can_manage_properties() {
    return is_manager() || is_admin();
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
 * جميع المستخدمين يمكنهم عرض العقارات
 */
function can_view_properties() {
    return is_logged_in();
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
            
        case 'manage_properties':
            if (!can_manage_properties()) {
                $_SESSION["error_message"] = "ليس لديك صلاحية لإدارة العقارات.";
                header("Location: dashboard.php");
                exit;
            }
            break;
            
        case 'view_properties':
            if (!can_view_properties()) {
                $_SESSION["error_message"] = "ليس لديك صلاحية لعرض العقارات.";
                header("Location: dashboard.php");
                exit;
            }
            break;
            
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
?>

