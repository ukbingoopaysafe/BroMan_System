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

$title = (get_lang_code() === 'ar') ? 'تقرير نشاط المستخدم' : 'User activity report';

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
    <link rel="stylesheet" href="css/premium_design.css">
    <?php if (get_direction() === 'rtl'): ?><link rel="stylesheet" href="rtl_styles.css"><?php endif; ?>
    <style>
        .premium-container.analysis-page {
            max-width: 1400px;
        }
        .analysis-subtitle {
            margin: 6px 0 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .activity-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .analysis-page {
            --premium-navy: #0b1b3a;
            --premium-gold: #a88d2b;
            --premium-gold-light: rgba(168, 141, 43, 0.12);
            --glass-bg-strong: rgba(255, 255, 255, 0.92);
            --bg-secondary: #f6f7f9;
            --text-muted: #6f7a86;
        }
        .analysis-page .premium-table thead th {
            background: #f1f3f6;
            color: #4b5563;
            text-transform: none;
            letter-spacing: 0;
            padding: 14px 16px;
        }
        .analysis-page .premium-table tbody td {
            padding: 14px 16px;
            line-height: 1.5;
        }
        .analysis-page .premium-table tbody tr {
            height: 54px;
        }
        .analysis-page .premium-table tbody tr:hover {
            background: #f9fafb;
        }
        .analysis-page .premium-stat-card:hover .stat-value {
            color: var(--premium-navy);
        }
        .analysis-page .btn-premium-gold {
            background: linear-gradient(135deg, #b79a3a 0%, #9b7f22 100%);
            box-shadow: 0 4px 16px rgba(155, 127, 34, 0.22);
        }
        .analysis-page .btn-premium-gold:hover {
            box-shadow: 0 6px 20px rgba(155, 127, 34, 0.28);
        }
        .analysis-page .premium-page-header {
            margin-bottom: 22px;
            padding-bottom: 18px;
        }
        .analysis-page .premium-card {
            padding: 24px;
        }
        .analysis-page .analysis-stats {
            gap: var(--space-xl);
            margin: var(--space-xl) 0;
        }
        .analysis-page .analysis-table-title {
            margin-bottom: 14px;
        }
        .analysis-page .analysis-table-note {
            font-size: 0.85rem;
        }
        .analysis-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-lg);
            margin: var(--space-lg) 0;
        }
        .analysis-chart {
            margin-bottom: var(--space-lg);
        }
        .analysis-table-title {
            margin: 0 0 var(--space-md);
            font-weight: var(--font-weight-semibold);
            color: var(--premium-navy);
        }
        .premium-table thead th,
        .premium-table tbody td {
            text-align: left;
        }
        html[dir="rtl"] .premium-table thead th,
        html[dir="rtl"] .premium-table tbody td {
            text-align: right;
        }
        .analysis-table-note {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        @media (max-width: 640px) {
            .premium-filter-row {
                flex-direction: column;
            }
        }
    </style>
    </head>
    <body class="premium-theme">
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="premium-container analysis-page">
        <div class="premium-page-header">
            <div>
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <p class="analysis-subtitle"><?php echo (get_lang_code() === 'ar') ? 'ملخص النشاط حسب الفترة.' : 'Activity summary by date range.'; ?></p>
            </div>
            <div class="activity-actions">
                <a class="btn-premium btn-premium-outline btn-sm" href="manager_activity.php">
                    <?php echo (get_lang_code() === 'ar') ? 'سجل النشاط' : 'Activity log'; ?>
                </a>
            </div>
        </div>

        <div class="premium-filter-section">
            <form method="get" action="user_activity_analysis.php">
                <div class="premium-filter-row">
                    <div class="premium-filter-item">
                        <label for="employee_id"><?php echo (get_lang_code() === 'ar') ? 'الموظف' : 'Employee'; ?></label>
                        <select class="premium-select" name="employee_id" id="employee_id">
                            <option value="0"><?php echo (get_lang_code() === 'ar') ? '--- اختر ---' : '--- choose ---'; ?></option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?php echo (int)$e['EmployeeID']; ?>" <?php echo ($employee_id == $e['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['FullName'] ?: $e['Username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="premium-filter-item">
                        <label for="fromDate"><?php echo (get_lang_code() === 'ar') ? 'من' : 'From'; ?></label>
                        <input class="premium-input" type="date" name="fromDate" id="fromDate" value="<?php echo htmlspecialchars($fromDate); ?>" />
                    </div>
                    <div class="premium-filter-item">
                        <label for="toDate"><?php echo (get_lang_code() === 'ar') ? 'إلى' : 'To'; ?></label>
                        <input class="premium-input" type="date" name="toDate" id="toDate" value="<?php echo htmlspecialchars($toDate); ?>" />
                    </div>
                </div>
                <div class="premium-filter-buttons">
                    <button class="btn-premium btn-premium-gold btn-sm" type="submit">
                        <?php echo (get_lang_code() === 'ar') ? 'عرض التقرير' : 'Show report'; ?>
                    </button>
                </div>
            </form>
        </div>
    <?php if ($employee_id > 0): ?>
        <div class="analysis-stats">
            <div class="premium-card premium-stat-card premium-card-gold">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد تسجيلات الدخول' : 'Total logins'; ?></h3>
                <p class="stat-value"><?php echo (int)($summary['login_count'] ?? 0); ?></p>
            </div>
            <div class="premium-card premium-stat-card premium-card-navy">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد زيارات العقارات' : 'Property visits'; ?></h3>
                <p class="stat-value"><?php echo (int)($summary['visit_count'] ?? 0); ?></p>
            </div>
            <div class="premium-card premium-stat-card premium-card-gold">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد الإضافات' : 'Properties added'; ?></h3>
                <p class="stat-value"><?php echo (int)($summary['added_count'] ?? 0); ?></p>
            </div>
            <div class="premium-card premium-stat-card premium-card-navy">
                <h3><?php echo (get_lang_code() === 'ar') ? 'عدد التعديلات' : 'Properties edited'; ?></h3>
                <p class="stat-value"><?php echo (int)($summary['edited_count'] ?? 0); ?></p>
            </div>
        </div>

        <div class="premium-card analysis-chart">
            <h3 class="analysis-table-title"><?php echo (get_lang_code() === 'ar') ? 'النشاط اليومي' : 'Daily activity'; ?></h3>
            <canvas id="activityChart" height="120"></canvas>
        </div>

        <div class="premium-card">
            <h3 class="analysis-table-title"><?php echo (get_lang_code() === 'ar') ? 'عقارات تمت زيارتها' : 'Visited properties'; ?></h3>
            <?php if (empty($summary['property_counts'])): ?>
                <p class="analysis-table-note"><?php echo (get_lang_code() === 'ar') ? 'لا توجد زيارات مسجلة.' : 'No visits recorded.'; ?></p>
            <?php else: ?>
                <div class="premium-table-container">
                    <table class="premium-table">
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
                                    <?php if($ftype): ?><span class="analysis-table-note"> (<?php echo htmlspecialchars($ftype); ?>)</span><?php endif; ?>
                                </td>
                                <td><?php if ((int)$pid > 0) { echo '<a href="view_property.php?id=' . (int)$pid . '" target="_blank" rel="noopener">' . htmlspecialchars($display_title) . '</a>'; } else { echo htmlspecialchars($display_title); } ?></td>
                                <td><?php echo (int)$info['count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <!-- Properties added by the user -->
        <div class="premium-card" style="margin-top:12px;">
            <h3 class="analysis-table-title"><?php echo (get_lang_code() === 'ar') ? 'عقارات تمت إضافتها' : 'Properties added'; ?></h3>
            <?php if (empty($added_props)): ?>
                <p class="analysis-table-note">-</p>
            <?php else: ?>
                <div class="premium-table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th><?php echo (get_lang_code() === 'ar') ? 'رقم العقار' : 'Property ID'; ?></th>
                                <th><?php echo (get_lang_code() === 'ar') ? 'العنوان' : 'Title'; ?></th>
                                <th><?php echo (get_lang_code() === 'ar') ? 'وقت الإضافة' : 'Added time'; ?></th>
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
                                    <?php if($ftype): ?><span class="analysis-table-note"> (<?php echo htmlspecialchars($ftype); ?>)</span><?php endif; ?>
                                </td>
                                <td><?php if ((int)$pid > 0) { echo '<a href="view_property.php?id=' . (int)$pid . '" target="_blank" rel="noopener">' . htmlspecialchars($display_title) . '</a>'; } else { echo htmlspecialchars($display_title); } ?></td>
                                <td><?php echo htmlspecialchars($info['added_at'] ? date('Y-m-d H:i:s', strtotime($info['added_at'])) : '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Properties edited by the user -->
        <div class="premium-card" style="margin-top:12px;">
            <h3 class="analysis-table-title"><?php echo (get_lang_code() === 'ar') ? 'عقارات تم تعديلها' : 'Properties edited'; ?></h3>
            <?php if (empty($edited_props)): ?>
                <p class="analysis-table-note">-</p>
            <?php else: ?>
                <div class="premium-table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th><?php echo (get_lang_code() === 'ar') ? 'رقم العقار' : 'Property ID'; ?></th>
                                <th><?php echo (get_lang_code() === 'ar') ? 'العنوان' : 'Title'; ?></th>
                                <th><?php echo (get_lang_code() === 'ar') ? 'عدد التعديلات' : 'Edit count'; ?></th>
                                <th><?php echo (get_lang_code() === 'ar') ? 'وقت آخر تعديل' : 'Last edit time'; ?></th>
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
                                    <?php if($ftype): ?><span class="analysis-table-note"> (<?php echo htmlspecialchars($ftype); ?>)</span><?php endif; ?>
                                </td>
                                <td><?php if ((int)$pid > 0) { echo '<a href="view_property.php?id=' . (int)$pid . '" target="_blank" rel="noopener">' . htmlspecialchars($display_title) . '</a>'; } else { echo htmlspecialchars($display_title); } ?></td>
                                <td><?php echo (int)($info['edits'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($info['last_edited'] ? date('Y-m-d H:i:s', strtotime($info['last_edited'])) : '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
                            { label: '<?php echo addslashes((get_lang_code()==='ar')?'زيارات العقار':'Property visits'); ?>', data: visit, borderColor:'#2563eb', backgroundColor:gradVisit, pointBackgroundColor:'#2563eb', tension:0.32, fill:true, pointRadius:2, borderWidth:2 },
                            { label: '<?php echo addslashes((get_lang_code()==='ar')?'عقارات مضافة':'Properties added'); ?>', data: add, borderColor:'#10b981', backgroundColor:gradAdd, pointBackgroundColor:'#10b981', tension:0.32, fill:true, pointRadius:2, borderWidth:2 },
                            { label: '<?php echo addslashes((get_lang_code()==='ar')?'عقارات معدلة':'Properties edited'); ?>', data: edit, borderColor:'#f59e0b', backgroundColor:gradEdit, pointBackgroundColor:'#f59e0b', tension:0.32, fill:true, pointRadius:2, borderWidth:2 },
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
        <div class="premium-card">
            <p class="analysis-table-note"><?php echo (get_lang_code() === 'ar') ? 'اختر موظف لعرض التقرير.' : 'Select an employee to view the report.'; ?></p>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
