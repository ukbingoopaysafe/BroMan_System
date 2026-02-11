<?php
/**
 * Operational Intelligence System — Manager Activity Monitor (Enterprise v2)
 * Light theme, enriched details, context columns, expandable rows, users directory.
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

// Employee list for filter
$employees = [];
$eq = $conn->query("SELECT EmployeeID, FullName, Role FROM Employees ORDER BY FullName ASC");
if ($eq) { while ($r = $eq->fetch_assoc()) $employees[] = $r; $eq->close(); }

$active_page = 'manager_activity.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_ar ? 'مراقب النظام — استخبارات تشغيلية' : 'System Monitor — Operational Intelligence'; ?></title>
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
    /* ══════════════════════════════════════════════════════════════
       OIS v2 — Light Enterprise Theme
       ══════════════════════════════════════════════════════════════ */

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
        background: var(--ois-bg);
        color: var(--ois-text);
        font-family: var(--ois-font);
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }

    .ois-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px 20px 60px;
    }

    /* Header */
    .ois-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }
    .ois-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--ois-text-bright);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ois-header h1 .ois-icon {
        width: 32px; height: 32px;
        background: linear-gradient(135deg, var(--ois-accent) 0%, #8a6b08 100%);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px;
        color: #fff;
    }

    /* Scope Controls */
    .ois-scope {
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--ois-surface);
        border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius);
        padding: 10px 16px;
        flex-wrap: wrap;
        box-shadow: var(--ois-shadow);
    }
    .ois-scope label {
        font-size: 0.8rem;
        color: var(--ois-text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .ois-scope input[type="date"],
    .ois-scope select {
        background: var(--ois-surface-alt);
        border: 1px solid var(--ois-border);
        color: var(--ois-text);
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-family: var(--ois-font);
        outline: none;
        transition: border-color 0.2s;
    }
    .ois-scope input[type="date"]:focus,
    .ois-scope select:focus {
        border-color: var(--ois-accent);
        box-shadow: 0 0 0 3px var(--ois-accent-dim);
    }
    .ois-scope .ois-divider {
        width: 1px;
        height: 28px;
        background: var(--ois-border);
    }

    /* Status Strip */
    .ois-status-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .ois-stat-card {
        background: var(--ois-surface);
        border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius);
        padding: 16px;
        text-align: center;
        transition: all 0.2s;
        box-shadow: var(--ois-shadow);
    }
    .ois-stat-card:hover {
        border-color: var(--ois-accent);
        box-shadow: var(--ois-shadow-md);
        transform: translateY(-1px);
    }
    .ois-stat-card .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--ois-text-bright);
        line-height: 1;
    }
    .ois-stat-card .stat-label {
        font-size: 0.72rem;
        color: var(--ois-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 6px;
        font-weight: 600;
    }
    .ois-stat-card.accent .stat-value { color: var(--ois-accent); }
    .ois-stat-card.success .stat-value { color: var(--ois-success); }
    .ois-stat-card.danger .stat-value { color: var(--ois-danger); }
    .ois-stat-card.warning .stat-value { color: var(--ois-warning); }

    /* Online Users Ticker */
    .ois-online-users {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        padding: 10px 0;
    }
    .ois-user-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--ois-surface);
        border: 1px solid var(--ois-border);
        border-radius: 20px;
        padding: 5px 14px 5px 8px;
        font-size: 0.8rem;
        color: var(--ois-text);
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        box-shadow: var(--ois-shadow);
        font-weight: 500;
    }
    .ois-user-chip:hover {
        background: var(--ois-accent-dim);
        border-color: var(--ois-accent);
        color: var(--ois-text-bright);
    }
    .ois-user-chip .dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--ois-success);
        flex-shrink: 0;
        animation: ois-pulse 2s ease-in-out infinite;
    }
    @keyframes ois-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Users Directory */
    .ois-users-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px;
    }
    .ois-user-card {
        background: var(--ois-surface);
        border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius);
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s;
        text-decoration: none;
        color: inherit;
        box-shadow: var(--ois-shadow);
        cursor: pointer;
    }
    .ois-user-card:hover {
        border-color: var(--ois-accent);
        box-shadow: var(--ois-shadow-md);
        transform: translateY(-1px);
    }
    .ois-user-avatar {
        width: 42px; height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--ois-accent) 0%, #8a6b08 100%);
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
        position: relative;
    }
    .ois-user-avatar .online-dot {
        position: absolute;
        bottom: 0; right: 0;
        width: 12px; height: 12px;
        border-radius: 50%;
        background: var(--ois-success);
        border: 2px solid var(--ois-surface);
    }
    .ois-user-card-info {
        flex: 1;
        min-width: 0;
    }
    .ois-user-card-name {
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--ois-text-bright);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ois-user-card-meta {
        font-size: 0.72rem;
        color: var(--ois-text-muted);
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 2px;
        flex-wrap: wrap;
    }
    .ois-user-card-meta .badge {
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .ois-user-card-meta .badge.admin { background: rgba(220,38,38,0.1); color: var(--ois-danger); }
    .ois-user-card-meta .badge.manager { background: var(--ois-accent-dim); color: var(--ois-accent); }
    .ois-user-card-meta .badge.employee { background: rgba(37,99,235,0.1); color: var(--ois-info); }
    .ois-user-card-actions {
        font-size: 0.75rem;
        color: var(--ois-text-muted);
        font-weight: 500;
    }

    /* Sections */
    .ois-section {
        background: var(--ois-surface);
        border: 1px solid var(--ois-border);
        border-radius: var(--ois-radius);
        margin-bottom: 16px;
        overflow: hidden;
        box-shadow: var(--ois-shadow);
    }
    .ois-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        cursor: pointer;
        user-select: none;
        transition: background 0.2s;
    }
    .ois-section-header:hover {
        background: var(--ois-surface-alt);
    }
    .ois-section-header h2 {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--ois-text-bright);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ois-section-header .section-count {
        background: var(--ois-accent-dim);
        color: var(--ois-accent);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
    }
    .ois-section-header .chevron {
        color: var(--ois-text-muted);
        font-size: 0.8rem;
        transition: transform 0.3s;
    }
    .ois-section.collapsed .chevron {
        transform: rotate(-90deg);
    }
    [dir="rtl"] .ois-section.collapsed .chevron {
        transform: rotate(90deg);
    }
    .ois-section-body {
        padding: 0 18px 16px;
        max-height: 600px;
        overflow-y: auto;
        transition: max-height 0.3s ease;
    }
    .ois-section.collapsed .ois-section-body {
        max-height: 0;
        padding: 0 18px;
        overflow: hidden;
    }

    /* Tables */
    .ois-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .ois-table th {
        text-align: start;
        padding: 10px 10px;
        color: var(--ois-text-muted);
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--ois-border);
        position: sticky;
        top: 0;
        background: var(--ois-surface);
        z-index: 1;
    }
    .ois-table td {
        padding: 10px 10px;
        border-bottom: 1px solid var(--ois-border-light);
        color: var(--ois-text);
        vertical-align: top;
    }
    .ois-table tr:last-child td { border-bottom: none; }
    .ois-table tr.ois-row { cursor: pointer; transition: background 0.15s; }
    .ois-table tr.ois-row:hover td { background: var(--ois-surface-alt); }
    .ois-table tr.ois-row.expanded td { background: var(--ois-accent-dim); }

    /* Expandable Detail Panel */
    .ois-expand-row { display: none; }
    .ois-expand-row.visible { display: table-row; }
    .ois-expand-panel {
        background: var(--ois-surface-alt);
        border-radius: 8px;
        padding: 14px 18px;
        margin: 4px 0;
    }
    .ois-expand-panel h4 {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--ois-text-bright);
        margin: 0 0 10px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .ois-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 8px 16px;
    }
    .ois-detail-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .ois-detail-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--ois-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .ois-detail-value {
        font-size: 0.82rem;
        color: var(--ois-text-bright);
        font-weight: 500;
    }
    .ois-detail-value a {
        color: var(--ois-accent);
        text-decoration: none;
        font-weight: 600;
    }
    .ois-detail-value a:hover { text-decoration: underline; }

    /* Before/After Diff */
    .ois-diff-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
        margin-top: 10px;
    }
    .ois-diff-table th {
        background: var(--ois-border-light);
        padding: 6px 10px;
        text-align: start;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: var(--ois-text-muted);
        font-weight: 600;
        position: static;
    }
    .ois-diff-table td {
        padding: 5px 10px;
        border-bottom: 1px solid var(--ois-border-light);
    }
    .ois-diff-before {
        color: var(--ois-danger);
        background: rgba(220,38,38,0.04);
    }
    .ois-diff-after {
        color: var(--ois-success);
        background: rgba(22,163,74,0.04);
    }

    /* Action badges */
    .ois-action-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.3px;
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
    .ois-action-badge.property_share_details,
    .ois-action-badge.PROPERTY_SHARE_DETAILS { background: rgba(37,211,102,0.1); color: #16a34a; }
    .ois-action-badge.property_share_location,
    .ois-action-badge.PROPERTY_SHARE_LOCATION { background: rgba(37,99,235,0.1); color: #2563eb; }

    /* User link */
    .ois-user-link {
        color: var(--ois-accent);
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }
    .ois-user-link:hover { color: var(--ois-text-bright); text-decoration: underline; }

    /* Summary + Context cells */
    .ois-summary {
        max-width: 380px;
        word-break: break-word;
        color: var(--ois-text);
        font-size: 0.8rem;
        line-height: 1.45;
    }
    .ois-context-cell {
        font-size: 0.75rem;
        color: var(--ois-text-muted);
        white-space: nowrap;
    }
    .ois-context-cell .ctx-device { font-weight: 500; color: var(--ois-text); }
    .ois-context-cell .ctx-entry { display: block; font-size: 0.68rem; margin-top: 1px; }
    .ois-time {
        color: var(--ois-text-muted);
        font-size: 0.78rem;
        white-space: nowrap;
    }

    /* Row expand toggle icon */
    .ois-expand-icon {
        display: inline-block;
        width: 18px; height: 18px;
        line-height: 18px;
        text-align: center;
        font-size: 0.65rem;
        color: var(--ois-text-muted);
        border-radius: 4px;
        transition: all 0.2s;
        cursor: pointer;
        flex-shrink: 0;
    }
    .ois-row:hover .ois-expand-icon { color: var(--ois-accent); }
    .ois-row.expanded .ois-expand-icon { transform: rotate(90deg); color: var(--ois-accent); }

    /* Empty state */
    .ois-empty {
        text-align: center;
        padding: 32px 16px;
        color: var(--ois-text-muted);
        font-size: 0.85rem;
    }
    .ois-empty .empty-icon {
        font-size: 2rem;
        margin-bottom: 8px;
        opacity: 0.4;
    }

    /* Loading */
    .ois-loading {
        text-align: center;
        padding: 24px;
        color: var(--ois-text-muted);
    }
    .ois-spinner {
        display: inline-block;
        width: 20px; height: 20px;
        border: 2px solid var(--ois-border);
        border-top-color: var(--ois-accent);
        border-radius: 50%;
        animation: ois-spin 0.8s linear infinite;
    }
    @keyframes ois-spin { to { transform: rotate(360deg); } }

    /* Responsive */
    @media (max-width: 768px) {
        .ois-container { padding: 12px 10px 40px; }
        .ois-header { flex-direction: column; align-items: flex-start; }
        .ois-scope { flex-direction: column; align-items: stretch; }
        .ois-scope .ois-divider { display: none; }
        .ois-status-strip { grid-template-columns: repeat(2, 1fr); }
        .ois-table { font-size: 0.75rem; }
        .ois-table th, .ois-table td { padding: 6px 6px; }
        .ois-summary { max-width: 180px; }
        .ois-users-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
        .ois-detail-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body class="ois-body">

<?php require_once 'navbar.php'; ?>

<div class="ois-container">
    <!-- Header -->
    <div class="ois-header">
        <h1>
            <span class="ois-icon">⚡</span>
            <?php echo $is_ar ? 'مراقب النظام' : 'System Monitor'; ?>
        </h1>
        <div class="ois-scope" id="scopeControls">
            <label><?php echo $is_ar ? 'من' : 'From'; ?></label>
            <input type="date" id="dateFrom" value="<?php echo date('Y-m-01'); ?>">
            <label><?php echo $is_ar ? 'إلى' : 'To'; ?></label>
            <input type="date" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
            <span class="ois-divider"></span>
            <label><?php echo $is_ar ? 'المستخدم' : 'User'; ?></label>
            <select id="employeeFilter">
                <option value=""><?php echo $is_ar ? 'الكل' : 'All Users'; ?></option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['EmployeeID']; ?>"><?php echo htmlspecialchars($emp['FullName']); ?> (<?php echo $emp['Role']; ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Status Strip -->
    <div class="ois-status-strip" id="statusStrip">
        <div class="ois-stat-card success">
            <div class="stat-value" id="statOnline">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'متصل الآن' : 'Online Now'; ?></div>
        </div>
        <div class="ois-stat-card accent">
            <div class="stat-value" id="statTotal">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'إجمالي الأنشطة' : 'Total Actions'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statViews">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'زيارات العقارات' : 'Property Views'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statAdded">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'عقارات مضافة' : 'Properties Added'; ?></div>
        </div>
        <div class="ois-stat-card">
            <div class="stat-value" id="statEdited">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'عقارات معدّلة' : 'Modified'; ?></div>
        </div>
        <div class="ois-stat-card warning">
            <div class="stat-value" id="statShares">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'مشاركات' : 'Shares'; ?></div>
        </div>
        <div class="ois-stat-card danger" id="statSecurityCard">
            <div class="stat-value" id="statSecurity">—</div>
            <div class="stat-label"><?php echo $is_ar ? 'تنبيهات أمنية' : 'Security Alerts'; ?></div>
        </div>
    </div>

    <!-- Online Users -->
    <div id="onlineUsersRow" class="ois-online-users" style="display:none;"></div>

    <!-- Users Directory Section -->
    <div class="ois-section collapsed" id="usersDirectorySection">
        <div class="ois-section-header" onclick="toggleSection('usersDirectorySection')">
            <h2>👥 <?php echo $is_ar ? 'دليل المستخدمين' : 'Users Directory'; ?> <span class="section-count" id="usersDirectoryCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="usersDirectoryContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Security & Access Section -->
    <div class="ois-section collapsed" id="securitySection">
        <div class="ois-section-header" onclick="toggleSection('securitySection')">
            <h2>🔒 <?php echo $is_ar ? 'الأمان والوصول' : 'Security & Access'; ?> <span class="section-count" id="securityCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="securityContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Property Views Section -->
    <div class="ois-section collapsed" id="viewsSection">
        <div class="ois-section-header" onclick="toggleSection('viewsSection')">
            <h2>👁 <?php echo $is_ar ? 'زيارات العقارات' : 'Property Views'; ?> <span class="section-count" id="viewsCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="viewsContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Properties Added Section -->
    <div class="ois-section collapsed" id="addedSection">
        <div class="ois-section-header" onclick="toggleSection('addedSection')">
            <h2>➕ <?php echo $is_ar ? 'عقارات مضافة' : 'Properties Added'; ?> <span class="section-count" id="addedCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="addedContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Properties Modified Section -->
    <div class="ois-section collapsed" id="modifiedSection">
        <div class="ois-section-header" onclick="toggleSection('modifiedSection')">
            <h2>✏️ <?php echo $is_ar ? 'عقارات معدّلة' : 'Properties Modified'; ?> <span class="section-count" id="modifiedCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="modifiedContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Share Actions Section -->
    <div class="ois-section collapsed" id="sharesSection">
        <div class="ois-section-header" onclick="toggleSection('sharesSection')">
            <h2>📤 <?php echo $is_ar ? 'المشاركات' : 'Share Actions'; ?> <span class="section-count" id="sharesCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="sharesContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Media Downloads Section -->
    <div class="ois-section collapsed" id="downloadsSection">
        <div class="ois-section-header" onclick="toggleSection('downloadsSection')">
            <h2>📥 <?php echo $is_ar ? 'تحميل الوسائط' : 'Media Downloads'; ?> <span class="section-count" id="downloadsCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="downloadsContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Media Uploads Section -->
    <div class="ois-section collapsed" id="uploadsSection">
        <div class="ois-section-header" onclick="toggleSection('uploadsSection')">
            <h2>📤 <?php echo $is_ar ? 'رفع الوسائط' : 'Media Uploads'; ?> <span class="section-count" id="uploadsCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="uploadsContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Employee Management Section -->
    <div class="ois-section collapsed" id="empSection">
        <div class="ois-section-header" onclick="toggleSection('empSection')">
            <h2>👤 <?php echo $is_ar ? 'إدارة الموظفين' : 'Employee Management'; ?> <span class="section-count" id="empCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="empContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>

    <!-- Full Activity Timeline -->
    <div class="ois-section collapsed" id="timelineSection">
        <div class="ois-section-header" onclick="toggleSection('timelineSection')">
            <h2>📋 <?php echo $is_ar ? 'سجل النشاط الكامل' : 'Full Activity Timeline'; ?> <span class="section-count" id="timelineCount">0</span></h2>
            <span class="chevron">▼</span>
        </div>
        <div class="ois-section-body">
            <div id="timelineContent" class="ois-loading"><div class="ois-spinner"></div></div>
        </div>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
// OIS v2 JavaScript — Enterprise enriched rendering
// ══════════════════════════════════════════════════════════════
const IS_AR = <?php echo json_encode($is_ar); ?>;
const API_URL = 'api/activity_data.php';

const ACTION_LABELS = IS_AR ? {
    login: 'تسجيل دخول', logout: 'تسجيل خروج',
    add_property: 'إضافة عقار', edit_property: 'تعديل عقار', delete_property: 'حذف عقار',
    property_visit: 'زيارة عقار', failed_login: 'محاولة فاشلة',
    download_media: 'تحميل وسائط', upload_media: 'رفع وسائط', upload_attachment: 'رفع مرفقات',
    add_employee: 'إضافة موظف', edit_employee: 'تعديل موظف', delete_employee: 'حذف موظف',
    share_details: 'مشاركة تفاصيل', share_location: 'مشاركة موقع',
    property_share_details: 'مشاركة تفاصيل', property_share_location: 'مشاركة موقع',
    PROPERTY_SHARE_DETAILS: 'مشاركة تفاصيل', PROPERTY_SHARE_LOCATION: 'مشاركة موقع'
} : {
    login: 'Login', logout: 'Logout',
    add_property: 'Add Property', edit_property: 'Edit Property', delete_property: 'Delete Property',
    property_visit: 'Property Visit', failed_login: 'Failed Login',
    download_media: 'Download Media', upload_media: 'Upload Media', upload_attachment: 'Upload Attachment',
    add_employee: 'Add Employee', edit_employee: 'Edit Employee', delete_employee: 'Delete Employee',
    share_details: 'Share Details', share_location: 'Share Location',
    property_share_details: 'Share Details', property_share_location: 'Share Location',
    PROPERTY_SHARE_DETAILS: 'Share Details', PROPERTY_SHARE_LOCATION: 'Share Location'
};

function getParams() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    const emp = document.getElementById('employeeFilter').value;
    let p = `from=${from}&to=${to}`;
    if (emp) p += `&employee_id=${emp}`;
    return p;
}

async function fetchSection(section, extraParams) {
    const url = `${API_URL}?section=${section}&${getParams()}${extraParams ? '&' + extraParams : ''}`;
    const resp = await fetch(url);
    return resp.json();
}

function toggleSection(id) {
    const el = document.getElementById(id);
    el.classList.toggle('collapsed');
    if (!el.classList.contains('collapsed') && el.dataset.loaded !== 'true') {
        loadSection(id);
    }
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

function renderActionBadge(action) {
    const label = ACTION_LABELS[action] || action;
    return `<span class="ois-action-badge ${action}">${label}</span>`;
}

function renderUserLink(empId, name) {
    if (!empId) return IS_AR ? 'النظام' : 'System';
    return `<a href="user_activity_analysis.php?id=${empId}" class="ois-user-link">${escHtml(name)}</a>`;
}

function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ─── Context Rendering ─────────────────────────────────
function renderContext(ctx) {
    if (!ctx) return '<span class="ois-context-cell">—</span>';
    let html = '<div class="ois-context-cell">';
    html += `<span class="ctx-device">${escHtml(ctx.device || '—')}</span>`;
    if (ctx.entry_point) {
        html += `<span class="ctx-entry">${escHtml(ctx.entry_point)}`;
        if (ctx.source_type && ctx.source_type !== 'Manual') {
            html += ` · ${escHtml(ctx.source_type)}`;
        }
        html += '</span>';
    }
    html += '</div>';
    return html;
}

// ─── Expandable Detail Panel ───────────────────────────
function renderStructuredPanel(item) {
    if (!item.structured || Object.keys(item.structured).length === 0) return '';
    
    let html = '<div class="ois-expand-panel">';
    html += `<h4>${IS_AR ? 'تفاصيل كاملة' : 'Full Details'}</h4>`;
    html += '<div class="ois-detail-grid">';
    
    for (const [key, val] of Object.entries(item.structured)) {
        if (val === null || val === '' || val === undefined) continue;
        
        // Before/After diff rendering
        if (Array.isArray(val) && val.length > 0 && val[0] && val[0].field !== undefined) {
            html += '</div>'; // close detail-grid
            html += renderDiffTable(val);
            html += '<div class="ois-detail-grid">'; // reopen
            continue;
        }
        
        html += '<div class="ois-detail-item">';
        html += `<span class="ois-detail-label">${escHtml(key)}</span>`;
        html += `<span class="ois-detail-value">${escHtml(String(val))}</span>`;
        html += '</div>';
    }
    html += '</div>';
    
    // Property link
    if (item.property_link) {
        html += `<div style="margin-top:10px;"><a href="${escHtml(item.property_link)}" class="ois-detail-value" style="display:inline-flex;align-items:center;gap:4px;">`;
        html += `<span style="font-size:1rem;">🔗</span> ${IS_AR ? 'فتح العقار' : 'Open Property'}`;
        html += '</a></div>';
    }
    
    html += '</div>';
    return html;
}

function renderDiffTable(diffs) {
    let html = `<table class="ois-diff-table">`;
    html += `<thead><tr><th>${IS_AR ? 'الحقل' : 'Field'}</th><th>${IS_AR ? 'قبل' : 'Before'}</th><th>${IS_AR ? 'بعد' : 'After'}</th></tr></thead>`;
    html += '<tbody>';
    for (const d of diffs) {
        html += `<tr>`;
        html += `<td style="font-weight:600">${escHtml(d.field)}</td>`;
        html += `<td class="ois-diff-before">${escHtml(String(d.before || '—'))}</td>`;
        html += `<td class="ois-diff-after">${escHtml(String(d.after || '—'))}</td>`;
        html += `</tr>`;
    }
    html += '</tbody></table>';
    return html;
}

// ─── Toggle expand row ─────────────────────────────────
function toggleExpandRow(rowId) {
    const expandRow = document.getElementById('expand-' + rowId);
    const mainRow = document.getElementById('row-' + rowId);
    if (!expandRow) return;
    
    const isVisible = expandRow.classList.contains('visible');
    
    // Close all other expanded rows
    document.querySelectorAll('.ois-expand-row.visible').forEach(r => {
        r.classList.remove('visible');
    });
    document.querySelectorAll('.ois-row.expanded').forEach(r => {
        r.classList.remove('expanded');
    });
    
    if (!isVisible) {
        expandRow.classList.add('visible');
        if (mainRow) mainRow.classList.add('expanded');
    }
}

// ─── Enriched Table Renderer ───────────────────────────
function renderTable(items, showEmployee = true) {
    if (!items || items.length === 0) {
        return `<div class="ois-empty"><div class="empty-icon">📭</div>${IS_AR ? 'لا توجد بيانات لهذه الفترة' : 'No data for this period'}</div>`;
    }
    let html = '<table class="ois-table"><thead><tr>';
    html += `<th style="width:28px"></th>`;
    html += `<th>${IS_AR ? 'الوقت' : 'Time'}</th>`;
    if (showEmployee) html += `<th>${IS_AR ? 'المستخدم' : 'User'}</th>`;
    html += `<th>${IS_AR ? 'الحدث' : 'Action'}</th>`;
    html += `<th>${IS_AR ? 'التفاصيل' : 'Details'}</th>`;
    html += `<th>${IS_AR ? 'السياق' : 'Context'}</th>`;
    html += '</tr></thead><tbody>';
    
    const colSpan = showEmployee ? 6 : 5;
    
    for (const item of items) {
        const rowId = item.id;
        
        // Main row
        html += `<tr class="ois-row" id="row-${rowId}" onclick="toggleExpandRow(${rowId})">`;
        html += `<td><span class="ois-expand-icon">▶</span></td>`;
        html += `<td class="ois-time">${formatTime(item.time)}</td>`;
        if (showEmployee) html += `<td>${renderUserLink(item.employee_id, item.employee_name)}</td>`;
        html += `<td>${renderActionBadge(item.action)}</td>`;
        html += `<td class="ois-summary">${escHtml(item.summary || '')}</td>`;
        html += `<td>${renderContext(item.context)}</td>`;
        html += '</tr>';
        
        // Expandable detail row
        html += `<tr class="ois-expand-row" id="expand-${rowId}">`;
        html += `<td colspan="${colSpan}">${renderStructuredPanel(item)}</td>`;
        html += '</tr>';
    }
    html += '</tbody></table>';
    return html;
}

// ─── Security Table ────────────────────────────────────
function renderSecurityTable(data) {
    const stats = data.stats || {};
    let html = '';

    // ── Security Overview Stats ──
    html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">';
    
    html += `<div style="background:var(--ois-surface);border:1px solid var(--ois-border);border-radius:10px;padding:14px 16px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:var(--ois-danger);">${stats.total_failed || 0}</div>
        <div style="font-size:0.75rem;color:var(--ois-text-dim);margin-top:2px;">${IS_AR ? 'محاولات فاشلة' : 'Failed Attempts'}</div>
    </div>`;
    
    html += `<div style="background:var(--ois-surface);border:1px solid var(--ois-border);border-radius:10px;padding:14px 16px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:var(--ois-warning);">${stats.unique_usernames || 0}</div>
        <div style="font-size:0.75rem;color:var(--ois-text-dim);margin-top:2px;">${IS_AR ? 'مستخدمين مستهدفين' : 'Targeted Users'}</div>
    </div>`;
    
    html += `<div style="background:var(--ois-surface);border:1px solid var(--ois-border);border-radius:10px;padding:14px 16px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:var(--ois-text-bright);">${stats.unique_ips || 0}</div>
        <div style="font-size:0.75rem;color:var(--ois-text-dim);margin-top:2px;">${IS_AR ? 'عناوين IP فريدة' : 'Unique IPs'}</div>
    </div>`;
    
    html += `<div style="background:var(--ois-surface);border:1px solid ${stats.blocked_count > 0 ? 'var(--ois-danger)' : 'var(--ois-border)'};border-radius:10px;padding:14px 16px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:${stats.blocked_count > 0 ? 'var(--ois-danger)' : 'var(--ois-success)'};">${stats.blocked_count || 0}</div>
        <div style="font-size:0.75rem;color:var(--ois-text-dim);margin-top:2px;">${IS_AR ? 'IP محظور حالياً' : 'Currently Blocked'}</div>
    </div>`;
    
    html += '</div>';

    // ── Blocking Policy Info ──
    html += `<div style="background:rgba(183,148,88,0.08);border:1px solid rgba(183,148,88,0.25);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.78rem;color:var(--ois-text-dim);display:flex;align-items:center;gap:8px;">
        <span style="font-size:1.1rem;">🛡️</span>
        <span>${IS_AR 
            ? 'سياسة الحماية: يتم حظر عنوان IP تلقائياً بعد <strong>' + (stats.max_ip_attempts || 5) + '</strong> محاولات فاشلة لمدة <strong>' + (stats.block_duration_min || 15) + '</strong> دقيقة'
            : 'Protection Policy: IP is auto-blocked after <strong>' + (stats.max_ip_attempts || 5) + '</strong> failed attempts for <strong>' + (stats.block_duration_min || 15) + '</strong> minutes'
        }</span>
    </div>`;

    // ── Currently Blocked IPs ──
    if (data.blocked_ips && data.blocked_ips.length > 0) {
        html += `<h4 style="color:var(--ois-danger);margin:16px 0 10px;font-size:0.85rem;display:flex;align-items:center;gap:6px;">
            <span>🚫</span> ${IS_AR ? 'عناوين IP محظورة حالياً' : 'Currently Blocked IPs'}
            <span style="background:var(--ois-danger);color:#fff;font-size:0.7rem;padding:1px 7px;border-radius:10px;font-weight:600;">${data.blocked_ips.length}</span>
        </h4>`;
        html += '<table class="ois-table" style="table-layout:fixed;"><thead><tr>';
        html += `<th style="width:30%;">IP</th>`;
        html += `<th style="width:25%;">${IS_AR ? 'وقت الحظر' : 'Blocked At'}</th>`;
        html += `<th style="width:25%;">${IS_AR ? 'ينتهي في' : 'Expires'}</th>`;
        html += `<th style="width:20%;">${IS_AR ? 'الوقت المتبقي' : 'Remaining'}</th>`;
        html += '</tr></thead><tbody>';
        for (const b of data.blocked_ips) {
            const remaining = b.remaining_minutes || 0;
            const urgency = remaining <= 5 ? 'var(--ois-success)' : 'var(--ois-danger)';
            html += `<tr>
                <td><code style="background:rgba(220,38,38,0.1);color:var(--ois-danger);padding:2px 8px;border-radius:4px;font-size:0.8rem;">${escHtml(b.ip_address)}</code></td>
                <td class="ois-time">${formatTime(b.blocked_at)}</td>
                <td class="ois-time">${formatTime(b.blocked_until)}</td>
                <td><span style="color:${urgency};font-weight:600;font-size:0.8rem;">⏱ ${remaining} ${IS_AR ? 'دقيقة' : 'min'}</span></td>
            </tr>`;
        }
        html += '</tbody></table>';
    }

    // ── Top Offenders (by username) ──
    const attemptsByUser = stats.attempts_by_user || {};
    const userEntries = Object.entries(attemptsByUser);
    if (userEntries.length > 0) {
        html += `<h4 style="color:var(--ois-warning);margin:20px 0 10px;font-size:0.85rem;display:flex;align-items:center;gap:6px;">
            <span>🎯</span> ${IS_AR ? 'أكثر المستخدمين المستهدفين' : 'Most Targeted Usernames'}
        </h4>`;
        html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">';
        for (const [user, cnt] of userEntries) {
            const barWidth = Math.min(100, (cnt / (stats.max_ip_attempts || 5)) * 100);
            const severity = cnt >= (stats.max_ip_attempts || 5) ? 'var(--ois-danger)' : (cnt >= 3 ? 'var(--ois-warning)' : 'var(--ois-text-dim)');
            html += `<div style="background:var(--ois-surface);border:1px solid var(--ois-border);border-radius:8px;padding:8px 12px;min-width:160px;flex:1;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <span style="font-weight:600;font-size:0.8rem;color:var(--ois-text-bright);">${escHtml(user)}</span>
                    <span style="font-weight:700;font-size:0.8rem;color:${severity};">${cnt}×</span>
                </div>
                <div style="background:var(--ois-border);height:4px;border-radius:2px;overflow:hidden;">
                    <div style="background:${severity};height:100%;width:${barWidth}%;border-radius:2px;transition:width 0.3s;"></div>
                </div>
            </div>`;
        }
        html += '</div>';
    }

    // ── Failed Login Attempts Table ──
    html += `<h4 style="color:var(--ois-warning);margin:20px 0 10px;font-size:0.85rem;display:flex;align-items:center;gap:6px;">
        <span>⚠️</span> ${IS_AR ? 'سجل المحاولات الفاشلة' : 'Failed Login Attempts Log'}
        <span style="background:rgba(217,119,6,0.15);color:var(--ois-warning);font-size:0.7rem;padding:1px 7px;border-radius:10px;font-weight:600;">${(data.failed_logins || []).length}</span>
    </h4>`;
    if (!data.failed_logins || data.failed_logins.length === 0) {
        html += `<div class="ois-empty"><div class="empty-icon">✅</div>${IS_AR ? 'لا توجد محاولات فاشلة في هذه الفترة' : 'No failed attempts in this period'}</div>`;
    } else {
        html += '<table class="ois-table" style="table-layout:fixed;"><thead><tr>';
        html += `<th style="width:22%;">${IS_AR ? 'الوقت' : 'Time'}</th>`;
        html += `<th style="width:20%;">${IS_AR ? 'المستخدم' : 'Username'}</th>`;
        html += `<th style="width:22%;">${IS_AR ? 'عنوان IP' : 'IP Address'}</th>`;
        html += `<th style="width:36%;">${IS_AR ? 'السياق' : 'Context'}</th>`;
        html += '</tr></thead><tbody>';
        for (const fl of data.failed_logins) {
            html += `<tr>
                <td class="ois-time">${formatTime(fl.time)}</td>
                <td><strong style="color:var(--ois-text-bright);">${escHtml(fl.username)}</strong></td>
                <td><code style="background:rgba(0,0,0,0.05);padding:2px 6px;border-radius:3px;font-size:0.78rem;">${escHtml(fl.ip || 'N/A')}</code></td>
                <td>${renderContext(fl.context)}</td>
            </tr>`;
        }
        html += '</tbody></table>';
    }
    return html;
}

// ─── Users Directory Renderer ──────────────────────────
function renderUsersDirectory(users) {
    if (!users || users.length === 0) {
        return `<div class="ois-empty"><div class="empty-icon">👥</div>${IS_AR ? 'لا يوجد مستخدمون' : 'No users found'}</div>`;
    }
    
    let html = '<div class="ois-users-grid">';
    for (const u of users) {
        const initials = (u.name || '??').split(' ').map(w => w[0] || '').join('').substring(0, 2).toUpperCase();
        const roleBadgeClass = u.role === 'admin' ? 'admin' : (u.role === 'manager' ? 'manager' : 'employee');
        const roleLabel = u.role;
        const lastAct = u.last_activity ? formatTime(u.last_activity) : (IS_AR ? 'لا يوجد' : 'None');
        
        html += `<a href="user_activity_analysis.php?id=${u.id}" class="ois-user-card">`;
        html += `<div class="ois-user-avatar">${initials}`;
        if (u.is_online) html += '<span class="online-dot"></span>';
        html += '</div>';
        html += '<div class="ois-user-card-info">';
        html += `<div class="ois-user-card-name">${escHtml(u.name)}</div>`;
        html += `<div class="ois-user-card-meta">`;
        html += `<span class="badge ${roleBadgeClass}">${roleLabel}</span>`;
        html += `<span>${lastAct}</span>`;
        html += '</div>';
        html += '</div>';
        html += `<div class="ois-user-card-actions">${u.total_actions}</div>`;
        html += '</a>';
    }
    html += '</div>';
    return html;
}

// ─── Load Data ─────────────────────────────────────────

async function loadOverview() {
    try {
        const data = await fetchSection('overview');
        document.getElementById('statOnline').textContent = data.online_users ? data.online_users.length : 0;
        document.getElementById('statTotal').textContent = data.total_actions || 0;
        document.getElementById('statViews').textContent = data.action_counts?.property_visit || 0;
        document.getElementById('statAdded').textContent = data.action_counts?.add_property || 0;
        document.getElementById('statEdited').textContent = data.action_counts?.edit_property || 0;
        document.getElementById('statShares').textContent = (data.action_counts?.share_details || 0) + (data.action_counts?.share_location || 0);
        document.getElementById('statSecurity').textContent = data.security_alerts || 0;

        // Online users
        const row = document.getElementById('onlineUsersRow');
        if (data.online_users && data.online_users.length > 0) {
            row.style.display = 'flex';
            row.innerHTML = data.online_users.map(u =>
                `<a href="user_activity_analysis.php?id=${u.id}" class="ois-user-chip"><span class="dot"></span>${escHtml(u.name)}</a>`
            ).join('');
        } else {
            row.style.display = 'none';
        }

        // Update section counts
        document.getElementById('securityCount').textContent = data.security_alerts || 0;
        document.getElementById('viewsCount').textContent = data.action_counts?.property_visit || 0;
        document.getElementById('addedCount').textContent = data.action_counts?.add_property || 0;
        document.getElementById('modifiedCount').textContent = data.action_counts?.edit_property || 0;
        document.getElementById('sharesCount').textContent = (data.action_counts?.share_details || 0) + (data.action_counts?.share_location || 0);
        document.getElementById('downloadsCount').textContent = data.action_counts?.download_media || 0;
        document.getElementById('uploadsCount').textContent = (data.action_counts?.upload_media || 0) + (data.action_counts?.upload_attachment || 0);
        document.getElementById('empCount').textContent = (data.action_counts?.add_employee || 0) + (data.action_counts?.edit_employee || 0) + (data.action_counts?.delete_employee || 0);
        document.getElementById('timelineCount').textContent = data.total_actions || 0;
    } catch(e) {
        console.error('Failed loading overview:', e);
    }
}

async function loadUsersDirectory() {
    try {
        const data = await fetchSection('users_directory');
        const target = document.getElementById('usersDirectoryContent');
        target.innerHTML = renderUsersDirectory(data);
        document.getElementById('usersDirectoryCount').textContent = data.length || 0;
        document.getElementById('usersDirectorySection').dataset.loaded = 'true';
    } catch(e) {
        document.getElementById('usersDirectoryContent').innerHTML = `<div class="ois-empty">${IS_AR ? 'خطأ' : 'Error loading data'}</div>`;
        console.error('Users directory load error:', e);
    }
}

async function loadSection(sectionId) {
    const sectionMap = {
        securitySection: { api: 'security', target: 'securityContent', renderer: renderSecurityTable },
        viewsSection: { api: 'property_views', target: 'viewsContent' },
        addedSection: { api: 'properties_added', target: 'addedContent' },
        modifiedSection: { api: 'properties_modified', target: 'modifiedContent' },
        sharesSection: { api: 'share_actions', target: 'sharesContent' },
        downloadsSection: { api: 'media_downloads', target: 'downloadsContent' },
        uploadsSection: { api: 'media_uploads', target: 'uploadsContent' },
        empSection: { api: 'employee_actions', target: 'empContent' },
        timelineSection: { api: 'all_activity', target: 'timelineContent' },
        usersDirectorySection: { api: 'users_directory', target: 'usersDirectoryContent', renderer: renderUsersDirectory, isArray: true }
    };

    const cfg = sectionMap[sectionId];
    if (!cfg) return;

    const target = document.getElementById(cfg.target);
    target.innerHTML = '<div class="ois-loading"><div class="ois-spinner"></div></div>';

    try {
        const data = await fetchSection(cfg.api);
        if (cfg.renderer) {
            target.innerHTML = cfg.renderer(cfg.isArray ? data : data);
        } else {
            target.innerHTML = renderTable(data.items);
        }
        document.getElementById(sectionId).dataset.loaded = 'true';
    } catch(e) {
        target.innerHTML = `<div class="ois-empty">${IS_AR ? 'خطأ في تحميل البيانات' : 'Error loading data'}</div>`;
        console.error('Section load error:', e);
    }
}

// ─── Init ─────────────────────────────────────────────

function refreshAll() {
    document.querySelectorAll('.ois-section').forEach(s => { s.dataset.loaded = 'false'; });
    loadOverview();
    loadUsersDirectory();
    // Load security section since it's expanded by default
    loadSection('securitySection');
    document.getElementById('securitySection').dataset.loaded = 'true';
}

document.getElementById('dateFrom').addEventListener('change', refreshAll);
document.getElementById('dateTo').addEventListener('change', refreshAll);
document.getElementById('employeeFilter').addEventListener('change', refreshAll);

// Initial load
refreshAll();

// Auto-refresh every 60 seconds
setInterval(refreshAll, 60000);
</script>

</body>
</html>
