<?php
require_once __DIR__ . 
'/lang/translate.php'; // Translation handler
require_once 'permissions.php'; // Include permissions system
require_once 'session_check.php'; // Include session timeout check
require_once __DIR__ . '/db.php'; // Database connection
require_once __DIR__ . '/security.php';

ensure_secure_session_started();

// Check permissions - only manager and admin can add properties
check_permission('manage_properties');

// Enforce mode selection - redirect to dashboard if no mode is set
if (!isset($_SESSION['listing_context']) || ($_SESSION['listing_context'] !== 'Primary' && $_SESSION['listing_context'] !== 'Resale')) {
    header('Location: dashboard.php');
    exit();
}

$current_mode = $_SESSION['listing_context'];

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
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $errors[] = t('invalid_csrf_token');
    }
    // --- Data Sanitization ---
    $property_type_id = filter_input(INPUT_POST, 'property_type_id', FILTER_VALIDATE_INT);
    $listing_type = $_SESSION['listing_context'] ?? 'Primary';
    $project_name = trim($_POST['project_name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? null);
    $governorate = trim($_POST['governorate'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $location = trim($_POST['location'] ?? null);
    $company_name = trim($_POST['company_name'] ?? null);
    $floors_count = filter_input(INPUT_POST, 'floors_count', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $min_area = filter_input(INPUT_POST, 'min_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_area = filter_input(INPUT_POST, 'max_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $avg_area = filter_input(INPUT_POST, 'avg_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $area = filter_input(INPUT_POST, 'area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $bedrooms = null; // Not applicable
    $unit_design = null; // Not applicable
    $compound_area = null; // Not applicable
    $finishing_type_id = filter_input(INPUT_POST, 'finishing_type_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $min_price = filter_input(INPUT_POST, 'min_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_price = filter_input(INPUT_POST, 'max_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $down_payment_percentage = filter_input(INPUT_POST, 'down_payment_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $payment_years = filter_input(INPUT_POST, 'payment_years', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $delivery_date_str = trim($_POST['delivery_date'] ?? '');
    $status = trim($_POST['status'] ?? 'available');
    $employee_id = $_SESSION['employee_id'] ?? null;
    $sales_manager_name = trim($_POST['sales_manager_name'] ?? null);
    $sales_manager_phone = trim($_POST['sales_manager_phone'] ?? null);
    $salesperson_name = trim($_POST['salesperson_name'] ?? null);
    $salesperson_phone = trim($_POST['salesperson_phone'] ?? null);
    $seller_type = trim($_POST['seller_type'] ?? null);
    $seller_name = trim($_POST['seller_name'] ?? null);
    $seller_phone = trim($_POST['seller_phone'] ?? null);
    $latitude = trim($_POST['latitude'] ?? null);
    $longitude = trim($_POST['longitude'] ?? null);
    $year_built = filter_input(INPUT_POST, 'year_built', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $unit_floor = trim($_POST['unit_floor'] ?? null);
    $net_area = filter_input(INPUT_POST, 'net_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $loading_percentage = filter_input(INPUT_POST, 'loading_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price_cash = filter_input(INPUT_POST, 'price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price_installments = filter_input(INPUT_POST, 'price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $meter_price_cash = filter_input(INPUT_POST, 'meter_price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $meter_price_installments = filter_input(INPUT_POST, 'meter_price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $delivery_date = !empty($delivery_date_str) ? $delivery_date_str : null;

    // --- Database Insertion ---
    $sql = "INSERT INTO Properties (PropertyTypeID, Title, Description, Location, Area, Bedrooms, Status, listing_type, EmployeeID, unit_design, finishing_type_id, compound_area, down_payment_percentage, payment_years, delivery_date, ProjectName, min_area, max_area, avg_area, district, governorate, company_name, FloorsCount, sales_manager_name, sales_manager_phone, salesperson_name, salesperson_phone, seller_type, seller_name, seller_phone, latitude, longitude, year_built, unit_floor, net_area, loading_percentage, price_cash, price_installments, meter_price_cash, meter_price_installments, DateAdded) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param(
            "isssdisssiiiddissddddsssisssssssssdddddd",
            $property_type_id, $title, $description, $location, $area, $bedrooms, $status, $listing_type, $employee_id, $unit_design, $finishing_type_id, $compound_area, $down_payment_percentage, $payment_years, $delivery_date, $project_name, $min_area, $max_area, $avg_area, $district, $governorate, $company_name, $floors_count, $sales_manager_name, $sales_manager_phone, $salesperson_name, $salesperson_phone, $seller_type, $seller_name, $seller_phone, $latitude, $longitude, $year_built, $unit_floor, $net_area, $loading_percentage, $price_cash, $price_installments, $meter_price_cash, $meter_price_installments
        );

        if ($stmt->execute()) {
            $new_property_id = $conn->insert_id;

            // Handle optional media uploads (same logic)
            $base_upload_dir = __DIR__ . '/uploads/';
            $image_dir = $base_upload_dir . 'images/';
            $audio_dir = $base_upload_dir . 'audio/';
            if (!is_dir($base_upload_dir)) { mkdir($base_upload_dir, 0755, true); }
            if (!is_dir($image_dir)) { mkdir($image_dir, 0755, true); }
            if (!is_dir($audio_dir)) { mkdir($audio_dir, 0755, true); }

            // Images
            if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $is_first_image = true;
                $check_sql = "SELECT COUNT(*) as count FROM property_images WHERE property_id = ?";
                if ($check_stmt = $conn->prepare($check_sql)) {
                    $check_stmt->bind_param("i", $new_property_id);
                    $check_stmt->execute();
                    $res = $check_stmt->get_result();
                    if ($row = $res->fetch_assoc()) { $is_first_image = ($row['count'] == 0); }
                    $check_stmt->close();
                }

                $allowed_img_mime = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $max_img_size = 10 * 1024 * 1024;
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                foreach ($_FILES['images']['name'] as $idx => $name) {
                    if ($_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) { continue; }
                    if ($_FILES['images']['size'][$idx] > $max_img_size) { continue; }
                    $tmp = $_FILES['images']['tmp_name'][$idx];
                    $mime = finfo_file($finfo, $tmp);
                    if (!isset($allowed_img_mime[$mime])) { continue; }
                    $ext = $allowed_img_mime[$mime];
                    $filename = 'property_' . $new_property_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $image_dir . $filename;
                    $relative = 'uploads/images/' . $filename;
                    if (move_uploaded_file($tmp, $dest)) {
                        $is_primary = $is_first_image ? 1 : 0;
                        $is_first_image = false;
                        $ins = "INSERT INTO property_images (property_id, file_path, is_primary) VALUES (?, ?, ?)";
                        if ($img_stmt = $conn->prepare($ins)) {
                            $img_stmt->bind_param("isi", $new_property_id, $relative, $is_primary);
                            $img_stmt->execute();
                            $img_stmt->close();
                        }
                    }
                }
                finfo_close($finfo);
            }

            // Audio
            if (!empty($_FILES['audio']) && is_array($_FILES['audio']['name'])) {
                $allowed_audio_mime = ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/wav' => 'wav', 'audio/ogg' => 'ogg', 'audio/m4a' => 'm4a', 'audio/mp4' => 'm4a', 'video/mp4' => 'm4a'];
                $max_audio_size = 10 * 1024 * 1024;
                $finfo2 = finfo_open(FILEINFO_MIME_TYPE);
                foreach ($_FILES['audio']['name'] as $idx => $name) {
                    if ($_FILES['audio']['error'][$idx] !== UPLOAD_ERR_OK) { continue; }
                    if ($_FILES['audio']['size'][$idx] > $max_audio_size) { continue; }
                    $tmp = $_FILES['audio']['tmp_name'][$idx];
                    $mime = finfo_file($finfo2, $tmp);
                    if (!isset($allowed_audio_mime[$mime])) { continue; }
                    $ext = $allowed_audio_mime[$mime];
                    $filename = 'property_' . $new_property_id . '_audio_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $audio_dir . $filename;
                    $relative = 'uploads/audio/' . $filename;
                    if (move_uploaded_file($tmp, $dest)) {
                        $ins = "INSERT INTO property_audio_notes (property_id, file_path, duration) VALUES (?, ?, ?)";
                        $duration = null;
                        if ($aud_stmt = $conn->prepare($ins)) {
                            $aud_stmt->bind_param("isi", $new_property_id, $relative, $duration);
                            $aud_stmt->execute();
                            $aud_stmt->close();
                        }
                    }
                }
                finfo_close($finfo2);
            }

            $_SESSION["success_message"] = t('property_added_success');
            header("Location: commercial_properties.php");
            exit();
        } else {
            error_log("DB Error (add_other_property): " . $stmt->error);
            $errors[] = t('database_error') . ': ' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("DB Prepare Error (add_other_property): " . $conn->error);
        $errors[] = t('database_error');
    }
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
        
        /* Upload area styles */
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background-color: #f9f9f9;
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #0056b3;
            background-color: #f0f8ff;
        }
        .upload-btn {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .upload-btn:hover {
            background-color: #004494;
        }
        
        /* Preview styles */
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .image-preview {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .image-preview img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }
        .remove-file {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            line-height: 1;
        }
        
        .audio-notes {
            margin-top: 15px;
        }
        .audio-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .audio-preview span {
            flex: 1;
        }
        .audio-preview .remove-file {
            position: static;
            margin-left: auto;
        }
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

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="form-container" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>

        <!-- Listing Type (Hidden - determined by session mode) -->
        <input type="hidden" name="listing_type" value="<?php echo htmlspecialchars($current_mode); ?>">

        <div class="form-section">
            <h3><?php echo t('basic_info'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label><?php echo t('listing_type'); ?>:</label>
                    <div class="form-display"><?php echo ($current_mode === 'Primary') ? t('primary_properties') : t('resale_properties'); ?></div>
                </div>
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
                <div class="form-group">
                    <label for="year_built"><?php echo t('year_built'); ?>:</label>
                    <input type="number" id="year_built" name="year_built" class="form-input" value="<?php echo htmlspecialchars($_POST['year_built'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="unit_floor"><?php echo t('unit_floor'); ?>:</label>
                    <input type="text" id="unit_floor" name="unit_floor" class="form-input" value="<?php echo htmlspecialchars($_POST['unit_floor'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="net_area"><?php echo t('net_area'); ?> (<?php echo t('sqm'); ?>):</label>
                    <input type="number" step="0.01" id="net_area" name="net_area" class="form-input" value="<?php echo htmlspecialchars($_POST['net_area'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="loading_percentage"><?php echo t('loading_percentage'); ?> (%):</label>
                    <input type="number" step="0.01" id="loading_percentage" name="loading_percentage" class="form-input" value="<?php echo htmlspecialchars($_POST['loading_percentage'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><?php echo t('pricing_and_payment'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="price_cash"><?php echo t('price_cash'); ?> (EGP):</label>
                    <input type="number" step="0.01" id="price_cash" name="price_cash" class="form-input" value="<?php echo htmlspecialchars($_POST['price_cash'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="price_installments"><?php echo t('price_installments'); ?> (EGP):</label>
                    <input type="number" step="0.01" id="price_installments" name="price_installments" class="form-input" value="<?php echo htmlspecialchars($_POST['price_installments'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="meter_price_cash"><?php echo t('meter_price_cash'); ?> (EGP):</label>
                    <input type="number" step="0.01" id="meter_price_cash" name="meter_price_cash" class="form-input" value="<?php echo htmlspecialchars($_POST['meter_price_cash'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="meter_price_installments"><?php echo t('meter_price_installments'); ?> (EGP):</label>
                    <input type="number" step="0.01" id="meter_price_installments" name="meter_price_installments" class="form-input" value="<?php echo htmlspecialchars($_POST['meter_price_installments'] ?? ''); ?>">
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

        <!-- Location Section -->
        <div class="form-section">
            <h3><?php echo t("location"); ?></h3>
            <div class="form-grid">
                <div>
                    <label for="latitude"><?php echo t("latitude"); ?></label>
                    <input type="text" id="latitude" name="latitude" class="form-input" value="<?php echo htmlspecialchars($_POST["latitude"] ?? ""); ?>">
                </div>
                <div>
                    <label for="longitude"><?php echo t("longitude"); ?></label>
                    <input type="text" id="longitude" name="longitude" class="form-input" value="<?php echo htmlspecialchars($_POST["longitude"] ?? ""); ?>">
                </div>
            </div>
        </div>

        <!-- Seller Information Section -->
        <div class="form-section">
            <h3><?php echo t("seller_information"); ?></h3>
            <div class="form-grid">
                <div>
                    <label for="seller_type"><?php echo t("seller_type"); ?></label>
                    <select id="seller_type" name="seller_type" class="form-input">
                        <option value=""><?php echo t("select_seller_type"); ?></option>
                        <option value="مالك" <?php echo (($_POST["seller_type"] ?? "") == "مالك") ? "selected" : ""; ?>><?php echo t("owner"); ?></option>
                        <option value="وسيط" <?php echo (($_POST["seller_type"] ?? "") == "وسيط") ? "selected" : ""; ?>><?php echo t("broker"); ?></option>
                        <option value="وكيل" <?php echo (($_POST["seller_type"] ?? "") == "وكيل") ? "selected" : ""; ?>><?php echo t("agent"); ?></option>
                        <option value="بواب" <?php echo (($_POST["seller_type"] ?? "") == "بواب") ? "selected" : ""; ?>><?php echo t("doorman"); ?></option>
                        <option value="شركة مطورة" <?php echo (($_POST["seller_type"] ?? "") == "شركة مطورة") ? "selected" : ""; ?>><?php echo t("development_company"); ?></option>
                        <option value="شركة تشطيب" <?php echo (($_POST["seller_type"] ?? "") == "شركة تشطيب") ? "selected" : ""; ?>><?php echo t("finishing_company"); ?></option>
                    </select>
                </div>
                <div>
                    <label for="seller_name"><?php echo t("seller_name"); ?></label>
                    <input type="text" id="seller_name" name="seller_name" class="form-input" value="<?php echo htmlspecialchars($_POST["seller_name"] ?? ""); ?>">
                </div>
                <div>
                    <label for="seller_phone"><?php echo t("seller_phone"); ?></label>
                    <input type="tel" id="seller_phone" name="seller_phone" class="form-input" value="<?php echo htmlspecialchars($_POST["seller_phone"] ?? ""); ?>">
                </div>
            </div>
        </div>

        <!-- Media Upload Section -->
        <div class="form-section">
            <h3>📷 <?php echo t('property_images'); ?></h3>
            
            <!-- Image Upload Area -->
            <div id="image-upload-area" class="upload-area">
                <p>📁 Drag and drop images here or click to select</p>
                <button type="button" id="image-upload-btn" class="upload-btn">Choose Images</button>
                <input type="file" id="image-upload" name="images[]" accept="image/jpeg,image/jpg,image/png,image/webp" multiple style="display: none;">
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    Supported formats: JPG, JPEG, PNG, WEBP | Max size: 10MB per image
                </p>
            </div>

            <!-- Selected Images Preview -->
            <div id="image-preview" class="images-grid" style="display: none;">
                <p>Selected images:</p>
                <div id="image-thumbnails"></div>
            </div>
        </div>

        <!-- Audio Notes Section -->
        <div class="form-section">
            <h3>🎤 <?php echo t('voice_notes'); ?></h3>
            
            <!-- Audio Upload Area -->
            <div id="audio-upload-area" class="upload-area">
                <p>🎵 Drag and drop audio files here or click to select</p>
                <button type="button" id="audio-upload-btn" class="upload-btn">Choose Audio Files</button>
                <input type="file" id="audio-upload" name="audio[]" accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/mp4,audio/m4a" multiple style="display: none;">
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    Supported formats: MP3, WAV, OGG, M4A | Max size: 10MB per file
                </p>
            </div>

            <!-- Selected Audio Preview -->
            <div id="audio-preview" class="audio-notes" style="display: none;">
                <p>Selected audio files:</p>
                <div id="audio-list"></div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo t("add_property_button"); ?></button>
            <a href="commercial_properties.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image upload functionality
    const imageUploadArea = document.getElementById('image-upload-area');
    const imageUploadBtn = document.getElementById('image-upload-btn');
    const imageUploadInput = document.getElementById('image-upload');
    const imagePreview = document.getElementById('image-preview');
    const imageThumbnails = document.getElementById('image-thumbnails');
    
    // Audio upload functionality
    const audioUploadArea = document.getElementById('audio-upload-area');
    const audioUploadBtn = document.getElementById('audio-upload-btn');
    const audioUploadInput = document.getElementById('audio-upload');
    const audioPreview = document.getElementById('audio-preview');
    const audioList = document.getElementById('audio-list');
    
    let selectedImages = [];
    let selectedAudio = [];
    
    // Image upload button click
    imageUploadBtn.addEventListener('click', function() {
        imageUploadInput.click();
    });
    
    // Audio upload button click
    audioUploadBtn.addEventListener('click', function() {
        audioUploadInput.click();
    });
    
    // Image file selection
    imageUploadInput.addEventListener('change', function(e) {
        handleImageFiles(e.target.files);
    });
    
    // Audio file selection
    audioUploadInput.addEventListener('change', function(e) {
        handleAudioFiles(e.target.files);
    });
    
    // Image drag and drop
    imageUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        imageUploadArea.classList.add('dragover');
    });
    
    imageUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        imageUploadArea.classList.remove('dragover');
    });
    
    imageUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        imageUploadArea.classList.remove('dragover');
        handleImageFiles(e.dataTransfer.files);
    });
    
    // Audio drag and drop
    audioUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        audioUploadArea.classList.add('dragover');
    });
    
    audioUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        audioUploadArea.classList.remove('dragover');
    });
    
    audioUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        audioUploadArea.classList.remove('dragover');
        handleAudioFiles(e.dataTransfer.files);
    });
    
    function handleImageFiles(files) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        Array.from(files).forEach(file => {
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type: ' + file.name + '. Please select JPG, JPEG, PNG, or WEBP files.');
                return;
            }
            
            if (file.size > maxSize) {
                alert('File too large: ' + file.name + '. Maximum size is 10MB.');
                return;
            }
            
            selectedImages.push(file);
        });
        
        updateImagePreview();
        updateImageInput();
    }
    
    function handleAudioFiles(files) {
        const allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/m4a'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        Array.from(files).forEach(file => {
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type: ' + file.name + '. Please select MP3, WAV, OGG, or M4A files.');
                return;
            }
            
            if (file.size > maxSize) {
                alert('File too large: ' + file.name + '. Maximum size is 10MB.');
                return;
            }
            
            selectedAudio.push(file);
        });
        
        updateAudioPreview();
        updateAudioInput();
    }
    
    function updateImagePreview() {
        imageThumbnails.innerHTML = '';
        
        if (selectedImages.length > 0) {
            imagePreview.style.display = 'block';
            
            selectedImages.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'image-preview';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <button type="button" class="remove-file" onclick="removeImage(${index})">×</button>
                    `;
                    imageThumbnails.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        } else {
            imagePreview.style.display = 'none';
        }
    }
    
    function updateAudioPreview() {
        audioList.innerHTML = '';
        
        if (selectedAudio.length > 0) {
            audioPreview.style.display = 'block';
            
            selectedAudio.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'audio-preview';
                div.innerHTML = `
                    <span>🎵 ${file.name}</span>
                    <button type="button" class="remove-file" onclick="removeAudio(${index})">×</button>
                `;
                audioList.appendChild(div);
            });
        } else {
            audioPreview.style.display = 'none';
        }
    }
    
    function updateImageInput() {
        const dt = new DataTransfer();
        selectedImages.forEach(file => dt.items.add(file));
        imageUploadInput.files = dt.files;
    }
    
    function updateAudioInput() {
        const dt = new DataTransfer();
        selectedAudio.forEach(file => dt.items.add(file));
        audioUploadInput.files = dt.files;
    }
    
    // Global functions for remove buttons
    window.removeImage = function(index) {
        selectedImages.splice(index, 1);
        updateImagePreview();
        updateImageInput();
    };
    
    window.removeAudio = function(index) {
        selectedAudio.splice(index, 1);
        updateAudioPreview();
        updateAudioInput();
    };
});
</script>

</body>
</html>


