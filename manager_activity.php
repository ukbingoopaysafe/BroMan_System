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

// Fetch summaries
$summaries = get_activity_summary_by_employee($conn, 500);

// Helper: determine online status (use SESSION_TIMEOUT fallback)
$session_timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800; // 30 minutes

$employee_filter = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
$view = $_GET['view'] ?? null;

// If viewing logs for a specific employee
$logs = [];
if ($view === 'logs' && $employee_filter) {
    $logs = get_activity_logs($conn, $employee_filter, 1000);
}

// Helper: readable relative time
function time_ago($datetime)
{
    // Set timezone to match your server/MySQL timezone
    date_default_timezone_set('Africa/Cairo');

    $time = strtotime($datetime);
    if (!$time) return $datetime;
    
    // Get current time for comparison
    $now = time();
    $diff = $now - $time;
    
    // For debugging
    error_log("Now: " . date('Y-m-d H:i:s', $now) . " | Log time: " . $datetime . " | Diff: " . $diff . " seconds");
    
    // Localized output for Arabic/English
    $lang = get_lang_code();
    // If the record timestamp is in the future (negative diff), show absolute time instead
    if ($diff < 0) {
        return date('Y-m-d H:i', $time);
    }

    // Use human-friendly thresholds
    // seconds, minutes, hours, days
    if ($lang === 'ar') {
        if ($diff < 30) { // Reduced "just now" threshold
            return 'الآن';
        }
        if ($diff < 90) {
            return 'منذ دقيقة';
        }
        if ($diff < 45 * 60) {
            $m = round($diff / 60);
            if ($m == 1) return 'منذ دقيقة';
            if ($m == 2) return 'منذ دقيقتين';
            if ($m >= 3 && $m <= 10) return 'منذ ' . $m . ' دقائق';
            return 'منذ ' . $m . ' دقيقة';
        }
        if ($diff < 90 * 60) {
            return 'منذ ساعة';
        }
        if ($diff < 22 * 3600) {
            $h = round($diff / 3600);
            if ($h == 1) return 'منذ ساعة';
            if ($h == 2) return 'منذ ساعتين';
            if ($h >= 3 && $h <= 10) return 'منذ ' . $h . ' ساعات';
            return 'منذ ' . $h . ' ساعة';
        }
        if ($diff < 36 * 3600) {
            return 'أمس';
        }
        if ($diff < 25 * 86400) {
            $d = round($diff / 86400);
            if ($d == 1) return 'منذ يوم';
            if ($d == 2) return 'منذ يومين';
            if ($d >= 3 && $d <= 10) return 'منذ ' . $d . ' أيام';
            return 'منذ ' . $d . ' يوم';
        }
        return date('Y-m-d H:i', $time);
    } else {
        if ($diff < 30) return 'just now';
        if ($diff < 90) return '1 minute ago';
        if ($diff < 45 * 60) return round($diff / 60) . ' minutes ago';
        if ($diff < 90 * 60) return '1 hour ago';
        if ($diff < 22 * 3600) return round($diff / 3600) . ' hours ago';
        if ($diff < 36 * 3600) return 'yesterday';
        if ($diff < 25 * 86400) return round($diff / 86400) . ' days ago';
        return date('Y-m-d H:i', $time);
    }
}

// Strings (support Arabic/English fallback)
$is_ar = get_lang_code() === 'ar';
$title = $is_ar ? 'سجل نشاط الموظفين' : 'Staff activity log';
$subtitle = $is_ar ? 'متابعة نشاط المستخدمين.' : 'Monitor user activity.';
$label_user = $is_ar ? 'الموظف' : 'Employee';
$label_action = $is_ar ? 'الإجراء' : 'Action';
$label_type = $is_ar ? 'النوع' : 'Type';
$label_time = $is_ar ? 'الوقت' : 'Time';
$label_details = $is_ar ? 'تفاصيل' : 'Details';
$label_report = $is_ar ? 'تقرير النشاط' : 'Activity report';
$view_logs_label = t('view_logs');
$refresh_label = t('refresh');
$export_label = t('export');

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/activity_table.js" defer></script>
    <style>
        .premium-container.activity-page {
            max-width: 1400px;
        }
        .activity-page {
            --premium-navy: #0b1b3a;
            --premium-gold: #a88d2b;
            --premium-gold-light: rgba(168, 141, 43, 0.12);
            --glass-bg-strong: rgba(255, 255, 255, 0.92);
            --bg-secondary: #f6f7f9;
            --text-muted: #6f7a86;
        }
        .activity-page .premium-table thead th {
            background: #f1f3f6;
            color: #4b5563;
            text-transform: none;
            letter-spacing: 0;
            padding: 14px 16px;
        }
        .activity-page .premium-table tbody td {
            padding: 14px 16px;
            line-height: 1.5;
        }
        .activity-page .premium-table tbody tr {
            height: 54px;
        }
        .activity-page .premium-table tbody tr:hover {
            background: #f9fafb;
        }
        .activity-page .premium-badge-info {
            background: #e8eef6;
            color: #34506f;
        }
        .activity-page .premium-badge-success {
            background: #e7f3ee;
            color: #1b5e3b;
        }
        .activity-page .premium-badge-warning {
            background: #f6efe3;
            color: #7a5a1c;
        }
        .activity-page .premium-badge-danger {
            background: #f5e9e9;
            color: #7a2d2d;
        }
        .activity-page .premium-badge-gold {
            background: #f3efe4;
            color: #6d5a20;
        }
        .activity-page .premium-badge-navy {
            background: #e8edf4;
            color: #2f3f57;
        }
        .activity-page .btn-premium-outline,
        .activity-page .btn-premium-ghost {
            color: #30415c;
        }
        .activity-page .btn-premium-outline:hover {
            color: #6d5a20;
        }
        .activity-page .premium-card {
            padding: 24px;
        }
        .activity-page .premium-page-header {
            margin-bottom: 22px;
            padding-bottom: 18px;
        }
        .activity-page .activity-section-title {
            margin-bottom: 14px;
        }
        .activity-page .activity-avatar {
            width: 40px;
            height: 40px;
        }
        .activity-page .status-dot {
            margin-inline-start: 10px;
        }
        .activity-page .text-muted,
        .activity-page .dt-raw {
            font-size: 0.85rem;
        }
        .activity-page .icon-btn {
            border-color: transparent;
        }
        .activity-page .icon-btn:hover {
            border-color: var(--glass-border);
        }
        .activity-page .premium-table thead th i {
            display: none;
        }
        .activity-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .activity-subtitle {
            margin: 6px 0 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .activity-section-title {
            margin: 0 0 var(--space-md);
            font-weight: var(--font-weight-semibold);
            color: var(--premium-navy);
        }
        .activity-table {
            width: 100%;
        }
        .activity-table .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-name {
            font-weight: var(--font-weight-semibold);
            color: var(--premium-navy);
        }
        .activity-avatar {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            background: var(--premium-gold-light);
            color: var(--premium-navy);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--font-weight-semibold);
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-inline-start: 8px;
            box-shadow: 0 0 0 4px rgba(0,0,0,0.04);
        }
        .status-dot.online { background: var(--status-success); }
        .status-dot.offline { background: var(--status-danger); }
        .icon-btn {
            background: transparent;
            border: 1px solid var(--glass-border);
            padding: 6px 8px;
            border-radius: 8px;
            cursor: pointer;
            color: var(--premium-navy);
        }
        .icon-btn:hover {
            border-color: var(--premium-gold);
            color: var(--premium-gold);
        }
        .expand-toggle {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            width: 34px;
            height: 34px;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-muted);
        }
        .expand-toggle.expanded {
            color: var(--premium-gold);
            border-color: var(--premium-gold);
        }
        .datetime-display {
            font-weight: var(--font-weight-semibold);
            color: var(--premium-navy);
        }
        .dt-raw {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .activity-table tr.subrow td {
            background: var(--bg-secondary);
        }
        .premium-table thead th,
        .premium-table tbody td {
            text-align: left;
        }
        html[dir="rtl"] .premium-table thead th,
        html[dir="rtl"] .premium-table tbody td {
            text-align: right;
        }
        #detailsModal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 32px;
            background: rgba(2, 13, 36, 0.45);
            z-index: 9999;
        }
        #detailsModal.open {
            display: flex;
        }
        #detailsModal .modal-card {
            background: var(--glass-bg-strong);
            border-radius: var(--radius-lg);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-xl);
            max-width: 860px;
            width: 100%;
            max-height: 80vh;
            overflow: auto;
            padding: var(--space-lg);
        }
        #detailsModal .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: var(--space-md);
        }
        #detailsModal .modal-title {
            font-weight: var(--font-weight-semibold);
            color: var(--premium-navy);
        }
        #detailsModal .modal-close {
            background: transparent;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            width: 32px;
            height: 32px;
            cursor: pointer;
        }
        #detailsModal .modal-body {
            white-space: pre-wrap;
            color: var(--text-secondary);
        }
        @media (max-width: 900px) {
            .premium-page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="premium-theme">
<?php require_once __DIR__ . '/navbar.php'; ?>

<div class="premium-container activity-page">
    <div class="premium-page-header">
        <div>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p class="activity-subtitle"><?php echo htmlspecialchars($subtitle); ?></p>
        </div>
        <div class="activity-actions">
            <?php // Link to the new per-user analytics page ?>
            <button class="btn-premium btn-premium-outline btn-sm" onclick="location.href='user_activity_analysis.php';">
                <i class="fa fa-chart-line"></i>
                <?php echo htmlspecialchars($label_report); ?>
            </button>
            <button class="btn-premium btn-premium-ghost btn-sm" onclick="location.reload();">
                <i class="fa fa-rotate"></i>
                <?php echo htmlspecialchars($refresh_label); ?>
            </button>
        </div>
    </div>

    <div class="premium-card premium-card-navy">
        <h3 class="activity-section-title"><?php echo htmlspecialchars(t('activity_logs')); ?></h3>

        <?php
            // Load all logs (recent). We'll group by employee and show expandable rows per user.
                $logs = get_activity_logs($conn, null, 1000);
                
                // ---------------------------------------------------------
                // Pre-fetch property details (friendly_id, listing_type) to replace internal PropertyID
                // ---------------------------------------------------------
                $log_pids = [];
                // First pass: gather IDs
                foreach ($logs as $k => $l) {
                    $d = $l['details'];
                    if (is_string($d)) {
                        $d = json_decode($d, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $logs[$k]['details'] = $d; // keep as array for easier modification
                        } else {
                            $d = [];
                        }
                    }
                    if (is_array($d) && !empty($d['property_id'])) {
                        $log_pids[] = (int)$d['property_id'];
                    }
                }
                
                $log_pids = array_unique($log_pids);
                $prop_lookup = [];
                if (!empty($log_pids)) {
                    // Safe batch query
                    $chunks = array_chunk($log_pids, 200); // chunk to avoid query limits
                    foreach ($chunks as $chunk) {
                         $ids_str = implode(',', array_map('intval', $chunk));
                         $res = $conn->query("SELECT PropertyID, friendly_id, listing_type, Title FROM properties WHERE PropertyID IN ($ids_str)");
                         if ($res) {
                             while ($row = $res->fetch_assoc()) {
                                 $prop_lookup[(int)$row['PropertyID']] = $row;
                             }
                             $res->close();
                         }
                    }
                }

                // Second pass: Update log details with friendly info
                foreach ($logs as $k => $l) {
                    $d = $l['details']; // is now array or null/string
                    if (is_array($d) && !empty($d['property_id'])) {
                        $pid = (int)$d['property_id'];
                        if (isset($prop_lookup[$pid])) {
                            // Fetch friendly data
                            $friendly = $prop_lookup[$pid]['friendly_id'];
                            $type = $prop_lookup[$pid]['listing_type'];
                            
                            // Inject friendly info
                            $d['friendly_id'] = $friendly;
                            $d['listing_type'] = $type;
                            
                            // Also ensure title is present
                            if (empty($d['property_title']) && !empty($prop_lookup[$pid]['Title'])) {
                                $d['property_title'] = $prop_lookup[$pid]['Title'];
                            }

                            $logs[$k]['details'] = $d;
                        }
                    }
                }
                // ---------------------------------------------------------

                // cleanup old active sessions once
                require_once 'session_activity.php';
                cleanup_old_sessions($conn);

                // Group activities by employee id (preserve order as returned)
                $byEmployee = [];
                foreach ($logs as $l) {
                    $eid = $l['employee_id'] ?? 0;
                    if (!isset($byEmployee[$eid])) {
                        $emp_name = 'ID ' . ($eid ?: 'N/A');
                        if (!empty($eid)) {
                            foreach ($summaries as $s) { if ((int)$s['EmployeeID'] === (int)$eid) { $emp_name = $s['FullName'] ?: $emp_name; break; } }
                        }
                        $byEmployee[$eid] = [
                            'id' => $eid,
                            'name' => $emp_name,
                            'online' => is_user_online($conn, $eid),
                            'activities' => []
                        ];
                    }
                    $byEmployee[$eid]['activities'][] = $l;
                }
        ?>

        <?php if (empty($logs)): ?>
            <p class="text-muted"><?php echo htmlspecialchars(t('no_activity_recorded')); ?></p>
        <?php else: ?>
            <div class="premium-table-container">
            <table id="activityTable" class="premium-table activity-table">
                <thead>
                    <tr>
                        <th class="col-user"><i class="fa fa-user"></i> <?php echo htmlspecialchars($label_user); ?></th>
                        <th class="col-event"><i class="fa fa-bolt"></i> <?php echo htmlspecialchars($label_action); ?></th>
                        <th class="col-category"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($label_type); ?></th>
                        <th class="col-datetime"><i class="fa fa-calendar"></i> <?php echo htmlspecialchars($label_time); ?></th>
                        <th class="col-actions"><i class="fa fa-eye"></i> <?php echo htmlspecialchars($label_details); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($byEmployee as $emp):
                    // employee summary row (show latest activity as preview)
                    $empId = $emp['id'];
                    $empKey = 'emp_' . ($empId ?: 'x' . rand(1000,9999));
                    $latest = $emp['activities'][0] ?? null;
                    $latest_action = $latest['action'] ?? '';
                    $latest_cat_class = 'premium-badge-navy';
                    switch ($latest_action) {
                        case 'property_visit': $latest_cat_class = 'premium-badge-info'; break;
                        case 'add_property': $latest_cat_class = 'premium-badge-success'; break;
                        case 'edit_property': $latest_cat_class = 'premium-badge-warning'; break;
                        case 'delete_property': $latest_cat_class = 'premium-badge-danger'; break;
                        case 'login': $latest_cat_class = 'premium-badge-gold'; break;
                        case 'logout': $latest_cat_class = 'premium-badge-navy'; break;
                        default: $latest_cat_class = 'premium-badge-navy'; break;
                    }
                ?>
                    <tr class="employee-row" data-employee-id="<?php echo htmlspecialchars($empKey); ?>" data-employee="<?php echo htmlspecialchars($emp['name']); ?>">
                        <td>
                            <div class="user-cell">
                                <button class="expand-toggle" data-employee-id="<?php echo htmlspecialchars($empKey); ?>" aria-label="expand">&#x25BA;</button>
                                <div class="activity-avatar"><?php echo mb_substr($emp['name'],0,1); ?></div>
                                <div>
                                    <div class="user-name"><?php echo htmlspecialchars($emp['name']); ?> <span class="status-dot <?php echo $emp['online'] ? 'online' : 'offline'; ?>" title="<?php echo $emp['online'] ? t('online') : t('offline'); ?>"></span></div>
                                    <div class="text-muted user-username">&nbsp;</div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $latest ? htmlspecialchars(t($latest['action'])) : '-'; ?></td>
                        <td><?php if($latest): ?><span class="premium-badge <?php echo $latest_cat_class; ?>"><?php echo htmlspecialchars(t($latest['action'])); ?></span><?php else: ?>-<?php endif; ?></td>
                        <td><?php if($latest): ?><div class="datetime-display"><?php echo date('d M Y، h:i A', strtotime($latest['created_at'])); ?></div><div class="dt-raw"><?php echo htmlspecialchars(time_ago($latest['created_at'])); ?></div><?php else: ?>-<?php endif; ?></td>
                        <td><?php if($latest): ?><button class="icon-btn details-toggle" data-details="<?php echo htmlspecialchars(base64_encode(format_activity_details($latest['action'], $latest['details']))); ?>"><i class="fa fa-eye"></i></button><?php else: ?>-<?php endif; ?></td>
                    </tr>
                    <?php foreach ($emp['activities'] as $act):
                        $action = $act['action'];
                        $cat_class = 'premium-badge-navy';
                        switch ($action) {
                            case 'property_visit': $cat_class = 'premium-badge-info'; break;
                            case 'add_property': $cat_class = 'premium-badge-success'; break;
                            case 'edit_property': $cat_class = 'premium-badge-warning'; break;
                            case 'delete_property': $cat_class = 'premium-badge-danger'; break;
                            case 'login': $cat_class = 'premium-badge-gold'; break;
                            case 'logout': $cat_class = 'premium-badge-navy'; break;
                            default: $cat_class = 'premium-badge-navy'; break;
                        }
                    ?>
                    <tr class="subrow" data-parent="<?php echo htmlspecialchars($empKey); ?>" style="display:none" data-action="<?php echo htmlspecialchars($action); ?>" data-datetime="<?php echo htmlspecialchars($act['created_at']); ?>">
                        <td></td>
                        <td><?php echo htmlspecialchars(t($action)); ?></td>
                        <td><span class="premium-badge <?php echo $cat_class; ?>"><?php echo htmlspecialchars(t($action)); ?></span></td>
                        <td><div class="datetime-display"><?php echo date('d M Y، h:i A', strtotime($act['created_at'])); ?></div><div class="dt-raw"><?php echo htmlspecialchars($act['created_at']); ?></div></td>
                        <td><button class="icon-btn details-toggle" data-details="<?php echo htmlspecialchars(base64_encode(format_activity_details($act['action'], $act['details']))); ?>"><i class="fa fa-eye"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Details Modal -->
<div id="detailsModal" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title"><?php echo htmlspecialchars($label_details); ?></div>
            <button class="modal-close" aria-label="close">&times;</button>
        </div>
        <div class="modal-body">&nbsp;</div>
    </div>
</div>

</body>
</html>
