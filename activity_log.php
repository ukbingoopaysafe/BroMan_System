<?php
/**
 * Simple activity logging helper
 * Usage: require_once __DIR__ . '/activity_log.php';
 *        log_activity($conn, $employee_id, 'login', $details);
 */

if (!function_exists('activity_taxonomy_map')) {
    function activity_taxonomy_map(): array
    {
        return [
            'PROPERTY_CREATE' => 'add_property',
            'PROPERTY_UPDATE' => 'edit_property',
            'PROPERTY_DELETE' => 'delete_property',
            'PROPERTY_VIEW' => 'property_visit',
            'PROPERTY_SHARE_DETAILS' => 'property_share_details',
            'PROPERTY_SHARE_LOCATION' => 'property_share_location',
            'PROPERTY_MEDIA_DOWNLOAD' => 'property_media_download',
            'AUTH_LOGIN_SUCCESS' => 'login',
            'AUTH_LOGIN_FAILED' => 'auth_login_failed',
            'AUTH_USER_BLOCKED' => 'auth_user_blocked',
            'AUTH_LOGOUT' => 'logout',
        ];
    }
}

if (!function_exists('activity_event_label_key')) {
    function activity_event_label_key(string $event_type): string
    {
        $map = [
            'PROPERTY_CREATE' => 'event_property_create',
            'PROPERTY_UPDATE' => 'event_property_update',
            'PROPERTY_DELETE' => 'event_property_delete',
            'PROPERTY_VIEW' => 'event_property_view',
            'PROPERTY_SHARE_DETAILS' => 'event_property_share_details',
            'PROPERTY_SHARE_LOCATION' => 'event_property_share_location',
            'PROPERTY_MEDIA_DOWNLOAD' => 'event_property_media_download',
            'AUTH_LOGIN_SUCCESS' => 'event_auth_login_success',
            'AUTH_LOGIN_FAILED' => 'event_auth_login_failed',
            'AUTH_USER_BLOCKED' => 'event_auth_user_blocked',
            'AUTH_LOGOUT' => 'event_auth_logout',
        ];
        return $map[$event_type] ?? $event_type;
    }
}

if (!function_exists('activity_label')) {
    function activity_label(string $event_type): string
    {
        $key = activity_event_label_key($event_type);
        if (function_exists('t')) {
            $translated = t($key);
            if ($translated !== $key) {
                return $translated;
            }
        }
        return $event_type;
    }
}

if (!function_exists('activity_build_presentation')) {
    function activity_build_presentation(string $event_type, array $context, ?array $property): array
    {
        $summary = activity_label($event_type);
        $fields = [];

        $add_field = function(string $label_key, $value) use (&$fields) {
            if ($value === null || $value === '') return;
            $label = function_exists('t') ? t($label_key) : $label_key;
            if ($label === $label_key) {
                $label = $label_key;
            }
            $fields[] = ['label' => $label, 'value' => $value];
        };

        $property_id = $property['property_id'] ?? ($context['property_id'] ?? null);
        $friendly_id = $property['friendly_id'] ?? null;
        $title = $property['title'] ?? null;
        $listing_type = $property['listing_type'] ?? null;
        $types = $property['property_type_names'] ?? null;

        switch ($event_type) {
            case 'PROPERTY_CREATE':
            case 'PROPERTY_UPDATE':
            case 'PROPERTY_DELETE':
            case 'PROPERTY_VIEW':
            case 'PROPERTY_SHARE_DETAILS':
            case 'PROPERTY_SHARE_LOCATION':
            case 'PROPERTY_MEDIA_DOWNLOAD':
                if ($friendly_id) {
                    $add_field('event_field_property', '#' . $friendly_id);
                } elseif ($property_id) {
                    $add_field('event_field_property', '#' . $property_id);
                }
                if (!empty($title)) {
                    $add_field('event_field_title', $title);
                }
                if (!empty($listing_type)) {
                    $add_field('event_field_listing', $listing_type);
                }
                if (!empty($types) && is_array($types)) {
                    $type_names = [];
                    foreach ($types as $t) {
                        if (!empty($t['ar']) && function_exists('get_lang_code') && get_lang_code() === 'ar') {
                            $type_names[] = $t['ar'];
                        } elseif (!empty($t['en'])) {
                            $type_names[] = $t['en'];
                        }
                    }
                    if (!empty($type_names)) {
                        $add_field('event_field_type', implode(' / ', $type_names));
                    }
                }
                if (!empty($context['file_name'])) {
                    $add_field('event_field_file', $context['file_name']);
                }
                if (!empty($context['media_type'])) {
                    $add_field('event_field_media_type', $context['media_type']);
                }
                break;
            case 'AUTH_LOGIN_SUCCESS':
            case 'AUTH_LOGIN_FAILED':
            case 'AUTH_USER_BLOCKED':
            case 'AUTH_LOGOUT':
                if (!empty($context['username'])) {
                    $add_field('event_field_username', $context['username']);
                }
                if (!empty($context['unauthenticated']) && empty($context['reason'])) {
                    $context['reason'] = function_exists('t') ? t('event_reason_unauthenticated') : 'unauthenticated';
                }
                if (!empty($context['reason'])) {
                    $add_field('event_field_reason', $context['reason']);
                }
                if (!empty($context['ip_address'])) {
                    $add_field('event_field_ip', $context['ip_address']);
                }
                if (isset($context['attempts_remaining'])) {
                    $add_field('event_field_attempts_remaining', $context['attempts_remaining']);
                }
                if (!empty($context['role'])) {
                    $add_field('event_field_role', $context['role']);
                }
                break;
        }

        return [
            'summary' => $summary,
            'fields' => $fields
        ];
    }
}

if (!function_exists('activity_resolve_employee_id')) {
    function activity_resolve_employee_id(mysqli $conn, ?string $username): ?int
    {
        if (!$username) return null;
        $stmt = $conn->prepare("SELECT EmployeeID FROM employees WHERE Username = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? (int)$row['EmployeeID'] : null;
    }
}

if (!function_exists('activity_get_client_ip')) {
    function activity_get_client_ip(): ?string
    {
        if (function_exists('get_client_ip')) {
            return get_client_ip();
        }
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}

if (!function_exists('activity_property_snapshot')) {
    function activity_property_snapshot(mysqli $conn, int $property_id): ?array
    {
        $sql = "SELECT PropertyID, friendly_id, PropertyTypeID, listing_type, Title, ProjectName, Status, governorate, district, Location, latitude, longitude
                FROM properties WHERE PropertyID = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $property_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) return null;

        $type_ids = [];
        $type_names = [];
        $type_stmt = $conn->prepare("SELECT ptl.property_type_id, pt.TypeName, pt.TypeName_ar FROM property_type_links ptl LEFT JOIN propertytypes pt ON pt.PropertyTypeID = ptl.property_type_id WHERE ptl.property_id = ?");
        if ($type_stmt) {
            $type_stmt->bind_param('i', $property_id);
            $type_stmt->execute();
            $type_res = $type_stmt->get_result();
            while ($t = $type_res->fetch_assoc()) {
                if (!empty($t['property_type_id'])) {
                    $type_ids[] = (int)$t['property_type_id'];
                }
                $type_names[] = [
                    'en' => $t['TypeName'] ?? null,
                    'ar' => $t['TypeName_ar'] ?? null,
                ];
            }
            $type_stmt->close();
        }

        return [
            'property_id' => (int)$row['PropertyID'],
            'friendly_id' => $row['friendly_id'] ?? null,
            'listing_type' => $row['listing_type'] ?? null,
            'property_type_id' => isset($row['PropertyTypeID']) ? (int)$row['PropertyTypeID'] : null,
            'property_type_ids' => $type_ids ?: null,
            'property_type_names' => $type_names ?: null,
            'title' => $row['Title'] ?? null,
            'project_name' => $row['ProjectName'] ?? null,
            'status' => $row['Status'] ?? null,
            'governorate' => $row['governorate'] ?? null,
            'district' => $row['district'] ?? null,
            'location' => $row['Location'] ?? null,
            'latitude' => isset($row['latitude']) ? (float)$row['latitude'] : null,
            'longitude' => isset($row['longitude']) ? (float)$row['longitude'] : null,
        ];
    }
}

if (!function_exists('activity_log_event')) {
    function activity_log_event(mysqli $conn, $employee_id, string $event_type, array $context = []): bool
    {
        $taxonomy = activity_taxonomy_map();
        $action = $taxonomy[$event_type] ?? $event_type;

        $session_id = session_status() === PHP_SESSION_ACTIVE ? session_id() : null;
        $ip = activity_get_client_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $identity = [
            'employee_id' => $employee_id === null ? null : (int)$employee_id,
            'username' => $context['username'] ?? null,
            'unauthenticated' => $employee_id === null,
        ];

        $details = [
            'event_type' => $event_type,
            'identity' => $identity,
            'raw' => $context,
        ];

        $snapshot = null;
        if (!empty($context['property_id'])) {
            $snapshot = activity_property_snapshot($conn, (int)$context['property_id']);
            if ($snapshot) {
                $details['property'] = $snapshot;
            }
        }

        $details['display'] = activity_build_presentation($event_type, $context, $snapshot);

        $details_json = json_encode($details, JSON_UNESCAPED_UNICODE);
        if ($details_json === false) {
            $details_json = json_encode(['event_type' => $event_type], JSON_UNESCAPED_UNICODE);
        }

        $sql = "INSERT INTO activity_logs (employee_id, session_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('activity_log_event prepare failed: ' . $conn->error);
            return false;
        }

        $emp = $employee_id === null ? null : (int)$employee_id;
        $stmt->bind_param('isssss', $emp, $session_id, $action, $details_json, $ip, $ua);
        $result = $stmt->execute();
        if (!$result) {
            error_log('activity_log_event execute failed: ' . $stmt->error);
        }
        $stmt->close();
        return (bool)$result;
    }
}

if (!function_exists('log_activity')) {
    function log_activity(mysqli $conn, $employee_id, string $action, $details = null)
    {
        $context = [];
        if (is_array($details)) {
            $context = $details;
        } elseif (is_scalar($details) && $details !== null) {
            $context = ['message' => (string)$details];
        }
        return activity_log_event($conn, $employee_id, $action, $context);
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

        $is_ar = function_exists('get_lang_code') && get_lang_code() === 'ar';

        $format_property_block = function(array $prop) {
            $lines = [];
            $fid = $prop['friendly_id'] ?? null;
            $pid = $prop['property_id'] ?? null;
            if ($fid) {
                $lines[] = 'العقار: #' . $fid;
            } elseif ($pid) {
                $lines[] = 'العقار: #' . $pid;
            }
            if (!empty($prop['title'])) {
                $lines[] = 'العنوان: ' . $prop['title'];
            }
            if (!empty($prop['listing_type'])) {
                $lines[] = 'نظام القائمة: ' . $prop['listing_type'];
            }
            if (!empty($prop['property_type_names']) && is_array($prop['property_type_names'])) {
                $type_names = [];
                foreach ($prop['property_type_names'] as $t) {
                    if (!empty($t['ar'])) {
                        $type_names[] = $t['ar'];
                    } elseif (!empty($t['en'])) {
                        $type_names[] = $t['en'];
                    }
                }
                if (!empty($type_names)) {
                    $lines[] = 'النوع: ' . implode(' / ', $type_names);
                }
            }
            return $lines;
        };

        if ($is_ar && is_array($decoded) && !empty($decoded['event_type'])) {
            $event_type = $decoded['event_type'];
            $raw = is_array($decoded['raw'] ?? null) ? $decoded['raw'] : [];
            $prop = is_array($decoded['property'] ?? null) ? $decoded['property'] : [];

            $lines = [];
            switch ($event_type) {
                case 'PROPERTY_CREATE':
                    $lines[] = 'تمت إضافة عقار جديد';
                    $lines = array_merge($lines, $format_property_block($prop));
                    break;
                case 'PROPERTY_UPDATE':
                    $lines[] = 'تم تعديل بيانات العقار';
                    $lines = array_merge($lines, $format_property_block($prop));
                    if (!empty($raw['changes']) && is_array($raw['changes'])) {
                        $lines[] = 'التعديلات:';
                        foreach ($raw['changes'] as $c) {
                            $lines[] = '- ' . $c;
                        }
                    }
                    break;
                case 'PROPERTY_DELETE':
                    $lines[] = 'تم حذف العقار';
                    $lines = array_merge($lines, $format_property_block($prop));
                    break;
                case 'PROPERTY_VIEW':
                    $lines[] = 'تم عرض العقار';
                    $lines = array_merge($lines, $format_property_block($prop));
                    break;
                case 'PROPERTY_SHARE_DETAILS':
                    $lines[] = 'تمت مشاركة تفاصيل العقار';
                    $lines = array_merge($lines, $format_property_block($prop));
                    if (!empty($raw['destination'])) {
                        $lines[] = 'الوجهة: ' . $raw['destination'];
                    }
                    break;
                case 'PROPERTY_SHARE_LOCATION':
                    $lines[] = 'تمت مشاركة موقع العقار';
                    $lines = array_merge($lines, $format_property_block($prop));
                    if (!empty($raw['destination'])) {
                        $lines[] = 'الوجهة: ' . $raw['destination'];
                    }
                    break;
                case 'PROPERTY_MEDIA_DOWNLOAD':
                    $lines[] = 'تم تنزيل وسائط العقار';
                    $lines = array_merge($lines, $format_property_block($prop));
                    if (!empty($raw['file_name'])) {
                        $lines[] = 'الملف: ' . $raw['file_name'];
                    }
                    if (!empty($raw['media_type'])) {
                        $lines[] = 'نوع الوسيط: ' . $raw['media_type'];
                    }
                    break;
                case 'AUTH_LOGIN_SUCCESS':
                    $lines[] = 'تسجيل دخول ناجح';
                    if (!empty($raw['username'])) {
                        $lines[] = 'اسم المستخدم: ' . $raw['username'];
                    }
                    if (!empty($raw['role'])) {
                        $lines[] = 'الدور: ' . $raw['role'];
                    }
                    break;
                case 'AUTH_LOGIN_FAILED':
                    $lines[] = 'تسجيل دخول فاشل';
                    if (!empty($raw['username'])) {
                        $lines[] = 'اسم المستخدم: ' . $raw['username'];
                    } else {
                        $lines[] = 'الهوية: محاولة غير موثقة';
                    }
                    if (!empty($raw['ip_address'])) {
                        $lines[] = 'عنوان IP: ' . $raw['ip_address'];
                    }
                    if (isset($raw['attempts_remaining'])) {
                        $lines[] = 'المحاولات المتبقية: ' . $raw['attempts_remaining'];
                    }
                    break;
                case 'AUTH_USER_BLOCKED':
                    $lines[] = 'تم حظر المستخدم';
                    if (!empty($raw['username'])) {
                        $lines[] = 'اسم المستخدم: ' . $raw['username'];
                    }
                    if (!empty($raw['ip_address'])) {
                        $lines[] = 'عنوان IP: ' . $raw['ip_address'];
                    }
                    if (!empty($raw['reason'])) {
                        $lines[] = 'السبب: ' . $raw['reason'];
                    }
                    break;
                case 'AUTH_LOGOUT':
                    $lines[] = 'تسجيل خروج';
                    if (!empty($raw['username'])) {
                        $lines[] = 'اسم المستخدم: ' . $raw['username'];
                    }
                    break;
                default:
                    break;
            }
            if (!empty($lines)) {
                return implode("\n", $lines);
            }
        }

        if (is_array($decoded) && isset($decoded['display']['summary'])) {
            $summary = $decoded['display']['summary'];
            $fields = $decoded['display']['fields'] ?? [];
            if (!empty($fields) && is_array($fields)) {
                $lines = [];
                foreach ($fields as $f) {
                    if (!is_array($f)) continue;
                    $label = $f['label'] ?? '';
                    $value = $f['value'] ?? '';
                    if ($label === '' || $value === '') continue;
                    $lines[] = $label . ': ' . $value;
                }
                if (!empty($lines)) {
                    return $summary . "\n" . implode("\n", $lines);
                }
            }
            return $summary;
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

        // Fallback: return safe string without dumping raw JSON
        if (is_string($details)) return $details;
        return '';
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
