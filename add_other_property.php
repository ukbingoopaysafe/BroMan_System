<?php
require_once __DIR__ . 
'/lang/translate.php'; // Translation handler
require_once 'permissions.php'; // Include permissions system
require_once 'session_check.php'; // Include session timeout check
require_once __DIR__ . '/db.php'; // Database connection
require_once __DIR__ . '/security.php';

ensure_secure_session_started();

$csrf_token = generateCSRFToken();

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
$sql_finishing = "SELECT FinishingID, FinishingName, FinishingName_ar FROM propertyfinishingtypes ORDER BY FinishingID";
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
    // Bathrooms column removed from schema
    $unit_design = trim($_POST['unit_design'] ?? '');
    $compound_area = null; // Not applicable
    $finishing_type_id = filter_input(INPUT_POST, 'finishing_type_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    // Price columns removed from schema
    $down_payment_percentage = filter_input(INPUT_POST, 'down_payment_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    // Allow fractional years (e.g. 1.5). Use float validation and keep NULL when not provided
    $payment_years = null;
    if (isset($_POST['payment_years']) && trim($_POST['payment_years']) !== '') {
        $payment_years = filter_var($_POST['payment_years'], FILTER_VALIDATE_FLOAT);
    }
    $delivery_date_str = trim($_POST['delivery_date'] ?? '');
    $status = trim($_POST['status'] ?? 'available');
    $employee_id = $_SESSION['employee_id'] ?? null;
    $sales_manager_name = trim($_POST['sales_manager_name'] ?? null);
    $sales_manager_phone = trim($_POST['sales_manager_phone'] ?? null);
    $salesperson_name = trim($_POST['salesperson_name'] ?? null);
    $salesperson_phone = trim($_POST['salesperson_phone'] ?? null);
    $delivery_date = !empty($delivery_date_str) ? $delivery_date_str : null;
    
    // New Phase 2 fields
    $year_built = trim($_POST['year_built'] ?? null);
    $unit_floor = trim($_POST['unit_floor'] ?? null);
    $net_area = filter_input(INPUT_POST, 'net_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $loading_percentage = filter_input(INPUT_POST, 'loading_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price_cash = filter_input(INPUT_POST, 'price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price_installments = filter_input(INPUT_POST, 'price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $meter_price_cash = filter_input(INPUT_POST, 'meter_price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $seller_type = trim($_POST['seller_type'] ?? '');
    if ($seller_type === '') { $seller_type = null; }
    if ($seller_type !== null && !in_array($seller_type, allowed_seller_types(), true)) {
        $seller_type = normalize_seller_type_for_db($conn, $seller_type);
    }
    $meter_price_installments = filter_input(INPUT_POST, 'meter_price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $seller_name = trim($_POST['seller_name'] ?? null);
    $seller_phone = trim($_POST['seller_phone'] ?? null);
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

    $sql = "INSERT INTO Properties (PropertyTypeID, Title, Description, Location, Area, Bedrooms, Status, listing_type, EmployeeID, unit_design, finishing_type_id, compound_area, down_payment_percentage, payment_years, delivery_date, ProjectName, min_area, max_area, avg_area, district, governorate, company_name, FloorsCount, sales_manager_name, sales_manager_phone, salesperson_name, salesperson_phone, year_built, unit_floor, net_area, loading_percentage, price_cash, price_installments, meter_price_cash, meter_price_installments, seller_type, seller_name, seller_phone, latitude, longitude, DateAdded) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        // Build bind-vars and types dynamically to preserve decimal types (payment_years)
        $bind_vars = [
            $property_type_id, $title, $description, $location, $area, $bedrooms, $status, $listing_type, $employee_id, $unit_design, $finishing_type_id, $compound_area, $down_payment_percentage, $payment_years, $delivery_date, $project_name, $min_area, $max_area, $avg_area, $district, $governorate, $company_name, $floors_count, $sales_manager_name, $sales_manager_phone, $salesperson_name, $salesperson_phone, $year_built, $unit_floor, $net_area, $loading_percentage, $price_cash, $price_installments, $meter_price_cash, $meter_price_installments, $seller_type, $seller_name, $seller_phone, $latitude, $longitude
        ];

        $types = '';
        foreach ($bind_vars as $bv) {
            if (is_int($bv)) {
                $types .= 'i';
            } elseif (is_float($bv)) {
                $types .= 'd';
            } elseif (is_numeric($bv)) {
                $types .= (strpos((string)$bv, '.') !== false) ? 'd' : 'i';
            } else {
                $types .= 's';
            }
        }

        $params = array_merge([$stmt, $types], $bind_vars);
        call_user_func_array('safe_bind_param', $params);

    // Debug: log key fields before execute to help diagnose missing storage
    error_log("DEBUG add_other_property: year_built=" . var_export($year_built, true) . " seller_type=" . var_export($seller_type, true));
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

            // Videos
            if (!empty($_FILES['videos']) && is_array($_FILES['videos']['name'])) {
                $allowed_video_mime = [
                    'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv',
                    'video/avi' => 'avi', 'video/quicktime' => 'mov', 'video/x-ms-wmv' => 'wmv', 'video/x-flv' => 'flv'
                ];
                $max_video_size = 250 * 1024 * 1024; // 250MB
                $finfo3 = finfo_open(FILEINFO_MIME_TYPE);
                foreach ($_FILES['videos']['name'] as $idx => $name) {
                    if ($_FILES['videos']['error'][$idx] !== UPLOAD_ERR_OK) { continue; }
                    if ($_FILES['videos']['size'][$idx] > $max_video_size) { continue; }
                    $tmp = $_FILES['videos']['tmp_name'][$idx];
                    $mime = finfo_file($finfo3, $tmp);
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    if (isset($allowed_video_mime[$mime])) {
                        $ext = $allowed_video_mime[$mime];
                    }
                    if (empty($ext)) {
                        error_log('Skipping video (no extension/mime): ' . $name . ' mime=' . $mime);
                        continue;
                    }
                    $filename = 'property_' . $new_property_id . '_video_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $base_upload_dir . 'videos/' . $filename;
                    $relative = 'uploads/videos/' . $filename;
                    if (move_uploaded_file($tmp, $dest)) {
                        $employee_id = $_SESSION['employee_id'] ?? 0;
                        $emp_id_int = (int)$employee_id;
                        $ins = "INSERT INTO property_videos (PropertyID, VideoPath, VideoName, VideoSize, EmployeeID) VALUES (?, ?, ?, ?, ?)";
                        if ($vstmt = $conn->prepare($ins)) {
                            $videoSize = filesize($dest);
                            $vstmt->bind_param("issii", $new_property_id, $relative, $name, $videoSize, $emp_id_int);
                            $vstmt->execute();
                            $vstmt->close();
                        }
                    } else {
                        error_log('Failed to move uploaded video file ' . $tmp . ' to ' . $dest);
                    }
                }
                finfo_close($finfo3);
            }

            // Attachments
            if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
                $attachments_dir = $base_upload_dir . 'attachments/';
                if (!is_dir($attachments_dir)) { mkdir($attachments_dir, 0755, true); }
                $allowed_ext = ['jpg','jpeg','png','webp','gif','pdf','doc','docx','xls','xlsx','txt','csv','odt'];
                $max_attach_size = 10 * 1024 * 1024; // 10MB
                $finfo4 = finfo_open(FILEINFO_MIME_TYPE);
                foreach ($_FILES['attachments']['name'] as $idx => $name) {
                    if ($_FILES['attachments']['error'][$idx] !== UPLOAD_ERR_OK) { continue; }
                    if ($_FILES['attachments']['size'][$idx] > $max_attach_size) { continue; }
                    $tmp = $_FILES['attachments']['tmp_name'][$idx];
                    $mime = finfo_file($finfo4, $tmp);
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_ext)) {
                        $mime_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','text/plain'=>'txt','text/csv'=>'csv'];
                        if (isset($mime_map[$mime])) { $ext = $mime_map[$mime]; }
                    }
                    if (!in_array($ext, $allowed_ext)) { continue; }
                    $filename = 'property_' . $new_property_id . '_attach_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $attachments_dir . $filename;
                    $relative = 'uploads/attachments/' . $filename;
                    if (move_uploaded_file($tmp, $dest)) {
                        $emp_id = $_SESSION['employee_id'] ?? null;
                        $ins = "INSERT INTO property_attachments (PropertyID, FileName, FilePath, FileType, FileSize, EmployeeID) VALUES (?, ?, ?, ?, ?, ?)";
                        if ($ast = $conn->prepare($ins)) {
                            $size = filesize($dest);
                            $emp_int = ($emp_id === null) ? null : (int)$emp_id;
                            $ast->bind_param("isssii", $new_property_id, $name, $relative, $mime, $size, $emp_int);
                            $ast->execute();
                            $ast->close();
                        }
                    }
                }
                finfo_close($finfo4);
            }

            // Documents (PDFs uploaded as part of media)
            if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
                $documents_dir = $base_upload_dir . 'documents/';
                if (!is_dir($documents_dir)) { mkdir($documents_dir, 0755, true); }
                $allowed_doc_mime = ['application/pdf' => 'pdf'];
                $max_doc_size = 10 * 1024 * 1024; // 10MB
                $finfoDoc = finfo_open(FILEINFO_MIME_TYPE);
                foreach ($_FILES['documents']['name'] as $idx => $name) {
                    if ($_FILES['documents']['error'][$idx] !== UPLOAD_ERR_OK) { continue; }
                    if ($_FILES['documents']['size'][$idx] > $max_doc_size) { continue; }
                    $tmp = $_FILES['documents']['tmp_name'][$idx];
                    $mime = finfo_file($finfoDoc, $tmp);
                    if (!isset($allowed_doc_mime[$mime])) { continue; }
                    $ext = $allowed_doc_mime[$mime];
                    $filename = 'property_' . $new_property_id . '_doc_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $documents_dir . $filename;
                    $relative = 'uploads/documents/' . $filename;
                    if (move_uploaded_file($tmp, $dest)) {
                        $ins = "INSERT INTO property_documents (property_id, file_path, file_name, file_size) VALUES (?, ?, ?, ?)";
                        if ($dstmt = $conn->prepare($ins)) {
                            $size = filesize($dest);
                            $dstmt->bind_param("issi", $new_property_id, $relative, $name, $size);
                            $dstmt->execute();
                            $dstmt->close();
                        }
                    }
                }
                finfo_close($finfoDoc);
            }

            // Move any temporary uploads into property
            if (function_exists('moveTempUploadsToProperty')) {
                $moved = moveTempUploadsToProperty($new_property_id, session_id(), $conn);
                error_log('Moved temp uploads for session ' . session_id() . ': ' . json_encode($moved));
            }

            // Use robust flash helper and set legacy session key for compatibility (mirror edit behavior)
            require_once __DIR__ . '/flash.php';
            if (function_exists('add_flash')) { add_flash(t('property_added_success'), 6); }
            // session is written before redirect
            session_write_close(); // Force session data to be saved now
            header("Location: view_property.php?id=" . $new_property_id . "&added=1");
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
    <!-- Map provider: Leaflet + OpenStreetMap (Leaflet-only) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="form-container" enctype="multipart/form-data" data-property-id="0" data-csrf-token="<?php echo $csrf_token; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

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
                
                <!-- Interactive Map Section -->
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label><?php echo t('select_location_on_map'); ?>:</label>

                    <!-- Google Maps link input -->
                    <div style="margin:8px 0; display:flex; gap:8px; align-items:center;">
                        <input type="text" id="google_map_link" class="form-input" style="flex:1;" placeholder="https://www.google.com/maps/..." value="<?php echo htmlspecialchars($_POST['google_map_link'] ?? ''); ?>">
                        <button type="button" id="parse_google_link" class="btn">تحديد من الرابط</button>
                    </div>
                    <small>لصق رابط خرائط جوجل ثم اضغط "تحديد من الرابط"</small>

                    <div id="map" style="height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 4px;"></div>
                    
                    <!-- Hidden inputs for coordinates -->
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
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
                            <option value="<?php echo $finishing['FinishingID']; ?>" <?php echo (($_POST['finishing_type_id'] ?? '') == $finishing['FinishingID']) ? 'selected' : ''; ?>>
                                <?php 
                                if (get_lang_code() === 'ar' && !empty($finishing['FinishingName_ar'])) {
                                    echo htmlspecialchars($finishing['FinishingName_ar']);
                                } else {
                                    echo htmlspecialchars($finishing['FinishingName']);
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="year_built"><?php echo t('year_built'); ?>:</label>
                    <input type="text" id="year_built" name="year_built" class="form-input" value="<?php echo htmlspecialchars($_POST['year_built'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="unit_floor"><?php echo t('unit_floor'); ?>:</label>
                    <input type="text" id="unit_floor" name="unit_floor" class="form-input" value="<?php echo htmlspecialchars($_POST['unit_floor'] ?? ''); ?>">
                </div>
                <!-- Add Unit Design input (was missing in this form) -->
                <div class="form-group">
                    <label for="unit_design"><?php echo t('unit_design'); ?>:</label>
                    <input type="text" id="unit_design" name="unit_design" class="form-input" value="<?php echo htmlspecialchars($_POST['unit_design'] ?? ''); ?>">
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
                    <label for="net_area"><?php echo t('net_area'); ?>:</label>
                    <input type="number" step="0.01" id="net_area" name="net_area" class="form-input" value="<?php echo htmlspecialchars($_POST['net_area'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="loading_percentage"><?php echo t('loading_percentage'); ?>:</label>
                    <input type="number" step="0.01" id="loading_percentage" name="loading_percentage" class="form-input" min="0" max="100" value="<?php echo htmlspecialchars($_POST['loading_percentage'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><?php echo t('pricing_and_payment'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="price_cash"><?php echo t('price_cash'); ?>:</label>
                    <input type="number" step="0.01" id="price_cash" name="price_cash" class="form-input" value="<?php echo htmlspecialchars($_POST['price_cash'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="price_installments"><?php echo t('price_installments'); ?>:</label>
                    <input type="number" step="0.01" id="price_installments" name="price_installments" class="form-input" value="<?php echo htmlspecialchars($_POST['price_installments'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="meter_price_cash"><?php echo t('meter_price_cash'); ?>:</label>
                    <input type="number" step="0.01" id="meter_price_cash" name="meter_price_cash" class="form-input" value="<?php echo htmlspecialchars($_POST['meter_price_cash'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="meter_price_installments"><?php echo t('meter_price_installments'); ?>:</label>
                    <input type="number" step="0.01" id="meter_price_installments" name="meter_price_installments" class="form-input" value="<?php echo htmlspecialchars($_POST['meter_price_installments'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="down_payment_percentage"><?php echo t('down_payment_percentage'); ?> (%):</label>
                    <input type="number" step="0.01" id="down_payment_percentage" name="down_payment_percentage" class="form-input" value="<?php echo htmlspecialchars($_POST['down_payment_percentage'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="payment_years"><?php echo t('payment_years'); ?>:</label>
                    <input type="number" step="0.5" min="0.5" id="payment_years" name="payment_years" class="form-input" value="<?php echo isset($_POST['payment_years']) ? htmlspecialchars($_POST['payment_years']) : ''; ?>" placeholder="مثال: 1.5 للسنة والنصف">
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

        <!-- Owner/Seller Information Section -->
        <div class="form-section">
            <h3><?php echo t("owner_information"); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="seller_type"><?php echo t("seller_type"); ?>:</label>
                    <select id="seller_type" name="seller_type" class="form-input">
                        <option value=""><?php echo t('select_seller_type'); ?></option>
                        <option value="مالك" <?php echo (($_POST['seller_type'] ?? '') == 'مالك') ? 'selected' : ''; ?>><?php echo t('seller_type_owner'); ?></option>
                        <option value="وسيط" <?php echo (($_POST['seller_type'] ?? '') == 'وسيط') ? 'selected' : ''; ?>><?php echo t('seller_type_broker'); ?></option>
                        <option value="وكيل" <?php echo (($_POST['seller_type'] ?? '') == 'وكيل') ? 'selected' : ''; ?>><?php echo t('seller_type_agent'); ?></option>
                        <option value="بواب" <?php echo (($_POST['seller_type'] ?? '') == 'بواب') ? 'selected' : ''; ?>><?php echo t('seller_type_doorman'); ?></option>
                        <option value="شركة مطورة" <?php echo (($_POST['seller_type'] ?? '') == 'شركة مطورة') ? 'selected' : ''; ?>><?php echo t('seller_type_developer_company'); ?></option>
                        <option value="شركة تشطيب" <?php echo (($_POST['seller_type'] ?? '') == 'شركة تشطيب') ? 'selected' : ''; ?>><?php echo t('seller_type_finishing_company'); ?></option>
                        <option value="شركة تسويق" <?php echo (($_POST['seller_type'] ?? '') == 'شركة تسويق') ? 'selected' : ''; ?>><?php echo t('seller_type_marketing_company'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="seller_name"><?php echo t("seller_name"); ?></label>
                    <input type="text" id="seller_name" name="seller_name" class="form-input" value="<?php echo htmlspecialchars($_POST["seller_name"] ?? ""); ?>">
                </div>
                <div class="form-group">
                    <label for="seller_phone"><?php echo t("seller_phone"); ?></label>
                    <input type="tel" id="seller_phone" name="seller_phone" class="form-input" value="<?php echo htmlspecialchars($_POST["seller_phone"] ?? ""); ?>">
                </div>
            </div>
        </div>

        <!-- Unified Media Upload Section -->
        <div class="form-section">
            <?php include 'unified_media_uploader.php'; ?>
            <?php include 'unified_attachment_uploader.php'; ?>
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
    // Initialize interactive map using Leaflet only
    let map;
    let marker;

    function initMap() {
        const defaultLat = 30.0444;
        const defaultLng = 31.2357;

        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const lat = parseFloat(latInput.value) || defaultLat;
        const lng = parseFloat(lngInput.value) || defaultLng;

        if (typeof L === 'undefined') {
            console.error('Leaflet is not loaded.');
            return;
        }

        map = L.map('map').setView([lat, lng], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (!isNaN(parseFloat(latInput.value)) && !isNaN(parseFloat(lngInput.value))) {
            marker = L.marker([lat, lng]).addTo(map);
        }

        map.on('click', function(e) {
            const clickedLat = e.latlng.lat;
            const clickedLng = e.latlng.lng;

            latInput.value = clickedLat;
            lngInput.value = clickedLng;

            if (marker) { map.removeLayer(marker); }
            marker = L.marker([clickedLat, clickedLng]).addTo(map);
        });

        // Helper: extract lat/lng from common Google Maps link formats
        function extractLatLngFromGoogleUrl(url) {
            try {
                if (!url || typeof url !== 'string') return null;

                const atMatch = url.match(/@([0-9.+-]+),([0-9.+-]+)/);
                if (atMatch) return {lat: parseFloat(atMatch[1]), lng: parseFloat(atMatch[2])};

                const qMatch = url.match(/[?&](?:query|q)=\s*([0-9.+-]+),\s*([0-9.+-]+)/);
                if (qMatch) return {lat: parseFloat(qMatch[1]), lng: parseFloat(qMatch[2])};

                const dMatch = url.match(/!3d([0-9.+-]+)!4d([0-9.+-]+)/);
                if (dMatch) return {lat: parseFloat(dMatch[1]), lng: parseFloat(dMatch[2])};

                return null;
            } catch (e) { return null; }
        }

        const parseBtn = document.getElementById('parse_google_link');
        if (parseBtn) {
            parseBtn.addEventListener('click', function() {
                const input = document.getElementById('google_map_link');
                const val = input ? input.value.trim() : '';
                const coords = extractLatLngFromGoogleUrl(val);
                if (coords && !isNaN(coords.lat) && !isNaN(coords.lng)) {
                    latInput.value = coords.lat;
                    lngInput.value = coords.lng;
                    if (marker) { map.removeLayer(marker); }
                    marker = L.marker([coords.lat, coords.lng]).addTo(map);
                    map.setView([coords.lat, coords.lng], 15);
                } else {
                    alert('لم يتم العثور على إحداثيات في الرابط. الرجاء التحقق من أن الرابط من خرائط جوجل ويتضمن إحداثيات.');
                }
            });
        }
    }

    window.addEventListener('load', initMap);
});
</script>

</body>
</html>


