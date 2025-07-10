<?php
require_once __DIR__ .
'/lang/translate.php'; // Include translation handler

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php'; // Include database connection using absolute path

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Prepare SQL to fetch clients using ACTUAL schema column names from broman_db.sql
$sql = "SELECT ClientID, FullName, Phone, Email, DateRegistered FROM clients"; // Corrected column names
if (!empty($search_term)) {
    // Use placeholders for security
    // Corrected column names in WHERE clause
    $sql .= " WHERE FullName LIKE ? OR Email LIKE ? OR Phone LIKE ?";
}
$sql .= " ORDER BY ClientID ASC";

$clients = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($search_term)) {
        $like_search_term = "%" . $search_term . "%";
        // Bind parameters
        $stmt->bind_param("sss", $like_search_term, $like_search_term, $like_search_term);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Error fetching clients: " . $stmt->error);
        // Use translation key for error message
        $_SESSION['error_message'] = t('error_retrieving_clients');
    }
} else {
    error_log("Error preparing statement for clients: " . $conn->error);
    // Use translation key for error message
    $_SESSION['error_message'] = t('database_error');
}

$is_admin = (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"); // Assuming role check is still relevant

$conn->close(); // Close connection after fetching data

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('manage_clients_title'); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; // Include the navigation bar using absolute path?>

    <div class="container">
        <div class="page-header">
            <h1><?php echo t('client_management'); ?></h1>
            <a href="add_client.php" class="btn btn-add">+ <?php echo t('add_new_client'); ?></a>
        </div>

        <form method="GET" action="index.php" class="search-form">
            <input type="text" name="search" class="form-input" placeholder="<?php echo t('search_clients_placeholder'); ?>" value="<?php echo htmlspecialchars($search_term); ?>">
            <input type="submit" value="<?php echo t('search'); ?>" class="btn btn-primary">
        </form>

        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="error-message"><?php echo htmlspecialchars($_SESSION['error_message']);
            unset($_SESSION['error_message']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="success-message"><?php echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']); ?></p>
        <?php endif; ?>

        <?php if (!empty($clients)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo t('client_id'); ?></th>
                            <th><?php echo t('full_name'); ?></th>
                            <th><?php echo t('email'); ?></th>
                            <th><?php echo t('phone'); ?></th>
                            <th><?php echo t('date_registered'); // Corrected translation key if needed?></th> 
                            <?php if ($is_admin): // Keep actions column if admin role exists?>
                                <th><?php echo t('actions'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <!-- Use ACTUAL schema column names -->
                                <td><?php echo htmlspecialchars($client['ClientID']); ?></td>
                                <td><?php echo htmlspecialchars($client['FullName']); ?></td>
                                <td><?php echo htmlspecialchars($client['Email'] ?: t('n_a')); ?></td>
                                <td><?php echo htmlspecialchars($client['Phone'] ?: t('n_a')); ?></td> 
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($client['DateRegistered']))); ?></td>
                                <?php if ($is_admin): ?>
                                    <td class="actions">
                                        <!-- Corrected ID parameter -->
                                        <a href="edit_client.php?id=<?php echo $client['ClientID']; ?>" class="btn btn-edit"><?php echo t('edit'); ?></a>
                                        <a href="delete_client.php?id=<?php echo $client['ClientID']; ?>" class="btn btn-delete" onclick="return confirm('<?php echo t('confirm_delete_client'); ?>');"><?php echo t('delete'); ?></a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-data">
                <?php echo !empty($search_term) ? t('no_clients_matching_search') : t('no_clients_found'); ?> 
                <a href="add_client.php"><?php echo t('ask_add_new_client'); ?></a>
            </p>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/footer.php'; // Include the footer using absolute path?>

</body>
</html>

