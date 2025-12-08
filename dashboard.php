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
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
             transform: translateY(-5px);
             box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        .stat-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1em;
            color: #555;
        }
        .stat-card p {
            font-size: 2em;
            font-weight: bold;
            margin: 0;
            color: var(--primary-color);
        }
        .stat-card a {
            text-decoration: none;
            color: inherit; /* Inherit color from parent */
            display: block; /* Make the whole card clickable */
        }
        .quick-links {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
        }
        .quick-links h2 {
            margin-bottom: 20px;
        }
        .quick-links .btn {
            margin: 5px;
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
                <a href="add_residential_property.php" class="btn btn-primary"><?php echo t('add_new_residential_property'); ?></a>
                <a href="add_coastal_property.php" class="btn btn-primary"><?php echo t('add_new_coastal_property'); ?></a>
                <a href="add_other_property.php" class="btn btn-primary"><?php echo t('add_new_commercial_property'); ?></a>
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


