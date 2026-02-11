<?php
/**
 * Activity Data API Endpoint — Enterprise OIS v2
 * Provides enriched JSON data for the Operational Intelligence System.
 * 
 * Query params:
 *   section      - overview|security|property_views|properties_added|properties_modified|
 *                   media_downloads|media_uploads|employee_actions|all_activity|
 *                   share_actions|employees_list|users_directory|user_profile|log_share
 *   from         - start date (Y-m-d), default: first of current month
 *   to           - end date (Y-m-d), default: today
 *   employee_id  - optional filter by employee
 */
// Suppress all PHP error output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../security.php';
ensure_secure_session_started();
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../activity_log.php';
require_once __DIR__ . '/../session_activity.php';

header('Content-Type: application/json; charset=utf-8');

// Only managers can access this endpoint (except share logging which any logged-in user can do)
$section = $_GET['section'] ?? ($_POST['section'] ?? 'overview');

if ($section === 'log_share') {
    // Any logged-in user can log a share action
    if (empty($_SESSION['employee_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    echo json_encode(handle_log_share($conn));
    exit;
}

if (!is_manager()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$emp_filter = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int)$_GET['employee_id'] : null;

$from_dt = $from . ' 00:00:00';
$to_dt = $to . ' 23:59:59';

$response = [];

switch ($section) {
    case 'overview':
        $response = get_overview($conn, $from_dt, $to_dt, $emp_filter);
        break;
    case 'security':
        $response = get_security($conn, $from_dt, $to_dt);
        break;
    case 'property_views':
        $response = get_activity_by_action($conn, 'property_visit', $from_dt, $to_dt, $emp_filter);
        break;
    case 'properties_added':
        $response = get_activity_by_action($conn, 'add_property', $from_dt, $to_dt, $emp_filter);
        break;
    case 'properties_modified':
        $response = get_activity_by_action($conn, 'edit_property', $from_dt, $to_dt, $emp_filter);
        break;
    case 'media_downloads':
        $response = get_activity_by_action($conn, 'download_media', $from_dt, $to_dt, $emp_filter);
        break;
    case 'media_uploads':
        $response = get_activity_by_actions($conn, ['upload_media', 'upload_attachment'], $from_dt, $to_dt, $emp_filter);
        break;
    case 'employee_actions':
        $response = get_activity_by_actions($conn, ['add_employee', 'edit_employee', 'delete_employee'], $from_dt, $to_dt, $emp_filter);
        break;
    case 'share_actions':
        $response = get_activity_by_actions($conn, ['share_details', 'share_location'], $from_dt, $to_dt, $emp_filter);
        break;
    case 'all_activity':
        $response = get_all_activity($conn, $from_dt, $to_dt, $emp_filter);
        break;
    case 'employees_list':
        $response = get_employees_list($conn);
        break;
    case 'users_directory':
        $response = get_users_directory($conn, $from_dt, $to_dt);
        break;
    case 'user_profile':
        $response = get_user_profile($conn, $emp_filter, $from_dt, $to_dt);
        break;
    default:
        $response = ['error' => 'Unknown section'];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// ═══════════════════════════════════════════════════════════════
//  Share Logging (POST endpoint for reliable server-side tracking)
// ═══════════════════════════════════════════════════════════════

function handle_log_share(mysqli $conn) : array {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    if (!in_array($action, ['share_details', 'share_location'])) {
        return ['error' => 'Invalid share action'];
    }
    
    $emp_id = (int)$_SESSION['employee_id'];
    $property_id = (int)($input['property_id'] ?? 0);
    
    $details = [
        'property_id' => $property_id,
        'friendly_id' => $input['friendly_id'] ?? '',
        'listing_type' => $input['listing_type'] ?? '',
        'property_type' => $input['property_type'] ?? '',
        'share_method' => $input['share_method'] ?? 'WhatsApp',
        'entry_point' => 'Property Page'
    ];
    
    if (function_exists('log_activity')) {
        log_activity($conn, $emp_id, $action, json_encode($details, JSON_UNESCAPED_UNICODE));
    }
    
    return ['success' => true, 'action' => $action];
}

// ═══════════════════════════════════════════════════════════════
//  Overview
// ═══════════════════════════════════════════════════════════════

function get_overview(mysqli $conn, string $from, string $to, ?int $emp) : array {
    $data = [
        'online_users' => [],
        'action_counts' => [],
        'total_actions' => 0,
        'security_alerts' => 0
    ];

    // Online users — deduplicated by employee_id, preserving latest activity
    $sql = "SELECT a.employee_id, e.FullName, e.Role, MAX(a.last_activity) as last_activity
            FROM active_sessions a 
            LEFT JOIN Employees e ON a.employee_id = e.EmployeeID 
            WHERE a.last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            GROUP BY a.employee_id, e.FullName, e.Role";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $data['online_users'][] = [
                'id' => (int)$r['employee_id'],
                'name' => $r['FullName'],
                'role' => $r['Role'],
                'last_activity' => $r['last_activity']
            ];
        }
        $res->close();
    }

    // Action counts
    $where = "WHERE created_at BETWEEN ? AND ?";
    $params = [$from, $to];
    $types = 'ss';
    if ($emp) {
        $where .= " AND employee_id = ?";
        $params[] = $emp;
        $types .= 'i';
    }
    $sql = "SELECT action, COUNT(*) as cnt FROM activity_logs $where GROUP BY action ORDER BY cnt DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $data['action_counts'][$r['action']] = (int)$r['cnt'];
            $data['total_actions'] += (int)$r['cnt'];
        }
        $stmt->close();
    }

    // Security alerts
    $sql = "SELECT COUNT(*) as cnt FROM activity_logs WHERE action = 'failed_login' AND created_at BETWEEN ? AND ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $data['security_alerts'] += (int)$r['cnt'];
        }
        $stmt->close();
    }

    $sql = "SELECT COUNT(*) as cnt FROM blocked_ips WHERE blocked_until > NOW()";
    $res = $conn->query($sql);
    if ($res && $r = $res->fetch_assoc()) {
        $data['blocked_ips_count'] = (int)$r['cnt'];
        $data['security_alerts'] += (int)$r['cnt'];
    }

    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  Security
// ═══════════════════════════════════════════════════════════════

function get_security(mysqli $conn, string $from, string $to) : array {
    $data = [
        'failed_logins' => [],
        'blocked_ips' => [],
        'stats' => [
            'total_failed' => 0,
            'unique_ips' => 0,
            'unique_usernames' => 0,
            'blocked_count' => 0,
            'max_ip_attempts' => defined('MAX_IP_ATTEMPTS') ? MAX_IP_ATTEMPTS : 5,
            'block_duration_min' => defined('IP_BLOCK_DURATION') ? ceil(IP_BLOCK_DURATION / 60) : 15,
        ]
    ];

    // Failed login events with IP address
    $sql = "SELECT al.id, al.details, al.ip_address, al.user_agent, al.created_at
            FROM activity_logs al 
            WHERE al.action = 'failed_login' AND al.created_at BETWEEN ? AND ?
            ORDER BY al.created_at DESC LIMIT 100";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $ips_seen = [];
        $users_seen = [];
        while ($r = $res->fetch_assoc()) {
            $details = json_decode($r['details'], true) ?: [];
            $username = $details['username'] ?? 'unknown';
            $ip = $details['ip'] ?? ($r['ip_address'] ?? 'unknown');
            $data['failed_logins'][] = [
                'id' => (int)$r['id'],
                'username' => $username,
                'ip' => $ip,
                'context' => parse_context($r['user_agent'], 'Login Screen', 'Manual'),
                'time' => $r['created_at']
            ];
            $ips_seen[$ip] = true;
            $users_seen[$username] = true;
        }
        $stmt->close();
        $data['stats']['total_failed'] = count($data['failed_logins']);
        $data['stats']['unique_ips'] = count($ips_seen);
        $data['stats']['unique_usernames'] = count($users_seen);
    }

    // Currently blocked IPs
    $sql = "SELECT ip_address, blocked_at, blocked_until, reason FROM blocked_ips WHERE blocked_until > NOW() ORDER BY blocked_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            // Calculate remaining time
            $remaining_sec = max(0, strtotime($r['blocked_until']) - time());
            $r['remaining_minutes'] = ceil($remaining_sec / 60);
            $data['blocked_ips'][] = $r;
        }
        $res->close();
    }
    $data['stats']['blocked_count'] = count($data['blocked_ips']);

    // Attempt counts per username (top offenders)
    $sql = "SELECT al.details, COUNT(*) as cnt 
            FROM activity_logs al 
            WHERE al.action = 'failed_login' AND al.created_at BETWEEN ? AND ?
            GROUP BY JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.username'))
            ORDER BY cnt DESC LIMIT 10";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_attempts = [];
        while ($r = $res->fetch_assoc()) {
            $d = json_decode($r['details'], true) ?: [];
            $user_attempts[$d['username'] ?? 'unknown'] = (int)$r['cnt'];
        }
        $stmt->close();
        $data['stats']['attempts_by_user'] = $user_attempts;
    }

    // Attempt counts per IP
    $sql = "SELECT al.ip_address, COUNT(*) as cnt 
            FROM activity_logs al 
            WHERE al.action = 'failed_login' AND al.created_at BETWEEN ? AND ?
            GROUP BY al.ip_address
            ORDER BY cnt DESC LIMIT 10";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $ip_attempts = [];
        while ($r = $res->fetch_assoc()) {
            $ip_attempts[$r['ip_address']] = (int)$r['cnt'];
        }
        $stmt->close();
        $data['stats']['attempts_by_ip'] = $ip_attempts;
    }

    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  Activity by action(s) — enriched items
// ═══════════════════════════════════════════════════════════════

function get_activity_by_action(mysqli $conn, string $action, string $from, string $to, ?int $emp) : array {
    return get_activity_by_actions($conn, [$action], $from, $to, $emp);
}

function get_activity_by_actions(mysqli $conn, array $actions, string $from, string $to, ?int $emp) : array {
    $data = ['items' => [], 'total' => 0];
    
    $placeholders = str_repeat('?,', count($actions) - 1) . '?';
    $where = "WHERE al.action IN ($placeholders) AND al.created_at BETWEEN ? AND ?";
    $params = array_merge($actions, [$from, $to]);
    $types = str_repeat('s', count($actions)) . 'ss';
    
    if ($emp) {
        $where .= " AND al.employee_id = ?";
        $params[] = $emp;
        $types .= 'i';
    }

    // Count
    $sql = "SELECT COUNT(*) as cnt FROM activity_logs al $where";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $data['total'] = (int)$r['cnt'];
        }
        $stmt->close();
    }

    // Items — enriched
    $sql = "SELECT al.id, al.employee_id, e.FullName as employee_name, al.action, al.details, al.ip_address, al.user_agent, al.created_at
            FROM activity_logs al 
            LEFT JOIN Employees e ON al.employee_id = e.EmployeeID
            $where
            ORDER BY al.created_at DESC LIMIT 200";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $data['items'][] = enrich_activity_item($conn, $r);
        }
        $stmt->close();
    }

    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  All Activity — enriched
// ═══════════════════════════════════════════════════════════════

function get_all_activity(mysqli $conn, string $from, string $to, ?int $emp) : array {
    $data = ['items' => [], 'total' => 0];
    
    $where = "WHERE al.created_at BETWEEN ? AND ?";
    $params = [$from, $to];
    $types = 'ss';
    if ($emp) {
        $where .= " AND al.employee_id = ?";
        $params[] = $emp;
        $types .= 'i';
    }

    $sql = "SELECT COUNT(*) as cnt FROM activity_logs al $where";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $data['total'] = (int)$r['cnt'];
        }
        $stmt->close();
    }

    $sql = "SELECT al.id, al.employee_id, e.FullName as employee_name, al.action, al.details, al.ip_address, al.user_agent, al.created_at
            FROM activity_logs al 
            LEFT JOIN Employees e ON al.employee_id = e.EmployeeID
            $where
            ORDER BY al.created_at DESC LIMIT 500";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $data['items'][] = enrich_activity_item($conn, $r);
        }
        $stmt->close();
    }

    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  Users Directory (enriched with last_activity, total_actions, online)
// ═══════════════════════════════════════════════════════════════

function get_users_directory(mysqli $conn, string $from, string $to) : array {
    $data = [];
    
    // Get all employees with their action counts and last activity
    $sql = "SELECT e.EmployeeID, e.FullName, e.Username, e.Role,
                   COUNT(al.id) as total_actions,
                   MAX(al.created_at) as last_activity
            FROM Employees e
            LEFT JOIN activity_logs al ON al.employee_id = e.EmployeeID AND al.created_at BETWEEN ? AND ?
            GROUP BY e.EmployeeID, e.FullName, e.Username, e.Role
            ORDER BY total_actions DESC, e.FullName ASC";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        
        // Get all online user IDs
        $online_ids = [];
        $online_sql = "SELECT DISTINCT employee_id FROM active_sessions WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        $online_res = $conn->query($online_sql);
        if ($online_res) {
            while ($or = $online_res->fetch_assoc()) {
                $online_ids[] = (int)$or['employee_id'];
            }
            $online_res->close();
        }
        
        while ($r = $res->fetch_assoc()) {
            $data[] = [
                'id' => (int)$r['EmployeeID'],
                'name' => $r['FullName'],
                'username' => $r['Username'],
                'role' => $r['Role'],
                'total_actions' => (int)$r['total_actions'],
                'last_activity' => $r['last_activity'],
                'is_online' => in_array((int)$r['EmployeeID'], $online_ids)
            ];
        }
        $stmt->close();
    }
    
    return $data;
}

function get_employees_list(mysqli $conn) : array {
    $data = [];
    $sql = "SELECT EmployeeID, FullName, Username, Role FROM Employees ORDER BY FullName ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $data[] = [
                'id' => (int)$r['EmployeeID'],
                'name' => $r['FullName'],
                'username' => $r['Username'],
                'role' => $r['Role']
            ];
        }
        $res->close();
    }
    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  User Profile
// ═══════════════════════════════════════════════════════════════

function get_user_profile(mysqli $conn, ?int $emp_id, string $from, string $to) : array {
    if (!$emp_id) return ['error' => 'Employee ID required'];

    $data = [
        'employee' => null,
        'is_online' => false,
        'action_counts' => [],
        'last_login' => null,
        'recent_activity' => [],
        'security' => ['logins' => 0, 'logouts' => 0, 'devices' => []]
    ];

    // Employee info
    $sql = "SELECT EmployeeID, FullName, Username, Role FROM Employees WHERE EmployeeID = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $data['employee'] = [
                'id' => (int)$r['EmployeeID'],
                'name' => $r['FullName'],
                'username' => $r['Username'],
                'role' => $r['Role']
            ];
        }
        $stmt->close();
    }

    if (!$data['employee']) return ['error' => 'Employee not found'];

    if (function_exists('is_user_online')) {
        $data['is_online'] = is_user_online($conn, $emp_id);
    }

    // Action counts
    $sql = "SELECT action, COUNT(*) as cnt FROM activity_logs WHERE employee_id = ? AND created_at BETWEEN ? AND ? GROUP BY action ORDER BY cnt DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('iss', $emp_id, $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $data['action_counts'][$r['action']] = (int)$r['cnt'];
        }
        $stmt->close();
    }

    // Last login
    $sql = "SELECT created_at FROM activity_logs WHERE employee_id = ? AND action = 'login' ORDER BY created_at DESC LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $data['last_login'] = $r['created_at'];
        }
        $stmt->close();
    }

    $data['security']['logins'] = $data['action_counts']['login'] ?? 0;
    $data['security']['logouts'] = $data['action_counts']['logout'] ?? 0;

    // Devices
    $sql = "SELECT DISTINCT user_agent FROM activity_logs WHERE employee_id = ? AND user_agent IS NOT NULL AND user_agent != '' AND created_at BETWEEN ? AND ? LIMIT 10";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('iss', $emp_id, $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $data['security']['devices'][] = parse_user_agent_short($r['user_agent']);
        }
        $stmt->close();
    }

    // Recent activity — enriched
    $sql = "SELECT al.id, al.employee_id, e.FullName as employee_name, al.action, al.details, al.ip_address, al.user_agent, al.created_at
            FROM activity_logs al
            LEFT JOIN Employees e ON al.employee_id = e.EmployeeID
            WHERE al.employee_id = ? AND al.created_at BETWEEN ? AND ?
            ORDER BY al.created_at DESC LIMIT 100";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('iss', $emp_id, $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $data['recent_activity'][] = enrich_activity_item($conn, $r);
        }
        $stmt->close();
    }

    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  Event-Type-Specific Enrichment Layer
// ═══════════════════════════════════════════════════════════════

function enrich_activity_item(mysqli $conn, array $row) : array {
    $action = $row['action'];
    $raw_details = $row['details'];
    $details = json_decode($raw_details, true) ?: [];
    
    // Determine entry point from action context
    $entry_point = determine_entry_point($action, $details);
    $source_type = determine_source_type($action);
    
    $item = [
        'id' => (int)$row['id'],
        'employee_id' => $row['employee_id'] ? (int)$row['employee_id'] : null,
        'employee_name' => $row['employee_name'] ?? 'System',
        'action' => $action,
        'summary' => '',          // Human-readable one-liner
        'structured' => [],       // Key-value pairs for expandable panel
        'property_link' => null,  // Direct link to property if applicable
        'context' => parse_context($row['user_agent'] ?? '', $entry_point, $source_type),
        'time' => $row['created_at']
    ];
    
    // Event-type-specific rendering
    switch ($action) {
        case 'login':
            $item['summary'] = 'تسجيل دخول ناجح';
            $item['structured'] = [
                'الحدث' => 'تسجيل دخول',
                'الحالة' => 'ناجح ✓'
            ];
            break;
            
        case 'logout':
            $item['summary'] = 'تسجيل خروج';
            $item['structured'] = ['الحدث' => 'تسجيل خروج'];
            break;
            
        case 'add_property':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $prop['friendly_id'] ?? ($details['friendly_id'] ?? '');
            $listing = $prop['listing_type'] ?? ($details['listing_type'] ?? '');
            $ptype = $prop['property_types'] ?? ($details['property_type'] ?? '');
            $title = $prop['title'] ?? '';
            
            $item['summary'] = "تمت إضافة عقار جديد | رقم العقار: #$fid | نظام القائمة: $listing" . ($ptype ? " | النوع: $ptype" : '');
            $item['structured'] = array_filter([
                'رقم العقار (Friendly ID)' => $fid ? "#$fid" : null,
                'عنوان العقار' => $title ?: null,
                'نظام القائمة' => $listing ?: null,
                'نوع العقار' => $ptype ?: null,
                'المشروع' => $prop['project'] ?? null,
                'المحافظة' => $prop['governorate'] ?? null,
                'الحي' => $prop['district'] ?? null,
            ]);
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;
            
        case 'edit_property':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $prop['friendly_id'] ?? ($details['friendly_id'] ?? '');
            $listing = $prop['listing_type'] ?? '';
            $changes = $details['changes'] ?? [];
            // changes from track_property_changes() are human-readable strings
            $changes_list = [];
            if (is_array($changes)) {
                foreach ($changes as $ch) {
                    $changes_list[] = is_array($ch) ? json_encode($ch, JSON_UNESCAPED_UNICODE) : (string)$ch;
                }
            }
            $changes_str = implode(' | ', $changes_list);
            $changes_short = count($changes_list) . ' تغيير';
            
            $item['summary'] = "تم تعديل عقار #$fid" . ($changes_short ? " | $changes_short" : '');
            $item['structured'] = array_filter([
                'رقم العقار (Friendly ID)' => $fid ? "#$fid" : null,
                'عنوان العقار' => $prop['title'] ?? null,
                'نظام القائمة' => $listing ?: null,
                'نوع العقار' => $prop['property_types'] ?? null,
            ]);
            
            // Build diff table from the changes strings
            if (!empty($changes_list)) {
                $diff = [];
                foreach ($changes_list as $change_text) {
                    // Parse "تم تغيير LABEL من: OLD إلى: NEW"
                    if (preg_match('/تم تغيير (.+?) من: (.+?) إلى: (.+)$/u', $change_text, $m)) {
                        $diff[] = ['field' => trim($m[1]), 'before' => trim($m[2]), 'after' => trim($m[3])];
                    } elseif (preg_match('/تمت إضافة (.+?): (.+)$/u', $change_text, $m)) {
                        $diff[] = ['field' => trim($m[1]), 'before' => '—', 'after' => trim($m[2])];
                    } elseif (preg_match('/تم حذف (.+?) \(كانت: (.+?)\)$/u', $change_text, $m)) {
                        $diff[] = ['field' => trim($m[1]), 'before' => trim($m[2]), 'after' => '—'];
                    } else {
                        $diff[] = ['field' => $change_text, 'before' => '', 'after' => ''];
                    }
                }
                if (!empty($diff)) {
                    $item['structured']['التغييرات التفصيلية'] = $diff;
                }
            }
            
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;
            
        case 'delete_property':
            $fid = $details['friendly_id'] ?? ($details['property_id'] ?? '');
            $item['summary'] = "تم حذف عقار #$fid";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'نظام القائمة' => $details['listing_type'] ?? null,
            ]);
            break;
            
        case 'property_visit':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $prop['friendly_id'] ?? '';
            $title = $prop['title'] ?? '';
            
            $item['summary'] = "زيارة عقار #$fid" . ($title ? " — $title" : '');
            $item['structured'] = array_filter([
                'رقم العقار (Friendly ID)' => $fid ? "#$fid" : null,
                'عنوان العقار' => $title ?: null,
                'نظام القائمة' => $prop['listing_type'] ?? null,
                'نوع العقار' => $prop['property_types'] ?? null,
                'المشروع' => $prop['project'] ?? null,
            ]);
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;
            
        case 'failed_login':
            $username = $details['username'] ?? 'unknown';
            $ip = $details['ip'] ?? ($item['ip_address'] ?? 'unknown');
            $item['summary'] = "محاولة تسجيل دخول فاشلة — المستخدم: $username | IP: $ip";
            $item['structured'] = array_filter([
                'اسم المستخدم' => $username,
                'عنوان IP' => $ip,
                'الحالة' => '❌ فاشلة',
                'المتصفح' => $item['context']['device'] ?? null,
            ]);
            break;
            
        case 'download_media':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $prop['friendly_id'] ?? '';
            $count = $details['file_count'] ?? 0;
            
            $item['summary'] = "تحميل وسائط عقار #$fid | عدد الملفات: $count";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'عدد الملفات' => $count ?: null,
                'نظام القائمة' => $details['listing_type'] ?? ($prop['listing_type'] ?? null),
            ]);
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;
            
        case 'upload_media':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $prop['friendly_id'] ?? '';
            $type = $details['upload_type'] ?? '';
            
            $item['summary'] = "رفع وسائط ($type) لعقار #$fid";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'نوع الرفع' => $type ?: null,
                'نظام القائمة' => $prop['listing_type'] ?? null,
            ]);
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;
            
        case 'upload_attachment':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $prop['friendly_id'] ?? '';
            $count = $details['file_count'] ?? 0;
            
            $item['summary'] = "رفع مرفقات لعقار #$fid | عدد الملفات: $count";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'عدد الملفات' => $count ?: null,
            ]);
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;
            
        case 'add_employee':
            $name = $details['name'] ?? '';
            $role = $details['role'] ?? '';
            $username = $details['username'] ?? '';
            // Look up the full employee record for comprehensive details
            $emp_full = enrich_employee_data($conn, $details['new_employee_id'] ?? null);
            $item['summary'] = "تمت إضافة موظف جديد: $name ($role)";
            $item['structured'] = array_filter([
                'اسم الموظف' => $name ?: null,
                'اسم المستخدم' => $username ?: null,
                'الدور' => $role ?: null,
                'الأنظمة المسموحة' => $emp_full['allowed_modes'] ?? null,
                'رقم الموظف' => isset($details['new_employee_id']) ? '#' . $details['new_employee_id'] : null,
            ]);
            break;
            
        case 'edit_employee':
            $name = $details['name'] ?? '';
            $changes = $details['changes'] ?? [];
            $changes_str = is_array($changes) ? implode('، ', $changes) : (string)$changes;
            $edited_id = $details['edited_employee_id'] ?? null;
            // Look up full current employee data for comprehensive details
            $emp_full = enrich_employee_data($conn, $edited_id);
            $item['summary'] = "تم تعديل بيانات الموظف: $name" . ($changes_str ? " | التغييرات: $changes_str" : '');
            $item['structured'] = array_filter([
                'اسم الموظف' => $emp_full['name'] ?? $name ?: null,
                'اسم المستخدم' => $emp_full['username'] ?? null,
                'الدور الحالي' => $emp_full['role'] ?? null,
                'الأنظمة المسموحة' => $emp_full['allowed_modes'] ?? null,
                'الإجراءات المعدّلة' => $changes_str ?: null,
                'رقم الموظف' => $edited_id ? '#' . $edited_id : null,
            ]);
            break;
            
        case 'delete_employee':
            $del_id = $details['deleted_employee_id'] ?? '';
            $item['summary'] = "تم حذف الموظف رقم: #$del_id";
            $item['structured'] = [
                'رقم الموظف المحذوف' => "#$del_id"
            ];
            break;
            
        case 'share_details':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $details['friendly_id'] ?? ($prop['friendly_id'] ?? '');
            $method = $details['share_method'] ?? 'WhatsApp';
            
            $item['summary'] = "مشاركة تفاصيل عقار #$fid عبر $method";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'نظام القائمة' => $details['listing_type'] ?? ($prop['listing_type'] ?? null),
                'نوع العقار' => $details['property_type'] ?? ($prop['property_types'] ?? null),
                'طريقة المشاركة' => $method,
            ]);
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;
            
        case 'share_location':
            $prop = enrich_property_data($conn, $details['property_id'] ?? null);
            $fid = $details['friendly_id'] ?? ($prop['friendly_id'] ?? '');
            $method = $details['share_method'] ?? 'WhatsApp';
            
            $item['summary'] = "مشاركة موقع عقار #$fid على الخريطة عبر $method";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'نظام القائمة' => $details['listing_type'] ?? ($prop['listing_type'] ?? null),
                'نوع العقار' => $details['property_type'] ?? ($prop['property_types'] ?? null),
                'طريقة المشاركة' => $method,
            ]);
            if ($details['property_id'] ?? null) {
                $item['property_link'] = 'view_property.php?id=' . (int)$details['property_id'];
            }
            break;

        // Legacy share event actions (before the tracking update)
        case 'property_share_details':
        case 'PROPERTY_SHARE_DETAILS':
            $prop_id = $details['property_id'] ?? null;
            $prop = $prop_id ? enrich_property_data($conn, $prop_id) : [];
            $fid = $details['friendly_id'] ?? ($prop['friendly_id'] ?? '');
            $dest = $details['destination'] ?? 'WhatsApp';
            
            $item['summary'] = "مشاركة تفاصيل عقار #$fid عبر $dest";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'نظام القائمة' => $details['listing_type'] ?? ($prop['listing_type'] ?? null),
                'نوع العقار' => $prop['property_types'] ?? null,
                'طريقة المشاركة' => $dest,
            ]);
            if ($prop_id) {
                $item['property_link'] = 'view_property.php?id=' . (int)$prop_id;
            }
            break;
            
        case 'property_share_location':
        case 'PROPERTY_SHARE_LOCATION':
            $prop_id = $details['property_id'] ?? null;
            $prop = $prop_id ? enrich_property_data($conn, $prop_id) : [];
            $fid = $details['friendly_id'] ?? ($prop['friendly_id'] ?? '');
            $dest = $details['destination'] ?? 'WhatsApp';
            
            $item['summary'] = "مشاركة موقع عقار #$fid على الخريطة عبر $dest";
            $item['structured'] = array_filter([
                'رقم العقار' => $fid ? "#$fid" : null,
                'نظام القائمة' => $details['listing_type'] ?? ($prop['listing_type'] ?? null),
                'نوع العقار' => $prop['property_types'] ?? null,
                'طريقة المشاركة' => $dest,
            ]);
            if ($prop_id) {
                $item['property_link'] = 'view_property.php?id=' . (int)$prop_id;
            }
            break;
            
        default:
            // Generic fallback — safely handle any nested data
            $item['summary'] = $action;
            if (is_array($details) && !empty($details)) {
                $parts = [];
                $safe_structured = [];
                foreach ($details as $k => $v) {
                    if ($v === null || $v === '') continue;
                    if (is_array($v)) {
                        $parts[] = "$k: " . json_encode($v, JSON_UNESCAPED_UNICODE);
                        $safe_structured[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
                    } else {
                        $parts[] = "$k: $v";
                        $safe_structured[$k] = (string)$v;
                    }
                }
                $item['summary'] = implode(' · ', $parts);
                $item['structured'] = $safe_structured;
            } elseif (is_string($raw_details) && !empty($raw_details)) {
                $item['summary'] = $raw_details;
            }
            break;
    }
    
    return $item;
}

// ═══════════════════════════════════════════════════════════════
//  Property Data Enrichment (friendly_id, listing_type, types, etc.)
// ═══════════════════════════════════════════════════════════════

// Cache for property lookups within a single request
$_property_cache = [];

function enrich_property_data(mysqli $conn, $property_id) : array {
    global $_property_cache;
    
    if (!$property_id) return [];
    $pid = (int)$property_id;
    
    if (isset($_property_cache[$pid])) {
        return $_property_cache[$pid];
    }
    
    $data = [];
    $sql = "SELECT p.PropertyID, p.friendly_id, p.listing_type, p.Title, p.ProjectName, 
                   p.governorate, p.district,
                   GROUP_CONCAT(pt.TypeName SEPARATOR '، ') as property_types
            FROM Properties p
            LEFT JOIN property_type_links ptl ON p.PropertyID = ptl.property_id
            LEFT JOIN PropertyTypes pt ON ptl.property_type_id = pt.PropertyTypeID
            WHERE p.PropertyID = ?
            GROUP BY p.PropertyID";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $data = [
                'property_id' => (int)$r['PropertyID'],
                'friendly_id' => $r['friendly_id'] ?? '',
                'listing_type' => $r['listing_type'] ?? '',
                'title' => $r['Title'] ?? $r['ProjectName'] ?? '',
                'project' => $r['ProjectName'] ?? '',
                'property_types' => $r['property_types'] ?? '',
                'governorate' => $r['governorate'] ?? '',
                'district' => $r['district'] ?? '',
            ];
        }
        $stmt->close();
    }
    
    $_property_cache[$pid] = $data;
    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  Employee Data Enrichment (role, AllowedModes)
// ═══════════════════════════════════════════════════════════════

function enrich_employee_data(mysqli $conn, $employee_id) : array {
    if (!$employee_id) return [];
    $eid = (int)$employee_id;
    
    $data = [];
    // Try with AllowedModes first, fallback without if column doesn't exist
    $sql = "SELECT EmployeeID, FullName, Username, Role, AllowedModes FROM Employees WHERE EmployeeID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Fallback without AllowedModes
        $sql = "SELECT EmployeeID, FullName, Username, Role FROM Employees WHERE EmployeeID = ?";
        $stmt = $conn->prepare($sql);
    }
    
    if ($stmt) {
        $stmt->bind_param('i', $eid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            // Translate AllowedModes to Arabic labels
            $modes_display = '';
            if (!empty($r['AllowedModes'])) {
                $mode_labels = [
                    'Primary' => 'طرح أولي',
                    'Resale' => 'إعادة بيع',
                    'Rental' => 'إيجار'
                ];
                $modes_arr = explode(',', $r['AllowedModes']);
                $translated = [];
                foreach ($modes_arr as $m) {
                    $m = trim($m);
                    $translated[] = $mode_labels[$m] ?? $m;
                }
                $modes_display = implode('، ', $translated);
            }
            
            $data = [
                'id' => (int)$r['EmployeeID'],
                'name' => $r['FullName'] ?? '',
                'username' => $r['Username'] ?? '',
                'role' => $r['Role'] ?? '',
                'allowed_modes' => $modes_display ?: null,
            ];
        }
        $stmt->close();
    }
    
    return $data;
}

// ═══════════════════════════════════════════════════════════════
//  Context Parsing (Browser/OS + Entry Point + Source Type)
// ═══════════════════════════════════════════════════════════════

function parse_context(string $ua, string $entry_point = '', string $source_type = 'Manual') : array {
    return [
        'device' => parse_user_agent_short($ua),
        'entry_point' => $entry_point,
        'source_type' => $source_type
    ];
}

function determine_entry_point(string $action, array $details) : string {
    switch ($action) {
        case 'login':
        case 'failed_login':
            return 'Login Screen';
        case 'logout':
            return 'System';
        case 'property_visit':
            return 'Property Page';
        case 'add_property':
            return 'Add Property Form';
        case 'edit_property':
            return 'Edit Property Form';
        case 'delete_property':
            return 'Property Management';
        case 'download_media':
            return 'Media Downloads';
        case 'upload_media':
        case 'upload_attachment':
            return 'Media Upload';
        case 'add_employee':
        case 'edit_employee':
        case 'delete_employee':
            return 'Employee Management';
        case 'share_details':
        case 'share_location':
            return $details['entry_point'] ?? 'Property Page';
        default:
            return 'Dashboard';
    }
}

function determine_source_type(string $action) : string {
    switch ($action) {
        case 'login':
        case 'logout':
        case 'failed_login':
            return 'System';
        default:
            return 'Manual';
    }
}

function parse_user_agent_short(string $ua) : string {
    if (empty($ua)) return 'Unknown';
    
    $browser = 'Unknown';
    $os = 'Unknown';
    
    if (stripos($ua, 'Chrome') !== false && stripos($ua, 'Edg') === false) $browser = 'Chrome';
    elseif (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) $browser = 'Safari';
    elseif (stripos($ua, 'Edg') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false) $browser = 'IE';
    
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Mac') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
    
    return "$browser / $os";
}
?>
