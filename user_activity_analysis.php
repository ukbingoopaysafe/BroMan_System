<?php
/**
 * Operational Intelligence System — User Monitoring Profile (Enterprise v2)
 * Light theme, enriched details, context column, expandable rows.
 */
require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/permissions.php';

check_permission('manage_employees');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';
require_once __DIR__ . '/session_activity.php';

$lang = get_lang_code();
$dir  = get_direction();
$is_ar = ($lang === 'ar');

$target_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$target_id) {
    header('Location: manager_activity.php');
    exit;
}

// Fetch employee info
$emp = null;
$stmt = $conn->prepare("SELECT EmployeeID, FullName, Username, Role FROM Employees WHERE EmployeeID = ?");
$stmt->bind_param('i', $target_id);
$stmt->execute();
$res = $stmt->get_result();
if ($r = $res->fetch_assoc()) $emp = $r;
$stmt->close();

if (!$emp) {
    header('Location: manager_activity.php');
    exit;
}

$is_online = function_exists('is_user_online') ? is_user_online($conn, $target_id) : false;

$active_page = 'manager_activity.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_ar ? 'ملف المراقبة — ' : 'User Profile — '; echo htmlspecialchars($emp['FullName']); ?></title>
    <?php
    $app_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($app_base === '/' || $app_base === '.') $app_base = '';
    $favicon_path = $app_base . '/images/Logo%20Broman.svg';
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_path); ?>" type="image/svg+xml">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($favicon_path); ?>">
    <link rel="stylesheet" href="modern_styles.css">
    <?php if ($dir === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="session_timeout.js"></script>
    <style>
    /* ═══ OIS v2 Light Theme — User Profile ═══ */
    :root {
        --ois-bg: #f4f6f9;
        --ois-surface: #ffffff;
        --ois-surface-alt: #f8f9fb;
        --ois-border: #e2e6ed;
        --ois-border-light: #eff1f5;
        --ois-text: #2d3748;
        --ois-text-muted: #718096;
        --ois-text-bright: #1a202c;
        --ois-accent: #b08a0e;
        --ois-accent-dim: rgba(176,138,14,0.12);
        --ois-success: #16a34a;
        --ois-danger: #dc2626;
        --ois-warning: #d97706;
        --ois-info: #2563eb;
        --ois-radius: 10px;
        --ois-font: 'Inter', 'Tajawal', system-ui, sans-serif;
        --ois-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --ois-shadow-md: 0 4px 12px rgba(0,0,0,0.06);
    }
    body.ois-body {
        background: var(--ois-bg); color: var(--ois-text);
        font-family: var(--ois-font); margin: 0; padding: 0; min-height: 100vh;
    }
    .ois-container { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }

    /* Back link */
    .ois-back {
        display: inline-flex; align-items: center; gap: 6px;
        color: var(--ois-text-muted); text-decoration: none;
        font-size: 0.82rem; margin-bottom: 16px;
        transition: color 0.2s; font-weight: 500;
    }
    .ois-back:hover { color: var(--ois-accent); }

    /* Profile Header */
    .ois-profile-header {
        background: var(--ois-surface); border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius); padding: 24px;
        margin-bottom: 20px; display: flex; align-items: center; gap: 20px;
        flex-wrap: wrap; box-shadow: var(--ois-shadow);
    }
    .ois-avatar {
        width: 64px; height: 64px;
        background: linear-gradient(135deg, var(--ois-accent), #8a6b08);
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .ois-profile-info { flex: 1; }
    .ois-profile-info h1 {
        font-size: 1.4rem; font-weight: 700;
        color: var(--ois-text-bright); margin: 0 0 4px;
    }
    .ois-profile-meta {
        display: flex; gap: 16px; flex-wrap: wrap;
        font-size: 0.82rem; color: var(--ois-text-muted);
    }
    .ois-profile-meta span { display: flex; align-items: center; gap: 4px; }
    .ois-status-dot {
        width: 10px; height: 10px; border-radius: 50%;
        display: inline-block; flex-shrink: 0;
    }
    .ois-status-dot.online { background: var(--ois-success); box-shadow: 0 0 6px rgba(22,163,74,0.5); }
    .ois-status-dot.offline { background: var(--ois-text-muted); }

    /* Scope Controls */
    .ois-scope {
        display: flex; align-items: center; gap: 12px;
        background: var(--ois-surface); border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius); padding: 10px 16px;
        margin-bottom: 20px; flex-wrap: wrap; box-shadow: var(--ois-shadow);
    }
    .ois-scope label {
        font-size: 0.8rem; color: var(--ois-text-muted);
        font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .ois-scope input[type="date"] {
        background: var(--ois-surface-alt); border: 1px solid var(--ois-border);
        color: var(--ois-text); padding: 6px 10px; border-radius: 6px;
        font-size: 0.85rem; font-family: var(--ois-font); outline: none;
        transition: border-color 0.2s;
    }
    .ois-scope input:focus { border-color: var(--ois-accent); box-shadow: 0 0 0 3px var(--ois-accent-dim); }

    /* Stat Cards */
    .ois-stats-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px; margin-bottom: 20px;
    }
    .ois-stat-card {
        background: var(--ois-surface); border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius); padding: 16px; text-align: center;
        transition: all 0.2s; box-shadow: var(--ois-shadow);
    }
    .ois-stat-card:hover { border-color: var(--ois-accent); box-shadow: var(--ois-shadow-md); transform: translateY(-1px); }
    .ois-stat-card .stat-value {
        font-size: 1.6rem; font-weight: 700;
        color: var(--ois-text-bright); line-height: 1;
    }
    .ois-stat-card .stat-label {
        font-size: 0.72rem; color: var(--ois-text-muted);
        text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; font-weight: 600;
    }
    .ois-stat-card.accent .stat-value { color: var(--ois-accent); }
    .ois-stat-card.success .stat-value { color: var(--ois-success); }
    .ois-stat-card.danger .stat-value { color: var(--ois-danger); }

    /* Sections */
    .ois-section {
        background: var(--ois-surface); border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius); margin-bottom: 16px; overflow: hidden;
        box-shadow: var(--ois-shadow);
    }
    .ois-section-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px; cursor: pointer; user-select: none;
        transition: background 0.2s;
    }
    .ois-section-header:hover { background: var(--ois-surface-alt); }
    .ois-section-header h2 {
        font-size: 0.95rem; font-weight: 600;
        color: var(--ois-text-bright); margin: 0;
        display: flex; align-items: center; gap: 8px;
    }
    .ois-section-header .section-count {
        background: var(--ois-accent-dim); color: var(--ois-accent);
        font-size: 0.7rem; font-weight: 700;
        padding: 2px 8px; border-radius: 10px;
    }
    .ois-section-header .chevron {
        color: var(--ois-text-muted); font-size: 0.8rem;
        transition: transform 0.3s;
    }
    .ois-section.collapsed .chevron { transform: rotate(-90deg); }
    [dir="rtl"] .ois-section.collapsed .chevron { transform: rotate(90deg); }
    .ois-section-body {
        padding: 0 18px 16px; max-height: 600px;
        overflow-y: auto; transition: max-height 0.3s ease;
    }
    .ois-section.collapsed .ois-section-body {
        max-height: 0; padding: 0 18px; overflow: hidden;
    }

    /* Tables */
    .ois-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .ois-table th {
        text-align: start; padding: 10px 10px;
        color: var(--ois-text-muted); font-weight: 600;
        font-size: 0.72rem; text-transform: uppercase;
        letter-spacing: 0.5px; border-bottom: 2px solid var(--ois-border);
        position: sticky; top: 0; background: var(--ois-surface); z-index: 1;
    }
    .ois-table td {
        padding: 10px 10px; border-bottom: 1px solid var(--ois-border-light);
        color: var(--ois-text); vertical-align: top;
    }
    .ois-table tr:last-child td { border-bottom: none; }
    .ois-table tr.ois-row { cursor: pointer; transition: background 0.15s; }
    .ois-table tr.ois-row:hover td { background: var(--ois-surface-alt); }
    .ois-table tr.ois-row.expanded td { background: var(--ois-accent-dim); }

    /* Expandable row */
    .ois-expand-row { display: none; }
    .ois-expand-row.visible { display: table-row; }
    .ois-expand-panel {
        background: var(--ois-surface-alt); border-radius: 8px;
        padding: 14px 18px; margin: 4px 0;
    }
    .ois-expand-panel h4 {
        font-size: 0.8rem; font-weight: 600; color: var(--ois-text-bright);
        margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.3px;
    }
    .ois-detail-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 8px 16px;
    }
    .ois-detail-item { display: flex; flex-direction: column; gap: 2px; }
    .ois-detail-label {
        font-size: 0.7rem; font-weight: 600; color: var(--ois-text-muted);
        text-transform: uppercase; letter-spacing: 0.3px;
    }
    .ois-detail-value { font-size: 0.82rem; color: var(--ois-text-bright); font-weight: 500; }
    .ois-detail-value a { color: var(--ois-accent); text-decoration: none; font-weight: 600; }
    .ois-detail-value a:hover { text-decoration: underline; }

    /* Before/After diff */
    .ois-diff-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; margin-top: 10px; }
    .ois-diff-table th {
        background: var(--ois-border-light); padding: 6px 10px;
        text-align: start; font-size: 0.7rem; text-transform: uppercase;
        letter-spacing: 0.3px; color: var(--ois-text-muted); font-weight: 600; position: static;
    }
    .ois-diff-table td { padding: 5px 10px; border-bottom: 1px solid var(--ois-border-light); }
    .ois-diff-before { color: var(--ois-danger); background: rgba(220,38,38,0.04); }
    .ois-diff-after { color: var(--ois-success); background: rgba(22,163,74,0.04); }

    /* Badges */
    .ois-action-badge {
        display: inline-block; padding: 3px 10px; border-radius: 6px;
        font-size: 0.72rem; font-weight: 600; letter-spacing: 0.3px;
    }
    .ois-action-badge.login { background: rgba(22,163,74,0.1); color: #16a34a; }
    .ois-action-badge.logout { background: rgba(113,128,150,0.12); color: #718096; }
    .ois-action-badge.add_property { background: rgba(37,99,235,0.1); color: #2563eb; }
    .ois-action-badge.edit_property { background: rgba(217,119,6,0.1); color: #d97706; }
    .ois-action-badge.delete_property { background: rgba(220,38,38,0.1); color: #dc2626; }
    .ois-action-badge.property_visit { background: rgba(124,58,237,0.1); color: #7c3aed; }
    .ois-action-badge.failed_login { background: rgba(220,38,38,0.12); color: #dc2626; }
    .ois-action-badge.download_media { background: rgba(6,182,212,0.1); color: #0891b2; }
    .ois-action-badge.upload_media,
    .ois-action-badge.upload_attachment { background: rgba(22,163,74,0.1); color: #16a34a; }
    .ois-action-badge.add_employee { background: rgba(37,99,235,0.1); color: #2563eb; }
    .ois-action-badge.edit_employee { background: rgba(217,119,6,0.1); color: #d97706; }
    .ois-action-badge.delete_employee { background: rgba(220,38,38,0.1); color: #dc2626; }
    .ois-action-badge.share_details { background: rgba(37,211,102,0.1); color: #16a34a; }
    .ois-action-badge.share_location { background: rgba(37,99,235,0.1); color: #2563eb; }

    /* Device chips */
    .ois-device-chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .ois-device-chip {
        background: var(--ois-surface-alt); border: 1px solid var(--ois-border);
        border-radius: 6px; padding: 6px 12px;
        font-size: 0.78rem; color: var(--ois-text); font-weight: 500;
    }

    /* Utilities */
    .ois-time { color: var(--ois-text-muted); font-size: 0.78rem; white-space: nowrap; }
    .ois-summary { max-width: 380px; word-break: break-word; color: var(--ois-text); font-size: 0.8rem; line-height: 1.45; }
    .ois-context-cell { font-size: 0.75rem; color: var(--ois-text-muted); white-space: nowrap; }
    .ois-context-cell .ctx-device { font-weight: 500; color: var(--ois-text); }
    .ois-context-cell .ctx-entry { display: block; font-size: 0.68rem; margin-top: 1px; }
    .ois-expand-icon {
        display: inline-block; width: 18px; height: 18px;
        line-height: 18px; text-align: center; font-size: 0.65rem;
        color: var(--ois-text-muted); border-radius: 4px;
        transition: all 0.2s; cursor: pointer;
    }
    .ois-row:hover .ois-expand-icon { color: var(--ois-accent); }
    .ois-row.expanded .ois-expand-icon { transform: rotate(90deg); color: var(--ois-accent); }

    .ois-empty { text-align: center; padding: 32px 16px; color: var(--ois-text-muted); font-size: 0.85rem; }
    .ois-empty .empty-icon { font-size: 2rem; margin-bottom: 8px; opacity: 0.4; }
    .ois-loading { text-align: center; padding: 24px; color: var(--ois-text-muted); }
    .ois-spinner {
        display: inline-block; width: 20px; height: 20px;
        border: 2px solid var(--ois-border); border-top-color: var(--ois-accent);
        border-radius: 50%; animation: ois-spin 0.8s linear infinite;
    }
    @keyframes ois-spin { to { transform: rotate(360deg); } }

    @media (max-width: 768px) {
        .ois-container { padding: 12px 10px 40px; }
        .ois-profile-header { flex-direction: column; text-align: center; }
        .ois-profile-meta { justify-content: center; }
        .ois-stats-grid { grid-template-columns: repeat(2, 1fr); }
        .ois-summary { max-width: 180px; }
        .ois-detail-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body class="ois-body">

<?php require_once 'navbar.php'; ?>

<div class="ois-container">
    <!-- Back Link -->
    <a href="manager_activity.php" class="ois-back">← <?php echo $is_ar ? 'العودة لمراقب النظام' : 'Back to System Monitor'; ?></a>

    <!-- Profile Header -->
    <div class="ois-profile-header">
        <div class="ois-avatar"><?php echo mb_substr($emp['FullName'], 0, 1); ?></div>
        <div class="ois-profile-info">
            <h1><?php echo htmlspecialchars($emp['FullName']); ?></h1>
            <div class="ois-profile-meta">
                <span>
                    <span class="ois-status-dot <?php echo $is_online ? 'online' : 'offline'; ?>"></span>
                    <?php echo $is_online ? ($is_ar ? 'متصل' : 'Online') : ($is_ar ? 'غير متصل' : 'Offline'); ?>
                </span>
                <span>@<?php echo htmlspecialchars($emp['Username']); ?></span>
                <span><?php echo htmlspecialchars(ucfirst($emp['Role'])); ?></span>
                <span id="lastLoginText">—</span>
            </div>
        </div>
    </div>

    <!-- Scope Controls -->
    <div class="ois-scope">
        <label><?php echo $is_ar ? 'من' : 'From'; ?></label>
        <input type="date" id="dateFrom" value="<?php echo date('Y-m-01'); ?>">
        <label><?php echo $is_ar ? 'إلى' : 'To'; ?></label>
        <input type="date" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
    </div>

    <!-- Activity Summary Cards -->
    <div class="ois-stats-grid" id="statsGrid">
        <div class="ois-stat-card accent">
            <div class="stat-value" id="statTotal">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'إجمالي الأنشطة' : 'Total Actions'; ?></div>
        </div>
        <div class="ois-stat-card success">
            <div class="stat-value" id="statLogins">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'تسجيلات الدخول' : 'Logins'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statViews">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'زيارات' : 'Views'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statAdded">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'إضافات' : 'Added'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statEdited">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'تعديلات' : 'Edits'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statShares">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'مشاركات' : 'Shares'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statDownloads">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'تحميلات' : 'Downloads'; ?></div>
        </div>
    </div>

    <!-- Security Section -->
    <div class="ois-section collapsed" id="securitySection">
        <div class="ois-section-header" onclick="toggleSection('securitySection')">
            <h2>🔒 <?php echo $is_ar ? 'الأمان والأجهزة' : 'Security & Devices'; ?> <span class="section-count" id="secDeviceCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body" id="securityBody">
            <div class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="ois-section" id="timelineSection">
        <div class="ois-section-header" onclick="toggleSection('timelineSection')">
            <h2>📋 <?php echo $is_ar ? 'سجل النشاط' : 'Activity Timeline'; ?> <span class="section-count" id="timelineCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body" id="timelineBody">
            <div class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>
</div>

<script>
const IS_AR = <?php echo json_encode($is_ar); ?>;
const EMP_ID = <?php echo $target_id; ?>;
const API_URL = 'api/activity_data.php';

const ACTION_LABELS = IS_AR ? {
    login: 'تسجيل دخول', logout: 'تسجيل خروج',
    add_property: 'إضافة عقار', edit_property: 'تعديل عقار', delete_property: 'حذف عقار',
    property_visit: 'زيارة عقار', failed_login: 'محاولة فاشلة',
    download_media: 'تحميل وسائط', upload_media: 'رفع وسائط', upload_attachment: 'رفع مرفقات',
    add_employee: 'إضافة موظف', edit_employee: 'تعديل موظف', delete_employee: 'حذف موظف',
    share_details: 'مشاركة تفاصيل', share_location: 'مشاركة موقع'
} : {
    login: 'Login', logout: 'Logout',
    add_property: 'Add Property', edit_property: 'Edit Property', delete_property: 'Delete Property',
    property_visit: 'Property Visit', failed_login: 'Failed Login',
    download_media: 'Download Media', upload_media: 'Upload Media', upload_attachment: 'Upload Attachment',
    add_employee: 'Add Employee', edit_employee: 'Edit Employee', delete_employee: 'Delete Employee',
    share_details: 'Share Details', share_location: 'Share Location'
};

function getParams() {
    return `from=${document.getElementById('dateFrom').value}&to=${document.getElementById('dateTo').value}&employee_id=${EMP_ID}`;
}

function formatTime(ts) {
    if (!ts) return '—';
    const d = new Date(ts.replace(' ', 'T'));
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return IS_AR ? 'الآن' : 'Just now';
    if (diff < 3600) return IS_AR ? `${Math.floor(diff/60)} دقيقة` : `${Math.floor(diff/60)}m ago`;
    if (diff < 86400) return IS_AR ? `${Math.floor(diff/3600)} ساعة` : `${Math.floor(diff/3600)}h ago`;
    return d.toLocaleDateString(IS_AR ? 'ar-EG' : 'en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function renderContext(ctx) {
    if (!ctx) return '<span class="ois-context-cell">—</span>';
    let html = '<div class="ois-context-cell">';
    html += `<span class="ctx-device">${escHtml(ctx.device || '—')}</span>`;
    if (ctx.entry_point) {
        html += `<span class="ctx-entry">${escHtml(ctx.entry_point)}`;
        if (ctx.source_type && ctx.source_type !== 'Manual') html += ` · ${escHtml(ctx.source_type)}`;
        html += '</span>';
    }
    html += '</div>';
    return html;
}

function renderStructuredPanel(item) {
    if (!item.structured || Object.keys(item.structured).length === 0) return '';
    let html = '<div class="ois-expand-panel">';
    html += `<h4>${IS_AR ? 'تفاصيل كاملة' : 'Full Details'}</h4>`;
    html += '<div class="ois-detail-grid">';
    for (const [key, val] of Object.entries(item.structured)) {
        if (val === null || val === '' || val === undefined) continue;
        if (Array.isArray(val) && val.length > 0 && val[0] && val[0].field !== undefined) {
            html += '</div>';
            html += renderDiffTable(val);
            html += '<div class="ois-detail-grid">';
            continue;
        }
        html += '<div class="ois-detail-item">';
        html += `<span class="ois-detail-label">${escHtml(key)}</span>`;
        html += `<span class="ois-detail-value">${escHtml(String(val))}</span>`;
        html += '</div>';
    }
    html += '</div>';
    if (item.property_link) {
        html += `<div style="margin-top:10px;"><a href="${escHtml(item.property_link)}" class="ois-detail-value" style="display:inline-flex;align-items:center;gap:4px;">`;
        html += `<span style="font-size:1rem;">🔗</span> ${IS_AR ? 'فتح العقار' : 'Open Property'}`;
        html += '</a></div>';
    }
    html += '</div>';
    return html;
}

function renderDiffTable(diffs) {
    let html = '<table class="ois-diff-table">';
    html += `<thead><tr><th>${IS_AR ? 'الحقل' : 'Field'}</th><th>${IS_AR ? 'قبل' : 'Before'}</th><th>${IS_AR ? 'بعد' : 'After'}</th></tr></thead>`;
    html += '<tbody>';
    for (const d of diffs) {
        html += `<tr><td style="font-weight:600">${escHtml(d.field)}</td>`;
        html += `<td class="ois-diff-before">${escHtml(String(d.before || '—'))}</td>`;
        html += `<td class="ois-diff-after">${escHtml(String(d.after || '—'))}</td></tr>`;
    }
    html += '</tbody></table>';
    return html;
}

function toggleExpandRow(rowId) {
    const expandRow = document.getElementById('expand-' + rowId);
    const mainRow = document.getElementById('row-' + rowId);
    if (!expandRow) return;
    const isVisible = expandRow.classList.contains('visible');
    document.querySelectorAll('.ois-expand-row.visible').forEach(r => r.classList.remove('visible'));
    document.querySelectorAll('.ois-row.expanded').forEach(r => r.classList.remove('expanded'));
    if (!isVisible) {
        expandRow.classList.add('visible');
        if (mainRow) mainRow.classList.add('expanded');
    }
}

function toggleSection(id) {
    document.getElementById(id).classList.toggle('collapsed');
}

async function loadProfile() {
    try {
        const resp = await fetch(`${API_URL}?section=user_profile&${getParams()}`);
        const data = await resp.json();
        if (data.error) { console.error(data.error); return; }

        // Stats
        const ac = data.action_counts || {};
        const total = Object.values(ac).reduce((a, b) => a + b, 0);
        document.getElementById('statTotal').textContent = total;
        document.getElementById('statLogins').textContent = ac.login || 0;
        document.getElementById('statViews').textContent = ac.property_visit || 0;
        document.getElementById('statAdded').textContent = ac.add_property || 0;
        document.getElementById('statEdited').textContent = ac.edit_property || 0;
        document.getElementById('statShares').textContent = (ac.share_details || 0) + (ac.share_location || 0);
        document.getElementById('statDownloads').textContent = ac.download_media || 0;

        // Last login
        if (data.last_login) {
            document.getElementById('lastLoginText').textContent =
                (IS_AR ? 'آخر دخول: ' : 'Last login: ') + formatTime(data.last_login);
        }

        // Security section
        const secBody = document.getElementById('securityBody');
        let secHtml = '';

        const devices = data.security?.devices || [];
        document.getElementById('secDeviceCount').textContent = devices.length;
        if (devices.length > 0) {
            secHtml += `<h4 style="color:var(--ois-text-bright);margin:0 0 10px;font-size:0.85rem;">💻 ${IS_AR ? 'الأجهزة المستخدمة' : 'Devices Used'}</h4>`;
            secHtml += '<div class="ois-device-chips">';
            for (const d of devices) {
                secHtml += `<span class="ois-device-chip">${escHtml(d)}</span>`;
            }
            secHtml += '</div><br>';
        }

        secHtml += `<table class="ois-table"><thead><tr>`;
        secHtml += `<th>${IS_AR ? 'المقياس' : 'Metric'}</th><th>${IS_AR ? 'العدد' : 'Count'}</th>`;
        secHtml += `</tr></thead><tbody>`;
        secHtml += `<tr><td>${IS_AR ? 'تسجيلات الدخول' : 'Successful Logins'}</td><td>${data.security?.logins || 0}</td></tr>`;
        secHtml += `<tr><td>${IS_AR ? 'تسجيلات الخروج' : 'Logouts'}</td><td>${data.security?.logouts || 0}</td></tr>`;
        secHtml += `</tbody></table>`;
        secBody.innerHTML = secHtml;

        // Activity Timeline — enriched with expandable rows
        const timeBody = document.getElementById('timelineBody');
        const items = data.recent_activity || [];
        document.getElementById('timelineCount').textContent = items.length;

        if (items.length === 0) {
            timeBody.innerHTML = `<div class="ois-empty"><div class="empty-icon">📭</div>${IS_AR ? 'لا توجد أنشطة لهذه الفترة' : 'No activity for this period'}</div>`;
        } else {
            let html = '<table class="ois-table"><thead><tr>';
            html += '<th style="width:28px"></th>';
            html += `<th>${IS_AR ? 'الوقت' : 'Time'}</th>`;
            html += `<th>${IS_AR ? 'الحدث' : 'Action'}</th>`;
            html += `<th>${IS_AR ? 'التفاصيل' : 'Details'}</th>`;
            html += `<th>${IS_AR ? 'السياق' : 'Context'}</th>`;
            html += '</tr></thead><tbody>';

            for (const item of items) {
                const label = ACTION_LABELS[item.action] || item.action;
                const rowId = item.id;

                html += `<tr class="ois-row" id="row-${rowId}" onclick="toggleExpandRow(${rowId})">`;
                html += '<td><span class="ois-expand-icon">▶</span></td>';
                html += `<td class="ois-time">${formatTime(item.time)}</td>`;
                html += `<td><span class="ois-action-badge ${item.action}">${label}</span></td>`;
                html += `<td class="ois-summary">${escHtml(item.summary || '')}</td>`;
                html += `<td>${renderContext(item.context)}</td>`;
                html += '</tr>';

                html += `<tr class="ois-expand-row" id="expand-${rowId}">`;
                html += `<td colspan="5">${renderStructuredPanel(item)}</td>`;
                html += '</tr>';
            }
            html += '</tbody></table>';
            timeBody.innerHTML = html;
        }
    } catch(e) {
        console.error('Profile load error:', e);
    }
}

document.getElementById('dateFrom').addEventListener('change', loadProfile);
document.getElementById('dateTo').addEventListener('change', loadProfile);

loadProfile();
</script>

</body>
</html>
