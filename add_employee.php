<?php
require_once __DIR__ . '/lang/translate.php'; // Include translation handler
require_once 'permissions.php'; // Include permissions system
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once 'session_check.php'; // Include session timeout check

// Session already ensured securely

// Check permissions - only manager can manage employees
check_permission("manage_employees");
require_once __DIR__ . 
'/db.php'; // Database connection

$full_name = $username = $password = $role = "";
$full_name_err = $username_err = $password_err = $role_err = $general_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $general_err = t('invalid_csrf_token');
    }
    // Validate Full Name
    if (empty(trim($_POST["fullName"]))) {
        $full_name_err = "Please enter the employee's full name.";
    } else {
        $full_name = trim($_POST["fullName"]);
    }

    // Validate Username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } else {
        // Check if username already exists
        $sql_check_username = "SELECT EmployeeID FROM Employees WHERE Username = ?";
        if ($stmt_check = $conn->prepare($sql_check_username)) {
            $stmt_check->bind_param("s", $param_username_check);
            $param_username_check = trim($_POST["username"]);
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $username_err = "This username is already taken.";
                }
                $username = trim($_POST["username"]);
            } else {
                $general_err = "Error checking username. Please try again.";
                error_log("Error executing username check: " . $stmt_check->error);
            }
            $stmt_check->close();
        } else {
            $general_err = "Database error (username check). Please try again.";
            error_log("Error preparing username check: " . $conn->error);
        }
    }

    // Validate Password
    if (empty($_POST["password"])) {
        $password_err = "Please enter a password.";
    } elseif (strlen($_POST["password"]) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = $_POST["password"];
    }

    // Validate Role
    if (empty($_POST["role"])) {
        $role_err = "Please select a role.";
    } elseif (!in_array($_POST["role"], ["admin", "user", "manager"])) {
        $role_err = "Invalid role selected.";
    } else {
        $role = $_POST["role"];
    }

    // Check input errors before inserting in database
    if (empty($full_name_err) && empty($username_err) && empty($password_err) && empty($role_err) && empty($general_err)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO Employees (FullName, Username, Password, Role) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $param_full_name, $param_username, $param_hashed_password, $param_role);
            
            $param_full_name = $full_name;
            $param_username = $username;
            $param_hashed_password = $hashed_password;
            $param_role = $role;
            
            if ($stmt->execute()) {
                $_SESSION["success_message"] = "Employee added successfully!";
                header("Location: employees_list.php"); // Redirect to employees list or dashboard
                exit();
            } else {
                $general_err = "Something went wrong. Please try again later.";
                error_log("Error executing employee insert: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('add_new_employee'); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
    <script src="session_timeout.js"></script>
</head>
<body>

<?php require_once 'navbar.php'; // Include the navigation bar?>

<div class="container">
    <div class="page-header">
        <h1><?php echo t('add_new_employee'); ?></h1>
    </div>

    <div class="form-container">
        <div class="form-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <?php echo csrf_input(); ?>
                <?php if (!empty($general_err)): ?>
                    <div class="message message-error"><?php echo htmlspecialchars($general_err); ?></div>
                <?php endif; ?>

                <div>
                    <label for="fullName"><?php echo t('full_name'); ?>:</label>
                    <input type="text" id="fullName" name="fullName" class="form-input <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>" required>
                    <?php if (!empty($full_name_err)): ?><span class="error-message"><?php echo htmlspecialchars($full_name_err); ?></span><?php endif; ?>
                </div>

                <div>
                    <label for="username"><?php echo t('username'); ?>:</label>
                    <input type="text" id="username" name="username" class="form-input <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
                    <?php if (!empty($username_err)): ?><span class="error-message"><?php echo htmlspecialchars($username_err); ?></span><?php endif; ?>
                </div>

                <div>
                    <label for="password"><?php echo t('password'); ?>:</label>
                    <input type="password" id="password" name="password" class="form-input <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($password_err)): ?><span class="error-message"><?php echo htmlspecialchars($password_err); ?></span><?php endif; ?>
                </div>

                <div>
                    <label for="role"><?php echo t('role'); ?>:</label>
                    <select id="role" name="role" class="form-input <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" required>
                        <option value=""><?php echo t('select_role'); ?></option>
                        <option value="user" <?php echo ($role == "user") ? "selected" : ""; ?>><?php echo t('user_role'); ?></option>
                        <option value="admin" <?php echo ($role == "admin") ? "selected" : ""; ?>><?php echo t('admin_role'); ?></option>
                        <option value="manager" <?php echo ($role == "manager") ? "selected" : ""; ?>><?php echo t('manager_role'); ?></option>
                    </select>
                    <?php if (!empty($role_err)): ?><span class="error-message"><?php echo htmlspecialchars($role_err); ?></span><?php endif; ?>
                </div>

                <div class="form-actions">
                    <input type="submit" value="<?php echo t('add_new_employee'); ?>" class="btn btn-success">
                    <a href="employees_list.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
