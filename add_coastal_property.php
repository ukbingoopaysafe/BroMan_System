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

// --- Fetch Lookups (Similar to add_residential) ---
$property_types = [];
$unit_designs = [];
$finishing_types = [];
$employees = [];

// Fetch Property Types (Filter for Coastal Type = 2 - Assuming 2 is Coastal)
$sql_types = "SELECT PropertyTypeID, TypeName FROM PropertyTypes WHERE PropertyTypeID = 2";
if ($result = $conn->query($sql_types)) {
    while ($row = $result->fetch_assoc()) {
        $property_types[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching coastal property types: " . $conn->error);
}

// Fetch Unit Designs
$sql_designs = "SELECT DesignID, DesignName FROM propertyunitdesigns ORDER BY DesignID";
if ($result = $conn->query($sql_designs)) {
    while ($row = $result->fetch_assoc()) {
        $unit_designs[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching unit designs: " . $conn->error);
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
    $property_type_id = 2; // Hardcoded for Coastal
    $listing_type = $_SESSION['listing_context'] ?? 'Primary';
    $project_name = trim($_POST['project_name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? null);
    $governorate = trim($_POST['governorate'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $location = trim($_POST['location'] ?? null);
    $company_name = trim($_POST['company_name'] ?? null);
    $compound_area = filter_input(INPUT_POST, 'compound_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $floors_count = filter_input(INPUT_POST, 'floors_count', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $unit_design_id = filter_input(INPUT_POST, 'unit_design_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $min_area = filter_input(INPUT_POST, 'min_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_area = filter_input(INPUT_POST, 'max_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $avg_area = filter_input(INPUT_POST, 'avg_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $area = filter_input(INPUT_POST, 'area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $bedrooms = null; // Bedrooms field removed from this form
    $bathrooms = filter_input(INPUT_POST, 'bathrooms', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
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
    $owner_name = trim($_POST['owner_name'] ?? null);
    $owner_phone = trim($_POST['owner_phone'] ?? null);
    $delivery_date = !empty($delivery_date_str) ? $delivery_date_str : null;

    // --- Database Insertion ---
    $sql = "INSERT INTO Properties (PropertyTypeID, Title, Description, Location, Price, Area, Bedrooms, Bathrooms, Status, listing_type, EmployeeID, unit_design_id, finishing_type_id, compound_area, down_payment_percentage, payment_years, delivery_date, ProjectName, min_price, max_price, min_area, max_area, avg_area, district, governorate, company_name, FloorsCount, sales_manager_name, sales_manager_phone, salesperson_name, salesperson_phone, owner_name, owner_phone, DateAdded) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param(
            "isssddiissiiiddssssdddddsssisssss",
            $property_type_id, $title, $description, $location, $price, $area, $bedrooms, $bathrooms, $status, $listing_type, $employee_id, $unit_design_id, $finishing_type_id, $compound_area, $down_payment_percentage, $payment_years, $delivery_date, $project_name, $min_price, $max_price, $min_area, $max_area, $avg_area, $district, $governorate, $company_name, $floors_count, $sales_manager_name, $sales_manager_phone, $salesperson_name, $salesperson_phone, $owner_name, $owner_phone
        );

        if ($stmt->execute()) {
            $new_property_id = $conn->insert_id;

            // Handle optional media uploads (same as residential)
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
            header("Location: coastal_properties.php");
            exit();
        } else {
            error_log("DB Error (add_coastal_property): " . $stmt->error);
            $errors[] = t('database_error') . ': ' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("DB Prepare Error (add_coastal_property): " . $conn->error);
        $errors[] = t('database_error');
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t("add_coastal_property_title"); ?></title>
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
    <h1><?php echo t("add_coastal_property_title"); ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="success-message">
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="form-container" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>

        <!-- Property Type (Hidden/Fixed for this page) -->
        <input type="hidden" name="property_type_id" value="2">
        
        <!-- Listing Type (Hidden - determined by session mode) -->
        <input type="hidden" name="listing_type" value="<?php echo htmlspecialchars($current_mode); ?>"> 

        <!-- Form Sections (Identical structure to Residential, including Compound Area) -->
        
        <div class="form-section">
            <h3><?php echo t('basic_info'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label><?php echo t('listing_type'); ?>:</label>
                    <div class="form-display"><?php echo ($current_mode === 'Primary') ? t('primary_properties') : t('resale_properties'); ?></div>
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
            <h3><?php echo t('area_and_design'); ?></h3>
            <div class="form-grid">
                 <div class="form-group">
                    <label for="compound_area"><?php echo t('compound_area'); ?> (<?php echo t('faddan'); ?>):</label>
                    <input type="number" step="0.01" id="compound_area" name="compound_area" class="form-input" value="<?php echo htmlspecialchars($_POST['compound_area'] ?? ''); ?>">
                </div>
                 <div class="form-group">
                    <label for="floors_count"><?php echo t('floors_count'); ?>:</label>
                    <input type="number" id="floors_count" name="floors_count" class="form-input" value="<?php echo htmlspecialchars($_POST['floors_count'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="unit_design_id"><?php echo t('unit_design'); ?>: *</label>
                    <select id="unit_design_id" name="unit_design_id" class="form-input" required>
                        <option value=""><?php echo t('select_unit_design'); ?></option>
                        <?php foreach ($unit_designs as $design): ?>
                            <option value="<?php echo $design['DesignID']; ?>" <?php echo (($_POST['unit_design_id'] ?? '') == $design['DesignID']) ? 'selected' : ''; ?>><?php echo t(htmlspecialchars($design['DesignName'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="finishing_type_id"><?php echo t('finishing_type'); ?>: *</label>
                    <select id="finishing_type_id" name="finishing_type_id" class="form-input" required>
                        <option value=""><?php echo t('select_finishing_type'); ?></option>
                        <?php foreach ($finishing_types as $finishing): ?>
                            <option value="<?php echo $finishing['FinishingID']; ?>" <?php echo (($_POST['finishing_type_id'] ?? '') == $finishing['FinishingID']) ? 'selected' : ''; ?>><?php echo t(htmlspecialchars($finishing['FinishingName'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Removed bedrooms field as requested -->
                <div class="form-group">
                    <label for="bathrooms"><?php echo t('bathrooms'); ?>:</label>
                    <input type="number" id="bathrooms" name="bathrooms" class="form-input" value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? ''); ?>">
                </div>
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
        </div>

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

        <!-- Owner Information Section -->
        <div class="form-section">
            <h3><?php echo t("owner_information"); ?></h3>
            <div class="form-grid">
                <div>
                    <label for="owner_name"><?php echo t("owner_name"); ?></label>
                    <input type="text" id="owner_name" name="owner_name" class="form-input" value="<?php echo htmlspecialchars($_POST["owner_name"] ?? ""); ?>">
                </div>
                <div>
                    <label for="owner_phone"><?php echo t("owner_phone"); ?></label>
                    <input type="tel" id="owner_phone" name="owner_phone" class="form-input" value="<?php echo htmlspecialchars($_POST["owner_phone"] ?? ""); ?>">
                </div>
            </div>
        </div>

        <div class="form-note">
            <p><?php echo t('add_property_media_note'); ?></p>
        </div>

        <!-- Media Upload (optional) -->
        <div class="form-section">
            <h3>📷 <?php echo t('property_images'); ?></h3>
            <input type="file" name="images[]" accept="image/jpeg,image/jpg,image/png,image/webp" multiple>
            <p style="font-size: 12px; color: #666; margin-top: 10px;">
                <?php echo t('supported_image_formats'); ?> JPG, JPEG, PNG, WEBP | <?php echo t('max_size'); ?> 10MB
            </p>
        </div>

        <div class="form-section">
            <h3>🎤 <?php echo t('voice_notes'); ?></h3>
            <input type="file" name="audio[]" accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/mp4,audio/m4a" multiple>
            <p style="font-size: 12px; color: #666; margin-top: 10px;">
                <?php echo t('supported_audio_formats'); ?> MP3, WAV, OGG, M4A | <?php echo t('max_size'); ?> 10MB
            </p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo t("add_property_button"); ?></button>
            <a href="coastal_properties.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>

</body>
</html>


