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

require_once 'db.php'; // Database connection

$full_name = $email = $phone = "";
$full_name_err = $email_err = $phone_err = $general_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate Full Name
    if (empty(trim($_POST["fullName"]))) {
        $full_name_err = "Please enter the client's full name.";
    } else {
        $full_name = trim($_POST["fullName"]);
        // Optionally, add more validation for full name (e.g., length, characters)
    }

    // Validate Email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email already exists
        $sql_check_email = "SELECT ClientID FROM Clients WHERE Email = ?";
        if ($stmt_check = $conn->prepare($sql_check_email)) {
            $stmt_check->bind_param("s", $param_email_check);
            $param_email_check = trim($_POST["email"]);
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $email_err = "This email address is already registered.";
                }
                $email = trim($_POST["email"]);
            } else {
                $general_err = "Error checking email. Please try again.";
                error_log("Error executing email check: " . $stmt_check->error);
            }
            $stmt_check->close();
        } else {
            $general_err = "Database error (email check). Please try again.";
            error_log("Error preparing email check: " . $conn->error);
        }
    }

    // Validate Phone (Optional)
    if (!empty(trim($_POST["phone"]))) {
        $phone = trim($_POST["phone"]);
        // Optionally, add more specific phone number validation (e.g., regex for format)
    } else {
        $phone = null; // Allow phone to be optional
    }

    // Check input errors before inserting in database
    if (empty($full_name_err) && empty($email_err) && empty($phone_err) && empty($general_err)) {
        $sql = "INSERT INTO Clients (FullName, Email, Phone) VALUES (?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("sss", $param_full_name, $param_email, $param_phone);
            
            // Set parameters
            $param_full_name = $full_name;
            $param_email = $email;
            $param_phone = $phone;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $_SESSION["success_message"] = "Client added successfully!";
                header("Location: index.php"); // Redirect to clients list or dashboard
                exit();
            } else {
                $general_err = "Something went wrong. Please try again later.";
                error_log("Error executing client insert: " . $stmt->error);
            }
            // Close statement
            $stmt->close();
        }
    }
    // Close connection
    // $conn->close(); // Usually closed at the end of script execution by PHP if not persistent
}
?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('add_new_client'); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
</head>
<body>

<?php require_once 'navbar.php'; // Include the navigation bar?>

<div class="container">
    <div class="page-header">
        <h1><?php echo t('add_new_client'); ?></h1>
    </div>

    <div class="form-container">
        <div class="form-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
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
                    <input type="submit" value="<?php echo t('add_new_client'); ?>" class="btn btn-success">
                    <a href="index.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
