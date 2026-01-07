<?php
require_once __DIR__ . '/lang/translate.php'; // Include translation handler
require_once 'session_check.php'; // Include session timeout check
require_once 'permissions.php'; // Include permissions system

require_once 'db.php'; // Include database connection

$allowed_modes = get_allowed_modes_for_role(get_user_role());
$current_listing_mode = $_SESSION['listing_context'] ?? null;

// Fetch statistics for the dashboard
$stats = [
    'primary_count' => 0,
    'resale_count' => 0,
    'rental_count' => 0,
    'total_employees' => 0,
    'duplicates_needs_review' => 0,
];

// Get Primary and Resale property counts
$sql_properties = "SELECT
    SUM(CASE WHEN listing_type = 'Primary' THEN 1 ELSE 0 END) AS primary_count,
    SUM(CASE WHEN listing_type = 'Resale' THEN 1 ELSE 0 END) AS resale_count,
    SUM(CASE WHEN listing_type = 'Rental' THEN 1 ELSE 0 END) AS rental_count
FROM properties";
if ($result = $conn->query($sql_properties)) {
    $row = $result->fetch_assoc();
    $stats['primary_count'] = $row['primary_count'] ?? 0;
    $stats['resale_count'] = $row['resale_count'] ?? 0;
    $stats['rental_count'] = $row['rental_count'] ?? 0;
    $result->free();
} else {
    error_log("Error fetching property counts: " . $conn->error);
}

// Get total employees (if manager)
if (can_manage_employees()) {
    $sql_employees = "SELECT COUNT(EmployeeID) AS count FROM employees";
    if ($result = $conn->query($sql_employees)) {
        $row = $result->fetch_assoc();
        $stats['total_employees'] = $row['count'] ?? 0;
        $result->free();
    } else {
        error_log("Error fetching employee count: " . $conn->error);
    }
}

// Get duplicate groups that need review (filtered by the selected mode only)
if (!empty($current_listing_mode)) {
    $sql_duplicates = "SELECT COUNT(*) AS count
                       FROM (
                           SELECT g.normalized_phone
                           FROM v_duplicate_property_phone_groups g
                           JOIN v_property_phones_normalized v ON v.normalized_phone = g.normalized_phone
                           JOIN properties p ON p.PropertyID = v.PropertyID
                           WHERE p.listing_type = ?
                           GROUP BY g.normalized_phone, g.phone_roles
                           HAVING SUM(CASE WHEN p.duplicate_status = 1 THEN 1 ELSE 0 END) > 0
                       ) AS needs_review_groups";
    if ($stmt_dup = $conn->prepare($sql_duplicates)) {
        $stmt_dup->bind_param('s', $current_listing_mode);
        if ($stmt_dup->execute()) {
            $res_dup = $stmt_dup->get_result();
            $row = $res_dup->fetch_assoc();
            $stats['duplicates_needs_review'] = (int)($row['count'] ?? 0);
        } else {
            error_log("Error executing duplicates count: " . $stmt_dup->error);
        }
        $stmt_dup->close();
    } else {
        error_log("Error preparing duplicates count: " . $conn->error);
    }
} else {
    // No mode selected yet: hide/skip duplicates count
    $stats['duplicates_needs_review'] = 0;
}

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('dashboard_title'); ?></title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="images/Logo%20Broman.svg">
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="css/premium_design.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <style>
        /* Dashboard Premium Overrides */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--space-lg, 24px);
            margin-bottom: var(--space-xl, 40px);
        }
        
        .stat-card {
            background: var(--glass-bg-strong, rgba(255, 255, 255, 0.95));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: var(--space-xl, 32px) var(--space-lg, 24px);
            border-radius: var(--radius-lg, 16px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.06));
            border: 1px solid var(--glass-border, rgba(0, 0, 0, 0.08));
            text-align: center;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--premium-gold, #b08a0e) 0%, #8a6b08 100%);
            transform: scaleX(0);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg, 0 8px 32px rgba(0, 0, 0, 0.12));
            border-color: rgba(176, 138, 14, 0.2);
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card h3 {
            margin-top: 0;
            margin-bottom: var(--space-sm, 8px);
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted, #7f8c8d);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card p {
            font-size: 2.75rem;
            font-weight: 700;
            margin: 0;
            color: var(--premium-navy, #020d24);
            transition: color 0.3s ease;
        }
        
        .stat-card:hover p {
            color: var(--premium-gold, #b08a0e);
        }
        
        .stat-card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        /* Quick Actions Section */
        .quick-links {
            margin-top: var(--space-2xl, 48px);
            padding-top: var(--space-xl, 32px);
            border-top: 2px solid var(--bg-tertiary, #f3f4f6);
            text-align: center;
        }
        
        .quick-links h2 {
            margin-bottom: var(--space-lg, 24px);
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--premium-navy, #020d24);
            position: relative;
            display: inline-block;
        }
        
        .quick-links h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: var(--premium-gold, #b08a0e);
            border-radius: 2px;
        }
        
        .quick-links .btn {
            margin: 6px;
        }
        
        /* Premium Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--premium-gold, #b08a0e) 0%, #8a6b08 100%);
            border: none;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm, 8px);
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(176, 138, 14, 0.25);
            cursor: pointer;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(176, 138, 14, 0.35);
        }
        
        /* Page Header */
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--premium-navy, #020d24);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header h1::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 1.3em;
            background: linear-gradient(180deg, var(--premium-gold, #b08a0e) 0%, #8a6b08 100%);
            border-radius: 2px;
        }
        
        /* Section Title */
        h2[style*="text-align:center"] {
            font-size: 1.15rem;
            font-weight: 500;
            color: var(--text-secondary, #495057);
        }
        
        /* Container Premium Override */
        .container {
            background: var(--glass-bg-strong, rgba(255, 255, 255, 0.95));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: var(--radius-xl, 20px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0, 0, 0, 0.08));
            border: 1px solid var(--glass-border, rgba(0, 0, 0, 0.08));
        }
        
        /* Duplicates Card Special Style */
        .stat-card[style*="border-left"] {
            border-left: 4px solid var(--premium-gold, #b08a0e) !important;
        }
        
        .stat-card[style*="border-left"] p {
            color: var(--premium-gold, #b08a0e) !important;
        }
    </style>
    <script src="session_timeout.js"></script>
</head>
<body>

<?php require_once 'navbar.php'; // Include the navigation bar?>

    <div class="container">
        <div class="page-header">
            <h1><?php echo sprintf(t('welcome_message'), htmlspecialchars($_SESSION["full_name"])); ?></h1>
            <!-- Removed the text "هذا هو مركزك الرئيسي لإدارة عمليات برومان العقارية" as requested -->
        </div>

        <!-- Operating Mode Selection -->
        <h2 style="text-align:center; margin-top: 40px; margin-bottom: 20px;"><?php echo t('choose_operating_mode'); ?></h2>
        <div class="stats-grid">
            <?php if (in_array('Primary', $allowed_modes, true)): ?>
                <div class="stat-card">
                    <a href="set_mode.php?mode=Primary">
                        <h3><?php echo t('primary_properties'); ?></h3>
                        <p><?php echo $stats['primary_count']; ?></p>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (in_array('Resale', $allowed_modes, true)): ?>
                <div class="stat-card">
                    <a href="set_mode.php?mode=Resale">
                        <h3><?php echo t('resale_properties'); ?></h3>
                        <p><?php echo $stats['resale_count']; ?></p>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (in_array('Rental', $allowed_modes, true)): ?>
                <div class="stat-card">
                    <a href="set_mode.php?mode=Rental">
                        <h3><?php echo t('rental_properties'); ?></h3>
                        <p><?php echo $stats['rental_count']; ?></p>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (can_manage_employees()): ?>
            <div class="stat-card">
                 <a href="employees_list.php">
                    <h3><?php echo t('total_employees'); ?></h3>
                    <p><?php echo $stats['total_employees']; ?></p>
                 </a>
            </div>
            <?php endif; ?>
            <?php if (can_manage_properties() && !empty($current_listing_mode)): ?>
            <div class="stat-card" style="border-left: 4px solid #ffc107;">
                 <a href="admin_duplicate_properties.php?status_filter=needs_review">
                    <h3><?php echo t('duplicates_needs_review'); ?></h3>
                    <p style="color: #ffc107;"><?php echo $stats['duplicates_needs_review']; ?></p>
                 </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions - Removed "add_property.php" link as requested -->
        <?php if (can_manage_properties()): ?>
        <div class="quick-links">
            <h2><?php echo t('quick_actions'); ?></h2>
            <div>
                <a href="add_property.php" class="btn btn-primary"><?php echo t('add_property_title'); ?></a>
                <?php if (can_manage_employees()): ?>
                <a href="add_employee.php" class="btn btn-primary"><?php echo t('add_new_employee'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>

<?php
// Attempt to include a footer if it exists
$footer_path = __DIR__ . '/footer.php';
if (file_exists($footer_path)) {
    require_once $footer_path;
}
?>
</body>
</html>


