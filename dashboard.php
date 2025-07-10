<?php
require_once __DIR__ . '/lang/translate.php'; // Include translation handler

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db.php'; // Include database connection

// Fetch statistics for the dashboard
$stats = [
    'total_properties' => 0,
    'total_clients' => 0,
    'total_employees' => 0,
    'residential_count' => 0,
    'coastal_count' => 0,
    'commercial_admin_medical_hotel_count' => 0,
];

// Get total properties and counts by type
$sql_properties = "SELECT 
                    COUNT(PropertyID) AS total_count,
                    SUM(CASE WHEN PropertyTypeID = 1 THEN 1 ELSE 0 END) AS residential_count,
                    SUM(CASE WHEN PropertyTypeID = 2 THEN 1 ELSE 0 END) AS coastal_count,
                    SUM(CASE WHEN PropertyTypeID IN (3, 4, 5, 6) THEN 1 ELSE 0 END) AS commercial_admin_medical_hotel_count
                  FROM properties";
if ($result = $conn->query($sql_properties)) {
    $row = $result->fetch_assoc();
    $stats['total_properties'] = $row['total_count'] ?? 0;
    $stats['residential_count'] = $row['residential_count'] ?? 0;
    $stats['coastal_count'] = $row['coastal_count'] ?? 0;
    $stats['commercial_admin_medical_hotel_count'] = $row['commercial_admin_medical_hotel_count'] ?? 0;
    $result->free();
} else {
    error_log("Error fetching property counts: " . $conn->error);
}

// Get total clients
$sql_clients = "SELECT COUNT(ClientID) AS count FROM clients";
if ($result = $conn->query($sql_clients)) {
    $row = $result->fetch_assoc();
    $stats['total_clients'] = $row['count'] ?? 0;
    $result->free();
} else {
    error_log("Error fetching client count: " . $conn->error);
}

// Get total employees (if admin)
if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin') {
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
</head>
<body>

<?php require_once 'navbar.php'; // Include the navigation bar?>

    <div class="container">
        <div class="page-header">
            <h1><?php echo sprintf(t('welcome_message'), htmlspecialchars($_SESSION["full_name"])); ?></h1>
            <!-- Removed the text "هذا هو مركزك الرئيسي لإدارة عمليات برومان العقارية" as requested -->
        </div>

        <!-- General Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                 <a href="index_properties.php"> <!-- Fixed link to properties page -->
                    <h3><?php echo t('total_properties'); ?></h3>
                    <p><?php echo $stats['total_properties']; ?></p>
                 </a>
            </div>
            <div class="stat-card">
                 <a href="index.php"> <!-- Link to clients list -->
                    <h3><?php echo t('total_clients'); ?></h3>
                    <p><?php echo $stats['total_clients']; ?></p>
                 </a>
            </div>
            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin'): ?>
            <div class="stat-card">
                 <a href="employees_list.php"> <!-- Link to employees list -->
                    <h3><?php echo t('total_employees'); ?></h3>
                    <p><?php echo $stats['total_employees']; ?></p>
                 </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Property Stats by Type -->
        <h2 style="text-align:center; margin-top: 40px; margin-bottom: 20px;"><?php echo t('property_stats_by_type'); ?></h2>
        <div class="stats-grid">
            <div class="stat-card">
                <a href="residential_properties.php">
                    <h3><?php echo t('residential_properties'); ?></h3>
                    <p><?php echo $stats['residential_count']; ?></p>
                </a>
            </div>
            <div class="stat-card">
                <a href="coastal_properties.php">
                    <h3><?php echo t('coastal_properties'); ?></h3>
                    <p><?php echo $stats['coastal_count']; ?></p>
                </a>
            </div>
            <div class="stat-card">
                <a href="commercial_properties.php">
                    <h3><?php echo t('commercial_properties'); ?></h3>
                    <p><?php echo $stats['commercial_admin_medical_hotel_count']; ?></p>
                </a>
            </div>
        </div>

        <!-- Quick Actions - Removed "add_property.php" link as requested -->
        <div class="quick-links">
            <h2><?php echo t('quick_actions'); ?></h2>
            <div>
                <a href="add_residential_property.php" class="btn btn-primary"><?php echo t('add_new_residential_property'); ?></a>
                <a href="add_coastal_property.php" class="btn btn-primary"><?php echo t('add_new_coastal_property'); ?></a>
                <a href="add_other_property.php" class="btn btn-primary"><?php echo t('add_new_commercial_property'); ?></a>
                <a href="add_client.php" class="btn btn-primary"><?php echo t('add_new_client'); ?></a>
                <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin'): ?>
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
