<?php
// Corrected include path for translation handler
require_once __DIR__ . '/lang/translate.php';
require_once 'permissions.php'; // Include permissions system

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check permissions - only manager and admin can edit properties
check_permission('manage_properties');

// Enforce mode selection - redirect to dashboard if no mode is set
if (!isset($_SESSION['listing_context']) || ($_SESSION['listing_context'] !== 'Primary' && $_SESSION['listing_context'] !== 'Resale')) {
    header('Location: dashboard.php');
    exit();
}

$current_mode = $_SESSION['listing_context'];

require_once 'db.php'; // Database connection
require_once 'media_functions.php'; // Media helper functions

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Define Property Type IDs
const RESIDENTIAL_PROPERTY_TYPE_ID = 1;
const COASTAL_PROPERTY_TYPE_ID = 2;
const COMMERCIAL_PROPERTY_TYPE_ID = 3;
const ADMINISTRATIVE_PROPERTY_TYPE_ID = 4;
const HOTEL_PROPERTY_TYPE_ID = 5;
const MEDICAL_PROPERTY_TYPE_ID = 6;

// Initialize variables
$property_id = $title = $property_type_id = $price = $location = $description = $status = "";
$unit_design_id = $finishing_type_id = $compound_area = $down_payment_percentage = $payment_years = $delivery_date = "";
$project_name = $min_price = $max_price = $min_area = $max_area = $avg_area = $district = $governorate = $company_name = $floors_count = $bathrooms = "";
$sales_manager_name = $sales_manager_phone = $salesperson_name = $salesperson_phone = "";

$errors = []; // Array to hold errors

// --- Fetch data for dropdowns ---

// Property Types
$property_types_list = [];
$sql_property_types = "SELECT PropertyTypeID, TypeName FROM PropertyTypes ORDER BY PropertyTypeID";
if ($result_property_types = $conn->query($sql_property_types)) {
    while ($row_type = $result_property_types->fetch_assoc()) {
        $property_types_list[] = $row_type;
    }
    $result_property_types->free();
} else {
    $errors['db_fetch'] = t('error_fetching_property_types') . ': ' . $conn->error;
    error_log("Error fetching property types: " . $conn->error);
}

// Unit Designs
$unit_designs_list = [];
$sql_unit_designs = "SELECT DesignID, DesignName FROM PropertyUnitDesigns ORDER BY DesignName";
if ($result_unit_designs = $conn->query($sql_unit_designs)) {
    while ($row_design = $result_unit_designs->fetch_assoc()) {
        $unit_designs_list[] = $row_design;
    }
    $result_unit_designs->free();
} else {
    $errors['db_fetch'] = t('error_fetching_unit_designs') . ': ' . $conn->error;
    error_log("Error fetching unit designs: " . $conn->error);
}

// Finishing Types
$finishing_types_list = [];
$sql_finishing_types = "SELECT FinishingID, FinishingName FROM PropertyFinishingTypes ORDER BY FinishingName";
if ($result_finishing_types = $conn->query($sql_finishing_types)) {
    while ($row_finish = $result_finishing_types->fetch_assoc()) {
        $finishing_types_list[] = $row_finish;
    }
    $result_finishing_types->free();
} else {
    $errors['db_fetch'] = t('error_fetching_finishing_types') . ': ' . $conn->error;
    error_log("Error fetching finishing types: " . $conn->error);
}


// --- Fetch existing property data ---
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $property_id = trim($_GET["id"]);

    $sql_fetch = "SELECT * FROM Properties WHERE PropertyID = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $property_id);

        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                // Populate variables with existing data
                $property_type_id = $row["PropertyTypeID"];
                $title = $row["Title"];
                $description = $row["Description"];
                $location = $row["Location"];
                $price = $row["Price"];
                $area = $row["Area"];
                $bathrooms = $row["Bathrooms"];
                $status = $row["Status"];
                $unit_design_id = $row["unit_design_id"];
                $finishing_type_id = $row["finishing_type_id"];
                $compound_area = $row["compound_area"];
                $down_payment_percentage = $row["down_payment_percentage"];
                $payment_years = $row["payment_years"];
                $delivery_date = $row["delivery_date"];
                $project_name = $row["ProjectName"];
                $min_price = $row["min_price"];
                $max_price = $row["max_price"];
                $min_area = $row["min_area"];
                $max_area = $row["max_area"];
                $avg_area = $row["avg_area"];
                $district = $row["district"];
                $governorate = $row["governorate"];
                $company_name = $row["company_name"] ?? '';
                $floors_count = $row["FloorsCount"] ?? null;
                $sales_manager_name = $row["sales_manager_name"] ?? '';
                $sales_manager_phone = $row["sales_manager_phone"] ?? '';
                $salesperson_name = $row["salesperson_name"] ?? '';
                $salesperson_phone = $row["salesperson_phone"] ?? '';
                $listing_type = $row["listing_type"] ?? 'Primary';
            } else {
                $_SESSION["error_message"] = t('property_not_found');
                header("Location: index_properties.php");
                exit();
            }
        } else {
            $errors['db_fetch'] = t('error_fetching_property_details') . ': ' . $stmt_fetch->error;
            error_log("Error executing property fetch: " . $stmt_fetch->error);
        }
        $stmt_fetch->close();
    } else {
        $errors['db_prepare'] = t('error_preparing_statement') . ': ' . $conn->error;
        error_log("Error preparing property fetch: " . $conn->error);
    }
} else {
    $_SESSION["error_message"] = t('no_property_id_edit');
    header("Location: index_properties.php");
    exit();
}

// Get property media
$property_images = getPropertyImages($property_id, $conn);
$property_audio_notes = getPropertyAudioNotes($property_id, $conn);

// --- Process form submission for update ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST data: " . print_r($_POST, true)); // Log all POST data
    $property_id_post = trim($_POST["property_id"]);
    if (empty($property_id_post) || $property_id_post != $property_id) {
        $errors['general'] = t('invalid_property_id');
    }

    // Sanitize and retrieve data
    $property_type_id_posted = isset($_POST['property_type_id']) ? trim($_POST['property_type_id']) : '';
    $title_posted = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description_posted = isset($_POST['description']) ? trim($_POST['description']) : null;
    $location_posted = isset($_POST['location']) ? trim($_POST['location']) : null;
    $price_posted = isset($_POST["price"]) && trim($_POST["price"]) !== "" ? trim($_POST["price"]) : null;
    $area_posted = isset($_POST["area"]) && trim($_POST["area"]) !== "" ? trim($_POST["area"]) : null;
    $bathrooms_posted = isset($_POST["bathrooms"]) && trim($_POST["bathrooms"]) !== "" ? trim($_POST["bathrooms"]) : null;
    $status_posted = isset($_POST["status"]) ? trim($_POST["status"]) : "available";
    $unit_design_id_posted = isset($_POST['unit_design_id']) && trim($_POST['unit_design_id']) !== '' ? trim($_POST['unit_design_id']) : null;
    $finishing_type_id_posted = isset($_POST['finishing_type_id']) && trim($_POST['finishing_type_id']) !== '' ? trim($_POST['finishing_type_id']) : null;
    $compound_area_posted = isset($_POST['compound_area']) && trim($_POST['compound_area']) !== '' ? trim($_POST['compound_area']) : null;
    $down_payment_percentage_posted = isset($_POST['down_payment_percentage']) && trim($_POST['down_payment_percentage']) !== '' ? trim($_POST['down_payment_percentage']) : null;
    $payment_years_posted = isset($_POST['payment_years']) && trim($_POST['payment_years']) !== '' ? trim($_POST['payment_years']) : null;
    $delivery_date_posted = isset($_POST['delivery_date']) && trim($_POST['delivery_date']) !== '' ? trim($_POST['delivery_date']) : null;
    $project_name_posted = isset($_POST['project_name']) ? trim($_POST['project_name']) : null;
    $min_price_posted = isset($_POST['min_price']) && trim($_POST['min_price']) !== '' ? trim($_POST['min_price']) : null;
    $max_price_posted = isset($_POST['max_price']) && trim($_POST['max_price']) !== '' ? trim($_POST['max_price']) : null;
    $min_area_posted = isset($_POST['min_area']) && trim($_POST['min_area']) !== '' ? trim($_POST['min_area']) : null;
    $max_area_posted = isset($_POST['max_area']) && trim($_POST['max_area']) !== '' ? trim($_POST['max_area']) : null;
    $avg_area_posted = isset($_POST['avg_area']) && trim($_POST['avg_area']) !== '' ? trim($_POST['avg_area']) : null;
    $district_posted = isset($_POST['district']) ? trim($_POST['district']) : null;
    $governorate_posted = isset($_POST['governorate']) ? trim($_POST['governorate']) : null;
    $company_name_posted = isset($_POST['company_name']) ? trim($_POST['company_name']) : null;
    $floors_count_posted = isset($_POST["floors_count"]) && trim($_POST["floors_count"]) !== '' ? trim($_POST["floors_count"]) : null;
    $sales_manager_name_posted = isset($_POST['sales_manager_name']) ? trim($_POST['sales_manager_name']) : null;
    $sales_manager_phone_posted = isset($_POST['sales_manager_phone']) ? trim($_POST['sales_manager_phone']) : null;
    $salesperson_name_posted = isset($_POST['salesperson_name']) ? trim($_POST['salesperson_name']) : null;
    $salesperson_phone_posted = isset($_POST['salesperson_phone']) ? trim($_POST['salesperson_phone']) : null;
   
    // --- Validation ---
    // Note: Validation matches the "Add Property" forms exactly
    // Only governorate, district, unit_design_id, and finishing_type_id are required
    if (empty($governorate_posted)) {
        $errors['governorate'] = t('governorate_required');
    }
    if (empty($district_posted)) {
        $errors['district'] = t('district_required');
    }
    if (empty($unit_design_id_posted)) {
        $errors['unit_design_id'] = t('unit_design_required');
    }
    if (empty($finishing_type_id_posted)) {
        $errors['finishing_type_id'] = t('finishing_type_required');
    }
    if ($price_posted !== null && (!is_numeric($price_posted) || $price_posted < 0)) {
        $errors["price"] = t("enter_valid_price");
    }
    if ($area_posted !== null && (!is_numeric($area_posted) || $area_posted < 0)) {
        $errors["area"] = t("enter_valid_area");
    }
    if ($bathrooms_posted !== null && !filter_var($bathrooms_posted, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]])) {
        $errors["bathrooms"] = t("enter_valid_bathrooms");
    }
    if ($compound_area_posted !== null && (!is_numeric($compound_area_posted) || $compound_area_posted < 0)) {
        $errors["compound_area"] = t("invalid_compound_area");
    }
    if ($down_payment_percentage_posted !== null && (!is_numeric($down_payment_percentage_posted) || $down_payment_percentage_posted < 0 || $down_payment_percentage_posted > 100)) {
        $errors['down_payment_percentage'] = t('invalid_down_payment_percentage');
    }
    if ($payment_years_posted !== null && !filter_var($payment_years_posted, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
        $errors['payment_years'] = t('invalid_payment_years');
    }
    if ($min_price_posted !== null && (!is_numeric($min_price_posted) || $min_price_posted < 0)) {
        $errors['min_price'] = t('enter_valid_min_price');
    }
    if ($max_price_posted !== null && (!is_numeric($max_price_posted) || $max_price_posted < 0)) {
        $errors['max_price'] = t('enter_valid_max_price');
    }
    if ($min_area_posted !== null && (!is_numeric($min_area_posted) || $min_area_posted < 0)) {
        $errors['min_area'] = t('enter_valid_min_area');
    }
    if ($max_area_posted !== null && (!is_numeric($max_area_posted) || $max_area_posted < 0)) {
        $errors['max_area'] = t('enter_valid_max_area');
    }
    if ($avg_area_posted !== null && (!is_numeric($avg_area_posted) || $avg_area_posted < 0)) {
        $errors['avg_area'] = t('enter_valid_avg_area');
    }
    if ($floors_count_posted !== null && !filter_var($floors_count_posted, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
        $errors['floors_count'] = t('invalid_floors_count');
    }

    // If no errors, proceed with update
    if (empty($errors)) {
        $sql_update = "UPDATE Properties SET 
            PropertyTypeID = ?, Title = ?, Description = ?, Location = ?, Price = ?, Area = ?, 
            Bathrooms = ?, Status = ?, unit_design_id = ?, finishing_type_id = ?, 
            compound_area = ?, down_payment_percentage = ?, payment_years = ?, delivery_date = ?, 
            ProjectName = ?, min_price = ?, max_price = ?, min_area = ?, max_area = ?, avg_area = ?, 
            district = ?, governorate = ?, company_name = ?, FloorsCount = ?,
            sales_manager_name = ?, sales_manager_phone = ?, salesperson_name = ?, salesperson_phone = ?
            WHERE PropertyID = ?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param(
                "isssddisiiddissdddddsssissssi",
                $property_type_id_posted,
                $title_posted,
                $description_posted,
                $location_posted,
                $price_posted,
                $area_posted,
                $bathrooms_posted,
                $status_posted,
                $unit_design_id_posted,
                $finishing_type_id_posted,
                $compound_area_posted,
                $down_payment_percentage_posted,
                $payment_years_posted,
                $delivery_date_posted,
                $project_name_posted,
                $min_price_posted,
                $max_price_posted,
                $min_area_posted,
                $max_area_posted,
                $avg_area_posted,
                $district_posted,
                $governorate_posted,
                $company_name_posted,
                $floors_count_posted,
                $sales_manager_name_posted,
                $sales_manager_phone_posted,
                $salesperson_name_posted,
                $salesperson_phone_posted,
                $property_id
            );

            if ($stmt_update->execute()) {
                $_SESSION["success_message"] = t('property_updated_success');
                header("Location: view_property.php?id=" . $property_id);
                exit();
            } else {
                $errors['db_update'] = t('error_updating_property') . ': ' . $stmt_update->error;
                error_log("Error executing property update: " . $stmt_update->error);
            }
            $stmt_update->close();
        } else {
            $errors['db_prepare'] = t('error_preparing_statement') . ': ' . $conn->error;
            error_log("Error preparing property update: " . $conn->error);
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('edit_property_title'); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="lightbox.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
    <style>
        /* Add some spacing between form sections */
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .form-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
            display: inline-block;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box; /* Ensures padding doesn't increase overall width */
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .success-message {
            color: #28a745;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .button-group {
            margin-top: 20px;
            text-align: right;
        }
        .button-group button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        .button-group .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .button-group .btn-secondary {
            background-color: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .form-display { 
            padding: 8px 12px; 
            background-color: #f8f9fa; 
            border: 1px solid #dee2e6; 
            border-radius: 4px; 
            color: #495057; 
            font-weight: 500;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 280px; /* Adjust as needed */
        }
        /* Specific styles for property type sections */
        .property-type-section {
            display: none; /* Hidden by default */
        }
        .property-type-section.active {
            display: block;
        }
        .media-messages {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container" data-property-id="<?php echo $property_id; ?>" data-csrf-token="<?php echo $csrf_token; ?>">
        <h2><?php echo t('edit_property_title'); ?></h2>

        <div class="media-messages"></div>

        <?php
        if (!empty($errors)) {
            echo "<div class='alert alert-danger'>";
            echo "<p>" . t('fix_errors_proceed') . "</p><ul>";
            foreach ($errors as $error) {
                echo "<li>" . $error . "</li>";
            }
            echo "</ul></div>";
        }
        if (isset($_SESSION['success_message'])) {
            echo "<div class='alert alert-success'>" . $_SESSION['success_message'] . "</div>";
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<div class='alert alert-danger'>" . $_SESSION['error_message'] . "</div>";
            unset($_SESSION['error_message']);
        }
        ?>

        <!-- Media Upload Section -->
        <div class="form-section">
            <h3>📷 Property Images</h3>
            
            <!-- Image Upload Area -->
            <div id="image-upload-area" class="upload-area">
                <p>📁 Drag and drop images here or click to select</p>
                <button type="button" id="image-upload-btn" class="upload-btn">Choose Images</button>
                <input type="file" id="image-upload" accept="image/jpeg,image/jpg,image/png,image/webp" multiple style="display: none;">
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    Supported formats: JPG, JPEG, PNG, WEBP | Max size: 10MB per image
                </p>
            </div>

            <!-- Existing Images -->
            <?php if (!empty($property_images)): ?>
                <div class="images-grid">
                    <?php foreach ($property_images as $index => $image): ?>
                        <div class="image-item">
                            <img src="<?php echo htmlspecialchars($image['file_path']); ?>" 
                                 alt="Property Image" 
                                 class="property-image-clickable" 
                                 data-image-index="<?php echo $index; ?>">
                            <?php if ($image['is_primary']): ?>
                                <div class="image-primary-badge">Primary</div>
                            <?php endif; ?>
                            <button type="button" 
                                    class="image-delete-btn delete-image-btn" 
                                    data-image-id="<?php echo $image['image_id']; ?>"
                                    title="Delete Image">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; font-style: italic;">No images uploaded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Audio Notes Section -->
        <div class="form-section">
            <h3>🎤 Voice Notes</h3>
            
            <!-- Audio Upload Area -->
            <div id="audio-upload-area" class="upload-area">
                <p>🎵 Drag and drop audio files here or click to select</p>
                <button type="button" id="audio-upload-btn" class="upload-btn">Choose Audio Files</button>
                <input type="file" id="audio-upload" accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/mp4,audio/m4a" multiple style="display: none;">
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    Supported formats: MP3, WAV, OGG, M4A | Max size: 10MB per file
                </p>
            </div>

            <!-- Existing Audio Notes -->
            <?php if (!empty($property_audio_notes)): ?>
                <div class="audio-notes">
                    <?php foreach ($property_audio_notes as $audio): ?>
                        <div class="audio-item">
                            <audio controls>
                                <source src="<?php echo htmlspecialchars($audio['file_path']); ?>" type="audio/mpeg">
                                Your browser does not support the audio element.
                            </audio>
                            <div class="audio-info">
                                <div>Uploaded: <?php echo date('Y-m-d H:i', strtotime($audio['upload_date'])); ?></div>
                                <?php if ($audio['duration']): ?>
                                    <div>Duration: <?php echo formatDuration($audio['duration']); ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="button" 
                                    class="audio-delete-btn delete-audio-btn" 
                                    data-audio-id="<?php echo $audio['note_id']; ?>">Delete</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; font-style: italic;">No voice notes uploaded yet.</p>
            <?php endif; ?>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $property_id; ?>" method="post">
            <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-section">
                <h3><?php echo t('basic_info'); ?></h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><?php echo t('listing_type'); ?>:</label>
                        <div class="form-display"><?php echo ($listing_type === 'Primary') ? t('primary_properties') : t('resale_properties'); ?></div>
                    </div>
                    <div class="form-group">
                        <label for="property_type_id"><?php echo t('property_type_label'); ?></label>
                        <select id="property_type_id" name="property_type_id">
                            <option value=""><?php echo t('select_type'); ?></option>
                            <?php foreach ($property_types_list as $type): ?>
                                <option value="<?php echo $type['PropertyTypeID']; ?>"
                                    <?php echo ($property_type_id == $type['PropertyTypeID']) ? 'selected' : ''; ?>>
                                    <?php echo t($type['TypeName']); // Assuming TypeName can be used as a translation key or is already translated?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>
                    <div class="form-group">
                        <label for="project_name"><?php echo t('project_name'); ?></label>
                        <input type="text" id="project_name" name="project_name" value="<?php echo htmlspecialchars($project_name); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="title"><?php echo t('unit_address_optional'); ?></label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>">

                    </div>
                    <div class="form-group">
                        <label for="company_name"><?php echo t('company'); ?></label>
                        <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="status"><?php echo t('status'); ?></label>
                        <select id="status" name="status">
                            <option value="available" <?php echo ($status == 'available') ? 'selected' : ''; ?>><?php echo t('available'); ?></option>
                            <option value="sold" <?php echo ($status == 'sold') ? 'selected' : ''; ?>><?php echo t('sold'); ?></option>
                            <option value="rented" <?php echo ($status == 'rented') ? 'selected' : ''; ?>><?php echo t('rented'); ?></option>
                            <option value="unavailable" <?php echo ($status == 'unavailable') ? 'selected' : ''; ?>><?php echo t('unavailable'); ?></option>
                        </select>

                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('location_info'); ?></h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="governorate"><?php echo t('governorate'); ?> *</label>
                        <input type="text" id="governorate" name="governorate" value="<?php echo htmlspecialchars($governorate); ?>" required>
                        <?php if (isset($errors['governorate'])) {
                            echo "<span class='error-message'>" . $errors['governorate'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="district"><?php echo t('district'); ?> *</label>
                        <input type="text" id="district" name="district" value="<?php echo htmlspecialchars($district); ?>" required>
                        <?php if (isset($errors['district'])) {
                            echo "<span class='error-message'>" . $errors['district'] . "</span>";
                        } ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="location"><?php echo t('detailed_address'); ?></label>
                    <textarea id="location" name="location"><?php echo htmlspecialchars($location); ?></textarea>
                </div>
            </div>

            <!-- Residential/Coastal Specific Fields -->
            <div id="residential_coastal_fields" class="property-type-section">
                <div class="form-section">
                    <h3><?php echo t('area_and_design'); ?></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="compound_area"><?php echo t('compound_area_faddan'); ?></label>
                            <input type="number" step="0.01" id="compound_area" name="compound_area" value="<?php echo htmlspecialchars($compound_area); ?>">
                            <?php if (isset($errors['compound_area'])) {
                                echo "<span class='error-message'>" . $errors['compound_area'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="floors_count"><?php echo t('floors_count'); ?></label>
                            <input type="number" id="floors_count" name="floors_count" value="<?php echo htmlspecialchars($floors_count); ?>">
                            <?php if (isset($errors['floors_count'])) {
                                echo "<span class='error-message'>" . $errors['floors_count'] . "</span>";
                            } ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit_design_id"><?php echo t('unit_design'); ?> *</label>
                            <select id="unit_design_id" name="unit_design_id" required>
                                <option value=""><?php echo t('select_unit_design'); ?></option>
                                <?php foreach ($unit_designs_list as $design): ?>
                                    <option value="<?php echo $design['DesignID']; ?>"
                                        <?php echo ($unit_design_id == $design['DesignID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($design['DesignName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['unit_design_id'])) {
                                echo "<span class='error-message'>" . $errors['unit_design_id'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="finishing_type_id"><?php echo t('finishing_type'); ?> *</label>
                            <select id="finishing_type_id" name="finishing_type_id" required>
                                <option value=""><?php echo t('select_finishing_type'); ?></option>
                                <?php foreach ($finishing_types_list as $finish): ?>
                                    <option value="<?php echo $finish['FinishingID']; ?>"
                                        <?php echo ($finishing_type_id == $finish['FinishingID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($finish['FinishingName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['finishing_type_id'])) {
                                echo "<span class='error-message'>" . $errors['finishing_type_id'] . "</span>";
                            } ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bathrooms"><?php echo t('bathrooms'); ?></label>
                        <input type="number" id="bathrooms" name="bathrooms" value="<?php echo htmlspecialchars($bathrooms); ?>">
                        <?php if (isset($errors['bathrooms'])) {
                            echo "<span class='error-message'>" . $errors['bathrooms'] . "</span>";
                        } ?>
                    </div>
                </div>
            </div>

            <!-- Commercial/Administrative/Medical/Hotel Specific Fields -->
            <div id="commercial_fields" class="property-type-section">
                <div class="form-section">
                    <h3><?php echo t('area_and_specifications'); ?></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="floors_count_commercial"><?php echo t('floors_count'); ?></label>
                            <input type="number" id="floors_count_commercial" name="floors_count" value="<?php echo htmlspecialchars($floors_count); ?>">
                            <?php if (isset($errors['floors_count'])) {
                                echo "<span class='error-message'>" . $errors['floors_count'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="finishing_type_id_commercial"><?php echo t('finishing_type'); ?> *</label>
                            <select id="finishing_type_id_commercial" name="finishing_type_id" required>
                                <option value=""><?php echo t('select_finishing_type'); ?></option>
                                <?php foreach ($finishing_types_list as $finish): ?>
                                    <option value="<?php echo $finish['FinishingID']; ?>"
                                        <?php echo ($finishing_type_id == $finish['FinishingID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($finish['FinishingName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['finishing_type_id'])) {
                                echo "<span class='error-message'>" . $errors['finishing_type_id'] . "</span>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('area_details'); ?></h3>
                <p class="note"><?php echo t('specific_area_note'); ?></p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="area"><?php echo t('unit_area_sqm'); ?></label>
                        <input type="number" step="0.01" id="area" name="area" value="<?php echo htmlspecialchars($area); ?>">
                        <?php if (isset($errors['area'])) {
                            echo "<span class='error-message'>" . $errors['area'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="min_area"><?php echo t('min_area'); ?></label>
                        <input type="number" step="0.01" id="min_area" name="min_area" value="<?php echo htmlspecialchars($min_area); ?>">
                        <?php if (isset($errors['min_area'])) {
                            echo "<span class='error-message'>" . $errors['min_area'] . "</span>";
                        } ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_area"><?php echo t('max_area'); ?></label>
                        <input type="number" step="0.01" id="max_area" name="max_area" value="<?php echo htmlspecialchars($max_area); ?>">
                        <?php if (isset($errors['max_area'])) {
                            echo "<span class='error-message'>" . $errors['max_area'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="avg_area"><?php echo t('avg_area_sqm'); ?></label>
                        <input type="number" step="0.01" id="avg_area" name="avg_area" value="<?php echo htmlspecialchars($avg_area); ?>">
                        <?php if (isset($errors['avg_area'])) {
                            echo "<span class='error-message'>" . $errors['avg_area'] . "</span>";
                        } ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('pricing_and_payment'); ?></h3>
                <p class="note"><?php echo t('specific_price_note'); ?></p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="price"><?php echo t('unit_price_egp'); ?></label>
                        <input type="number" step="0.01" id="price" name="price" value="<?php echo htmlspecialchars($price); ?>">
                        <?php if (isset($errors['price'])) {
                            echo "<span class='error-message'>" . $errors['price'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="min_price"><?php echo t('min_price'); ?></label>
                        <input type="number" step="0.01" id="min_price" name="min_price" value="<?php echo htmlspecialchars($min_price); ?>">
                        <?php if (isset($errors['min_price'])) {
                            echo "<span class='error-message'>" . $errors['min_price'] . "</span>";
                        } ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_price"><?php echo t('max_price'); ?></label>
                        <input type="number" step="0.01" id="max_price" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>">
                        <?php if (isset($errors['max_price'])) {
                            echo "<span class='error-message'>" . $errors['max_price'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="down_payment_percentage"><?php echo t('down_payment_percentage'); ?></label>
                        <input type="number" step="0.01" id="down_payment_percentage" name="down_payment_percentage" value="<?php echo htmlspecialchars($down_payment_percentage); ?>">
                        <?php if (isset($errors['down_payment_percentage'])) {
                            echo "<span class='error-message'>" . $errors['down_payment_percentage'] . "</span>";
                        } ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_years"><?php echo t('payment_period'); ?></label>
                        <input type="number" id="payment_years" name="payment_years" value="<?php echo htmlspecialchars($payment_years); ?>">
                        <?php if (isset($errors['payment_years'])) {
                            echo "<span class='error-message'>" . $errors['payment_years'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="delivery_date"><?php echo t('delivery_date'); ?></label>
                        <input type="date" id="delivery_date" name="delivery_date" value="<?php echo htmlspecialchars($delivery_date); ?>">
                        <?php if (isset($errors['delivery_date'])) {
                            echo "<span class='error-message'>" . $errors['delivery_date'] . "</span>";
                        } ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('additional_information'); ?></h3>
                <div class="form-group">
                    <label for="description"><?php echo t('description'); ?></label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($description); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('sales_information'); ?></h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="sales_manager_name"><?php echo t('sales_manager_name_label'); ?></label>
                        <input type="text" id="sales_manager_name" name="sales_manager_name" value="<?php echo htmlspecialchars($sales_manager_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sales_manager_phone"><?php echo t('sales_manager_phone_label'); ?></label>
                        <input type="text" id="sales_manager_phone" name="sales_manager_phone" value="<?php echo htmlspecialchars($sales_manager_phone); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="salesperson_name"><?php echo t('salesperson_name_label'); ?></label>
                        <input type="text" id="salesperson_name" name="salesperson_name" value="<?php echo htmlspecialchars($salesperson_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="salesperson_phone"><?php echo t('salesperson_phone_label'); ?></label>
                        <input type="text" id="salesperson_phone" name="salesperson_phone" value="<?php echo htmlspecialchars($salesperson_phone); ?>">
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary"><?php echo t('update_property_button'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index_properties.php'"><?php echo t('cancel_button'); ?></button>
            </div>
        </form>
    </div>

    <script src="lightbox.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const propertyTypeIdSelect = document.getElementById('property_type_id');
            const residentialCoastalFields = document.getElementById('residential_coastal_fields');
            const commercialFields = document.getElementById('commercial_fields');

            const RESIDENTIAL_ID = <?php echo RESIDENTIAL_PROPERTY_TYPE_ID; ?>;
            const COASTAL_ID = <?php echo COASTAL_PROPERTY_TYPE_ID; ?>;
            const COMMERCIAL_ID = <?php echo COMMERCIAL_PROPERTY_TYPE_ID; ?>;
            const ADMINISTRATIVE_ID = <?php echo ADMINISTRATIVE_PROPERTY_TYPE_ID; ?>;
            const HOTEL_ID = <?php echo HOTEL_PROPERTY_TYPE_ID; ?>;
            const MEDICAL_ID = <?php echo MEDICAL_PROPERTY_TYPE_ID; ?>;

            function togglePropertyTypeFields() {
                const selectedTypeId = parseInt(propertyTypeIdSelect.value);

                if (selectedTypeId === RESIDENTIAL_ID || selectedTypeId === COASTAL_ID) {
                    residentialCoastalFields.classList.add('active');
                    commercialFields.classList.remove('active');

                    // Enable/disable required attributes for residential/coastal
                    document.getElementById('unit_design_id').setAttribute('required', 'required');
                    document.getElementById('finishing_type_id').setAttribute('required', 'required');
                    document.getElementById('finishing_type_id_commercial').removeAttribute('required');

                } else if (selectedTypeId === COMMERCIAL_ID || selectedTypeId === ADMINISTRATIVE_ID || selectedTypeId === HOTEL_ID || selectedTypeId === MEDICAL_ID) {
                    residentialCoastalFields.classList.remove('active');
                    commercialFields.classList.add('active');

                    // Enable/disable required attributes for commercial
                    document.getElementById('unit_design_id').removeAttribute('required');
                    document.getElementById('finishing_type_id').removeAttribute('required');
                    document.getElementById('finishing_type_id_commercial').setAttribute('required', 'required');

                } else {
                    residentialCoastalFields.classList.remove('active');
                    commercialFields.classList.remove('active');

                    // Remove required attributes if no specific type is selected
                    document.getElementById('unit_design_id').removeAttribute('required');
                    document.getElementById('finishing_type_id').removeAttribute('required');
                    document.getElementById('finishing_type_id_commercial').removeAttribute('required');
                }
            }

            // Initial call to set correct fields based on loaded property type
            togglePropertyTypeFields();

            // Add event listener for changes
            propertyTypeIdSelect.addEventListener('change', togglePropertyTypeFields);
        });
    </script>
</body>
</html>

