<?php
require_once __DIR__ . '/lang/translate.php'; // Include translation handler

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, send to login. If logged in, redirect to the dashboard page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
} else {
    // Redirect logged-in users to the main dashboard
    header("Location: dashboard.php");
    exit;
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


