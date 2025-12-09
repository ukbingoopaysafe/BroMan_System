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
$title = t('manager_activity_title');
$subtitle = t('manager_activity_description');
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
    <?php if (get_direction() === 'rtl'): ?><link rel="stylesheet" href="rtl_styles.css"><?php endif; ?>
    <link rel="stylesheet" href="assets/css/modern_activity_log.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/activity_table.js" defer></script>
    <!-- Premium UI: Google Fonts and compact theme overrides -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root{ --primary:#2563eb; --accent:#10b981; --card:#ffffff; --muted:#6b7280; --bg:#f7fafc }
        html,body{font-family: Inter, Tajawal, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background:var(--bg); color:#0b1220}
        .container{max-width:1100px;margin:28px auto;padding:0 20px}
        .activity-toolbar{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:18px}
        .activity-title{margin:0;font-size:20px;font-weight:700}
        .activity-sub{margin:4px 0 0;color:var(--muted);font-size:13px}
        .toolbar-actions{display:flex;align-items:center;gap:10px}
        .search-group{display:flex;align-items:center;gap:8px;background:#fff;padding:8px 10px;border-radius:10px;box-shadow:0 6px 20px rgba(2,6,23,0.06)}
        .search-group input{border:none;outline:none;width:220px;font-size:14px}
        .date-group input{padding:8px;border-radius:8px;border:1px solid #e6eef8;background:#fff}
        .btn{padding:8px 12px;border-radius:10px;border:none;cursor:pointer;font-weight:600;background:transparent}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-secondary{background:linear-gradient(90deg,#fff,#f8fbff);border:1px solid rgba(37,99,235,0.08);color:var(--primary)}
        .card{background:var(--card);Padding:18px;border-radius:12px;box-shadow:0 8px 30px rgba(15,23,42,0.06);min-width:160px}
        .table-wrap{overflow-x:auto;border-radius:10px;background:linear-gradient(180deg, #fff, #fbfdff);padding:12px}
        .activity-table{width:100%;border-collapse:collapse;font-size:14px}
        .activity-table thead th{background:transparent;color:var(--muted);text-align:left;padding:12px;border-bottom:1px solid #eef2f7}
        .activity-table td{padding:12px;border-bottom:1px dashed #f1f5f9;vertical-align:middle}
        .avatar.small-avatar{width:34px;height:34px;border-radius:8px;background:#eef2ff;color:var(--primary);display:inline-flex;align-items:center;justify-content:center;font-weight:700}
        .status-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-left:8px}
        .status-dot.online{background:#10b981}
        .status-dot.offline{background:#ef4444}
        .tag{display:inline-block;padding:6px 10px;border-radius:16px;font-size:12px;color:#fff}
        .tag-blue{background:#2563eb}
        .tag-green{background:#10b981}
        .tag-orange{background:#f59e0b}
        .tag-teal{background:#06b6d4}
        .tag-red{background:#dc3545}
        .tag-gray{background:#6b7280}
        .icon-btn{background:transparent;border:0;padding:6px;border-radius:8px;cursor:pointer}
        .expand-toggle{background:transparent;border:0;font-size:14px;margin-right:8px}
        .small{color:var(--muted);font-size:13px}
        /* RTL adjustments */
        html[dir="rtl"] .activity-toolbar{direction:rtl}
        html[dir="rtl"] .search-group input{direction:rtl}
        html[dir="rtl"] .activity-table thead th, html[dir="rtl"] .activity-table td{text-align:right}
        @media (max-width:820px){ .activity-toolbar{flex-direction:column;align-items:flex-start} .search-group input{width:140px} }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>

<div class="container">
    <div class="activity-toolbar">
        <div>
            <h1 class="activity-title"><?php echo htmlspecialchars($title); ?></h1>
            <p class="activity-sub"><?php echo htmlspecialchars($subtitle); ?></p>
        </div>
            <div class="toolbar-actions">
            <!-- Search and date filters removed per request -->
            <?php // Link to the new per-user analytics page ?>
            <button class="btn btn-secondary" onclick="location.href='user_activity_analysis.php';"><?php echo (get_lang_code() === 'ar') ? 'تحليل النشاط' : 'Analyze activity'; ?></button>
            <button class="btn" onclick="location.reload();"><?php echo htmlspecialchars($refresh_label); ?></button>
        </div>
    </div>

    <div class="card">
        <h3 class="small"><?php echo htmlspecialchars(t('activity_logs')); ?></h3>

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
            <p class="small"><?php echo htmlspecialchars(t('no_activity_recorded')); ?></p>
        <?php else: ?>
            <div class="table-wrap">
            <table id="activityTable" class="activity-table">
                <thead>
                    <tr>
                        <th class="col-user"><i class="fa fa-user"></i> <?php echo htmlspecialchars(t('user')); ?></th>
                        <th class="col-event"><i class="fa fa-bolt"></i> <?php echo htmlspecialchars(t('event')); ?></th>
                        <th class="col-category"><i class="fa fa-tag"></i> <?php echo htmlspecialchars(t('category')); ?></th>
                        <th class="col-datetime"><i class="fa fa-calendar"></i> <?php echo htmlspecialchars(t('date_time')); ?></th>
                        <th class="col-actions"><i class="fa fa-eye"></i> <?php echo htmlspecialchars(t('details')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($byEmployee as $emp):
                    // employee summary row (show latest activity as preview)
                    $empId = $emp['id'];
                    $empKey = 'emp_' . ($empId ?: 'x' . rand(1000,9999));
                    $latest = $emp['activities'][0] ?? null;
                    $latest_action = $latest['action'] ?? '';
                    $latest_cat_class = 'tag-gray';
                    switch ($latest_action) {
                        case 'property_visit': $latest_cat_class = 'tag-blue'; break;
                        case 'add_property': $latest_cat_class = 'tag-green'; break;
                        case 'edit_property': $latest_cat_class = 'tag-orange'; break;
                        case 'delete_property': $latest_cat_class = 'tag-red'; break;
                        case 'login': $latest_cat_class = 'tag-teal'; break;
                        case 'logout': $latest_cat_class = 'tag-gray'; break;
                        default: $latest_cat_class = 'tag-gray'; break;
                    }
                ?>
                    <tr class="employee-row" data-employee-id="<?php echo htmlspecialchars($empKey); ?>" data-employee="<?php echo htmlspecialchars($emp['name']); ?>">
                        <td>
                            <div class="user-inline">
                                <button class="expand-toggle" data-employee-id="<?php echo htmlspecialchars($empKey); ?>" aria-label="expand">&#x25BA;</button>
                                <div class="avatar small-avatar"><?php echo mb_substr($emp['name'],0,1); ?></div>
                                <div>
                                    <div class="user-name"><?php echo htmlspecialchars($emp['name']); ?> <span class="status-dot <?php echo $emp['online'] ? 'online' : 'offline'; ?>" title="<?php echo $emp['online'] ? t('online') : t('offline'); ?>"></span></div>
                                    <div class="small user-username">&nbsp;</div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $latest ? htmlspecialchars(t($latest['action'])) : '-'; ?></td>
                        <td><?php if($latest): ?><span class="tag <?php echo $latest_cat_class; ?>"><?php echo htmlspecialchars(t($latest['action'])); ?></span><?php else: ?>-<?php endif; ?></td>
                        <td><?php if($latest): ?><div class="datetime-display"><?php echo date('d M Y، h:i A', strtotime($latest['created_at'])); ?></div><div class="small"><?php echo htmlspecialchars(time_ago($latest['created_at'])); ?></div><?php else: ?>-<?php endif; ?></td>
                        <td><?php if($latest): ?><button class="icon-btn details-toggle" data-details="<?php echo htmlspecialchars(base64_encode(format_activity_details($latest['action'], $latest['details']))); ?>"><i class="fa fa-eye"></i></button><?php else: ?>-<?php endif; ?></td>
                    </tr>
                    <?php foreach ($emp['activities'] as $act):
                        $action = $act['action'];
                        $cat_class = 'tag-gray';
                        switch ($action) {
                            case 'property_visit': $cat_class = 'tag-blue'; break;
                            case 'add_property': $cat_class = 'tag-green'; break;
                            case 'edit_property': $cat_class = 'tag-orange'; break;
                            case 'delete_property': $cat_class = 'tag-red'; break;
                            case 'login': $cat_class = 'tag-teal'; break;
                            case 'logout': $cat_class = 'tag-gray'; break;
                            default: $cat_class = 'tag-gray'; break;
                        }
                    ?>
                    <tr class="subrow" data-parent="<?php echo htmlspecialchars($empKey); ?>" style="display:none" data-action="<?php echo htmlspecialchars($action); ?>" data-datetime="<?php echo htmlspecialchars($act['created_at']); ?>">
                        <td></td>
                        <td><?php echo htmlspecialchars(t($action)); ?></td>
                        <td><span class="tag <?php echo $cat_class; ?>"><?php echo htmlspecialchars(t($action)); ?></span></td>
                        <td><div class="datetime-display"><?php echo date('d M Y، h:i A', strtotime($act['created_at'])); ?></div><div class="small dt-raw"><?php echo htmlspecialchars($act['created_at']); ?></div></td>
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
            <div class="modal-title"><?php echo htmlspecialchars(t('details')); ?></div>
            <button class="modal-close" aria-label="close">&times;</button>
        </div>
        <div class="modal-body">&nbsp;</div>
    </div>
</div>

</body>
</html>
