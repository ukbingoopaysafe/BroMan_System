<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lang/translate.php'; // Include translation handler
require_once 'permissions.php'; // Include permissions system

// Check permissions - only manager can manage employees
check_permission('manage_employees');

require_once __DIR__ . '/db.php'; // Include database connection using absolute path

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Prepare SQL to fetch employees using ACTUAL schema column names from broman_db.sql
$sql = "SELECT EmployeeID, FullName, Username, Role, DateJoined FROM employees"; // Corrected column names
if (!empty($search_term)) {
    // Update search fields based on actual schema
    $sql .= " WHERE FullName LIKE ? OR Username LIKE ? OR Role LIKE ?"; // Search by FullName, Username, Role
}
$sql .= " ORDER BY EmployeeID ASC";

$employees = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($search_term)) {
        $like_search_term = "%" . $search_term . "%";
        // Bind parameters for new search fields
        $stmt->bind_param("sss", $like_search_term, $like_search_term, $like_search_term);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Error fetching employees: " . $stmt->error);
        // Use translation key for error message
        $_SESSION['error_message'] = t('error_retrieving_employees');
    }
} else {
    error_log("Error preparing statement for employees: " . $conn->error);
    // Use translation key for error message
    $_SESSION['error_message'] = t('database_error');
}

$conn->close(); // Close connection after fetching data

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('manage_employees_title'); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; // Include the navigation bar using absolute path?>

    <div class="container">
        <div class="page-header">
            <h1><?php echo t('employee_management'); ?></h1>
            <a href="add_employee.php" class="btn btn-add">+ <?php echo t('add_new_employee'); ?></a>
        </div>

        <div class="filter-section">
            <form method="GET" action="employees_list.php" class="filter-form">
                <div class="filter-item">
                    <input type="text" name="search" class="form-input" placeholder="<?php echo t('search_employees_placeholder'); ?>" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="filter-item">
                    <button type="submit" class="btn btn-primary"><?php echo t('search'); ?></button>
                </div>
            </form>
        </div>

        <?php
        // Show short-lived flash messages (preferred)
        require_once __DIR__ . '/flash.php';
        $fs = get_flash_messages();
        if (!empty($fs)) {
            $first_id = array_key_first($fs);
            echo "<div class='message message-success'>" . htmlspecialchars($fs[$first_id]) . "</div>";
            if (function_exists('clear_flash')) { clear_flash($first_id); }
        }
        if (isset($_SESSION["error_message"])) {
            echo "<div class='message message-error'>" . htmlspecialchars($_SESSION["error_message"]) . "</div>";
            unset($_SESSION["error_message"]);
        }
        ?>

        <?php if (!empty($employees)): ?>
            <div class="table-responsive">
                <table class="properties-table">
                    <thead>
                        <tr>
                            <th><?php echo t('employee_id'); ?></th>
                            <th><?php echo t('full_name'); // Use correct key if different?></th>
                            <th><?php echo t('username'); ?></th>
                            <th><?php echo t('role'); ?></th>
                            <th><?php echo t('date_joined'); // Use correct key if different?></th>
                            <th><?php echo t('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <!-- Use ACTUAL schema column names -->
                                <td><?php echo htmlspecialchars($employee['EmployeeID']); ?></td>
                                <td><?php echo htmlspecialchars($employee['FullName']); ?></td>
                                <td><?php echo htmlspecialchars($employee['Username']); ?></td>
                                <td><?php echo t(htmlspecialchars($employee['Role'])); // Translate role?></td> 
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($employee['DateJoined']))); ?></td>
                                <td class="actions">
                                    <!-- Corrected ID parameter -->
                                    <a href="edit_employee.php?id=<?php echo $employee['EmployeeID']; ?>" class="btn btn-edit"><?php echo t('edit'); ?></a>
                                    <?php // Check if the logged-in user is the one being listed to prevent self-deletion
                                    // Use EmployeeID from session if available, otherwise use a placeholder like 'user_id'
                                    $loggedInEmployeeId = $_SESSION['employee_id'] ?? ($_SESSION['user_id'] ?? null);
                            if ($loggedInEmployeeId != $employee['EmployeeID']): ?>
                                        <form method="POST" action="delete_employee.php" style="display:inline" onsubmit="return confirm('<?php echo t('confirm_delete_employee'); ?>');">
                                            <input type="hidden" name="id" value="<?php echo (int)$employee['EmployeeID']; ?>">
                                            <?php echo csrf_input(); ?>
                                            <button type="submit" class="btn btn-delete"><?php echo t('delete'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-data">
                <?php echo !empty($search_term) ? t('no_employees_matching_search') : t('no_employees_found'); ?>
                 <a href="add_employee.php"><?php echo t('ask_add_new_employee'); ?></a>
            </p>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/footer.php'; // Include the footer using absolute path?>

</body>
</html>

