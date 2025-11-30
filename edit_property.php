<?php
// Corrected include path for translation handler
require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once 'permissions.php'; // Include permissions system
require_once __DIR__ . '/property_changes_tracker.php'; // Include property changes tracker

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
// Flash helper (used by view_property.php to show short-lived messages)
require_once __DIR__ . '/flash.php';

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
$property_id = null;
$property_type_id = null;
$title = $description = $location = $status = "";
$area = null;
$unit_design = "";
$finishing_type_id = null;
$compound_area = $down_payment_percentage = null;
$payment_years = null;
$delivery_date = "";
$project_name = "";
$min_area = $max_area = $avg_area = null;
$district = $governorate = $company_name = "";
$floors_count = null;
$sales_manager_name = $sales_manager_phone = $salesperson_name = $salesperson_phone = "";
$year_built = $unit_floor = "";
$net_area = $loading_percentage = null;
$price_cash = $price_installments = $meter_price_cash = $meter_price_installments = null;
$seller_type = $seller_name = $seller_phone = "";
$latitude = $longitude = null;

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

// Unit designs are now handled as text input, no database lookup needed

// Finishing Types
$finishing_types_list = [];
$sql_finishing_types = "SELECT FinishingID, FinishingName, FinishingName_ar FROM PropertyFinishingTypes ORDER BY FinishingID";
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
                $area = $row["Area"];
                $status = $row["Status"];
                $unit_design = $row["unit_design"];
                $finishing_type_id = $row["finishing_type_id"];
                $compound_area = $row["compound_area"];
                $down_payment_percentage = $row["down_payment_percentage"];
                $payment_years = $row["payment_years"];
                $delivery_date = $row["delivery_date"];
                $project_name = $row["ProjectName"];
                // Price columns removed from schema
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
                $latitude = $row["latitude"] ?? '';
                $longitude = $row["longitude"] ?? '';
                
                // New Phase 2 fields
                $year_built = $row["year_built"] ?? null;
                $unit_floor = $row["unit_floor"] ?? null;
                $net_area = $row["net_area"] ?? null;
                $loading_percentage = $row["loading_percentage"] ?? null;
                $price_cash = $row["price_cash"] ?? null;
                $price_installments = $row["price_installments"] ?? null;
                $meter_price_cash = $row["meter_price_cash"] ?? null;
                $meter_price_installments = $row["meter_price_installments"] ?? null;
                $seller_type = $row["seller_type"] ?? null;
                $seller_name = $row["seller_name"] ?? null;
                $seller_phone = $row["seller_phone"] ?? null;
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
// Fetch user-facing documents (PDFs) for this property
$property_documents = [];
$doc_stmt = $conn->prepare("SELECT document_id, file_path, file_name, file_size, uploaded_by, upload_date FROM property_documents WHERE property_id = ? ORDER BY upload_date DESC");
if ($doc_stmt) {
    $doc_stmt->bind_param('i', $property_id);
    if ($doc_stmt->execute()) {
        $doc_res = $doc_stmt->get_result();
        while ($d = $doc_res->fetch_assoc()) {
            $property_documents[] = $d;
        }
    }
    $doc_stmt->close();
}
// Fetch attachments for this property
$property_attachments = [];
$att_stmt = $conn->prepare("SELECT pa.*, e.FullName AS UploadedBy FROM property_attachments pa LEFT JOIN employees e ON pa.EmployeeID = e.EmployeeID WHERE pa.PropertyID = ? ORDER BY pa.UploadedAt DESC");
if ($att_stmt) {
    $att_stmt->bind_param('i', $property_id);
    if ($att_stmt->execute()) {
        $att_res = $att_stmt->get_result();
        while ($a = $att_res->fetch_assoc()) {
            $property_attachments[] = $a;
        }
    }
    $att_stmt->close();
}

// --- Process form submission for update ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Store old property data for comparison
    $old_property_data = [
        'Title' => $title,
        'Description' => $description,
        'Location' => $location,
        'Area' => $area,
        'Status' => $status,
        'unit_design' => $unit_design,
        'compound_area' => $compound_area,
        'down_payment_percentage' => $down_payment_percentage,
        'payment_years' => $payment_years,
        'delivery_date' => $delivery_date,
        'ProjectName' => $project_name,
        'min_area' => $min_area,
        'max_area' => $max_area,
        'avg_area' => $avg_area,
        'district' => $district,
        'governorate' => $governorate,
        'company_name' => $company_name,
        'FloorsCount' => $floors_count,
        'sales_manager_name' => $sales_manager_name,
        'sales_manager_phone' => $sales_manager_phone,
        'salesperson_name' => $salesperson_name,
        'salesperson_phone' => $salesperson_phone,
        'year_built' => $year_built,
        'unit_floor' => $unit_floor,
        'net_area' => $net_area,
        'loading_percentage' => $loading_percentage,
        'price_cash' => $price_cash,
        'price_installments' => $price_installments,
        'meter_price_cash' => $meter_price_cash,
        'meter_price_installments' => $meter_price_installments,
        'seller_type' => $seller_type,
        'seller_name' => $seller_name,
        'seller_phone' => $seller_phone
    ];

    $property_id_post = trim($_POST["property_id"]);
    if (empty($property_id_post) || $property_id_post != $property_id) {
        $errors['general'] = t('invalid_property_id');
    }

    // Sanitize and retrieve data
    $property_type_id_posted = isset($_POST['property_type_id']) ? trim($_POST['property_type_id']) : '';
    $title_posted = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description_posted = isset($_POST['description']) ? trim($_POST['description']) : null;
    $location_posted = isset($_POST['location']) ? trim($_POST['location']) : null;
    // Price column removed from schema
    $area_posted = isset($_POST["area"]) && trim($_POST["area"]) !== "" ? trim($_POST["area"]) : null;
    $status_posted = isset($_POST["status"]) ? trim($_POST["status"]) : "available";
    $unit_design_posted = isset($_POST['unit_design']) && trim($_POST['unit_design']) !== '' ? trim($_POST['unit_design']) : null;
    $finishing_type_id_posted = isset($_POST['finishing_type_id']) && trim($_POST['finishing_type_id']) !== '' ? trim($_POST['finishing_type_id']) : null;
    $compound_area_posted = isset($_POST['compound_area']) && trim($_POST['compound_area']) !== '' ? trim($_POST['compound_area']) : null;
    $down_payment_percentage_posted = isset($_POST['down_payment_percentage']) && trim($_POST['down_payment_percentage']) !== '' ? trim($_POST['down_payment_percentage']) : null;
    $payment_years_posted = isset($_POST['payment_years']) && trim($_POST['payment_years']) !== '' ? trim($_POST['payment_years']) : null;
    $delivery_date_posted = isset($_POST['delivery_date']) && trim($_POST['delivery_date']) !== '' ? trim($_POST['delivery_date']) : null;
    $project_name_posted = isset($_POST['project_name']) ? trim($_POST['project_name']) : null;
    // Price columns removed from schema
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
    $latitude_posted = isset($_POST['latitude']) && trim($_POST['latitude']) !== '' ? filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $longitude_posted = isset($_POST['longitude']) && trim($_POST['longitude']) !== '' ? filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    
    // New Phase 2 fields
    $year_built_posted = isset($_POST['year_built']) ? trim($_POST['year_built']) : null;
    $seller_type_posted = isset($_POST['seller_type']) ? trim($_POST['seller_type']) : null;
    if ($seller_type_posted !== null && $seller_type_posted === '') { $seller_type_posted = null; }
    // Validate against allowed UI list; normalize to DB value if needed
    if ($seller_type_posted !== null && !in_array($seller_type_posted, allowed_seller_types(), true)) {
        $seller_type_posted = normalize_seller_type_for_db($conn, $seller_type_posted);
    }
    $unit_floor_posted = isset($_POST['unit_floor']) ? trim($_POST['unit_floor']) : null;
    $net_area_posted = isset($_POST['net_area']) && trim($_POST['net_area']) !== '' ? filter_input(INPUT_POST, 'net_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $loading_percentage_posted = isset($_POST['loading_percentage']) && trim($_POST['loading_percentage']) !== '' ? filter_input(INPUT_POST, 'loading_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $price_cash_posted = isset($_POST['price_cash']) && trim($_POST['price_cash']) !== '' ? filter_input(INPUT_POST, 'price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $price_installments_posted = isset($_POST['price_installments']) && trim($_POST['price_installments']) !== '' ? filter_input(INPUT_POST, 'price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $meter_price_cash_posted = isset($_POST['meter_price_cash']) && trim($_POST['meter_price_cash']) !== '' ? filter_input(INPUT_POST, 'meter_price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $meter_price_installments_posted = isset($_POST['meter_price_installments']) && trim($_POST['meter_price_installments']) !== '' ? filter_input(INPUT_POST, 'meter_price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $seller_type_posted = isset($_POST['seller_type']) ? trim($_POST['seller_type']) : null;
    $seller_name_posted = isset($_POST['seller_name']) ? trim($_POST['seller_name']) : null;
    $seller_phone_posted = isset($_POST['seller_phone']) ? trim($_POST['seller_phone']) : null;
    $bedrooms_posted = isset($_POST['bedrooms']) ? filter_input(INPUT_POST, 'bedrooms', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;
    $bathrooms_posted = isset($_POST['bathrooms']) ? filter_input(INPUT_POST, 'bathrooms', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;
   
    // --- Validation ---
    // Context-aware validation based on property type
    // $property_type_id is already fetched from the database above
    
    // Common required fields for all property types
    if (empty($governorate_posted)) {
        $errors['governorate'] = t('governorate_required');
    }
    if (empty($district_posted)) {
        $errors['district'] = t('district_required');
    }
    
    // Property type specific validation
    if ($property_type_id == 1 || $property_type_id == 2) // If Residential or Coastal
    {
        if (empty($unit_design_posted)) {
            $errors['unit_design'] = t('unit_design_required');
        }
        if (empty($finishing_type_id_posted)) {
            $errors['finishing_type_id'] = t('finishing_type_required');
        }
    } else if ($property_type_id == 3 || $property_type_id == 4 || $property_type_id == 5 || $property_type_id == 6) { // Commercial, Administrative, Hotel, Medical
        if (empty($finishing_type_id_posted)) {
            $errors['finishing_type_id'] = t('finishing_type_required');
        }
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
    if ($payment_years_posted !== null && (!is_numeric($payment_years_posted) || $payment_years_posted < 0.5)) {
        $errors['payment_years'] = t('invalid_payment_years');
    }
    // Price validation removed - columns no longer exist
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

    // تحقق من أن أرقام الهواتف المطلوبة هي 11 رقم فقط إذا تم إدخالها
    function isValidEgyptianPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 11;
    }
    if (!empty($seller_phone_posted) && !isValidEgyptianPhone($seller_phone_posted)) {
        $errors['seller_phone'] = 'يجب أن يكون رقم هاتف البائع 11 رقم.';
    }
    if (!empty($sales_manager_phone_posted) && !isValidEgyptianPhone($sales_manager_phone_posted)) {
        $errors['sales_manager_phone'] = 'يجب أن يكون رقم هاتف مدير المبيعات 11 رقم.';
    }
    if (!empty($salesperson_phone_posted) && !isValidEgyptianPhone($salesperson_phone_posted)) {
        $errors['salesperson_phone'] = 'يجب أن يكون رقم هاتف مسؤول المبيعات 11 رقم.';
    }

    // If no errors, proceed with update
    if (empty($errors)) {
        $sql_update = "UPDATE Properties SET 
            PropertyTypeID = ?, Title = ?, Description = ?, Location = ?, Area = ?,
            Status = ?, unit_design = ?, finishing_type_id = ?, 
            compound_area = ?, down_payment_percentage = ?, payment_years = ?, delivery_date = ?, 
            ProjectName = ?, min_area = ?, max_area = ?, avg_area = ?, 
            district = ?, governorate = ?, company_name = ?, FloorsCount = ?,
            sales_manager_name = ?, sales_manager_phone = ?, salesperson_name = ?, salesperson_phone = ?,
            year_built = ?, unit_floor = ?, net_area = ?, loading_percentage = ?, 
            price_cash = ?, price_installments = ?, meter_price_cash = ?, meter_price_installments = ?, 
            seller_type = ?, seller_name = ?, seller_phone = ?,
            latitude = ?, longitude = ?
            WHERE PropertyID = ?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            // Convert empty strings to null for numeric fields
            $area_val = empty($area_posted) ? null : $area_posted;
            $compound_area_val = empty($compound_area_posted) ? null : $compound_area_posted;
            $down_payment_val = empty($down_payment_percentage_posted) ? null : $down_payment_percentage_posted;
            $payment_years_val = empty($payment_years_posted) ? null : $payment_years_posted;
            $min_area_val = empty($min_area_posted) ? null : $min_area_posted;
            $max_area_val = empty($max_area_posted) ? null : $max_area_posted;
            $avg_area_val = empty($avg_area_posted) ? null : $avg_area_posted;
            $floors_count_val = empty($floors_count_posted) ? null : $floors_count_posted;
            $net_area_val = empty($net_area_posted) ? null : $net_area_posted;
            $loading_percentage_val = empty($loading_percentage_posted) ? null : $loading_percentage_posted;
            $price_cash_val = empty($price_cash_posted) ? null : $price_cash_posted;
            $price_installments_val = empty($price_installments_posted) ? null : $price_installments_posted;
            $meter_price_cash_val = empty($meter_price_cash_posted) ? null : $meter_price_cash_posted;
            $meter_price_installments_val = empty($meter_price_installments_posted) ? null : $meter_price_installments_posted;
            $latitude_val = empty($latitude_posted) ? null : $latitude_posted;
            $longitude_val = empty($longitude_posted) ? null : $longitude_posted;
            // Ensure finishing type is an integer when provided (helps with bind types)
            $finishing_type_id_val = empty($finishing_type_id_posted) ? null : intval($finishing_type_id_posted);

            // temporary debug logging removed

            // Build types string programmatically to avoid manual counting mistakes
            // 37 's' for the parameters and final 'i' for PropertyID (total 38)
            $types = str_repeat('s', 37) . 'i';
            $stmt_update->bind_param(
                $types,
                $property_type_id_posted,
                $title_posted,
                $description_posted,
                $location_posted,
                $area_val,

                $status_posted,
                $unit_design_posted,
                $finishing_type_id_val,
                $compound_area_val,
                $down_payment_val,
                $payment_years_val,
                $delivery_date_posted,
                $project_name_posted,
                $min_area_val,
                $max_area_val,
                $avg_area_val,
                $district_posted,
                $governorate_posted,
                $company_name_posted,
                $floors_count_val,
                $sales_manager_name_posted,
                $sales_manager_phone_posted,
                $salesperson_name_posted,
                $salesperson_phone_posted,
                $year_built_posted,
                $unit_floor_posted,
                $net_area_val,
                $loading_percentage_val,
                $price_cash_val,
                $price_installments_val,
                $meter_price_cash_val,
                $meter_price_installments_val,
                $seller_type_posted,
                $seller_name_posted,
                $seller_phone_posted,
                $latitude_val,
                $longitude_val,
                $property_id
            );

            // Debug: log key fields before executing the UPDATE to diagnose persistence issues
            error_log("DEBUG edit_property UPDATE: year_built_posted=" . var_export($year_built_posted, true) . " seller_type_posted=" . var_export($seller_type_posted, true));
            // Log normalized seller_type right before executing the UPDATE so we can verify what is being written
            error_log("DEBUG edit_property BEFORE EXECUTE: normalized_seller_type=" . var_export($seller_type_posted, true));
            if ($stmt_update->execute()) {
                // Use the project's flash helper so view_property.php (which reads get_flash_messages())
                // can show the success message immediately after redirect. Also set the legacy
                // session key for backward compatibility with other pages.
                if (function_exists('add_flash')) {
                    add_flash(t('property_updated_success'), 5);
                }
                require_once __DIR__ . '/flash.php';
                if (function_exists('add_flash')) { add_flash(t('property_updated_success'), 6); }
                // Move any temporary uploads (from unified uploader during edit) into this property
                if (function_exists('moveTempUploadsToProperty')) {
                    $moved = moveTempUploadsToProperty($property_id, session_id(), $conn);
                    error_log('Moved temp uploads after edit for session ' . session_id() . ': ' . json_encode($moved));
                }

                // Get the new property data after update for change tracking
                $new_property_data = [
                    'Title' => $title_posted,
                    'Description' => $description_posted,
                    'Location' => $location_posted,
                    'Area' => $area_val,
                    'Status' => $status_posted,
                    'unit_design' => $unit_design_posted,
                    'compound_area' => $compound_area_val,
                    'down_payment_percentage' => $down_payment_val,
                    'payment_years' => $payment_years_val,
                    'delivery_date' => $delivery_date_posted,
                    'ProjectName' => $project_name_posted,
                    'min_area' => $min_area_val,
                    'max_area' => $max_area_val,
                    'avg_area' => $avg_area_val,
                    'district' => $district_posted,
                    'governorate' => $governorate_posted,
                    'company_name' => $company_name_posted,
                    'FloorsCount' => $floors_count_val,
                    'sales_manager_name' => $sales_manager_name_posted,
                    'sales_manager_phone' => $sales_manager_phone_posted,
                    'salesperson_name' => $salesperson_name_posted,
                    'salesperson_phone' => $salesperson_phone_posted,
                    'year_built' => $year_built_posted,
                    'unit_floor' => $unit_floor_posted,
                    'net_area' => $net_area_val,
                    'loading_percentage' => $loading_percentage_val,
                    'price_cash' => $price_cash_val,
                    'price_installments' => $price_installments_val,
                    'meter_price_cash' => $meter_price_cash_val,
                    'meter_price_installments' => $meter_price_installments_val,
                    'seller_type' => $seller_type_posted,
                    'seller_name' => $seller_name_posted,
                    'seller_phone' => $seller_phone_posted
                ];

                // Track and log changes
                try {
                    require_once __DIR__ . '/activity_log.php';
                    $emp_for_log = $_SESSION['employee_id'] ?? null;
                    
                    // Get the changes between old and new data
                    $changes = track_property_changes($old_property_data, $new_property_data);
                    
                    if (function_exists('log_activity') && !empty($changes)) {
                        log_activity($conn, $emp_for_log, 'edit_property', [
                            'property_id' => $property_id,
                            'changes' => $changes
                        ]);
                    }
                } catch (Exception $e) {
                    error_log('Failed to record edit_property activity: ' . $e->getMessage());
                }

                header("Location: view_property.php?id=" . $property_id . "&edited=1");
                exit();
            } else {
                $errors['db_update'] = t('error_updating_property') . ': ' . $stmt_update->error;
                error_log("Error executing property update: " . $stmt_update->error);
            }
            // Log result so we can see whether the DB accepted the value and any mysqli error
            error_log("DEBUG edit_property AFTER EXECUTE: affected_rows=" . $stmt_update->affected_rows . " mysqli_error=" . (isset($conn) ? $conn->error : 'conn_not_set'));

            $stmt_update->close();
        } else {
            $errors['db_prepare'] = t('error_preparing_statement') . ': ' . $conn->error;
            error_log("Error preparing property update: " . $conn->error);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('edit_property_title'); ?></title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="/images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="/images/Logo%20Broman.svg">
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="lightbox.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css"> 
    <?php endif; ?>
    <!-- Map provider: Google Maps -->
    <style>
        /* Add some spacing between form sections */
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }


        .form-display { padding: 8px 12px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; color: #495057; }
        .property-type-section { display: none; }
        .property-type-section.active { display: block; }
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
        
        /* Override with form-input class for consistency */
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-input:focus {
            outline: none;
            border-color: #0056b3;
            box-shadow: 0 0 0 2px rgba(0,86,179,0.2);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        small { color: #666; font-size: 12px; margin-top: 5px; display: block; }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
        }
        .error-message {
            color: #dc3545;
            font-size: 12px;
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
        
        /* Override with form-actions class for consistency */
        .form-actions {
            margin-top: 30px;
            text-align: center;
        }
        .form-actions .btn {
            margin: 0 10px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            text-decoration: none;
            display: inline-block;
        }
        .form-actions .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .form-actions .btn-secondary {
            background-color: #6c757d;
            color: white;
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
        .form-row { display: none; }
        .form-display { 
            padding: 8px 12px; 
            background-color: #f8f9fa; 
            border: 1px solid #dee2e6; 
            border-radius: 4px; 
            color: #495057; 
            font-weight: 500;
        }
        .form-row .form-group { }
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
        /* Compact thumbnails for existing media in edit view */
        .existing-media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            align-items: start;
        }
        .existing-media-item {
            position: relative;
            background: #fff;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            overflow: hidden;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 90px;
        }
        .existing-media-item img,
        .existing-media-item video,
        .existing-media-item audio {
            max-width: 100%;
            max-height: 120px;
            object-fit: cover;
            display: block;
            border-radius: 4px;
        }
        /* Overlay action bar */
        .existing-media-actions {
            position: absolute;
            left: 6px;
            right: 6px;
            bottom: 6px;
            display: flex;
            gap: 6px;
            justify-content: space-between;
            align-items: center;
        }
        .existing-media-actions button, .existing-media-actions .badge {
            font-size: 12px;
            padding: 5px 8px;
            opacity: 0.98;
            border: none;
            cursor: pointer;
        }
        .existing-media-actions .left-actions { display: flex; gap:6px; align-items:center; }
        .existing-media-actions .right-actions { display: flex; gap:6px; align-items:center; }
        .existing-media-item .badge-primary {
            background-color: #007bff;
            color: #fff;
            border-radius: 3px;
            padding: 4px 6px;
        }
        .existing-media-item .set-primary-btn {
            background: rgba(0,123,255,0.95);
            color: #fff;
            border-radius: 3px;
        }
        .existing-media-item .delete-media-btn {
            background: rgba(220,53,69,0.95);
            color: #fff;
            border-radius: 3px;
        }
        /* On hover slightly raise the item */
        .existing-media-item:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.06); transform: translateY(-2px); transition: all 120ms ease; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container" data-property-id="<?php echo $property_id; ?>" data-csrf-token="<?php echo $csrf_token; ?>">
        <h2><?php echo t('edit_property_title'); ?></h2>

        <div class="media-messages"></div>

        <?php
        // Show existing media for the property so the user can manage (delete / set primary)
        $existing_images = [];
        $existing_videos = [];
        $existing_audio = [];
        if (!empty($property_id) && intval($property_id) > 0) {
            // media_functions.php is already required earlier
            $existing_images = getPropertyImages($property_id, $conn);
            $existing_videos = getPropertyVideos($property_id, $conn);
            $existing_audio = getPropertyAudioNotes($property_id, $conn);
        }
        ?>

        <?php if (!empty($existing_images) || !empty($existing_videos) || !empty($existing_audio)): ?>
        <div class="form-section" id="existing-media-section">
            <h3><?php echo t('current_media'); ?></h3>
            <?php if (!empty($existing_images)): ?>
                <div class="existing-media-grid" id="existingImages">
                    <?php foreach ($existing_images as $img): ?>
                        <div class="existing-media-item" data-id="<?php echo $img['image_id']; ?>">
                            <img src="<?php echo htmlspecialchars($img['file_path']); ?>" alt="" loading="lazy" />
                            <div class="existing-media-actions">
                                <div>
                                <?php if (!empty($img['is_primary'])): ?>
                                    <span class="badge badge-primary"><?php echo t('main_image'); ?></span>
                                <?php else: ?>
                                    <button type="button" class="set-primary-btn" data-id="<?php echo $img['image_id']; ?>"><?php echo t('set_as_primary'); ?></button>
                                <?php endif; ?>
                                </div>
                                <div>
                                    <button type="button" class="delete-media-btn" data-id="<?php echo $img['image_id']; ?>" data-type="image"><?php echo t('delete'); ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($existing_videos)): ?>
                <h4><?php echo t('videos'); ?></h4>
                <div class="existing-media-grid" id="existingVideos">
                    <?php foreach ($existing_videos as $v): ?>
                        <div class="existing-media-item" data-id="<?php echo $v['VideoID']; ?>">
                            <video src="<?php echo htmlspecialchars($v['VideoPath']); ?>" controls></video>
                            <div class="existing-media-actions">
                                <button type="button" class="btn btn-sm btn-danger delete-media-btn" data-id="<?php echo $v['VideoID']; ?>" data-type="video"><?php echo t('delete'); ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($existing_audio)): ?>
                <h4><?php echo t('audio_notes'); ?></h4>
                <div class="existing-media-grid" id="existingAudio">
                    <?php foreach ($existing_audio as $a): ?>
                        <div class="existing-media-item" data-id="<?php echo $a['note_id']; ?>">
                            <audio src="<?php echo htmlspecialchars($a['file_path']); ?>" controls></audio>
                            <div class="existing-media-actions">
                                <button type="button" class="btn btn-sm btn-danger delete-media-btn" data-id="<?php echo $a['note_id']; ?>" data-type="audio"><?php echo t('delete'); ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($property_documents)): ?>
                <h4><?php echo t('documents'); ?></h4>
                <div class="existing-media-grid" id="existingDocuments">
                    <?php foreach ($property_documents as $doc): ?>
                        <div class="existing-media-item" data-id="<?php echo (int)$doc['document_id']; ?>">
                            <div style="padding:12px; background:#fff; border-radius:6px; border:1px solid #eee; min-height:120px; display:flex; flex-direction:column;">
                                <div style="flex:1 1 auto; overflow:hidden;">
                                    <strong style="display:block;margin-bottom:8px; max-height:3.6em; overflow:hidden; text-overflow:ellipsis; word-break:break-word; white-space:normal;">
                                        <?php echo htmlspecialchars($doc['file_name']); ?>
                                    </strong>
                                    <div style="font-size:0.9rem;color:#666;">
                                        <?php echo htmlspecialchars(formatFileSize((int)$doc['file_size'])); ?>
                                    </div>
                                </div>
                                <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end; flex:0 0 auto;">
                                    <a href="view_document.php?id=<?php echo (int)$doc['document_id']; ?>" target="_blank" class="btn btn-primary"><?php echo t('view'); ?></a>
                                    <button type="button" class="btn btn-sm btn-danger delete-media-btn" data-id="<?php echo (int)$doc['document_id']; ?>" data-type="document"><?php echo t('delete'); ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
        } else {
            require_once __DIR__ . '/flash.php';
            $fs = get_flash_messages();
            if (!empty($fs)) { echo "<div class='alert alert-success'>" . htmlspecialchars(reset($fs)) . "</div>"; }
        }
        if (isset($_SESSION['error_message'])) {
            echo "<div class='alert alert-danger'>" . $_SESSION['error_message'] . "</div>";
            unset($_SESSION['error_message']);
        }
        ?>

        <!-- Unified Media Upload Section -->
        <div class="form-section">
            <?php include 'unified_media_uploader.php'; ?>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $property_id; ?>" method="post" class="form-container" data-prevent-multi-submit="1">
            <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
            <?php echo csrf_input(); ?>

            <div class="form-section">
                <h3><?php echo t('basic_info'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('listing_type'); ?>:</label>
                        <span class="form-display"><?php echo t($listing_type === 'Primary' ? 'primary_properties' : 'resale_properties'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="property_type_id"><?php echo t('property_type'); ?>:</label>
                        <select id="property_type_id" name="property_type_id" required>
                            <?php foreach ($property_types_list as $type): ?>
                                <option value="<?php echo $type['PropertyTypeID']; ?>" <?php echo ($property_type_id == $type['PropertyTypeID']) ? 'selected' : ''; ?>>
                                    <?php
                                    if (get_lang_code() === 'ar' && !empty($type['TypeName_ar'])) {
                                        echo htmlspecialchars($type['TypeName_ar']);
                                    } else {
                                        echo htmlspecialchars($type['TypeName']);
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['property_type_id'])) {
                            echo "<span class='error-message'>" . $errors['property_type_id'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="project_name"><?php echo t('project_name'); ?></label>
                        <input type="text" id="project_name" name="project_name" value="<?php echo htmlspecialchars($project_name); ?>">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="title"><?php echo t('unit_title_optional'); ?></label>
                        <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($title); ?>">
                        <?php if (isset($errors['title'])) {
                            echo "<span class='error-message'>" . $errors['title'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="company_name"><?php echo t('company'); ?></label>
                        <input type="text" id="company_name" name="company_name" class="form-input" value="<?php echo htmlspecialchars($company_name); ?>">
                        <?php if (isset($errors['company_name'])) {
                            echo "<span class='error-message'>" . $errors['company_name'] . "</span>";
                        } ?>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="status"><?php echo t('status'); ?></label>
                        <select id="status" name="status" class="form-input">
                            <option value="available" <?php echo ($status == 'available') ? 'selected' : ''; ?>><?php echo t('available'); ?></option>
                            <option value="sold" <?php echo ($status == 'sold') ? 'selected' : ''; ?>><?php echo t('sold'); ?></option>
                            <option value="rented" <?php echo ($status == 'rented') ? 'selected' : ''; ?>><?php echo t('rented'); ?></option>
                            <option value="unavailable" <?php echo ($status == 'unavailable') ? 'selected' : ''; ?>><?php echo t('unavailable'); ?></option>
                        </select>
                        <?php if (isset($errors['status'])) {
                            echo "<span class='error-message'>" . $errors['status'] . "</span>";
                        } ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('location_info'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="governorate"><?php echo t('governorate'); ?>: *</label>
                        <input type="text" id="governorate" name="governorate" class="form-input" value="<?php echo htmlspecialchars($governorate); ?>" required>
                        <?php if (isset($errors['governorate'])) {
                            echo "<span class='error-message'>" . $errors['governorate'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="district"><?php echo t('district'); ?>: *</label>
                        <input type="text" id="district" name="district" class="form-input" value="<?php echo htmlspecialchars($district); ?>" required>
                        <?php if (isset($errors['district'])) {
                            echo "<span class='error-message'>" . $errors['district'] . "</span>";
                        } ?>
                    </div>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="location"><?php echo t('detailed_address'); ?>:</label>
                    <input type="text" id="location" name="location" class="form-input" value="<?php echo htmlspecialchars($location); ?>">
                </div>
                
                <!-- Interactive Map Section -->
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label><?php echo t('select_location_on_map'); ?>:</label>

                    <!-- Google Maps link input -->
                    <div style="margin:8px 0; display:flex; gap:8px; align-items:center;">
                        <input type="text" id="google_map_link" class="form-input" style="flex:1;" placeholder="https://www.google.com/maps/..." value="<?php echo htmlspecialchars($google_map_link ?? ''); ?>">
                        <button type="button" id="parse_google_link" class="btn">تحديد من الرابط</button>
                    </div>
                    <small>لصق رابط خرائط جوجل ثم اضغط "تحديد من الرابط"</small>

                    <div id="map" style="height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 4px;"></div>
                    <small><?php echo t('click_map_to_set_location'); ?></small>

                    <!-- Hidden inputs for coordinates -->
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude ?? ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude ?? ''); ?>">
                </div>
            </div>

            <!-- Residential/Coastal Specific Fields -->
            <div id="residential_coastal_fields" class="property-type-section">
                <div class="form-section">
                    <h3><?php echo t('area_and_design'); ?></h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="compound_area"><?php echo t('compound_area'); ?> (<?php echo t('faddan'); ?>):</label>
                            <input type="number" step="0.01" id="compound_area" name="compound_area" class="form-input" value="<?php echo htmlspecialchars($compound_area); ?>">
                            <?php if (isset($errors['compound_area'])) {
                                echo "<span class='error-message'>" . $errors['compound_area'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="floors_count"><?php echo t('floors_count'); ?>:</label>
                            <input type="number" id="floors_count" name="floors_count" class="form-input" value="<?php echo htmlspecialchars($floors_count); ?>">
                            <?php if (isset($errors['floors_count'])) {
                                echo "<span class='error-message'>" . $errors['floors_count'] . "</span>";
                            } ?>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="unit_design"><?php echo t('unit_design'); ?>: *</label>
                            <input type="text" id="unit_design" name="unit_design" class="form-input" value="<?php echo htmlspecialchars($unit_design); ?>" required>
                            <?php if (isset($errors['unit_design'])) {
                                echo "<span class='error-message'>" . $errors['unit_design'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="finishing_type_id"><?php echo t('finishing_type'); ?>: *</label>
                            <select id="finishing_type_id" name="finishing_type_id" class="form-input" required>
                                <option value=""><?php echo t('select_finishing_type'); ?></option>
                                <?php foreach ($finishing_types_list as $finish): ?>
                                    <option value="<?php echo $finish['FinishingID']; ?>"
                                        <?php echo ($finishing_type_id == $finish['FinishingID']) ? 'selected' : ''; ?>>
                                        <?php 
                                        if (get_lang_code() === 'ar' && !empty($finish['FinishingName_ar'])) {
                                            echo htmlspecialchars($finish['FinishingName_ar']);
                                        } else {
                                            echo htmlspecialchars($finish['FinishingName']);
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['finishing_type_id'])) {
                                echo "<span class='error-message'>" . $errors['finishing_type_id'] . "</span>";
                            } ?>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="year_built"><?php echo t('year_built'); ?>:</label>
                            <input type="text" id="year_built" name="year_built" class="form-input" value="<?php echo htmlspecialchars($year_built); ?>">
                            <?php if (isset($errors['year_built'])) {
                                echo "<span class='error-message'>" . $errors['year_built'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="unit_floor"><?php echo t('unit_floor'); ?>:</label>
                            <input type="text" id="unit_floor" name="unit_floor" class="form-input" value="<?php echo htmlspecialchars($unit_floor); ?>">
                        </div>
                    </div>
                    <!-- Bathrooms field removed from schema -->
                </div>
            </div>

            <!-- Commercial/Administrative/Medical/Hotel Specific Fields -->
            <div id="commercial_fields" class="property-type-section">
                <div class="form-section">
                    <h3><?php echo t('area_and_specifications'); ?></h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="floors_count_commercial"><?php echo t('floors_count'); ?>:</label>
                            <input type="number" id="floors_count_commercial" name="floors_count" class="form-input" value="<?php echo htmlspecialchars($floors_count); ?>">
                            <?php if (isset($errors['floors_count'])) {
                                echo "<span class='error-message'>" . $errors['floors_count'] . "</span>";
                            } ?>
                        </div>
                        <!-- إضافة خانة نموذج الوحدة -->
                        <div class="form-group">
                            <label for="unit_design_commercial"><?php echo t('unit_design'); ?>:</label>
                            <input type="text" id="unit_design_commercial" name="unit_design" class="form-input" value="<?php echo htmlspecialchars($unit_design); ?>">
                            <?php if (isset($errors['unit_design'])) {
                                echo "<span class='error-message'>" . $errors['unit_design'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="finishing_type_id_commercial"><?php echo t('finishing_type'); ?>:</label>
                            <select id="finishing_type_id_commercial" name="finishing_type_id" class="form-input">
                                <option value=""><?php echo t('select_finishing_type'); ?></option>
                                <?php foreach ($finishing_types_list as $finish): ?>
                                    <option value="<?php echo $finish['FinishingID']; ?>" <?php echo ($finishing_type_id == $finish['FinishingID']) ? 'selected' : ''; ?>>
                                        <?php
                                        if (get_lang_code() === 'ar' && !empty($finish['FinishingName_ar'])) {
                                            echo htmlspecialchars($finish['FinishingName_ar']);
                                        } else {
                                            echo htmlspecialchars($finish['FinishingName']);
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['finishing_type_id'])) {
                                echo "<span class='error-message'>" . $errors['finishing_type_id'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="unit_floor_commercial"><?php echo t('unit_floor'); ?>:</label>
                            <input type="text" id="unit_floor_commercial" name="unit_floor" class="form-input" value="<?php echo htmlspecialchars($unit_floor); ?>">
                            <?php if (isset($errors['unit_floor'])) {
                                echo "<span class='error-message'>" . $errors['unit_floor'] . "</span>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('area_details'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="area"><?php echo t('unit_area'); ?> (<?php echo t('sqm'); ?>):</label>
                        <input type="number" step="0.01" id="area" name="area" class="form-input" value="<?php echo htmlspecialchars($area); ?>">
                        <small><?php echo t('specific_area_note'); ?></small>
                        <?php if (isset($errors['area'])) {
                            echo "<span class='error-message'>" . $errors['area'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="min_area"><?php echo t('min_area_sqm'); ?>:</label>
                        <input type="number" step="0.01" id="min_area" name="min_area" class="form-input" value="<?php echo htmlspecialchars($min_area); ?>">
                        <?php if (isset($errors['min_area'])) {
                            echo "<span class='error-message'>" . $errors['min_area'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="max_area"><?php echo t('max_area_sqm'); ?>:</label>
                        <input type="number" step="0.01" id="max_area" name="max_area" class="form-input" value="<?php echo htmlspecialchars($max_area); ?>">
                        <?php if (isset($errors['max_area'])) {
                            echo "<span class='error-message'>" . $errors['max_area'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="avg_area"><?php echo t('avg_area_sqm'); ?>:</label>
                        <input type="number" step="0.01" id="avg_area" name="avg_area" class="form-input" value="<?php echo htmlspecialchars($avg_area); ?>">
                        <?php if (isset($errors['avg_area'])) {
                            echo "<span class='error-message'>" . $errors['avg_area'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="net_area"><?php echo t('net_area'); ?>:</label>
                        <input type="number" step="0.01" id="net_area" name="net_area" class="form-input" value="<?php echo htmlspecialchars($net_area); ?>">
                    </div>
                    <div class="form-group">
                        <label for="loading_percentage"><?php echo t('loading_percentage'); ?>:</label>
                        <input type="number" step="0.01" id="loading_percentage" name="loading_percentage" class="form-input" min="0" max="100" value="<?php echo htmlspecialchars($loading_percentage); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('pricing_and_payment'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="price_cash"><?php echo t('price_cash'); ?>:</label>
                        <input type="number" step="0.01" id="price_cash" name="price_cash" class="form-input" value="<?php echo htmlspecialchars($price_cash); ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_installments"><?php echo t('price_installments'); ?>:</label>
                        <input type="number" step="0.01" id="price_installments" name="price_installments" class="form-input" value="<?php echo htmlspecialchars($price_installments); ?>">
                    </div>
                    <div class="form-group">
                        <label for="meter_price_cash"><?php echo t('meter_price_cash'); ?>:</label>
                        <input type="number" step="0.01" id="meter_price_cash" name="meter_price_cash" class="form-input" value="<?php echo htmlspecialchars($meter_price_cash); ?>">
                    </div>
                    <div class="form-group">
                        <label for="meter_price_installments"><?php echo t('meter_price_installments'); ?>:</label>
                        <input type="number" step="0.01" id="meter_price_installments" name="meter_price_installments" class="form-input" value="<?php echo htmlspecialchars($meter_price_installments); ?>">
                    </div>
                    <div class="form-group">
                        <label for="down_payment_percentage"><?php echo t('down_payment_percentage'); ?> (%):</label>
                        <input type="number" step="0.01" id="down_payment_percentage" name="down_payment_percentage" class="form-input" value="<?php echo htmlspecialchars($down_payment_percentage); ?>">
                        <?php if (isset($errors['down_payment_percentage'])) {
                            echo "<span class='error-message'>" . $errors['down_payment_percentage'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="payment_years"><?php echo t('payment_years'); ?>:</label>
                        <input type="number" id="payment_years" name="payment_years" class="form-input" step="0.5" min="0.5" value="<?php echo ($payment_years !== null && $payment_years !== '') ? number_format((float)$payment_years, 1, '.', '') : ''; ?>" placeholder="مثال: 1.5 للسنة والنصف">
                        <?php if (isset($errors['payment_years'])) {
                            echo "<span class='error-message'>" . $errors['payment_years'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="delivery_date"><?php echo t('delivery_date'); ?>:</label>
                        <input type="date" id="delivery_date" name="delivery_date" class="form-input" value="<?php echo htmlspecialchars($delivery_date); ?>">
                        <?php if (isset($errors['delivery_date'])) {
                            echo "<span class='error-message'>" . $errors['delivery_date'] . "</span>";
                        } ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('additional_info'); ?></h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="description"><?php echo t('description'); ?>:</label>
                        <textarea id="description" name="description" class="form-input" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Sales Information Section -->
            <div class="form-section">
                <h3><?php echo t("sales_information"); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sales_manager_name"><?php echo t("sales_manager_name_label"); ?></label>
                        <input type="text" id="sales_manager_name" name="sales_manager_name" class="form-input" value="<?php echo htmlspecialchars($sales_manager_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sales_manager_phone"><?php echo t("sales_manager_phone_label"); ?></label>
                        <input type="tel" id="sales_manager_phone" name="sales_manager_phone" class="form-input" value="<?php echo htmlspecialchars($sales_manager_phone); ?>">
                    </div>
                    <div class="form-group">
                        <label for="salesperson_name"><?php echo t("salesperson_name_label"); ?></label>
                        <input type="text" id="salesperson_name" name="salesperson_name" class="form-input" value="<?php echo htmlspecialchars($salesperson_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="salesperson_phone"><?php echo t("salesperson_phone_label"); ?></label>
                        <input type="tel" id="salesperson_phone" name="salesperson_phone" class="form-input" value="<?php echo htmlspecialchars($salesperson_phone); ?>">
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
                            <option value="مالك" <?php echo ($seller_type == 'مالك') ? 'selected' : ''; ?>><?php echo t('seller_type_owner'); ?></option>
                            <option value="وسيط" <?php echo ($seller_type == 'وسيط') ? 'selected' : ''; ?>><?php echo t('seller_type_broker'); ?></option>
                            <option value="وكيل" <?php echo ($seller_type == 'وكيل') ? 'selected' : ''; ?>><?php echo t('seller_type_agent'); ?></option>
                            <option value="بواب" <?php echo ($seller_type == 'بواب') ? 'selected' : ''; ?>><?php echo t('seller_type_doorman'); ?></option>
                            <option value="شركة مطورة" <?php echo ($seller_type == 'شركة مطورة') ? 'selected' : ''; ?>><?php echo t('seller_type_developer_company'); ?></option>
                            <option value="شركة تشطيب" <?php echo ($seller_type == 'شركة تشطيب') ? 'selected' : ''; ?>><?php echo t('seller_type_finishing_company'); ?></option>
                            <option value="شركة تسويق" <?php echo ($seller_type == 'شركة تسويق') ? 'selected' : ''; ?>><?php echo t('seller_type_marketing_company'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="seller_name"><?php echo t("seller_name"); ?></label>
                        <input type="text" id="seller_name" name="seller_name" class="form-input" value="<?php echo htmlspecialchars($seller_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="seller_phone"><?php echo t("seller_phone"); ?></label>
                        <input type="tel" id="seller_phone" name="seller_phone" class="form-input" value="<?php echo htmlspecialchars($seller_phone); ?>">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo t('update_property_button'); ?></button>
                <a href="index_properties.php" class="btn btn-secondary"><?php echo t('cancel'); ?></a>
            </div>
        </form>
    </div>

    <?php if (can_manage_properties()): ?>
        <?php include __DIR__ . '/unified_attachment_uploader.php'; ?>

        <div class="property-section">
            <h2><?php echo t('attachments'); ?></h2>
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <div class="attachments-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;">
                        <?php if (empty($property_attachments)): ?>
                            <div><?php echo t('no_attachments_found'); ?></div>
                        <?php else: ?>
                            <?php foreach ($property_attachments as $att): 
                                $ext = strtolower(pathinfo($att['FileName'], PATHINFO_EXTENSION));
                                $isImage = in_array($ext, ['jpg','jpeg','png','webp','gif']);
                                $iconClass = 'fa-file';
                                if (in_array($ext, ['pdf'])) $iconClass = 'fa-file-pdf';
                                if (in_array($ext, ['doc','docx'])) $iconClass = 'fa-file-word';
                                if (in_array($ext, ['xls','xlsx','csv'])) $iconClass = 'fa-file-excel';
                                if ($isImage) $iconClass = 'fa-file-image';
                                $secureUrl = 'serve_attachment.php?id=' . (int)$att['AttachmentID'];
                            ?>
                            <div id="att-<?php echo (int)$att['AttachmentID']; ?>" class="attachment-item" style="border:1px solid #eee;padding:8px;border-radius:6px;text-align:center;">
                                <div style="height:96px;display:flex;align-items:center;justify-content:center;">
                                    <?php if ($isImage): ?>
                                        <a href="<?php echo htmlspecialchars($secureUrl); ?>" class="attachment-image-clickable" data-src="<?php echo htmlspecialchars($secureUrl); ?>">
                                            <img src="<?php echo htmlspecialchars($secureUrl); ?>" alt="<?php echo htmlspecialchars($att['FileName']); ?>" style="max-height:96px;max-width:100%;object-fit:cover;border-radius:4px;">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($secureUrl); ?>" target="_blank"><i class="fas <?php echo $iconClass; ?> fa-3x" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top:8px;">
                                    <a href="<?php echo htmlspecialchars($secureUrl); ?>" target="_blank"><?php echo htmlspecialchars($att['FileName']); ?></a>
                                </div>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
                                <div style="font-size:0.9rem;color:#666;margin-top:6px;">
                                    <?php echo htmlspecialchars(substr($att['UploadedAt'],0,19)); ?> - <?php echo htmlspecialchars($att['UploadedBy'] ?? t('unknown')); ?>
                                </div>
                                <?php endif; ?>
                                <div style="margin-top:6px;">
                                    <button class="btn btn-danger delete-attachment" data-id="<?php echo (int)$att['AttachmentID']; ?>"><?php echo t('delete'); ?></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('click', function(e){
            if (e.target.classList.contains('delete-attachment')) {
                if (!confirm('<?php echo t('confirm_delete_attachment'); ?>')) return;
                const id = e.target.dataset.id;
                const fd = new FormData(); fd.append('attachment_id', id); fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                fetch('delete_attachment.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(r=>r.json()).then(data=>{
                    if (data.success) { const el = document.getElementById('att-' + id); if (el) el.remove(); alert('<?php echo t('attachment_deleted_successfully'); ?>'); }
                    else { alert('<?php echo t('error_deleting_attachment'); ?>'); }
                }).catch(()=>{ alert('<?php echo t('error_deleting_attachment'); ?>'); });
            }
        });
        </script>
    <?php endif; ?>

    <script>
    (function(){
        function postForm(url, fields) {
            const fd = new FormData();
            for (const k in fields) {
                if (!Object.prototype.hasOwnProperty.call(fields, k)) continue;
                fd.append(k, fields[k]);
            }
            return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
        }

        document.addEventListener('click', function(e){
            const target = e.target;
            if (target.classList.contains('delete-media-btn')) {
                const id = target.dataset.id;
                const type = target.dataset.type || 'image';
                if (!confirm('<?php echo t('confirm_delete_media'); ?>')) return;
                // CSRF token from container
                const container = document.querySelector('.container');
                const csrf = container ? container.getAttribute('data-csrf-token') : '';
                postForm('delete_media.php', { media_type: type, media_id: id, csrf_token: csrf })
                    .then(res => {
                        if (res.success) {
                            const el = target.closest('.existing-media-item');
                            if (el) el.remove();
                        } else {
                            alert(res.message || '<?php echo t('delete_failed'); ?>');
                        }
                    }).catch(err => alert(err.message || '<?php echo t('delete_failed'); ?>'));
            } else if (target.classList.contains('set-primary-btn')) {
                const id = target.dataset.id;
                if (!confirm('<?php echo t('confirm_set_primary'); ?>')) return;
                const container = document.querySelector('.container');
                const csrf = container ? container.getAttribute('data-csrf-token') : '';
                postForm('set_primary_image.php', { image_id: id, csrf_token: csrf, property_id: <?php echo intval($property_id); ?> })
                    .then(res => {
                        if (res.success) {
                            // update badges in-place: remove existing main badge and add to this one
                            document.querySelectorAll('.existing-media-item .badge-primary').forEach(b => b.remove());
                            // replace any set-primary buttons into badges for others
                            document.querySelectorAll('.existing-media-item .set-primary-btn').forEach(btn => {
                                btn.style.display = '';
                            });
                            // hide the clicked button and add badge
                            const parentItem = target.closest('.existing-media-item');
                            if (parentItem) {
                                // hide this button
                                target.style.display = 'none';
                                const span = document.createElement('span');
                                span.className = 'badge badge-primary';
                                span.textContent = '<?php echo t('main_image'); ?>';
                                parentItem.querySelector('.existing-media-actions .left-actions')?.prepend(span);
                            }
                        } else {
                            alert(res.message || '<?php echo t('operation_failed'); ?>');
                        }
                    }).catch(err => alert(err.message || '<?php echo t('operation_failed'); ?>'));
            }
        });
    })();
    </script>

    <script src="lightbox.js"></script>
    <script src="maps_loader.js"></script>
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
                    const unitDesignField = document.getElementById('unit_design');
                    const finishingTypeField = document.getElementById('finishing_type_id');
                    const finishingTypeCommercialField = document.getElementById('finishing_type_id_commercial');
                    
                    // Ensure residential inputs are enabled so their values are submitted
                    if (unitDesignField) {
                        unitDesignField.setAttribute('required', 'required');
                        unitDesignField.disabled = false;
                    }
                    if (finishingTypeField) {
                        finishingTypeField.setAttribute('required', 'required');
                        finishingTypeField.disabled = false;
                    }
                    if (finishingTypeCommercialField) {
                        finishingTypeCommercialField.removeAttribute('required');
                        finishingTypeCommercialField.disabled = true;
                    }

                    // Disable commercial-only fields so they don't get submitted and overwrite residential values
                    const unitDesignCommercial = document.getElementById('unit_design_commercial');
                    const unitFloorCommercial = document.getElementById('unit_floor_commercial');
                    const floorsCountCommercial = document.getElementById('floors_count_commercial');
                    if (unitDesignCommercial) unitDesignCommercial.disabled = true;
                    if (unitFloorCommercial) unitFloorCommercial.disabled = true;
                    if (floorsCountCommercial) floorsCountCommercial.disabled = true;

                } else if (selectedTypeId === COMMERCIAL_ID || selectedTypeId === ADMINISTRATIVE_ID || selectedTypeId === HOTEL_ID || selectedTypeId === MEDICAL_ID) {
                    residentialCoastalFields.classList.remove('active');
                    commercialFields.classList.add('active');

                    // Enable/disable required attributes for commercial
                    const unitDesignField = document.getElementById('unit_design');
                    const finishingTypeField = document.getElementById('finishing_type_id');
                    const finishingTypeCommercialField = document.getElementById('finishing_type_id_commercial');
                    
                    // Ensure commercial inputs are enabled and residential ones disabled
                    if (unitDesignField) {
                        unitDesignField.removeAttribute('required');
                        // disable residential main unit_design so commercial value will be the submitted one
                        unitDesignField.disabled = true;
                    }
                    if (finishingTypeField) {
                        finishingTypeField.removeAttribute('required');
                        finishingTypeField.disabled = true;
                    }
                    if (finishingTypeCommercialField) {
                        finishingTypeCommercialField.setAttribute('required', 'required');
                        finishingTypeCommercialField.disabled = false;
                    }

                    // Enable commercial-only fields
                    const unitDesignCommercial = document.getElementById('unit_design_commercial');
                    const unitFloorCommercial = document.getElementById('unit_floor_commercial');
                    const floorsCountCommercial = document.getElementById('floors_count_commercial');
                    if (unitDesignCommercial) unitDesignCommercial.disabled = false;
                    if (unitFloorCommercial) unitFloorCommercial.disabled = false;
                    if (floorsCountCommercial) floorsCountCommercial.disabled = false;

                } else {
                    residentialCoastalFields.classList.remove('active');
                    commercialFields.classList.remove('active');

                    // Remove required attributes if no specific type is selected
                    const unitDesignField = document.getElementById('unit_design');
                    const finishingTypeField = document.getElementById('finishing_type_id');
                    const finishingTypeCommercialField = document.getElementById('finishing_type_id_commercial');
                    
                    if (unitDesignField) {
                        unitDesignField.removeAttribute('required');
                        unitDesignField.disabled = true;
                    }
                    if (finishingTypeField) {
                        finishingTypeField.removeAttribute('required');
                        finishingTypeField.disabled = true;
                    }
                    if (finishingTypeCommercialField) {
                        finishingTypeCommercialField.removeAttribute('required');
                        finishingTypeCommercialField.disabled = true;
                    }

                    // Disable commercial-only fields as well
                    const unitDesignCommercial = document.getElementById('unit_design_commercial');
                    const unitFloorCommercial = document.getElementById('unit_floor_commercial');
                    const floorsCountCommercial = document.getElementById('floors_count_commercial');
                    if (unitDesignCommercial) unitDesignCommercial.disabled = true;
                    if (unitFloorCommercial) unitFloorCommercial.disabled = true;
                    if (floorsCountCommercial) floorsCountCommercial.disabled = true;
                }
            }

            // Initial call to set correct fields based on loaded property type
            togglePropertyTypeFields();

            // Add event listener for changes
            propertyTypeIdSelect.addEventListener('change', togglePropertyTypeFields);
            
            const mapElement = document.getElementById('map');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const parseBtn = document.getElementById('parse_google_link');
            const apiKey = '<?php echo addslashes(GOOGLE_MAPS_API_KEY); ?>';
            const defaultLat = 30.0444;
            const defaultLng = 31.2357;
            let map;
            let marker;

            function placeMarker(position) {
                if (!map) {
                    return;
                }
                if (marker) {
                    marker.setMap(null);
                }
                marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    animation: google.maps.Animation.DROP
                });
                map.panTo(position);
            }

            function extractLatLngFromGoogleUrl(url) {
                try {
                    if (!url || typeof url !== 'string') {
                        return null;
                    }

                    const atMatch = url.match(/@([0-9.+-]+),([0-9.+-]+)/);
                    if (atMatch) {
                        return { lat: parseFloat(atMatch[1]), lng: parseFloat(atMatch[2]) };
                    }

                    const qMatch = url.match(/[?&](?:query|q)=\s*([0-9.+-]+),\s*([0-9.+-]+)/);
                    if (qMatch) {
                        return { lat: parseFloat(qMatch[1]), lng: parseFloat(qMatch[2]) };
                    }

                    const dMatch = url.match(/!3d([0-9.+-]+)!4d([0-9.+-]+)/);
                    if (dMatch) {
                        return { lat: parseFloat(dMatch[1]), lng: parseFloat(dMatch[2]) };
                    }

                    return null;
                } catch (e) {
                    return null;
                }
            }

            function initMap() {
                if (!mapElement || !latInput || !lngInput) {
                    return;
                }

                const currentLat = parseFloat(latInput.value);
                const currentLng = parseFloat(lngInput.value);
                const hasCoordinates = !isNaN(currentLat) && !isNaN(currentLng);
                const center = {
                    lat: hasCoordinates ? currentLat : defaultLat,
                    lng: hasCoordinates ? currentLng : defaultLng
                };

                map = new google.maps.Map(mapElement, {
                    center: center,
                    zoom: hasCoordinates ? 15 : 10,
                    gestureHandling: 'greedy',
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true
                });

                if (hasCoordinates) {
                    placeMarker(center);
                }

                map.addListener('click', function (event) {
                    const clickedLat = event.latLng.lat();
                    const clickedLng = event.latLng.lng();
                    latInput.value = clickedLat.toFixed(6);
                    lngInput.value = clickedLng.toFixed(6);
                    placeMarker({ lat: clickedLat, lng: clickedLng });
                });

                if (parseBtn) {
                    parseBtn.addEventListener('click', function () {
                        const input = document.getElementById('google_map_link');
                        const val = input ? input.value.trim() : '';
                        const coords = extractLatLngFromGoogleUrl(val);
                        if (coords && !isNaN(coords.lat) && !isNaN(coords.lng)) {
                            latInput.value = coords.lat.toFixed(6);
                            lngInput.value = coords.lng.toFixed(6);
                            placeMarker({ lat: coords.lat, lng: coords.lng });
                            map.setZoom(15);
                        } else {
                            alert('لم يتم العثور على إحداثيات في الرابط. الرجاء التحقق من أن الرابط من خرائط جوجل ويتضمن إحداثيات.');
                        }
                    });
                }
            }

            function handleMapError(error) {
                console.error(error && error.message ? error.message : error);
                if (mapElement) {
                    BroManMaps.showError(mapElement, 'يرجى التأكد من إعداد مفتاح Google Maps API بشكل صحيح.');
                }
            }

            if (typeof BroManMaps !== 'undefined') {
                BroManMaps.loadGoogleMaps(apiKey, initMap, handleMapError);
            } else {
                console.error('maps_loader.js لم يتم تحميله.');
            }
        });
    </script>
    <script>
    // تحقق من أن خانات الهاتف المطلوبة هي 11 رقم
    function validatePhoneInput(input) {
        var value = input.value.replace(/[^0-9]/g, '');
        if (value.length !== 11) {
            input.setCustomValidity('يجب أن يكون رقم الهاتف 11 رقم');
        } else {
            input.setCustomValidity('');
        }
    }
    var sellerPhoneInput = document.getElementById('seller_phone');
    var salesManagerPhoneInput = document.getElementById('sales_manager_phone');
    var salespersonPhoneInput = document.getElementById('salesperson_phone');
    if (sellerPhoneInput) {
        sellerPhoneInput.addEventListener('input', function(){ validatePhoneInput(sellerPhoneInput); });
    }
    if (salesManagerPhoneInput) {
        salesManagerPhoneInput.addEventListener('input', function(){ validatePhoneInput(salesManagerPhoneInput); });
    }
    if (salespersonPhoneInput) {
        salespersonPhoneInput.addEventListener('input', function(){ validatePhoneInput(salespersonPhoneInput); });
    }
    </script>
    <script src="prevent_double_submit.js"></script>
</body>
</html>
<?php 
// Close the database connection at the very end
if (isset($conn)) {
    $conn->close();
}
?>
