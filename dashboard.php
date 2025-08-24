<?php
require_once __DIR__ . 
'/lang/translate.php'; // Include translation handler
require_once 'session_check.php'; // Include session timeout check
require_once 'permissions.php'; // Include permissions system

require_once 'db.php'; // Include database connection

// Fetch statistics for the dashboard
$stats = [
    'primary_count' => 0,
    'resale_count' => 0,
    'total_employees' => 0,
];

// Get Primary and Resale property counts
$sql_properties = "SELECT
    SUM(CASE WHEN listing_type = 'Primary' THEN 1 ELSE 0 END) AS primary_count,
    SUM(CASE WHEN listing_type = 'Resale' THEN 1 ELSE 0 END) AS resale_count
FROM properties";
if ($result = $conn->query($sql_properties)) {
    $row = $result->fetch_assoc();
    $stats['primary_count'] = $row['primary_count'] ?? 0;
    $stats['resale_count'] = $row['resale_count'] ?? 0;
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

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('dashboard_title'); ?></title>
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
            <div class="stat-card">
                <a href="set_mode.php?mode=Primary">
                    <h3><?php echo t('primary_properties'); ?></h3>
                    <p><?php echo $stats['primary_count']; ?></p>
                </a>
            </div>
            <div class="stat-card">
                <a href="set_mode.php?mode=Resale">
                    <h3><?php echo t('resale_properties'); ?></h3>
                    <p><?php echo $stats['resale_count']; ?></p>
                </a>
            </div>
            <?php if (can_manage_employees()): ?>
            <div class="stat-card">
                 <a href="employees_list.php">
                    <h3><?php echo t('total_employees'); ?></h3>
                    <p><?php echo $stats['total_employees']; ?></p>
                 </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions - Removed "add_property.php" link as requested -->
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


