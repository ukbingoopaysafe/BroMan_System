<?php
require_once __DIR__ . 
'/lang/translate.php'; // Translation handler
require_once 'permissions.php'; // Include permissions system
require_once 'session_check.php'; // Include session timeout check
require_once __DIR__ . '/db.php'; // Database connection

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check permissions - only manager and admin can add properties

// --- Fetch Lookups ---
$property_types = [];
$finishing_types = [];
$employees = [];

// Fetch Property Types (Filter for Commercial, Admin, Hotel, Medical - IDs 3, 4, 5, 6)
$sql_types = "SELECT PropertyTypeID, TypeName FROM PropertyTypes WHERE PropertyTypeID IN (3, 4, 5, 6) ORDER BY PropertyTypeID";
if ($result = $conn->query($sql_types)) {
    while ($row = $result->fetch_assoc()) {
        $property_types[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching other property types: " . $conn->error);
}

// Fetch Finishing Types
$sql_finishing = "SELECT FinishingID, FinishingName FROM propertyfinishingtypes ORDER BY FinishingID";
if ($result = $conn->query($sql_finishing)) {
    while ($row = $result->fetch_assoc()) {
        $finishing_types[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching finishing types: " . $conn->error);
}

// Fetch Employees
$sql_employees = "SELECT EmployeeID, FullName FROM employees ORDER BY FullName";
if ($result = $conn->query($sql_employees)) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching employees: " . $conn->error);
}

$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Data Validation and Sanitization ---
    $property_type_id = filter_input(INPUT_POST, 'property_type_id', FILTER_VALIDATE_INT);
    $project_name = trim($_POST['project_name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $governorate = trim($_POST['governorate'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $floors_count = filter_input(INPUT_POST, 'floors_count', FILTER_VALIDATE_INT);
    if ($floors_count === false) {
        $floors_count = null;
    }
    $min_area = filter_input(INPUT_POST, 'min_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_area = filter_input(INPUT_POST, 'max_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $avg_area = filter_input(INPUT_POST, 'avg_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $area = filter_input(INPUT_POST, 'area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $finishing_type_id_raw = $_POST['finishing_type_id'] ?? '';
    $finishing_type_id = is_numeric($finishing_type_id_raw) && $finishing_type_id_raw !== '' ? (int)$finishing_type_id_raw : null;
    $min_price = filter_input(INPUT_POST, 'min_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_price = filter_input(INPUT_POST, 'max_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $down_payment_percentage = filter_input(INPUT_POST, 'down_payment_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $payment_years = filter_input(INPUT_POST, 'payment_years', FILTER_VALIDATE_INT);
    $delivery_date_str = trim($_POST['delivery_date'] ?? '');
    $status = trim($_POST['status'] ?? 'available');
    $employee_id = $_SESSION['id'] ?? null; // Use 'id' from your session if that's the key
    $sales_manager_name = trim($_POST['sales_manager_name'] ?? '');
    $sales_manager_phone = trim($_POST['sales_manager_phone'] ?? '');
    $salesperson_name = trim($_POST['salesperson_name'] ?? '');
    $salesperson_phone = trim($_POST['salesperson_phone'] ?? '');

    // --- Basic Validation ---
    // (Your validation code here, unchanged)
    if (empty($project_name) && empty($title)) {
        $errors[] = t('error_project_or_title_required');
    }
    // ... etc.

    // Validate delivery date format if provided
    $delivery_date = null;
    if (!empty($delivery_date_str)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $delivery_date_str);
        if ($date_obj && $date_obj->format('Y-m-d') === $delivery_date_str) {
            $delivery_date = $delivery_date_str;
        } else {
            $errors[] = t('error_invalid_delivery_date');
        }
    }

// --- Database Insertion
$sql = "INSERT INTO Properties (
    PropertyTypeID, Title, Description, Location,
    governorate, district, company_name, FloorsCount,
    min_area, max_area, avg_area, Area,
    finishing_type_id, min_price, max_price,
    Price, down_payment_percentage, payment_years, delivery_date, Status,
    EmployeeID,
    sales_manager_name, sales_manager_phone, salesperson_name, salesperson_phone,
    ProjectName
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$date_added = date("Y-m-d H:i:s");

$params = array(
    $property_type_id,
    $title,
    $description,
    $location,
    $governorate,
    $district,
    $company_name,
    $floors_count,
    $min_area,
    $max_area,
    $avg_area,
    $area,
    $finishing_type_id,
    $min_price,
    $max_price,
    $price,
    $down_payment_percentage,
    $payment_years,
    $delivery_date,
    $status,
    $employee_id,
    $sales_manager_name,
    $sales_manager_phone,
    $salesperson_name,
    $salesperson_phone,
    $project_name,
    
);


$types = "issssssiddiddiddississssss"; // ✔️ طوله = 26


error_log("DEBUG: \$types string: " . $types);
error_log("DEBUG: Length of \$types: " . strlen($types));
error_log("DEBUG: \$params array count: " . count($params));
error_log("DEBUG: \$params array content: " . var_export($params, true));

if ($stmt = $conn->prepare($sql)) {
    if (count($params) !== strlen($types)) {
        error_log("Mismatch: Expected " . strlen($types) . " params, got " . count($params));
        $errors[] = t('database_error');
    } else {
        $stmt->bind_param(
            $types,
            ...$params
        );

        if ($stmt->execute()) {
            $_SESSION["success_message"] = t('success_property_added');
            header("Location: commercial_properties.php");
            exit();
        } else {
            $errors[] = t('error_adding_property') . " " . $stmt->error;
            error_log("Error executing property insert: " . $stmt->error);
        }
    }
    $stmt->close();
} else {
    $errors[] = t('database_error') . " " . $conn->error;
    error_log("Error preparing property insert: " . $conn->error);
}


    $conn->close();
}
?>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t("add_other_property_title"); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <style>
        .form-section { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .form-section h3 { margin-bottom: 15px; color: #0056b3; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    </style>
    <script src="session_timeout.js"></script>
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<div class="container">
    <h1><?php echo t("add_other_property_title"); ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="form-container">

        <div class="form-section">
            <h3><?php echo t('basic_info'); ?></h3>
            <div class="form-grid">
                 <div class="form-group">
                    <label for="property_type_id"><?php echo t('property_type'); ?>: *</label>
                    <select id="property_type_id" name="property_type_id" class="form-input" required>
                        <option value=""><?php echo t('select_property_type'); ?></option>
                        <?php foreach ($property_types as $type): ?>
                            <option value="<?php echo $type['PropertyTypeID']; ?>" <?php echo (($_POST['property_type_id'] ?? '') == $type['PropertyTypeID']) ? 'selected' : ''; ?>><?php echo t(htmlspecialchars($type['TypeName'])); // Translate type name?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="project_name"><?php echo t('project_name'); ?>:</label>
                    <input type="text" id="project_name" name="project_name" class="form-input" value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="title"><?php echo t('unit_title_optional'); ?>:</label>
                    <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                </div>
                 <div class="form-group">
                    <label for="company_name"><?php echo t('company'); ?>:</label>
                    <input type="text" id="company_name" name="company_name" class="form-input" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="status"><?php echo t('status'); ?>:</label>
                    <select id="status" name="status" class="form-input">
                        <option value="available" <?php echo (($_POST['status'] ?? 'available') == 'available') ? 'selected' : ''; ?>><?php echo t('available'); ?></option>
                        <option value="sold" <?php echo (($_POST['status'] ?? '') == 'sold') ? 'selected' : ''; ?>><?php echo t('sold'); ?></option>
                        <option value="rented" <?php echo (($_POST['status'] ?? '') == 'rented') ? 'selected' : ''; ?>><?php echo t('rented'); ?></option>
                        <option value="unavailable" <?php echo (($_POST['status'] ?? '') == 'unavailable') ? 'selected' : ''; ?>><?php echo t('unavailable'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><?php echo t('location_info'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="governorate"><?php echo t('governorate'); ?>: *</label>
                    <input type="text" id="governorate" name="governorate" class="form-input" required value="<?php echo htmlspecialchars($_POST['governorate'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="district"><?php echo t('district'); ?>: *</label>
                    <input type="text" id="district" name="district" class="form-input" required value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="location"><?php echo t('detailed_address'); ?>:</label>
                    <input type="text" id="location" name="location" class="form-input" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><?php echo t('area_and_specs'); // Changed title?></h3>
            <div class="form-grid">
                 <div class="form-group">
                    <label for="floors_count"><?php echo t('floors_count'); ?>:</label>
                    <input type="number" id="floors_count" name="floors_count" class="form-input" value="<?php echo htmlspecialchars($_POST['floors_count'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="finishing_type_id"><?php echo t('finishing_type'); ?>:</label> 
                    <select id="finishing_type_id" name="finishing_type_id" class="form-input"> 
                        <option value=""><?php echo t('select_finishing_type'); ?></option>
                        <?php foreach ($finishing_types as $finishing): ?>
                            <option value="<?php echo $finishing['FinishingID']; ?>" <?php echo (($_POST['finishing_type_id'] ?? '') == $finishing['FinishingID']) ? 'selected' : ''; ?>><?php echo t(htmlspecialchars($finishing['FinishingName'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <!-- Removed Unit Design, Bedrooms, Bathrooms -->
            </div>
        </div>

        <div class="form-section">
            <h3><?php echo t('area_details'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="area"><?php echo t('unit_area'); ?> (<?php echo t('sqm'); ?>):</label>
                    <input type="number" step="0.01" id="area" name="area" class="form-input" value="<?php echo htmlspecialchars($_POST['area'] ?? ''); ?>">
                    <small><?php echo t('specific_area_note'); ?></small>
                </div>
                <div class="form-group">
                    <label for="min_area"><?php echo t('min_area_sqm'); ?>:</label>
                    <input type="number" step="0.01" id="min_area" name="min_area" class="form-input" value="<?php echo htmlspecialchars($_POST['min_area'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="max_area"><?php echo t('max_area_sqm'); ?>:</label>
                    <input type="number" step="0.01" id="max_area" name="max_area" class="form-input" value="<?php echo htmlspecialchars($_POST['max_area'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="avg_area"><?php echo t('avg_area_sqm'); ?>:</label>
                    <input type="number" step="0.01" id="avg_area" name="avg_area" class="form-input" value="<?php echo htmlspecialchars($_POST['avg_area'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><?php echo t('pricing_and_payment'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="price"><?php echo t('unit_price'); ?> (EGP):</label>
                    <input type="number" step="0.01" id="price" name="price" class="form-input" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                    <small><?php echo t('specific_price_note'); ?></small>
                </div>
                <div class="form-group">
                    <label for="min_price"><?php echo t('min_price'); ?> (EGP):</label>
                    <input type="number" step="0.01" id="min_price" name="min_price" class="form-input" value="<?php echo htmlspecialchars($_POST['min_price'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="max_price"><?php echo t('max_price'); ?> (EGP):</label>
                    <input type="number" step="0.01" id="max_price" name="max_price" class="form-input" value="<?php echo htmlspecialchars($_POST['max_price'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="down_payment_percentage"><?php echo t('down_payment_percentage'); ?> (%):</label>
                    <input type="number" step="0.01" id="down_payment_percentage" name="down_payment_percentage" class="form-input" value="<?php echo htmlspecialchars($_POST['down_payment_percentage'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="payment_years"><?php echo t('payment_years'); ?>:</label>
                    <input type="number" id="payment_years" name="payment_years" class="form-input" value="<?php echo htmlspecialchars($_POST['payment_years'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="delivery_date"><?php echo t('delivery_date'); ?>:</label>
                    <input type="date" id="delivery_date" name="delivery_date" class="form-input" value="<?php echo htmlspecialchars($_POST['delivery_date'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
    <h3><?php echo t('additional_info'); ?></h3>
    <div class="form-grid">
        <div class="form-group" style="grid-column: 1 / -1;">
            <label for="description"><?php echo t('description'); ?>:</label>
            <textarea id="description" name="description" class="form-input" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
    </div> <!-- Close form-grid -->
</div> <!-- Close form-section -->

        <!-- Sales Information Section -->
        <div class="form-section">
            <h3><?php echo t("sales_information"); ?></h3>
            <div class="form-grid">
                <div>
                    <label for="sales_manager_name"><?php echo t("sales_manager_name_label"); ?></label>
                    <input type="text" id="sales_manager_name" name="sales_manager_name" class="form-input" value="<?php echo htmlspecialchars($_POST["sales_manager_name"] ?? ""); ?>">
                </div>
                <div>
                    <label for="sales_manager_phone"><?php echo t("sales_manager_phone_label"); ?></label>
                    <input type="tel" id="sales_manager_phone" name="sales_manager_phone" class="form-input <?php echo isset($errors["sales_manager_phone"]) ? "is-invalid" : ""; ?>" value="<?php echo htmlspecialchars($_POST["sales_manager_phone"] ?? ""); ?>">
                </div>
                <div>
                    <label for="salesperson_name"><?php echo t("salesperson_name_label"); ?></label>
                    <input type="text" id="salesperson_name" name="salesperson_name" class="form-input" value="<?php echo htmlspecialchars($_POST["salesperson_name"] ?? ""); ?>">
                </div>
                <div>
                    <label for="salesperson_phone"><?php echo t("salesperson_phone_label"); ?></label>
                    <input type="tel" id="salesperson_phone" name="salesperson_phone" class="form-input <?php echo isset($errors["salesperson_phone"]) ? "is-invalid" : ""; ?>" value="<?php echo htmlspecialchars($_POST["salesperson_phone"] ?? ""); ?>">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo t("add_property_button"); ?></button>
            <a href="commercial_properties.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>

</body>
</html>


