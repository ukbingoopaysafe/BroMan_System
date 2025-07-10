<?php
require_once __DIR__ . '/lang/translate.php'; // Include translation handler

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Ensure user is an admin to edit client details
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    $_SESSION["error_message"] = t('permission_denied');
    header("Location: index.php"); // Redirect to client list or dashboard
    exit;
}

require_once 'db.php'; // Database connection

// Initialize variables
$client_id = $full_name = $email = $phone = "";
$full_name_err = $email_err = $phone_err = $general_err = "";

// Check if ID is provided in URL
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $client_id = trim($_GET["id"]);

    // Prepare a select statement to fetch client details
    $sql_fetch = "SELECT FullName, Email, Phone FROM Clients WHERE ClientID = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $param_client_id);
        $param_client_id = $client_id;

        if ($stmt_fetch->execute()) {
            $stmt_fetch->store_result();
            if ($stmt_fetch->num_rows == 1) {
                $stmt_fetch->bind_result($full_name, $email, $phone);
                $stmt_fetch->fetch();
            } else {
                // Client not found, redirect or show error
                $_SESSION["error_message"] = "Client not found.";
                header("Location: index.php");
                exit();
            }
        } else {
            $general_err = "Error fetching client details.";
            error_log("Error executing client fetch: " . $stmt_fetch->error);
        }
        $stmt_fetch->close();
    } else {
        $general_err = "Database error (client fetch). Please try again.";
        error_log("Error preparing client fetch: " . $conn->error);
    }
} else {
    // No ID provided, redirect or show error
    $_SESSION["error_message"] = "No client ID specified for editing.";
    header("Location: index.php");
    exit();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get hidden client_id from form
    $client_id_post = trim($_POST["client_id"]);
    if (empty($client_id_post) || $client_id_post != $client_id) {
        $general_err = "Invalid client ID submitted.";
    }

    // Validate Full Name
    if (empty(trim($_POST["fullName"]))) {
        $full_name_err = "Please enter the client's full name.";
    } else {
        $full_name_posted = trim($_POST["fullName"]);
    }

    // Validate Email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email already exists for another client
        $sql_check_email = "SELECT ClientID FROM Clients WHERE Email = ? AND ClientID != ?";
        if ($stmt_check = $conn->prepare($sql_check_email)) {
            $stmt_check->bind_param("si", $param_email_check, $param_client_id_check);
            $param_email_check = trim($_POST["email"]);
            $param_client_id_check = $client_id;
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $email_err = "This email address is already registered to another client.";
                }
                $email_posted = trim($_POST["email"]);
            } else {
                $general_err = "Error checking email. Please try again.";
                error_log("Error executing email check (edit): " . $stmt_check->error);
            }
            $stmt_check->close();
        } else {
            $general_err = "Database error (email check). Please try again.";
            error_log("Error preparing email check (edit): " . $conn->error);
        }
    }

    // Validate Phone (Optional)
    if (!empty(trim($_POST["phone"]))) {
        $phone_posted = trim($_POST["phone"]);
    } else {
        $phone_posted = null;
    }

    // Check input errors before updating in database
    if (empty($full_name_err) && empty($email_err) && empty($phone_err) && empty($general_err)) {
        $sql_update = "UPDATE Clients SET FullName = ?, Email = ?, Phone = ? WHERE ClientID = ?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("sssi", $param_full_name, $param_email, $param_phone, $param_client_id_update);
            
            $param_full_name = $full_name_posted;
            $param_email = $email_posted;
            $param_phone = $phone_posted;
            $param_client_id_update = $client_id;
            
            if ($stmt_update->execute()) {
                $_SESSION["success_message"] = "Client details updated successfully!";
                header("Location: index.php");
                exit();
            } else {
                $general_err = "Something went wrong. Please try again later.";
                error_log("Error executing client update: " . $stmt_update->error);
            }
            $stmt_update->close();
        }
    } else {
        // If there are errors, re-populate form fields with submitted (but potentially erroneous) data for correction
        $full_name = isset($full_name_posted) ? $full_name_posted : $full_name;
        $email = isset($email_posted) ? $email_posted : $email;
        $phone = isset($phone_posted) ? $phone_posted : $phone;
    }
}

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('edit_client_title'); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
</head>
<body>

<?php require_once 'navbar.php'; // Include the navigation bar?>

<div class="container">
    <div class="page-header">
        <h1><?php echo t('edit_client_title'); ?></h1>
    </div>

    <div class="form-container">
        <div class="form-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($client_id); ?>" method="POST">
                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">

                <?php if (!empty($general_err)): ?>
                    <div class="message message-error"><?php echo htmlspecialchars($general_err); ?></div>
                <?php endif; ?>

                <div>
                    <label for="fullName"><?php echo t('full_name'); ?>:</label>
                    <input type="text" id="fullName" name="fullName" class="form-input <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>" required>
                    <?php if (!empty($full_name_err)): ?><span class="error-message"><?php echo htmlspecialchars($full_name_err); ?></span><?php endif; ?>
                </div>

                <div>
                    <label for="email"><?php echo t('email'); ?>:</label>
                    <input type="email" id="email" name="email" class="form-input <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (!empty($email_err)): ?><span class="error-message"><?php echo htmlspecialchars($email_err); ?></span><?php endif; ?>
                </div>

                <div>
                    <label for="phone"><?php echo t('phone'); ?> (<?php echo t('optional'); ?>):</label>
                    <input type="text" id="phone" name="phone" class="form-input <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone); ?>">
                    <?php if (!empty($phone_err)): ?><span class="error-message"><?php echo htmlspecialchars($phone_err); ?></span><?php endif; ?>
                </div>

                <div class="form-actions">
                    <input type="submit" value="<?php echo t('save_changes'); ?>" class="btn btn-success">
                    <a href="index.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
