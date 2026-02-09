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

// default range: current month
if (empty($fromDate)) { $fromDate = date('Y-m-01'); }
if (empty($toDate)) { $toDate = date('Y-m-d'); }

// normalize to datetime strings for SQL
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime = $toDate . ' 23:59:59';

// Fetch employees for selector + role map
$employees = [];
$employee_map = [];
$stmt = $conn->prepare("SELECT EmployeeID, FullName, Username, Role FROM employees ORDER BY FullName ASC");
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $employees[] = $r;
        $employee_map[(int)$r['EmployeeID']] = $r;
    }
    $stmt->close();
}

function get_employee_label($employee_map, $employee_id): string
{
    if (!$employee_id || !isset($employee_map[$employee_id])) {
        return 'غير معروف';
    }
    $row = $employee_map[$employee_id];
    $name = $row['FullName'] ?: $row['Username'];
    $role = $row['Role'] ?? '';
    if ($role !== '' && function_exists('get_role_name_ar')) {
        $role = get_role_name_ar($role);
    }
    return $name . ($role ? ' (' . $role . ')' : '');
}

function extract_property_info($details): array
{
    $decoded = null;
    if (is_string($details)) {
        $decoded = json_decode($details, true);
    } elseif (is_array($details)) {
        $decoded = $details;
    }
    if (!is_array($decoded)) {
        return ['id' => null, 'friendly_id' => null, 'title' => null];
    }

    $prop = $decoded['property'] ?? [];
    $raw = $decoded['raw'] ?? [];

    $pid = $prop['property_id'] ?? ($raw['property_id'] ?? ($raw['PropertyID'] ?? null));
    $fid = $prop['friendly_id'] ?? ($raw['friendly_id'] ?? null);
    $title = $prop['title'] ?? ($raw['property_title'] ?? ($raw['propertyTitle'] ?? null));

    return ['id' => $pid ? (int)$pid : null, 'friendly_id' => $fid, 'title' => $title];
}

function format_relative_time($timestamp): string
{
    $ts = strtotime($timestamp);
    if (!$ts) return '-';
    $now = time();
    $diff = $now - $ts;
    if ($diff < 0) $diff = 0;
    $mins = (int)floor($diff / 60);
    if ($mins < 60) {
        return 'منذ ' . $mins . ' دقيقة';
    }
    $hours = (int)floor($mins / 60);
    if ($hours < 24) {
        return 'منذ ' . $hours . ' ساعة';
    }
    $days = (int)floor($hours / 24);
    if ($days === 1) {
        return 'أمس';
    }
    return 'منذ ' . $days . ' أيام';
}

// Base where for filters
$where = "created_at BETWEEN ? AND ?";
$types = "ss";
$params = [$fromDateTime, $toDateTime];
if ($employee_id > 0) {
    $where .= " AND employee_id = ?";
    $types .= "i";
    $params[] = $employee_id;
}

// Section 1: Property Views (مشاهدات العقارات)
$views_by_property = [];
$sql_views = "SELECT employee_id, details, created_at
              FROM activity_logs
              WHERE $where AND action = 'property_visit'
              ORDER BY created_at DESC";
if ($stmt = $conn->prepare($sql_views)) {
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $k => $v) { $refs[] = &$params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $prop = extract_property_info($row['details']);
        $pid = $prop['id'] ?? 0;
        if ($pid <= 0) continue;
        if (!isset($views_by_property[$pid])) {
            $views_by_property[$pid] = [
                'property_id' => $pid,
                'friendly_id' => $prop['friendly_id'] ?? null,
                'title' => $prop['title'] ?? '',
                'total' => 0,
                'last_time' => $row['created_at'],
                'actors' => [],
            ];
        }
        $views_by_property[$pid]['total']++;
        if (strtotime($row['created_at']) > strtotime($views_by_property[$pid]['last_time'])) {
            $views_by_property[$pid]['last_time'] = $row['created_at'];
        }
        $eid = (int)$row['employee_id'];
        if (!isset($views_by_property[$pid]['actors'][$eid])) {
            $views_by_property[$pid]['actors'][$eid] = 0;
        }
        $views_by_property[$pid]['actors'][$eid]++;
    }
    $stmt->close();
}

// sort properties by total views desc
uasort($views_by_property, function($a, $b) {
    if ($b['total'] !== $a['total']) return $b['total'] <=> $a['total'];
    return strtotime($b['last_time']) <=> strtotime($a['last_time']);
});

// sort actors per property by view count desc
foreach ($views_by_property as $pid => $row) {
    if (!empty($views_by_property[$pid]['actors'])) {
        arsort($views_by_property[$pid]['actors']);
    }
}

$title = 'تحليلات النشاط التشغيلي';
?>
<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?></title>
    <?php
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
        .premium-container.analysis-page { max-width: 1500px; }
        .analysis-subtitle { margin: 6px 0 0; color: var(--text-muted); font-size: 0.9rem; }
        .analysis-page { --premium-navy: #0b1b3a; --premium-gold: #a88d2b; --text-muted: #6f7a86; }
        .analysis-page .premium-page-header { margin-bottom: 18px; padding-bottom: 14px; }
        .analysis-page .premium-card { padding: 18px 20px; }
        .analysis-page .premium-filter-section { padding: 18px 20px; margin-bottom: 16px; border-radius: var(--radius-lg); }
        .analysis-page .premium-filter-row { gap: var(--space-lg); align-items: end; }
        .analysis-page .premium-filter-item label { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .analysis-page .premium-filter-item select,
        .analysis-page .premium-filter-item input[type="date"] { min-height: 40px; }
        .analysis-page .premium-filter-buttons { padding-top: 10px; margin-top: 4px; }
        .analysis-table-title { margin: 0 0 10px; font-weight: 600; color: var(--premium-navy); }
        .analysis-table-note { color: var(--text-muted); font-size: 0.9rem; }
        .premium-table thead th { background: #f1f3f6; color: #4b5563; text-transform: none; letter-spacing: 0; padding: 10px 12px; white-space: nowrap; }
        .premium-table tbody td { padding: 10px 12px; vertical-align: middle; }
        .premium-table tbody tr:hover { background: #f9fafb; }
        .actor-list { display: flex; flex-direction: column; gap: 4px; }
        .actor-item { font-size: 0.85rem; color: #1f2937; }
        .actor-item span { color: var(--text-muted); font-size: 0.82rem; }
        @media (max-width: 640px) { .premium-filter-row { flex-direction: column; } }
    </style>
    </head>
    <body class="premium-theme">
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="premium-container analysis-page">
        <div class="premium-page-header">
            <div>
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <p class="analysis-subtitle">التركيز على ماذا حدث وعلى أي عقار، ثم من قام به ومتى.</p>
            </div>
        </div>

        <div class="premium-filter-section">
            <form method="get" action="user_activity_analysis.php">
                <div class="premium-filter-row">
                    <div class="premium-filter-item">
                        <label for="employee_id">الموظف (اختياري)</label>
                        <select class="premium-select" name="employee_id" id="employee_id">
                            <option value="0">كل المستخدمين</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?php echo (int)$e['EmployeeID']; ?>" <?php echo ($employee_id == $e['EmployeeID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($e['FullName'] ?: $e['Username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="premium-filter-item">
                        <label for="fromDate">من</label>
                        <input class="premium-input" type="date" name="fromDate" id="fromDate" value="<?php echo htmlspecialchars($fromDate); ?>" />
                    </div>
                    <div class="premium-filter-item">
                        <label for="toDate">إلى</label>
                        <input class="premium-input" type="date" name="toDate" id="toDate" value="<?php echo htmlspecialchars($toDate); ?>" />
                    </div>
                </div>
                <div class="premium-filter-buttons">
                    <button class="btn-premium btn-premium-gold btn-sm" type="submit">تحديث النتائج</button>
                </div>
            </form>
        </div>

        <div class="premium-card">
            <h3 class="analysis-table-title">مشاهدات العقارات</h3>
            <?php if (empty($views_by_property)): ?>
                <p class="analysis-table-note">لا توجد مشاهدات ضمن الفترة المحددة.</p>
            <?php else: ?>
                <div class="premium-table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>العقار</th>
                                <th>العنوان</th>
                                <th>عدد المشاهدات</th>
                                <th>بواسطة</th>
                                <th>آخر مشاهدة</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($views_by_property as $row): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($row['friendly_id'] ?: $row['property_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['title'] ?: '-'); ?></td>
                                <td><?php echo (int)$row['total']; ?></td>
                                <td>
                                    <div class="actor-list">
                                        <?php foreach ($row['actors'] as $eid => $count): ?>
                                            <div class="actor-item">
                                                <?php echo htmlspecialchars(get_employee_label($employee_map, $eid)); ?>
                                                <span>— <?php echo (int)$count; ?> مرة</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(format_relative_time($row['last_time'])); ?>
                                    <div class="analysis-table-note"><?php echo htmlspecialchars($row['last_time']); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
