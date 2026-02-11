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
$allowed_modes = ['Primary', 'Resale']; // Default modes
$full_name_err = $username_err = $password_err = $role_err = $modes_err = $general_err = "";

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

    // Validate Allowed Modes (only for non-managers)
    if ($role !== 'manager') {
        if (empty($_POST["allowed_modes"]) || !is_array($_POST["allowed_modes"])) {
            $modes_err = "Please select at least one mode.";
            $allowed_modes = [];
        } else {
            $valid_modes = ['Primary', 'Resale', 'Rental'];
            $allowed_modes = array_intersect($_POST["allowed_modes"], $valid_modes);
            if (empty($allowed_modes)) {
                $modes_err = "Please select at least one valid mode.";
            }
        }
    } else {
        // Managers get all modes
        $allowed_modes = ['Primary', 'Resale', 'Rental'];
    }

    // Check input errors before inserting in database
    if (empty($full_name_err) && empty($username_err) && empty($password_err) && empty($role_err) && empty($modes_err) && empty($general_err)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $allowed_modes_str = implode(',', $allowed_modes);

        // Try with AllowedModes first, fallback without if column doesn't exist
        $sql = "INSERT INTO Employees (FullName, Username, Password, Role, AllowedModes) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            // Column doesn't exist, insert without it
            $sql = "INSERT INTO Employees (FullName, Username, Password, Role) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $has_allowed_modes = false;
        } else {
            $has_allowed_modes = true;
        }

        if ($stmt) {
            if ($has_allowed_modes) {
                $stmt->bind_param("sssss", $param_full_name, $param_username, $param_hashed_password, $param_role, $param_modes);
                $param_modes = $allowed_modes_str;
            } else {
                $stmt->bind_param("ssss", $param_full_name, $param_username, $param_hashed_password, $param_role);
            }
            
            $param_full_name = $full_name;
            $param_username = $username;
            $param_hashed_password = $hashed_password;
            $param_role = $role;
            
            if ($stmt->execute()) {
                // Log add_employee action for OIS monitoring
                $new_emp_id = $conn->insert_id;
                require_once __DIR__ . '/activity_log.php';
                log_activity($conn, $_SESSION['employee_id'] ?? null, 'add_employee', json_encode([
                    'new_employee_id' => $new_emp_id,
                    'name' => $full_name,
                    'username' => $username,
                    'role' => $role,
                    'allowed_modes' => $allowed_modes_str ?? ''
                ], JSON_UNESCAPED_UNICODE));
                require_once __DIR__ . '/flash.php';
                if (function_exists('add_flash')) { add_flash("Employee added successfully!", 6); }
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
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="/images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="/images/Logo%20Broman.svg">
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

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
        <?php echo csrf_input(); ?>
        <?php if (!empty($general_err)): ?>
            <div class="message message-error"><?php echo htmlspecialchars($general_err); ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h3><?php echo t('basic_info'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="fullName"><?php echo t('full_name'); ?>:</label>
                    <input type="text" id="fullName" name="fullName" class="form-input <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>" required>
                    <?php if (!empty($full_name_err)): ?><span class="error-message"><?php echo htmlspecialchars($full_name_err); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="username"><?php echo t('username'); ?>:</label>
                    <input type="text" id="username" name="username" class="form-input <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
                    <?php if (!empty($username_err)): ?><span class="error-message"><?php echo htmlspecialchars($username_err); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password"><?php echo t('password'); ?>:</label>
                    <input type="password" id="password" name="password" class="form-input <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($password_err)): ?><span class="error-message"><?php echo htmlspecialchars($password_err); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="role"><?php echo t('role'); ?>:</label>
                    <select id="role" name="role" class="form-input <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" required onchange="toggleModesSection()">
                        <option value=""><?php echo t('select_role'); ?></option>
                        <option value="user" <?php echo ($role == "user") ? "selected" : ""; ?>><?php echo t('user_role'); ?></option>
                        <option value="admin" <?php echo ($role == "admin") ? "selected" : ""; ?>><?php echo t('admin_role'); ?></option>
                        <option value="manager" <?php echo ($role == "manager") ? "selected" : ""; ?>><?php echo t('manager_role'); ?></option>
                    </select>
                    <?php if (!empty($role_err)): ?><span class="error-message"><?php echo htmlspecialchars($role_err); ?></span><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-section" id="modes-section">
            <h3><?php echo t('allowed_modes'); ?></h3>
            <p class="form-hint"><?php echo t('allowed_modes_hint'); ?></p>
            <?php if (!empty($modes_err)): ?><div class="message message-error"><?php echo htmlspecialchars($modes_err); ?></div><?php endif; ?>
            <div class="checkbox-group">
                <label class="checkbox-item">
                    <input type="checkbox" name="allowed_modes[]" value="Primary" <?php echo in_array('Primary', $allowed_modes) ? 'checked' : ''; ?>>
                    <span class="checkbox-label"><?php echo t('mode_primary'); ?></span>
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="allowed_modes[]" value="Resale" <?php echo in_array('Resale', $allowed_modes) ? 'checked' : ''; ?>>
                    <span class="checkbox-label"><?php echo t('mode_resale'); ?></span>
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="allowed_modes[]" value="Rental" <?php echo in_array('Rental', $allowed_modes) ? 'checked' : ''; ?>>
                    <span class="checkbox-label"><?php echo t('mode_rental'); ?></span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo t('add_new_employee'); ?></button>
            <a href="employees_list.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
        </div>
    </form>
</div>

<script>
function toggleModesSection() {
    const role = document.getElementById('role').value;
    const modesSection = document.getElementById('modes-section');
    if (role === 'manager') {
        modesSection.style.display = 'none';
    } else {
        modesSection.style.display = 'block';
    }
}
// Run on page load
document.addEventListener('DOMContentLoaded', toggleModesSection);
</script>

</body>
</html>
