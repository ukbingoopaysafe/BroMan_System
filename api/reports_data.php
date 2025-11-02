<?php
// Small API endpoint to provide data for the Reports dashboard
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../activity_log.php';

// Basic auth/permission check: require logged-in session
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$out = [
    'activity_breakdown' => [],
    'top_employees' => [],
    'most_visited_properties' => [],
    'most_edited_properties' => [],
    'activity_over_time' => [],
    'kpis' => []
];

// 1) Activity breakdown: counts per action
try {
    $sql = "SELECT action, COUNT(*) AS cnt FROM activity_logs GROUP BY action ORDER BY cnt DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out['activity_breakdown'][] = ['action' => $r['action'], 'cnt' => (int)$r['cnt']];
        }
        $res->close();
    }
} catch (Exception $e) {
    error_log('reports_data activity_breakdown error: ' . $e->getMessage());
}

// 2) Top 5 active employees (by activity count)
try {
    $sql = "SELECT al.employee_id, COALESCE(e.FullName, e.Username, CONCAT('Employee #', al.employee_id)) AS name, COUNT(*) AS cnt
            FROM activity_logs al
            LEFT JOIN employees e ON e.EmployeeID = al.employee_id
            GROUP BY al.employee_id
            ORDER BY cnt DESC
            LIMIT 5";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out['top_employees'][] = ['employee_id' => $r['employee_id'], 'name' => $r['name'], 'cnt' => (int)$r['cnt']];
        }
        $res->close();
    }
} catch (Exception $e) {
    error_log('reports_data top_employees error: ' . $e->getMessage());
}

// 4) Most visited properties (Top 5)
try {
    // Attempt to extract property_id from JSON details and count
    $sql = "SELECT t.pid AS property_id, COALESCE(p.Title, CONCAT('Property #', t.pid)) AS title, t.cnt FROM (
        SELECT (JSON_UNQUOTE(JSON_EXTRACT(details, '$.property_id')) + 0) AS pid, COUNT(*) AS cnt
        FROM activity_logs
        WHERE action = 'property_visit'
        GROUP BY pid
    ) t LEFT JOIN Properties p ON p.PropertyID = t.pid
    ORDER BY t.cnt DESC
    LIMIT 5";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out['most_visited_properties'][] = ['property_id' => (int)$r['property_id'], 'title' => $r['title'], 'cnt' => (int)$r['cnt']];
        }
        $res->close();
    }
} catch (Exception $e) {
    error_log('reports_data most_visited_properties error: ' . $e->getMessage());
}

// 5) Most edited properties (Top 5)
try {
    $sql = "SELECT t.pid AS property_id, COALESCE(p.Title, CONCAT('Property #', t.pid)) AS title, t.cnt FROM (
        SELECT (JSON_UNQUOTE(JSON_EXTRACT(details, '$.property_id')) + 0) AS pid, COUNT(*) AS cnt
        FROM activity_logs
        WHERE action = 'edit_property'
        GROUP BY pid
    ) t LEFT JOIN Properties p ON p.PropertyID = t.pid
    ORDER BY t.cnt DESC
    LIMIT 5";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out['most_edited_properties'][] = ['property_id' => (int)$r['property_id'], 'title' => $r['title'], 'cnt' => (int)$r['cnt']];
        }
        $res->close();
    }
} catch (Exception $e) {
    error_log('reports_data most_edited_properties error: ' . $e->getMessage());
}

// 6) Activity over time (last 30 days) - return daily counts for last 30 days
try {
    $days = 30;
    $sql = "SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM activity_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($days-1) . " DAY) GROUP BY DATE(created_at) ORDER BY day ASC";
    $res = $conn->query($sql);
    $map = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) { $map[$r['day']] = (int)$r['cnt']; }
        $res->close();
    }

    // build continuous series for last $days days
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $series[] = ['date' => $d, 'cnt' => isset($map[$d]) ? $map[$d] : 0];
    }
    $out['activity_over_time'] = $series;
} catch (Exception $e) {
    error_log('reports_data activity_over_time error: ' . $e->getMessage());
}

// 3) Minimal KPIs (best-effort) -- these are optional and can be expanded later
try {
    // activities last 7 days
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $out['kpis']['activities_7d'] = ($res && ($r = $res->fetch_assoc())) ? (int)$r['cnt'] : 0;
    if ($res) $res->close();

    // property visits last 7 days
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM activity_logs WHERE action = 'property_visit' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $out['kpis']['visits_7d'] = ($res && ($r = $res->fetch_assoc())) ? (int)$r['cnt'] : 0;
    if ($res) $res->close();

    // properties added last 30 days
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM activity_logs WHERE action = 'add_property' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $out['kpis']['added_30d'] = ($res && ($r = $res->fetch_assoc())) ? (int)$r['cnt'] : 0;
    if ($res) $res->close();

    // active users today (reuse helper get_today_activity_counts or get_online_count)
    $out['kpis']['active_today'] = get_online_count($conn, 86400); // last 24h

} catch (Exception $e) {
    error_log('reports_data kpis error: ' . $e->getMessage());
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
