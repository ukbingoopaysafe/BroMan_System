<?php
require_once __DIR__ . 
'/lang/translate.php'; // Include translation handler

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php'; // Include database connection using absolute path

$is_admin = (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"); // Assuming role check is still relevant

$conn->close(); // Close connection after fetching data

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
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; // Include the navigation bar using absolute path?>

    <div class="container">
        <div class="page-header">
            <h1><?php echo t('welcome_dashboard'); ?></h1>
        </div>
        <p><?php echo t('dashboard_content'); ?></p>
    </div>

<?php require_once __DIR__ . '/footer.php'; // Include the footer using absolute path?>

</body>
</html>


