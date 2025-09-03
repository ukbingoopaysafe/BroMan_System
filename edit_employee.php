<?php
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once 'session_check.php'; // Include session timeout check
require_once 'permissions.php'; // Include permissions system

// Check permissions - only manager can manage employees
check_permission('manage_employees');

require_once 'db.php'; // Database connection
require_once 'navbar.php'; // Navigation bar

// Initialize variables
$employee_id = $full_name = $username = $role = "";
$full_name_err = $username_err = $password_err = $role_err = $general_err = "";
$is_editing_self = false;

// Check if ID is provided in URL
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $employee_id = trim($_GET["id"]);
    if ($_SESSION["employee_id"] == $employee_id) {
        $is_editing_self = true;
    }

    // Prepare a select statement to fetch employee details
    $sql_fetch = "SELECT FullName, Username, Role FROM Employees WHERE EmployeeID = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $param_employee_id);
        $param_employee_id = $employee_id;

        if ($stmt_fetch->execute()) {
            $stmt_fetch->store_result();
            if ($stmt_fetch->num_rows == 1) {
                $stmt_fetch->bind_result($full_name, $username, $role);
                $stmt_fetch->fetch();
            } else {
                $_SESSION["error_message"] = "Employee not found.";
                header("Location: employees_list.php");
                exit();
            }
        } else {
            $general_err = "Error fetching employee details.";
            error_log("Error executing employee fetch: " . $stmt_fetch->error);
        }
        $stmt_fetch->close();
    } else {
        $general_err = "Database error (employee fetch). Please try again.";
        error_log("Error preparing employee fetch: " . $conn->error);
    }
} else {
    $_SESSION["error_message"] = "No employee ID specified for editing.";
    header("Location: employees_list.php");
    exit();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $general_err = t('invalid_csrf_token');
    }
    $employee_id_post = trim($_POST["employee_id"]);
    if (empty($employee_id_post) || $employee_id_post != $employee_id) {
        $general_err = "Invalid employee ID submitted.";
    }

    // Validate Full Name
    if (empty(trim($_POST["fullName"]))) {
        $full_name_err = "Please enter the employee's full name.";
    } else {
        $full_name_posted = trim($_POST["fullName"]);
    }

    // Validate Username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } else {
        $sql_check_username = "SELECT EmployeeID FROM Employees WHERE Username = ? AND EmployeeID != ?";
        if ($stmt_check = $conn->prepare($sql_check_username)) {
            $stmt_check->bind_param("si", $param_username_check, $param_employee_id_check);
            $param_username_check = trim($_POST["username"]);
            $param_employee_id_check = $employee_id;
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $username_err = "This username is already taken.";
                }
                $username_posted = trim($_POST["username"]);
            } else {
                $general_err = "Error checking username. Please try again.";
                error_log("Error executing username check (edit): " . $stmt_check->error);
            }
            $stmt_check->close();
        } else {
            $general_err = "Database error (username check). Please try again.";
            error_log("Error preparing username check (edit): " . $conn->error);
        }
    }

    // Validate Role
    // Prevent admin from demoting themselves if they are the only admin (basic check)
    if ($is_editing_self && $role === 'admin' && $_POST["role"] === 'user') {
        $sql_count_admins = "SELECT COUNT(EmployeeID) as admin_count FROM Employees WHERE Role = 'admin'";
        $admin_count_result = $conn->query($sql_count_admins);
        $admin_count_row = $admin_count_result->fetch_assoc();
        if ($admin_count_row['admin_count'] <= 1) {
            $role_err = "Cannot demote the only administrator.";
        }
    }
    if (empty($role_err)) { // proceed if no error from self-demotion check
        if (empty($_POST["role"])) {
            $role_err = "Please select a role.";
        } elseif (!in_array($_POST["role"], ["admin", "user", "manager"])) {
            $role_err = "Invalid role selected.";
        } else {
            $role_posted = $_POST["role"];
        }
    }

    // Validate Password (only if new password is provided)
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];
    $hashed_password_to_update = null; // Will hold the new hash if password is changed

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $password_err = "New password must have at least 6 characters.";
        } elseif ($new_password != $confirm_password) {
            $password_err = "New passwords do not match.";
        } else {
            $hashed_password_to_update = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    // Check input errors before updating in database
    if (empty($full_name_err) && empty($username_err) && empty($password_err) && empty($role_err) && empty($general_err)) {
        if ($hashed_password_to_update !== null) {
            $sql_update = "UPDATE Employees SET FullName = ?, Username = ?, Role = ?, Password = ? WHERE EmployeeID = ?";
        } else {
            $sql_update = "UPDATE Employees SET FullName = ?, Username = ?, Role = ? WHERE EmployeeID = ?";
        }

        if ($stmt_update = $conn->prepare($sql_update)) {
            if ($hashed_password_to_update !== null) {
                $stmt_update->bind_param("ssssi", $param_full_name, $param_username, $param_role, $param_password, $param_employee_id_update);
                $param_password = $hashed_password_to_update;
            } else {
                $stmt_update->bind_param("sssi", $param_full_name, $param_username, $param_role, $param_employee_id_update);
            }
            
            $param_full_name = $full_name_posted;
            $param_username = $username_posted;
            $param_role = $role_posted;
            $param_employee_id_update = $employee_id;
            
            if ($stmt_update->execute()) {
                $_SESSION["success_message"] = "Employee details updated successfully!";
                // If admin edited their own role and demoted themselves, log them out or redirect to login
                if ($is_editing_self && $role === 'admin' && $role_posted === 'user') {
                    // Destroy session and redirect to login
                    session_destroy();
                    header("Location: login.php?message=role_changed");
                    exit();
                }
                header("Location: employees_list.php");
                exit();
            } else {
                $general_err = "Something went wrong. Please try again later.";
                error_log("Error executing employee update: " . $stmt_update->error);
            }
            $stmt_update->close();
        }
    } else {
        $full_name = isset($full_name_posted) ? $full_name_posted : $full_name;
        $username = isset($username_posted) ? $username_posted : $username;
        $role = isset($role_posted) ? $role_posted : $role;
    }
}

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t("edit_employee"); ?></title>
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
        <h1><?php echo t("edit_employee"); ?></h1>
    </div>

    <div class="form-container">
        <div class="form-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($employee_id); ?>" method="POST">
                <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">
                <?php echo csrf_input(); ?>
                <?php if (!empty($general_err)): ?>
                    <div class="message message-error"><?php echo htmlspecialchars($general_err); ?></div>
                <?php endif; ?>

    <div>
        <label for="fullName"><?php echo t("full_name"); ?>:</label>
        <input type="text" id="fullName" name="fullName" class="form-input <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>" required>
        <?php if (!empty($full_name_err)): ?><span class="error-message"><?php echo htmlspecialchars($full_name_err); ?></span><?php endif; ?>
    </div>

    <div>
        <label for="username"><?php echo t("username"); ?>:</label>
        <input type="text" id="username" name="username" class="form-input <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
        <?php if (!empty($username_err)): ?><span class="error-message"><?php echo htmlspecialchars($username_err); ?></span><?php endif; ?>
    </div>

    <div>
        <label for="role"><?php echo t("role"); ?>:</label>
        <select id="role" name="role" class="form-input <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" required>
            <option value=""><?php echo t("select_role"); ?></option>
            <option value="user" <?php echo ($role == "user") ? "selected" : ""; ?>><?php echo t("user_role"); ?></option>
            <option value="admin" <?php echo ($role == "admin") ? "selected" : ""; ?>><?php echo t("admin_role"); ?></option>
            <option value="manager" <?php echo ($role == "manager") ? "selected" : ""; ?>><?php echo t("manager_role"); ?></option>
        </select>
        <?php if (!empty($role_err)): ?><span class="error-message"><?php echo htmlspecialchars($role_err); ?></span><?php endif; ?>
    </div>

    <hr>
    <p class="password-note"><?php echo t("password_strength_hint"); ?></p>

    <div>
        <label for="new_password"><?php echo t("new_password"); ?>:</label>
        <input type="password" id="new_password" name="new_password" class="form-input <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
    </div>

    <div>
        <label for="confirm_password"><?php echo t("confirm_password"); ?>:</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-input <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
        <?php if (!empty($password_err)): ?><span class="error-message"><?php echo htmlspecialchars($password_err); ?></span><?php endif; ?>
    </div>

                <div class="form-actions">
                    <input type="submit" value="<?php echo t("save_changes"); ?>" class="btn btn-success">
                    <a href="employees_list.php" class="btn btn-secondary"><?php echo t("cancel"); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
