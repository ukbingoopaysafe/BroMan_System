<?php
require_once __DIR__ .
'/lang/translate.php'; // Include translation handler

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: dashboard.php");
    exit;
}

// Include the database connection file
require_once 'db.php';

$error_message = '';
$username_value = ''; // To retain username on error

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
                            session_regenerate_id(true);
                            
                            $_SESSION["loggedin"] = true;
                            $_SESSION["employee_id"] = $employee_id;
                            $_SESSION["username"] = $db_username;
                            $_SESSION["role"] = $role;
                            $_SESSION["full_name"] = $full_name;
                            // Language preference could be saved here if needed
                            
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            $error_message = t('invalid_password');
                        }
                    }
                } else {
                    $error_message = t('no_account_found');
                }
            } else {
                error_log("Error executing login statement: " . $stmt->error);
                $error_message = t('login_error');
            }
            $stmt->close();
        } else {
            error_log("Error preparing login statement: " . $conn->error);
            $error_message = t('login_error'); // Use generic error message
        }
    }
    // Close connection only after all operations are done
    if (isset($conn)) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('login_page_title'); ?></title>
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

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
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

