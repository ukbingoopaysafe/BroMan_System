<?php
session_start();
require_once 'db.php';
require_once 'rate_limiter.php'; // Security: Rate Limiting

// db.php provides $conn (mysqli object)

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check rate limit
    $limit_check = check_rate_limit($conn);
    
    if (!$limit_check['allowed']) {
        $error = $limit_check['message'];
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            // Prepare statement using mysqli
            $stmt = $conn->prepare("SELECT EmployeeID, Username, Password, Role, FullName FROM employees WHERE Username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['Password'])) {
                    // Successful login
                    handle_successful_login($conn, $user['Username']);
                                        
                    $_SESSION["loggedin"] = true; // For compatibility with existing system
                    $_SESSION['user_id'] = $user['EmployeeID']; // Map to generic user_id if needed, or prefer employee_id
                    $_SESSION['employee_id'] = $user['EmployeeID']; // Existing system uses this
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['role'] = $user['Role'];
                    $_SESSION['full_name'] = $user['FullName'];
                    
                    // Add activity log if module exists
                    if (file_exists('activity_log.php')) {
                        require_once 'activity_log.php';
                        if (function_exists('log_activity')) {
                            log_activity($conn, $user['EmployeeID'], 'login', 'User logged in via VIP Portal');
                        }
                    }

                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Failed login
                    handle_failed_login($conn, $username);
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Database error: Unable to prepare statement.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BroMan Real Estate</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicon -->
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="images/Logo%20Broman.svg">
 
    <!-- Custom Styles -->
    <link rel="stylesheet" href="css/vip_login.css">
</head>
<body>
    <!-- Animated Background -->
    <div id="canvas-container">
        <canvas id="bg-canvas"></canvas>
    </div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="glass-card">
            <div class="logo-container">
                <img src="images/Logo/Final_Logo.png" alt="BroMan Logo">
            </div>
            
            <div class="login-header">
                <h1>Welcome Back</h1>
                <p>Sign in to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required autocomplete="username">
                    <i class="fa-regular fa-user"></i>
                </div>
                
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <div class="actions">
                    <label class="custom-checkbox">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">
                    Sign In <i class="fa-solid fa-arrow-right-long" style="margin-left: 8px;"></i>
                </button>
            </form>

            <div class="footer-link">
                <p>Not a member? <a href="#">Contact Support</a></p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/vip_login.js"></script>
</body>
</html>
