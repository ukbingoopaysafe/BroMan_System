<?php
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/permissions.php';
// Only managers allowed
if (!is_manager()) {
    $_SESSION['error_message'] = get_lang_code() === 'ar' ? 'ليس لديك صلاحية الوصول إلى هذه الصفحة.' : 'You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';
require_once __DIR__ . '/lang/translate.php';

// Filters
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : null;
$toDate = isset($_GET['toDate']) ? $_GET['toDate'] : null;

// default range: last 30 days
if (empty($toDate)) { $toDate = date('Y-m-d'); }
if (empty($fromDate)) { $fromDate = date('Y-m-d', strtotime('-29 days')); }

// normalize to datetime strings for SQL
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime = $toDate . ' 23:59:59';

// Fetch employees for selector
$employees = [];
$stmt = $conn->prepare("SELECT EmployeeID, FullName, Username FROM employees ORDER BY FullName ASC");
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $employees[] = $r; }
    $stmt->close();
}

$summary = [];
if ($employee_id > 0) {
    // Action totals within range (login, property_visit, add_property, edit_property)
    $action_counts = [];
    $sql = "SELECT action, COUNT(*) as cnt FROM activity_logs WHERE employee_id = ? AND created_at BETWEEN ? AND ? GROUP BY action";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('iss', $employee_id, $fromDateTime, $toDateTime);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $action_counts[$r['action']] = (int)$r['cnt']; }
        $stmt->close();
    }

    $login_count = $action_counts['login'] ?? 0;
    $visit_count = $action_counts['property_visit'] ?? 0;
    $added_count = $action_counts['add_property'] ?? 0;
    $edited_count = $action_counts['edit_property'] ?? 0;

    // ensure these arrays exist even when there are no matching activity rows
    $prop_counts = [];
    $titles_map = [];

    // Aggregate visits per property in SQL (use JSON_EXTRACT when details is JSON), compute last_seen and active_days
    $sql_visits = "SELECT 
            COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.property_id')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.PropertyID')), ''),
                NULL
            ) AS pid_json,
            COUNT(*) AS cnt,
            MAX(created_at) AS last_seen,
            COUNT(DISTINCT DATE(created_at)) AS active_days
        FROM activity_logs
        WHERE employee_id = ? AND action = 'property_visit' AND created_at BETWEEN ? AND ?
        GROUP BY pid_json
        ORDER BY cnt DESC";

    // Aggregate visits per property in SQL (use JSON_EXTRACT when details is JSON), compute last_seen and active_days
    $sql_visits = "SELECT 
            COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.property_id')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.PropertyID')), ''),
                NULL
            ) AS pid_json,
            COUNT(*) AS cnt,
            MAX(created_at) AS last_seen,
            COUNT(DISTINCT DATE(created_at)) AS active_days
        FROM activity_logs
        WHERE employee_id = ? AND action = 'property_visit' AND created_at BETWEEN ? AND ?
        GROUP BY pid_json
        ORDER BY cnt DESC";

    if ($stmt = $conn->prepare($sql_visits)) {
        $stmt->bind_param('iss', $employee_id, $fromDateTime, $toDateTime);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $pid = isset($r['pid_json']) && $r['pid_json'] !== null ? (int)$r['pid_json'] : 0;
            if ($pid <= 0) continue; // skip entries without a numeric pid
            $prop_counts[$pid] = [
                'count' => (int)$r['cnt'],
                'last_seen' => $r['last_seen'],
                'active_days' => (int)$r['active_days']
            ];
        }
        $stmt->close();
    }

    // Top added / edited properties
    $top_added = [];
    $top_edited = [];
    // Aggregate adds / edits per property using JSON_EXTRACT for property_id
    $sql_changes = "SELECT 
            action,
            COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.property_id')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.PropertyID')), '')) AS pid_json,
            COUNT(*) as cnt
        FROM activity_logs
        WHERE employee_id = ? AND action IN ('add_property','edit_property') AND created_at BETWEEN ? AND ?
        GROUP BY action, pid_json
        ORDER BY action, cnt DESC";

    if ($stmt = $conn->prepare($sql_changes)) {
        $stmt->bind_param('iss', $employee_id, $fromDateTime, $toDateTime);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $pid = isset($r['pid_json']) && $r['pid_json'] !== null ? (int)$r['pid_json'] : 0;
            if ($pid <= 0) continue;
            $act = $r['action'];
            if ($act === 'add_property') {
                if (!isset($top_added[$pid])) $top_added[$pid] = ['count'=>0];
                $top_added[$pid]['count'] += (int)$r['cnt'];
            } else {
                if (!isset($top_edited[$pid])) $top_edited[$pid] = ['count'=>0];
                $top_edited[$pid]['count'] += (int)$r['cnt'];
            }
        }
        $stmt->close();
    }

    // Detailed lists: properties added (pid, title, added_at) and properties edited (pid, title, edits, last_edited)
    $added_props = [];
    $edited_props = [];

    $sql_added = "SELECT 
            COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.property_id')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.PropertyID')), '')) AS pid_json,
            COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.property_title')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.propertyTitle')), '')) AS title_json,
            MIN(created_at) AS added_at
        FROM activity_logs
        WHERE employee_id = ? AND action = 'add_property' AND created_at BETWEEN ? AND ?
        GROUP BY pid_json
        ORDER BY added_at DESC";

    if ($stmt = $conn->prepare($sql_added)) {
        $stmt->bind_param('iss', $employee_id, $fromDateTime, $toDateTime);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $pid = isset($r['pid_json']) && $r['pid_json'] !== null ? (int)$r['pid_json'] : 0;
            if ($pid <= 0) continue;
            $added_props[$pid] = [
                'title' => $r['title_json'] ?? '',
                'added_at' => $r['added_at'] ?? null
            ];
        }
        $stmt->close();
    }

    $sql_edited = "SELECT 
            COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.property_id')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.PropertyID')), '')) AS pid_json,
            COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.property_title')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details,'$.propertyTitle')), '')) AS title_json,
            COUNT(*) AS edits,
            MAX(created_at) AS last_edited
        FROM activity_logs
        WHERE employee_id = ? AND action = 'edit_property' AND created_at BETWEEN ? AND ?
        GROUP BY pid_json
        ORDER BY edits DESC";

    if ($stmt = $conn->prepare($sql_edited)) {
        $stmt->bind_param('iss', $employee_id, $fromDateTime, $toDateTime);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $pid = isset($r['pid_json']) && $r['pid_json'] !== null ? (int)$r['pid_json'] : 0;
            if ($pid <= 0) continue;
            $edited_props[$pid] = [
                'title' => $r['title_json'] ?? '',
                'edits' => (int)$r['edits'],
                'last_edited' => $r['last_edited'] ?? null
            ];
        }
        $stmt->close();
    }

    // Compute unique properties and top property for visits
    $unique_properties = count($prop_counts);
    $top_property = null;
    if (!empty($prop_counts)) {
        uasort($prop_counts, function($a, $b) { return $b['count'] <=> $a['count']; });
        reset($prop_counts);
    $top_key = key($prop_counts);
        $top_property = [
            'property_id' => $top_key,
            'title' => $titles_map[$top_key] ?? (isset($prop_counts[$top_key]['title']) ? $prop_counts[$top_key]['title'] : ''),
            'count' => $prop_counts[$top_key]['count']
        ];
    }

    // Time series (daily) for selected actions to draw a chart
    $series = []; // ['YYYY-MM-DD' => ['property_visit'=>n, 'add_property'=>n, 'edit_property'=>n, 'login'=>n]]
    $sql = "SELECT DATE(created_at) AS dt, action, COUNT(*) AS cnt FROM activity_logs WHERE employee_id = ? AND created_at BETWEEN ? AND ? GROUP BY dt, action ORDER BY dt ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('iss', $employee_id, $fromDateTime, $toDateTime);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $d = $r['dt']; $a = $r['action']; $c = (int)$r['cnt'];
            if (!isset($series[$d])) $series[$d] = [];
            $series[$d][$a] = $c;
        }
        $stmt->close();
    }

    // Build continuous date labels between from and to
    $labels = [];
    $data_visit = []; $data_add = []; $data_edit = []; $data_login = [];
    $begin = new DateTime($fromDate);
    $end = new DateTime($toDate);
    $end = $end->modify('+1 day'); // include end
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($begin, $interval, $end);
    foreach ($period as $dt) {
        $d = $dt->format('Y-m-d');
        $labels[] = $d;
        $data_visit[] = $series[$d]['property_visit'] ?? 0;
        $data_add[] = $series[$d]['add_property'] ?? 0;
        $data_edit[] = $series[$d]['edit_property'] ?? 0;
        $data_login[] = $series[$d]['login'] ?? 0;
    }

    // Resolve titles for any property IDs we encountered (visit/add/edit)
    $all_pids = array_unique(array_merge(array_keys($prop_counts), array_keys($top_added), array_keys($top_edited)));
    $titles_map = [];
    if (!empty($all_pids)) {
        // build placeholders
        $placeholders = implode(',', array_fill(0, count($all_pids), '?'));
        // Prepare dynamic IN query using integer params
        $sql = "SELECT PropertyID, Title, friendly_id, listing_type FROM properties WHERE PropertyID IN (" . $placeholders . ")";
        // mysqli prepared statements don't support binding a variable-length list easily; use a safe approach building types/references
        $types = str_repeat('i', count($all_pids));
        if ($stmt2 = $conn->prepare($sql)) {
            // bind params by reference
            $refs = [];
            foreach ($all_pids as $k => $v) { $refs[$k] = &$all_pids[$k]; }
            array_unshift($refs, $types);
            // call_user_func_array requires references
            call_user_func_array(array($stmt2, 'bind_param'), $refs);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($r2 = $res2->fetch_assoc()) { 
                $titles_map[(int)$r2['PropertyID']] = [
                    'title' => $r2['Title'],
                    'friendly_id' => $r2['friendly_id'],
                    'type' => $r2['listing_type']
                ];
            }
            $stmt2->close();
        } else {
            // fallback: query without prepare (safe because $all_pids are integers)
            $escaped = array_map('intval', $all_pids);
            $sql2 = "SELECT PropertyID, Title, friendly_id, listing_type FROM properties WHERE PropertyID IN (" . implode(',', $escaped) . ")";
            if ($res3 = $conn->query($sql2)) {
                while ($r3 = $res3->fetch_assoc()) { 
                    $titles_map[(int)$r3['PropertyID']] = [
                        'title' => $r3['Title'],
                        'friendly_id' => $r3['friendly_id'],
                        'type' => $r3['listing_type']
                    ];
                }
                $res3->close();
            }
        }
    }

    // Build combined props table: pid => ['title','visits','add','edit']
    $combined = [];
    foreach ($all_pids as $pid) {
        $info = $titles_map[$pid] ?? null;
        $combined[$pid] = [
            'title' => $info ? $info['title'] : (isset($prop_counts[$pid]['title']) ? $prop_counts[$pid]['title'] : ''),
            'friendly_id' => $info ? $info['friendly_id'] : null,
            'type' => $info ? $info['type'] : null,
            'visits' => isset($prop_counts[$pid]['count']) ? $prop_counts[$pid]['count'] : 0,
            'added' => isset($top_added[$pid]['count']) ? $top_added[$pid]['count'] : 0,
            'edited' => isset($top_edited[$pid]['count']) ? $top_edited[$pid]['count'] : 0,
        ];
    }

    // Sort combined by visits desc, then added, then edited
    uasort($combined, function($a, $b) {
        if ($b['visits'] !== $a['visits']) return $b['visits'] <=> $a['visits'];
        if ($b['added'] !== $a['added']) return $b['added'] <=> $a['added'];
        return $b['edited'] <=> $a['edited'];
    });

    // Limit to top 20 for display
    $combined = array_slice($combined, 0, 20, true);

    $summary = [
        'login_count' => $login_count,
        'visit_count' => $visit_count,
        'added_count' => $added_count,
        'edited_count' => $edited_count,
        'unique_properties' => $unique_properties,
        'top_property' => $top_property,
        'property_counts' => $prop_counts,
        'top_added' => $top_added,
        'top_edited' => $top_edited,
        'combined_props' => $combined,
        'chart' => ['labels'=>$labels, 'visit'=>$data_visit, 'add'=>$data_add, 'edit'=>$data_edit, 'login'=>$data_login]
    ];

    // CSV export removed per request

}

$title = (get_lang_code() === 'ar') ? 'تحليل نشاط المستخدم' : 'User activity analysis';

?>
<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?></title>
    <?php
    // Compute application base path so favicon requests target the correct folder (matches other pages)
    $app_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($app_base === '/' || $app_base === '.') { $app_base = ''; }
    $favicon_path = $app_base . '/images/Logo%20Broman.svg';
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_path); ?>" type="image/svg+xml">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($favicon_path); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($favicon_path); ?>">
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?><link rel="stylesheet" href="rtl_styles.css"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Premium page styles */
        :root{ --bg:#f6f8fb; --card:#ffffff; --muted:#6b7280; --primary:#2563eb; --accent:#10b981 }
        html,body{height:100%;background:var(--bg);font-family:Inter, Tajawal, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial}
        .container{max-width:1100px;margin:28px auto;padding:20px}
        .page-hero{background:linear-gradient(90deg, rgba(37,99,235,0.12), rgba(16,185,129,0.08));padding:18px;border-radius:12px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between}
        .page-hero h1{margin:0;font-size:20px;font-weight:700;color:#0f172a}
        .page-hero p{margin:0;color:var(--muted);font-size:13px}

        .filters .card{padding:14px;background:transparent;box-shadow:none}
        form select, form input[type=date]{padding:8px 10px;border-radius:8px;border:1px solid #e6eef8;background:#fff;margin-left:8px;margin-right:8px}
        form label{font-size:13px;color:var(--muted);margin-right:6px}
        .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:none;cursor:pointer;font-weight:600}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-secondary{background:transparent;color:var(--primary);border:1px solid rgba(37,99,235,0.12);text-decoration:none;padding:8px 10px;border-radius:8px}

        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:14px 0}
        .card{background:var(--card);Padding:18px;border-radius:12px;box-shadow:0 6px 24px rgba(15,23,42,0.06);min-width:160px;transition:transform .12s ease}
        .card:hover{transform:translateY(-4px)}
        .card h3{margin:0 0 8px 0;font-size:13px;color:var(--muted);font-weight:600}
        .card p{margin:0;font-size:22px;font-weight:700;color:#0b1220}

        .table-wrap{overflow-x:auto;border-radius:10px;background:linear-gradient(180deg, #fff, #fbfdff);padding:12px}
        .small-table{width:100%;border-collapse:separate;border-spacing:0;font-size:14px}
        .small-table thead th{background:transparent;color:var(--muted);text-align:left;padding:10px 12px;border-bottom:1px solid #eef2f7}
        .small-table td{padding:12px;border-bottom:1px dashed #f1f5f9}
        .small-table tbody tr:hover td{background:rgba(37,99,235,0.03)}
        .small-table a{color:var(--primary);text-decoration:none}
        .small-table a:hover{text-decoration:underline}

        @media (max-width:640px){ .page-hero{flex-direction:column;align-items:flex-start} form label, form select, form input[type=date]{display:block;margin:6px 0} }

        /* RTL fixes: ensure table headers and cells align to the right in Arabic/RTL mode */
        html[dir="rtl"] .small-table thead th,
        html[dir="rtl"] .small-table td {
            text-align: right;
        }
        /* Use logical padding to avoid left/right mismatches in RTL */
        html[dir="rtl"] .small-table thead th { padding-inline-start:12px; padding-inline-end:10px }
        html[dir="rtl"] .small-table td { padding-inline-start:12px; padding-inline-end:10px }
    </style>
    </head>
    <body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <h1><?php echo htmlspecialchars($title); ?></h1>

    <div class="card" style="margin-bottom:12px;">
        <form method="get" action="user_activity_analysis.php">
            <label for="employee_id"><?php echo (get_lang_code() === 'ar') ? 'اختر موظف' : 'Select employee'; ?></label>
            <select name="employee_id" id="employee_id">
                <option value="0"><?php echo (get_lang_code() === 'ar') ? '--- اختر ---' : '--- choose ---'; ?></option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?php echo (int)$e['EmployeeID']; ?>" <?php echo ($employee_id == $e['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['FullName'] ?: $e['Username']); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="fromDate"><?php echo (get_lang_code() === 'ar') ? 'من' : 'From'; ?></label>
            <input type="date" name="fromDate" id="fromDate" value="<?php echo htmlspecialchars($fromDate); ?>" />
            <label for="toDate"><?php echo (get_lang_code() === 'ar') ? 'إلى' : 'To'; ?></label>
            <input type="date" name="toDate" id="toDate" value="<?php echo htmlspecialchars($toDate); ?>" />
            <button class="btn btn-primary" type="submit"><?php echo (get_lang_code() === 'ar') ? 'عرض التحليل' : 'Show analysis'; ?></button>
        </form>
    </div>
    <?php if ($employee_id > 0): ?>
        <div class="stats">
            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد تسجيلات الدخول' : 'Total logins'; ?></h3>
                <p><?php echo (int)($summary['login_count'] ?? 0); ?></p>
            </div>
            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد زيارات العقارات' : 'Property visits'; ?></h3>
                <p><?php echo (int)($summary['visit_count'] ?? 0); ?></p>
            </div>
            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد الإضافات' : 'Properties added'; ?></h3>
                <p><?php echo (int)($summary['added_count'] ?? 0); ?></p>
            </div>
            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد التعديلات' : 'Properties edited'; ?></h3>
                <p><?php echo (int)($summary['edited_count'] ?? 0); ?></p>
            </div>
        </div>

        <div class="card" style="margin-bottom:12px;">
            <h3><?php echo (get_lang_code() === 'ar') ? 'الاتجاه اليومي للأنشطة' : 'Daily activity trends'; ?></h3>
            <canvas id="activityChart" height="120"></canvas>
        </div>

        <div class="card">
            <h3><?php echo (get_lang_code() === 'ar') ? 'قائمة العقارات التي زارها الموظف' : 'List of visited properties'; ?></h3>
            <?php if (empty($summary['property_counts'])): ?>
                <p class="small"><?php echo (get_lang_code() === 'ar') ? 'لا توجد زيارات مسجلة لهذا الموظف.' : 'No property visits recorded for this user.'; ?></p>
            <?php else: ?>
                <table class="small-table">
                    <thead>
                        <tr><th><?php echo (get_lang_code() === 'ar') ? 'رقم العقار' : 'Property ID'; ?></th><th><?php echo (get_lang_code() === 'ar') ? 'العنوان' : 'Title'; ?></th><th><?php echo (get_lang_code() === 'ar') ? 'عدد الزيارات' : 'Visits'; ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($summary['property_counts'] as $pid => $info): 
                        $lookup = $titles_map[$pid] ?? null;
                        $display_title = $lookup['title'] ?? ($info['title'] ?? '-'); 
                        $fid = $lookup['friendly_id'] ?? $pid;
                        $ftype = $lookup['type'] ?? '';
                    ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars($fid); ?></strong>
                                <?php if($ftype): ?><span style="font-size:0.85em;color:#666"> (<?php echo htmlspecialchars($ftype); ?>)</span><?php endif; ?>
                            </td>
                            <td><?php if ((int)$pid > 0) { echo '<a href="view_property.php?id=' . (int)$pid . '" target="_blank" rel="noopener">' . htmlspecialchars($display_title) . '</a>'; } else { echo htmlspecialchars($display_title); } ?></td>
                            <td><?php echo (int)$info['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <!-- Properties added by the user -->
        <div class="card" style="margin-top:12px;">
            <h3><?php echo (get_lang_code() === 'ar') ? 'العقارات التي أضافها المستخدم' : 'Properties added by user'; ?></h3>
            <?php if (empty($added_props)): ?>
                <p class="small">-</p>
            <?php else: ?>
                <table class="small-table">
                    <thead>
                        <tr>
                            <th><?php echo (get_lang_code() === 'ar') ? 'رقم العقار' : 'Property ID'; ?></th>
                            <th><?php echo (get_lang_code() === 'ar') ? 'اسم العقار' : 'Title'; ?></th>
                            <th><?php echo (get_lang_code() === 'ar') ? 'تاريخ ووقت الإضافة' : 'Added at'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($added_props as $pid => $info): 
                        $lookup = $titles_map[$pid] ?? null;
                        $display_title = $lookup['title'] ?? ($info['title'] ?? '-'); 
                        $fid = $lookup['friendly_id'] ?? $pid;
                        $ftype = $lookup['type'] ?? '';
                    ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars($fid); ?></strong>
                                <?php if($ftype): ?><span style="font-size:0.85em;color:#666"> (<?php echo htmlspecialchars($ftype); ?>)</span><?php endif; ?>
                            </td>
                            <td><?php if ((int)$pid > 0) { echo '<a href="view_property.php?id=' . (int)$pid . '" target="_blank" rel="noopener">' . htmlspecialchars($display_title) . '</a>'; } else { echo htmlspecialchars($display_title); } ?></td>
                            <td><?php echo htmlspecialchars($info['added_at'] ? date('Y-m-d H:i:s', strtotime($info['added_at'])) : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Properties edited by the user -->
        <div class="card" style="margin-top:12px;">
            <h3><?php echo (get_lang_code() === 'ar') ? 'العقارات التي قام بتعديلها المستخدم' : 'Properties edited by user'; ?></h3>
            <?php if (empty($edited_props)): ?>
                <p class="small">-</p>
            <?php else: ?>
                <table class="small-table">
                    <thead>
                        <tr>
                            <th><?php echo (get_lang_code() === 'ar') ? 'رقم العقار' : 'Property ID'; ?></th>
                            <th><?php echo (get_lang_code() === 'ar') ? 'اسم العقار' : 'Title'; ?></th>
                            <th><?php echo (get_lang_code() === 'ar') ? 'عدد التعديلات' : 'Edit count'; ?></th>
                            <th><?php echo (get_lang_code() === 'ar') ? 'تاريخ آخر تعديل' : 'Last edited at'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($edited_props as $pid => $info): 
                        $lookup = $titles_map[$pid] ?? null;
                        $display_title = $lookup['title'] ?? ($info['title'] ?? '-'); 
                        $fid = $lookup['friendly_id'] ?? $pid;
                        $ftype = $lookup['type'] ?? '';
                    ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars($fid); ?></strong>
                                <?php if($ftype): ?><span style="font-size:0.85em;color:#666"> (<?php echo htmlspecialchars($ftype); ?>)</span><?php endif; ?>
                            </td>
                            <td><?php if ((int)$pid > 0) { echo '<a href="view_property.php?id=' . (int)$pid . '" target="_blank" rel="noopener">' . htmlspecialchars($display_title) . '</a>'; } else { echo htmlspecialchars($display_title); } ?></td>
                            <td><?php echo (int)($info['edits'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($info['last_edited'] ? date('Y-m-d H:i:s', strtotime($info['last_edited'])) : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function(){
                const labels = <?php echo json_encode($summary['chart']['labels'] ?? []); ?>;
                const visit = <?php echo json_encode($summary['chart']['visit'] ?? []); ?>;
                const add = <?php echo json_encode($summary['chart']['add'] ?? []); ?>;
                const edit = <?php echo json_encode($summary['chart']['edit'] ?? []); ?>;
                const login = <?php echo json_encode($summary['chart']['login'] ?? []); ?>;

                const ctx = document.getElementById('activityChart').getContext('2d');

                // create subtle gradients matching theme
                const gradVisit = ctx.createLinearGradient(0,0,0,180);
                gradVisit.addColorStop(0, 'rgba(37,99,235,0.18)');
                gradVisit.addColorStop(1, 'rgba(37,99,235,0.02)');

                const gradAdd = ctx.createLinearGradient(0,0,0,180);
                gradAdd.addColorStop(0, 'rgba(16,185,129,0.16)');
                gradAdd.addColorStop(1, 'rgba(16,185,129,0.02)');

                const gradEdit = ctx.createLinearGradient(0,0,0,180);
                gradEdit.addColorStop(0, 'rgba(245,158,11,0.14)');
                gradEdit.addColorStop(1, 'rgba(245,158,11,0.02)');

                const gradLogin = ctx.createLinearGradient(0,0,0,180);
                gradLogin.addColorStop(0, 'rgba(107,114,128,0.12)');
                gradLogin.addColorStop(1, 'rgba(107,114,128,0.02)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: '<?php echo addslashes((get_lang_code()==='ar')?'زيارات':'Visits'); ?>', data: visit, borderColor:'#2563eb', backgroundColor:gradVisit, pointBackgroundColor:'#2563eb', tension:0.32, fill:true, pointRadius:2, borderWidth:2 },
                            { label: '<?php echo addslashes((get_lang_code()==='ar')?'إضافات':'Adds'); ?>', data: add, borderColor:'#10b981', backgroundColor:gradAdd, pointBackgroundColor:'#10b981', tension:0.32, fill:true, pointRadius:2, borderWidth:2 },
                            { label: '<?php echo addslashes((get_lang_code()==='ar')?'تعديلات':'Edits'); ?>', data: edit, borderColor:'#f59e0b', backgroundColor:gradEdit, pointBackgroundColor:'#f59e0b', tension:0.32, fill:true, pointRadius:2, borderWidth:2 },
                            { label: '<?php echo addslashes((get_lang_code()==='ar')?'تسجيلات الدخول':'Logins'); ?>', data: login, borderColor:'#6b7280', backgroundColor:gradLogin, pointBackgroundColor:'#6b7280', tension:0.32, fill:true, pointRadius:2, borderWidth:2 }
                        ]
                    },
                    options: {
                        responsive:true,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth:12, padding:12 } },
                            tooltip: { mode: 'index', intersect: false }
                        },
                        interaction: { mode: 'nearest', axis: 'x', intersect: false },
                        scales: {
                            x: { ticks: { maxRotation: 0, autoSkip:true }, grid: { display:false } },
                            y: { grid: { color: 'rgba(15,23,42,0.04)' }, beginAtZero:true }
                        }
                    }
                });
            })();
        </script>

    <?php else: ?>
        <div class="card small">
            <p><?php echo (get_lang_code() === 'ar') ? 'اختر موظف لعرض تحليلات النشاط الخاصة به.' : 'Select an employee to view activity analysis.'; ?></p>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
