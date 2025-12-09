<?php
/**
 * Simple activity logging helper
 * Usage: require_once __DIR__ . '/activity_log.php';
 *        log_activity($conn, $employee_id, 'login', $details);
 */

if (!function_exists('log_activity')) {
    function log_activity(mysqli $conn, $employee_id, string $action, $details = null)
    {
        error_log("log_activity called with: employee_id=" . $employee_id . ", action=" . $action);
        
        $session_id = session_status() === PHP_SESSION_ACTIVE ? session_id() : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql = "INSERT INTO activity_logs (employee_id, session_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            // bind params: i s s s s s (employee_id may be null)
            $emp = $employee_id === null ? null : (int)$employee_id;
            $session_id_val = $session_id;
            $action_val = $action;
            $details_val = is_scalar($details) ? (string)$details : json_encode($details);
            $ip_val = $ip;
            $ua_val = $ua;

            // mysqli requires types string; use 'isssss' but allow null employee
            $stmt->bind_param('isssss', $emp, $session_id_val, $action_val, $details_val, $ip_val, $ua_val);
            
            error_log("Executing query with params: " .
                     "emp=$emp, " .
                     "session=$session_id_val, " .
                     "action=$action_val, " .
                     "details=$details_val, " .
                     "ip=$ip_val");
            
            $result = $stmt->execute();
            if (!$result) {
                error_log("Query execution failed: " . $stmt->error);
            }
            $stmt->close();
            return $result;
        } else {
            error_log('log_activity prepare failed: ' . $conn->error);
        }
    }
}

if (!function_exists('get_activity_summary_by_employee')) {
    function get_activity_summary_by_employee(mysqli $conn, int $limit = 200)
    {
        // Aggregate counts per employee
        $sql = "SELECT al.employee_id AS EmployeeID, e.FullName, e.Username,
            MAX(al.created_at) AS last_time,
            SUM(al.action = 'add_property') AS added,
            SUM(al.action = 'edit_property') AS edited,
            MAX(CASE WHEN al.created_at = (SELECT MAX(created_at) FROM activity_logs WHERE employee_id = al.employee_id) THEN al.action END) AS last_action
            FROM activity_logs al
            LEFT JOIN employees e ON e.EmployeeID = al.employee_id
            GROUP BY al.employee_id
            ORDER BY last_time DESC
            LIMIT ?";

        $rows = [];
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $stmt->close();
        }
        return $rows;
    }
}

if (!function_exists('get_activity_logs')) {
    function get_activity_logs(mysqli $conn, $employee_id = null, $limit = 500)
    {
        if ($employee_id === null) {
            $sql = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $limit);
        } else {
            $sql = "SELECT * FROM activity_logs WHERE employee_id = ? ORDER BY created_at DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            $eid = (int)$employee_id;
            $stmt->bind_param('ii', $eid, $limit);
        }
        $rows = [];
        if ($stmt && $stmt->execute()) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $stmt->close();
        }
        return $rows;
    }
}

if (!function_exists('format_activity_details')) {
    /**
     * Convert stored details JSON (or string) into a human-friendly sentence.
     * Supports common actions like add_property, edit_property, login, logout.
     */
    function format_activity_details($action, $details)
    {
        if (empty($details)) return '';
        $decoded = null;
        if (is_string($details)) {
            $d = trim($details);
            // try json decode
            $decoded = json_decode($d, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null;
            }
        } elseif (is_array($details)) {
            $decoded = $details;
        }

        // Helper to format listing type
        $get_listing_type = function($d) {
            $t = $d['listing_type'] ?? '';
            if (!$t) return '';
            // If Arabic, translate
            if (function_exists('get_lang_code') && get_lang_code() === 'ar' && function_exists('t')) {
                // Try translation key
                $trans = t($t);
                return ($trans !== $t) ? $trans : $t;
            }
            return $t;
        };

        // Helper to get ID display
        $get_id_display = function($d) {
            if (!empty($d['friendly_id'])) {
                return '#' . $d['friendly_id'];
            }
            return $d['property_id'] ?? '';
        };

        // Human-friendly formatting
        switch ($action) {
            case 'add_property':
                if (is_array($decoded) && (isset($decoded['property_id']) || isset($decoded['friendly_id']))) {
                    $id_disp = $get_id_display($decoded);
                    $type = $decoded['type'] ?? null;
                    $listing_type = $get_listing_type($decoded);
                    
                    // If Arabic UI, return a translated, structured message
                    if (function_exists('get_lang_code') && get_lang_code() === 'ar') {
                        // Map common type keys to Arabic short labels
                        $type_map_ar = [
                            'residential' => 'سكني',
                            'coastal' => 'ساحلي',
                            'commercial' => 'تجاري/إداري/طبي/فندقي',
                            'other' => 'أخرى'
                        ];
                        $type_label = '';
                        if ($type !== null) {
                            $lower = mb_strtolower((string)$type);
                            $type_label = $type_map_ar[$lower] ?? $type_map_ar[$type] ?? $type;
                        }
                        $parts = [];
                        $parts[] = sprintf('تمت إضافة عقار جديد');
                        $parts[] = sprintf('رقم العقار: %s', $id_disp);
                        if ($listing_type !== '') {
                            $parts[] = sprintf('نظام القائمة: %s', $listing_type);
                        }
                        if ($type_label !== '') {
                            $parts[] = sprintf('النوع: %s', $type_label);
                        }
                        return implode(' | ', $parts);
                    }

                    // Fallback to previous English format
                    $parts = [];
                    $parts[] = sprintf("Added property %s", $id_disp);
                    if ($listing_type) $parts[] = "Listing: $listing_type";
                    if ($type) $parts[] = "Type: $type";
                    return implode(' | ', $parts);
                }
                break;
            case 'edit_property':
                if (is_array($decoded)) {
                    $header = '';
                    if (isset($decoded['property_id']) || isset($decoded['friendly_id'])) {
                        $id_disp = $get_id_display($decoded);
                        $listing_type = $get_listing_type($decoded);
                        $header = sprintf("تم تعديل العقار رقم %s", $id_disp);
                        if ($listing_type) {
                            $header .= sprintf(" (%s)", $listing_type);
                        }
                    }
                    if (isset($decoded['changes']) && is_array($decoded['changes'])) {
                        $body = implode("\n", $decoded['changes']);
                        return $header !== '' ? ($header . "\n" . $body) : $body;
                    } else if ($header !== '') {
                        return $header;
                    }
                }
                break;
            case 'login':
                if (is_array($decoded) && isset($decoded['username'])) {
                    return sprintf("Logged in as %s", $decoded['username']);
                }
                break;
            case 'logout':
                if (is_array($decoded) && isset($decoded['username'])) {
                    return sprintf("Logged out %s", $decoded['username']);
                }
                break;
            case 'property_visit':
                if (is_array($decoded) && (isset($decoded['property_id']) || isset($decoded['friendly_id']))) {
                    $id_disp = $get_id_display($decoded);
                    $title = $decoded['property_title'] ?? '';
                    $listing_type = $get_listing_type($decoded);
                    
                    $suffix = '';
                    if ($listing_type) $suffix = " - $listing_type";

                    return get_lang_code() === 'ar' ? 
                        sprintf("%s (رقم العقار: %s%s)", $title, $id_disp, $suffix) :
                        sprintf("%s (Property ID: %s%s)", $title, $id_disp, $suffix);
                }
                break;
            case 'delete_property':
                if (is_array($decoded) && (isset($decoded['property_id']) || isset($decoded['friendly_id']))) {
                    $id_disp = $get_id_display($decoded);
                    $title = $decoded['property_title'] ?? '';
                    $type = $decoded['property_type'] ?? null;
                    $listing_type = $get_listing_type($decoded);
                    
                    if (function_exists('get_lang_code') && get_lang_code() === 'ar') {
                        $parts = [];
                        $parts[] = sprintf('تم حذف العقار رقم %s', $id_disp);
                        if ($listing_type) $parts[] = $listing_type;
                        if ($title !== '') {
                            $parts[] = sprintf('العنوان: %s', $title);
                        }
                        if ($type !== null && $type !== '') {
                            $parts[] = sprintf('النوع: %s', $type);
                        }
                        return implode(' | ', $parts);
                    }
                    
                    // English format
                    $parts = [];
                    $parts[] = sprintf("Deleted property %s", $id_disp);
                    if ($listing_type) $parts[] = $listing_type;
                    if ($title !== '') {
                        $parts[] = sprintf("Title: %s", $title);
                    }
                    if ($type !== null && $type !== '') {
                        $parts[] = sprintf("Type: %s", $type);
                    }
                    return implode(' | ', $parts);
                }
                break;
        }

        // Fallback: return original string or JSON pretty
        if (is_array($decoded)) return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        return is_string($details) ? $details : json_encode($details);
    }
}

if (!function_exists('get_today_activity_counts')) {
    function get_today_activity_counts(mysqli $conn)
    {
        $sql = "SELECT action, COUNT(*) AS cnt FROM activity_logs WHERE DATE(created_at) = CURDATE() GROUP BY action";
        $res = $conn->query($sql);
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) { $out[$r['action']] = (int)$r['cnt']; }
            $res->close();
        }
        return $out;
    }
}

if (!function_exists('get_online_count')) {
    function get_online_count(mysqli $conn, $session_timeout_seconds = 1800)
    {
        // count distinct employee_ids with last activity within timeout and not logged out
        $sql = "SELECT COUNT(DISTINCT employee_id) as cnt FROM (
            SELECT employee_id, MAX(created_at) as last_time, SUBSTRING_INDEX(GROUP_CONCAT(action ORDER BY created_at DESC SEPARATOR ','), ',', 1) as last_action
            FROM activity_logs
            GROUP BY employee_id
        ) t WHERE t.last_action <> 'logout' AND TIMESTAMPDIFF(SECOND, t.last_time, NOW()) <= ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $session_timeout_seconds);
            $stmt->execute();
            $res = $stmt->get_result();
            $cnt = 0;
            if ($r = $res->fetch_assoc()) { $cnt = (int)$r['cnt']; }
            $stmt->close();
            return $cnt;
        }
        return 0;
    }
}

?>
