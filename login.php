<?php
require_once __DIR__ . '/lang/translate.php'; // Include translation handler
require_once __DIR__ . '/security.php'; // Include security helpers

// Ensure secure session is started
ensure_secure_session_started();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: dashboard.php");
    exit;
}

// Include the database connection file
require_once 'db.php';

// Include rate limiter (gracefully handle if tables don't exist yet)
$rate_limiting_enabled = false;
try {
    require_once __DIR__ . '/rate_limiter.php';
    // Check if required tables exist
    $table_check = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    $rate_limiting_enabled = $table_check && $table_check->num_rows > 0;
} catch (Exception $e) {
    error_log("Rate limiter not available: " . $e->getMessage());
}

$error_message = '';
$warning_message = '';
$username_value = ''; // To retain username on error

// Check for timeout messages
if (isset($_GET['timeout'])) {
    if ($_GET['timeout'] === 'idle') {
        $error_message = t('session_timeout_idle');
    } elseif ($_GET['timeout'] === 'absolute') {
        $error_message = t('session_timeout_absolute');
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error_message = 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.';
    } else {
        $username_value = isset($_POST['username']) ? trim($_POST['username']) : '';

        if (empty(trim($_POST["username"]))) {
            $error_message = t('username_required');
        } else {
            $username = trim($_POST["username"]);
        }

        if (empty(trim($_POST["password"]))) {
            $error_message = t('password_required');
        } else {
            $password = $_POST["password"];
        }

        // Check rate limiting before processing login
        if (empty($error_message) && $rate_limiting_enabled) {
            $rate_check = check_rate_limit($conn, $username ?? null);
            if (!$rate_check['allowed']) {
                $error_message = $rate_check['message'];
            }
        }

        if (empty($error_message)) {
            $sql = "SELECT EmployeeID, Username, Password, Role, FullName FROM employees WHERE Username = ?";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_username);
                $param_username = $username;
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($employee_id, $db_username, $hashed_password, $role, $full_name);
                        if ($stmt->fetch()) {
                            if (password_verify($password, $hashed_password)) {
                                // Successful login
                                session_regenerate_id(true);
                                
                                $_SESSION["loggedin"] = true;
                                $_SESSION["employee_id"] = $employee_id;
                                $_SESSION["username"] = $db_username;
                                $_SESSION["role"] = $role;
                                $_SESSION["full_name"] = $full_name;
                                $_SESSION["created_at"] = time();
                                $_SESSION["last_activity"] = time();
                                
                                // Record successful login for rate limiting
                                if ($rate_limiting_enabled) {
                                    handle_successful_login($conn, $db_username);
                                }

                                // Log login activity (non-blocking)
                                try {
                                    require_once __DIR__ . '/activity_log.php';
                                    if (function_exists('log_activity')) {
                                        log_activity($conn, $employee_id, 'login', json_encode(['username' => $db_username]));
                                    }
                                } catch (Exception $e) {
                                    error_log('Failed to record login activity: ' . $e->getMessage());
                                }

                                // Update active session record
                                try {
                                    require_once __DIR__ . '/session_activity.php';
                                    update_session_activity($conn);
                                } catch (Exception $e) {
                                    error_log('Failed to update session activity: ' . $e->getMessage());
                                }

                                header("Location: dashboard.php");
                                exit();
                            } else {
                                // Wrong password - record failed attempt
                                if ($rate_limiting_enabled) {
                                    $fail_result = handle_failed_login($conn, $username);
                                    if ($fail_result['attempts_remaining'] > 0 && $fail_result['attempts_remaining'] <= 3) {
                                        $warning_message = "تنبيه: متبقي {$fail_result['attempts_remaining']} محاولات قبل الحظر المؤقت.";
                                    }
                                }
                                // Use generic error message for security (don't reveal if user exists)
                                $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
                            }
                        }
                    } else {
                        // User not found - record failed attempt
                        if ($rate_limiting_enabled) {
                            $fail_result = handle_failed_login($conn, $username);
                            if ($fail_result['attempts_remaining'] > 0 && $fail_result['attempts_remaining'] <= 3) {
                                $warning_message = "تنبيه: متبقي {$fail_result['attempts_remaining']} محاولات قبل الحظر المؤقت.";
                            }
                        }
                        // Use generic error message for security
                        $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
                    }
                } else {
                    error_log("Error executing login statement: " . $stmt->error);
                    $error_message = t('login_error');
                }
                $stmt->close();
            } else {
                error_log("Error preparing login statement: " . $conn->error);
                $error_message = t('login_error');
            }
        }
    }
}

// Generate CSRF token for form
$csrf_token = generateCSRFToken();
?>


<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('login_page_title'); ?></title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="images/Logo%20Broman.svg">
    <link rel="stylesheet" href="modern_styles.css"> 
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
    <style>
        body {
            background-color: #f4f7f6; /* Light background for the whole page */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            width: 100%;
            max-width: 420px; /* Slightly wider */
            padding: 20px;
        }
        .login-card {
            background-color: #ffffff;
            padding: 40px 30px; /* More padding */
            border-radius: var(--border-radius, 8px);
            box-shadow: var(--box-shadow, 0 4px 15px rgba(0, 0, 0, 0.1));
            border: 1px solid #e0e0e0;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            max-width: 150px; /* Adjust as needed */
            margin-bottom: 15px;
        }
        .login-header h2 {
            font-size: 1.8rem; /* Larger title */
            color: var(--primary-color, #0056b3);
            margin: 0;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px; /* Consistent spacing */
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        .form-input {
            width: 100%;
            padding: 12px 15px; /* Comfortable padding */
            border: 1px solid #ced4da;
            border-radius: var(--border-radius, 4px);
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            box-sizing: border-box;
        }
        .form-input:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            width: 100%;
            padding: 12px 15px;
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 10px; /* Space above button */
        }
        .message-error {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: var(--border-radius, 4px);
        }
        .message-warning {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: var(--border-radius, 4px);
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        .language-switcher {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
        }
        .language-switcher a {
            color: var(--primary-color, #0056b3);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .language-switcher a:hover {
            color: var(--dark-color, #333);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <!-- Optional: Add logo here -->
                <!-- <img src="path/to/your/logo.png" alt="<?php echo t('broman_real_estate_logo'); ?>"> -->
                <h2><?php echo t('login_page_title'); ?></h2> <!-- Changed from employee_login -->
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="message message-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($warning_message)): ?>
                <div class="message-warning"><?= htmlspecialchars($warning_message) ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="username"><?php echo t('username'); ?>:</label>
                    <input type="text" name="username" id="username" class="form-input" required value="<?php echo htmlspecialchars($username_value); ?>">
                </div>

                <div class="form-group">
                    <label for="password"><?php echo t('password'); ?>:</label>
                    <input type="password" name="password" id="password" class="form-input" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><?php echo t('login'); ?></button>
                </div>
            </form>
            
            <!-- Language Switcher -->
            <div class="language-switcher">
                <?php
                $current_lang_login = get_lang_code();
$switch_lang_login = ($current_lang_login == 'ar') ? 'en' : 'ar';
$switch_lang_text_login = ($current_lang_login == 'ar') ? t('switch_language_en') : t('switch_language_ar');
$switch_lang_url_login = basename($_SERVER['PHP_SELF']) . '?lang=' . $switch_lang_login;
?>
                <a href="<?php echo htmlspecialchars($switch_lang_url_login); ?>"><?php echo $switch_lang_text_login; ?></a>
            </div>
        </div>
    </div>
</body>
</html>

