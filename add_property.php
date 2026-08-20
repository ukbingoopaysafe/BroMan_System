<?php
require_once __DIR__ . 
'/lang/translate.php'; // Translation handler
require_once 'permissions.php'; // Include permissions system
require_once 'session_check.php'; // Include session timeout check
require_once __DIR__ . '/db.php'; // Database connection
require_once __DIR__ . '/security.php';

ensure_secure_session_started();

$csrf_token = generateCSRFToken();
/**
 * Ensure property_sellers table exists to store multiple sellers per property.
 */
function ensurePropertySellersTable(mysqli $conn)
{
    static $created = false;
    if ($created) {
        return;
    }
    $sql = "CREATE TABLE IF NOT EXISTS property_sellers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        seller_type VARCHAR(255) NULL,
        seller_name VARCHAR(255) NULL,
        seller_phone VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_property (property_id),
        CONSTRAINT fk_property_sellers_property FOREIGN KEY (property_id) REFERENCES Properties(PropertyID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
    $created = true;
}

// Check permissions - only manager and admin can add properties
$current_mode = require_listing_context();
check_permission('manage_properties');

// --- Fetch Lookups ---
$property_types = [];
$finishing_types = [];
$employees = [];

// Fetch All Property Types (1=Residential, 2=Coastal, 3=Commercial, 4=Administrative, 5=Hotel, 6=Medical)
$sql_types = "SELECT PropertyTypeID, TypeName, TypeName_ar FROM PropertyTypes ORDER BY PropertyTypeID";
if ($result = $conn->query($sql_types)) {
    while ($row = $result->fetch_assoc()) {
        $property_types[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching property types: " . $conn->error);
}

// Fetch Finishing Types
$sql_finishing = "SELECT FinishingID, FinishingName, FinishingName_ar FROM propertyfinishingtypes ORDER BY sort_order ASC";
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
$seller_entries = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $t_start = microtime(true);
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $errors[] = t('invalid_csrf_token');
    }
    // Idempotency guard (120s)
    $fp_fields = [
        $_SESSION['listing_context'] ?? '',
        $_POST['project_name'] ?? '',
        $_POST['title'] ?? '',
        $_POST['location'] ?? '',
        $_POST['seller_phone'] ?? '',
        $_POST['sales_manager_phone'] ?? '',
        $_POST['salesperson_phone'] ?? '',
        $_POST['company_phone_number'] ?? '',
        $_POST['sales_director_phone_number'] ?? '',
        $_POST['operation_manager_phone_number'] ?? ''
    ];
    $submission_fp = hash('sha256', json_encode($fp_fields));
    $now_ts = time();
    if (!isset($_SESSION['last_add_property_fp'])) { $_SESSION['last_add_property_fp'] = ['fp'=>null,'ts'=>0]; }
    $last = $_SESSION['last_add_property_fp'];
    if ($last['fp'] === $submission_fp && ($now_ts - (int)$last['ts']) < 120) {
        header('Location: index_properties.php');
        exit();
    }
    // --- Data Sanitization ---
    $property_type_ids = $_POST['property_type_ids'] ?? [];
    if (!is_array($property_type_ids)) { $property_type_ids = [$property_type_ids]; }
    $property_type_ids = array_map('intval', $property_type_ids);
    $property_type_id = !empty($property_type_ids) ? $property_type_ids[0] : null;
    $listing_type = $_SESSION['listing_context'] ?? 'Primary';
    $project_name = trim($_POST['project_name'] ?? '');
    $phase_name = trim($_POST['phase_name'] ?? null);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? null);
    $governorate = trim($_POST['governorate'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $location = trim($_POST['location'] ?? null);
    $company_name = trim($_POST['company_name'] ?? null);
    
    // Concatenate Company Phone
    $cp_code = $_POST['company_phone_code'] ?? '';
    $cp_number = $_POST['company_phone_number'] ?? '';
    $company_phone = ($cp_code !== '' && $cp_number !== '') ? $cp_code . $cp_number : null;

    $sales_director_name = trim($_POST['sales_director_name'] ?? null);
    $sd_code = $_POST['sales_director_phone_code'] ?? '';
    $sd_number = $_POST['sales_director_phone_number'] ?? '';
    $sales_director_phone = ($sd_code !== '' && $sd_number !== '') ? $sd_code . $sd_number : null;

    $operation_manager_name = trim($_POST['operation_manager_name'] ?? null);
    $om_code = $_POST['operation_manager_phone_code'] ?? '';
    $om_number = $_POST['operation_manager_phone_number'] ?? '';
    $operation_manager_phone = ($om_code !== '' && $om_number !== '') ? $om_code . $om_number : null;

    $floors_count = filter_input(INPUT_POST, 'floors_count', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $min_area = filter_input(INPUT_POST, 'min_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_area = filter_input(INPUT_POST, 'max_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $avg_area = filter_input(INPUT_POST, 'avg_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $area = filter_input(INPUT_POST, 'area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $rooms = filter_input(INPUT_POST, 'rooms', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $bathrooms = filter_input(INPUT_POST, 'bathrooms', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $balconies = filter_input(INPUT_POST, 'balconies', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $elevators = filter_input(INPUT_POST, 'elevators', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    // Bind rooms to bedrooms slot logic equivalent to other files
    $bedrooms = null; // Explicit null, using rooms variable in bind array equivalent position
    $unit_design = trim($_POST['unit_design'] ?? null); // Allow null for unit_design
    // compound_area is applicable for residential and coastal types (IDs 1 and 2)
    $compound_area = filter_input(INPUT_POST, 'compound_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $finishing_type_id = filter_input(INPUT_POST, 'finishing_type_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $down_payment_percentage = filter_input(INPUT_POST, 'down_payment_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $cash_discount_percentage = filter_input(INPUT_POST, 'cash_discount_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $maintenance_percentage = filter_input(INPUT_POST, 'maintenance_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    
    // Allow fractional years (e.g. 1.5). Use float validation and keep NULL when not provided
    $payment_years = null;
    if (isset($_POST['payment_years']) && trim($_POST['payment_years']) !== '') {
        $payment_years = filter_var($_POST['payment_years'], FILTER_VALIDATE_FLOAT);
    }
    $delivery_date_str = trim($_POST['delivery_date'] ?? '');
    $delivery_status = trim($_POST['delivery_status'] ?? null);
    $construction_status = trim($_POST['construction_status'] ?? null);
    $status = trim($_POST['status'] ?? 'available');
    $employee_id = $_SESSION['employee_id'] ?? null;
    $sales_manager_name = trim($_POST['sales_manager_name'] ?? null);
    
    // Concatenate Sales Manager Phone
    $sm_code = $_POST['sales_manager_phone_code'] ?? '';
    $sm_number = $_POST['sales_manager_phone_number'] ?? '';
    $sales_manager_phone = ($sm_code !== '' && $sm_number !== '') ? $sm_code . $sm_number : null;

    $salesperson_name = trim($_POST['salesperson_name'] ?? null);
    
    // Concatenate Salesperson Phone
    $sp_code = $_POST['salesperson_phone_code'] ?? '';
    $sp_number = $_POST['salesperson_phone_number'] ?? '';
    $salesperson_phone = ($sp_code !== '' && $sp_number !== '') ? $sp_code . $sp_number : null;
    $delivery_date = !empty($delivery_date_str) ? $delivery_date_str : null;
    
    // Offers & Brochure
    $offer_title = trim($_POST['offer_title'] ?? null);
    $offer_description = trim($_POST['offer_description'] ?? null);
    $offer_start_date = !empty($_POST['offer_start_date']) ? trim($_POST['offer_start_date']) : null;
    $offer_end_date = !empty($_POST['offer_end_date']) ? trim($_POST['offer_end_date']) : null;
    $brochure_url = trim($_POST['brochure_url'] ?? null);

    // New Phase 2 fields
    $year_built = trim($_POST['year_built'] ?? null);
    $unit_floor = trim($_POST['unit_floor'] ?? null);
    $net_area = filter_input(INPUT_POST, 'net_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $loading_percentage = filter_input(INPUT_POST, 'loading_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price_cash = filter_input(INPUT_POST, 'price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $price_installments = filter_input(INPUT_POST, 'price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $min_price = filter_input(INPUT_POST, 'min_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_price = filter_input(INPUT_POST, 'max_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $monthly_rent = filter_input(INPUT_POST, 'monthly_rent', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $daily_rent = filter_input(INPUT_POST, 'daily_rent', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $yearly_rent = filter_input(INPUT_POST, 'yearly_rent', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $meter_price_cash = filter_input(INPUT_POST, 'meter_price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $meter_price_installments = filter_input(INPUT_POST, 'meter_price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $min_meter_price = filter_input(INPUT_POST, 'min_meter_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $max_meter_price = filter_input(INPUT_POST, 'max_meter_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    
    // Multi-seller parsing
    $seller_entries = [];
    $posted_seller_types = $_POST['seller_type'] ?? [];
    $posted_seller_names = $_POST['seller_name'] ?? [];
    $posted_seller_phones = $_POST['seller_phone'] ?? [];
    if (!is_array($posted_seller_types)) { $posted_seller_types = [$posted_seller_types]; }
    if (!is_array($posted_seller_names)) { $posted_seller_names = [$posted_seller_names]; }
    if (!is_array($posted_seller_phones)) { $posted_seller_phones = [$posted_seller_phones]; }
    $max_sellers = max(count($posted_seller_types), count($posted_seller_names), count($posted_seller_phones));
    for ($i = 0; $i < $max_sellers; $i++) {
        $stype = trim($posted_seller_types[$i] ?? '');
        $sname = trim($posted_seller_names[$i] ?? '');
        
        // Combine code and number for phone
        $code = $_POST['seller_phone_code'][$i] ?? '';
        $number = $_POST['seller_phone_number'][$i] ?? '';
        $sphone = ($code !== '' && $number !== '') ? $code . $number : '';
        if ($stype === '' && $sname === '' && $sphone === '') {
            continue;
        }
        if ($stype !== '' && !in_array($stype, allowed_seller_types(), true)) {
            $stype = normalize_seller_type_for_db($conn, $stype);
        }
        $seller_entries[] = [
            'seller_type' => ($stype === '') ? null : $stype,
            'seller_name' => ($sname === '') ? null : $sname,
            'seller_phone' => ($sphone === '') ? null : $sphone,
        ];
    }
    if (empty($seller_entries)) {
        $seller_entries[] = ['seller_type' => null, 'seller_name' => null, 'seller_phone' => null];
    }
    $primary_seller = $seller_entries[0];
    $seller_type = $primary_seller['seller_type'];
    $seller_name = $primary_seller['seller_name'];
    $seller_phone = $primary_seller['seller_phone'];
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

    // Phone validation: relaxed strict 11-digit check for international numbers
    // But enforce it for Egyptian numbers (detected by starting with +20 or 20 or 01)
    function isValidPhoneFormat($phone) {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        // If it starts with 20 (Egypt country code)
        if (strpos($clean, '20') === 0 && strlen($clean) === 12) { // 20 + 10 digits
             return true; 
        }
        // If it starts with 20 but wrong length
        if (strpos($clean, '20') === 0) {
             return strlen($clean) === 12; // Must be 12 digits total (2 for code + 10 for number)
        }
        // Otherwise checks for reasonable length (e.g., 8-15)
        return strlen($clean) >= 8 && strlen($clean) <= 15;
    }
    foreach ($seller_entries as $idx => $seller_row) {
        $phone_val = $seller_row['seller_phone'] ?? '';
        if ($phone_val !== null && trim($phone_val) !== '') {
            if (!isValidPhoneFormat($phone_val)) {
                $errors[] = 'رقم هاتف البائع (' . ($idx + 1) . ') غير صحيح (يجب أن يكون متوافقاً مع مفتاح الدولة).';
            }
        }
    }
    if (!empty($sales_manager_phone) && !isValidPhoneFormat($sales_manager_phone)) {
        $errors[] = 'رقم هاتف مدير المبيعات غير صحيح.';
    }
    if (!empty($salesperson_phone) && !isValidPhoneFormat($salesperson_phone)) {
        $errors[] = 'رقم هاتف مسؤول المبيعات غير صحيح.';
    }
    if (!empty($company_phone) && !isValidPhoneFormat($company_phone)) {
        $errors[] = 'رقم هاتف الشركة المطورة غير صحيح.';
    }
    if (!empty($sales_director_phone) && !isValidPhoneFormat($sales_director_phone)) {
        $errors[] = 'رقم هاتف رئيس قطاع المبيعات غير صحيح.';
    }
    if (!empty($operation_manager_phone) && !isValidPhoneFormat($operation_manager_phone)) {
        $errors[] = 'رقم هاتف مدير العمليات غير صحيح.';
    }

    // Calculate friendly_id
    $friendly_id = 1;
    $stmt_max = $conn->prepare("SELECT MAX(friendly_id) as max_id FROM Properties WHERE listing_type = ?");
    if ($stmt_max) {
        $stmt_max->bind_param("s", $listing_type);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        if ($row_max = $res_max->fetch_assoc()) {
            $friendly_id = ((int)$row_max['max_id']) + 1;
        }
        $stmt_max->close();
    }

    $currency = $_POST['currency'] ?? 'EGP';

    $sql = "INSERT INTO Properties (
        PropertyTypeID, Title, Description, Location, Area, Bedrooms, Status, listing_type, EmployeeID, unit_design,
        finishing_type_id, compound_area, down_payment_percentage, cash_discount_percentage, maintenance_percentage, payment_years, delivery_date, delivery_status, construction_status, ProjectName,
        phase_name, min_area, max_area, avg_area, district, governorate, company_name, company_phone, FloorsCount, sales_director_name,
        sales_director_phone, operation_manager_name, operation_manager_phone, sales_manager_name, sales_manager_phone, salesperson_name, salesperson_phone, year_built, unit_floor, net_area,
        loading_percentage, price_cash, price_installments, min_price, max_price, monthly_rent, daily_rent, yearly_rent, meter_price_cash, meter_price_installments,
        min_meter_price, max_meter_price, seller_type, seller_name, seller_phone, offer_title, offer_description, offer_start_date, offer_end_date, brochure_url,
        latitude, longitude, Bathrooms, balconies, elevators, DateAdded, friendly_id, currency
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?
    )";

    $t_prepare_start = microtime(true);
    if ($stmt = $conn->prepare($sql)) {
        // Build bind-vars in same order as INSERT and force phone-like fields to be strings
        $bind_vars = [
            $property_type_id, $title, $description, $location, $area, $rooms, $status, $listing_type, $employee_id, $unit_design,
            $finishing_type_id, $compound_area, $down_payment_percentage, $cash_discount_percentage, $maintenance_percentage, $payment_years, $delivery_date, $delivery_status, $construction_status, $project_name,
            $phase_name, $min_area, $max_area, $avg_area, $district, $governorate, $company_name, $company_phone, $floors_count, $sales_director_name,
            $sales_director_phone, $operation_manager_name, $operation_manager_phone, $sales_manager_name, $sales_manager_phone, $salesperson_name, $salesperson_phone, $year_built, $unit_floor, $net_area,
            $loading_percentage, $price_cash, $price_installments, $min_price, $max_price, $monthly_rent, $daily_rent, $yearly_rent, $meter_price_cash, $meter_price_installments,
            $min_meter_price, $max_meter_price, $seller_type, $seller_name, $seller_phone, $offer_title, $offer_description, $offer_start_date, $offer_end_date, $brochure_url,
            $latitude, $longitude, $bathrooms, $balconies, $elevators, date('Y-m-d H:i:s'), $friendly_id, $currency
        ];

        // Parallel keys for forced typing
        $bind_keys = [
            'property_type_id', 'title', 'description', 'location', 'area', 'rooms', 'status', 'listing_type', 'employee_id', 'unit_design',
            'finishing_type_id', 'compound_area', 'down_payment_percentage', 'cash_discount_percentage', 'maintenance_percentage', 'payment_years', 'delivery_date', 'delivery_status', 'construction_status', 'project_name',
            'phase_name', 'min_area', 'max_area', 'avg_area', 'district', 'governorate', 'company_name', 'company_phone', 'floors_count', 'sales_director_name',
            'sales_director_phone', 'operation_manager_name', 'operation_manager_phone', 'sales_manager_name', 'sales_manager_phone', 'salesperson_name', 'salesperson_phone', 'year_built', 'unit_floor', 'net_area',
            'loading_percentage', 'price_cash', 'price_installments', 'min_price', 'max_price', 'monthly_rent', 'daily_rent', 'yearly_rent', 'meter_price_cash', 'meter_price_installments',
            'min_meter_price', 'max_meter_price', 'seller_type', 'seller_name', 'seller_phone', 'offer_title', 'offer_description', 'offer_start_date', 'offer_end_date', 'brochure_url',
            'latitude', 'longitude', 'bathrooms', 'balconies', 'elevators', 'date_added', 'friendly_id', 'currency'
        ];

        $force_string_fields = [
            'sales_manager_phone', 'salesperson_phone', 'seller_phone', 'company_phone', 'sales_director_phone', 'operation_manager_phone'
        ];

        $types = '';
        foreach ($bind_vars as $i => $bv) {
            $key = $bind_keys[$i] ?? null;
            if ($key !== null && in_array($key, $force_string_fields, true)) {
                $types .= 's';
                continue;
            }

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

        $t_before_execute = microtime(true);
        if ($stmt->execute()) {
            $new_property_id = $conn->insert_id;
            ensurePropertySellersTable($conn);
            if ($seller_entries) {
                $insSeller = $conn->prepare("INSERT INTO property_sellers (property_id, seller_type, seller_name, seller_phone) VALUES (?, ?, ?, ?)");
                if ($insSeller) {
                    foreach ($seller_entries as $row) {
                        $stype = $row['seller_type'];
                        $sname = $row['seller_name'];
                        $sphone = $row['seller_phone'];
                        $insSeller->bind_param("isss", $new_property_id, $stype, $sname, $sphone);
                        $insSeller->execute();
                    }
                    $insSeller->close();
                }
            }

            // Save multiple property types to property_type_links
            if (!empty($property_type_ids)) {
                $insType = $conn->prepare("INSERT INTO property_type_links (property_id, property_type_id) VALUES (?, ?)");
                if ($insType) {
                    foreach ($property_type_ids as $tid) {
                        $insType->bind_param("ii", $new_property_id, $tid);
                        $insType->execute();
                    }
                    $insType->close();
                }
            }
            $_SESSION['last_add_property_fp'] = ['fp'=>$submission_fp,'ts'=>$now_ts];
            error_log(sprintf('TIMING add_other: prepare/bind=%.3fs exec=%.3fs',
                $t_before_execute - $t_prepare_start,
                microtime(true) - $t_before_execute
            ));

            // Handle optional media uploads (same logic)
            $base_upload_dir = __DIR__ . '/uploads/';
            $image_dir = $base_upload_dir . 'images/';
            $audio_dir = $base_upload_dir . 'audio/';
            if (!is_dir($base_upload_dir)) { mkdir($base_upload_dir, 0755, true); }
            if (!is_dir($image_dir)) { mkdir($image_dir, 0755, true); }
            if (!is_dir($audio_dir)) { mkdir($audio_dir, 0755, true); }

            // Helper checker for real uploads
            $hasUploads = function($arr){ if (empty($arr)||!isset($arr['error'])) return false; foreach ((array)$arr['error'] as $e){ if ($e===UPLOAD_ERR_OK) return true; } return false; };
            // Images
            if ($hasUploads($_FILES['images'] ?? null)) {
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
            if ($hasUploads($_FILES['audio'] ?? null)) {
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
            if ($hasUploads($_FILES['videos'] ?? null)) {
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
            if ($hasUploads($_FILES['attachments'] ?? null)) {
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
            if ($hasUploads($_FILES['documents'] ?? null)) {
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

            // Log activity before closing session
            try {
                require_once __DIR__ . '/activity_log.php';
                $employee_id = $_SESSION['employee_id'] ?? null;
                if (function_exists('log_activity') && $employee_id) {
                    log_activity($conn, $employee_id, 'add_property', [
                        'property_id' => $new_property_id,
                        'type' => 'commercial'
                    ]);
                }
            } catch (Exception $e) {
                error_log('Failed to record add_property activity: ' . $e->getMessage());
            }

            // Fast redirect only
            require_once __DIR__ . '/flash.php';
            if (function_exists('add_flash')) { add_flash(t('property_added_success'), 6); }
            
            // Update duplicate status for all properties sharing the same phone numbers
            require_once __DIR__ . '/duplicate_status_helper.php';
            updateDuplicateStatusByPhone($new_property_id, $conn);
            
            session_write_close();
            header("Location: view_property.php?id=" . $new_property_id . "&added=1", true, 303);
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
    error_log(sprintf('TIMING add_other TOTAL=%.3fs', microtime(true) - $t_start));
}
?>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t("add_property_title"); ?></title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="/images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="/images/Logo%20Broman.svg">
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="css/premium_design.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <!-- Map provider: Google Maps -->
    <style>
        .form-section { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .form-section h3 { margin-bottom: 15px; color: #020d24; border-bottom: 2px solid #b08a0e; display: inline-block; padding-bottom: 5px; }
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
            border-color: #b08a0e;
            background-color: rgba(176, 138, 14, 0.1);
        }
        .upload-btn {
            background: linear-gradient(135deg, #b08a0e 0%, #8a6b08 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(176, 138, 14, 0.25);
        }
        .upload-btn:hover {
            background: linear-gradient(135deg, #c9a011 0%, #b08a0e 100%);
            transform: translateY(-2px);
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
        .seller-row {
            border: 1px dashed #dcdcdc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            background-color: #fafafa;
        }
        .seller-row .remove-seller {
            margin-top: 10px;
        }
    </style>
    <script src="session_timeout.js" defer></script>
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<div class="container">
    <h1><?php echo t("add_property_title"); ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
        // Prepare seller rows for rendering (sticky)
        $seller_entries_for_render = $seller_entries;
        if (empty($seller_entries_for_render)) {
            $seller_entries_for_render[] = ['seller_type' => $seller_type ?? '', 'seller_name' => $seller_name ?? '', 'seller_phone' => $seller_phone ?? ''];
        }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="form-container" enctype="multipart/form-data" data-prevent-multi-submit="1" data-property-id="0" data-csrf-token="<?php echo $csrf_token; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="form-section">
            <h3><?php echo t('basic_info'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label><?php echo t('listing_type'); ?>:</label>
                    <div class="form-display">
                        <?php
                            if ($current_mode === 'Primary') {
                                echo t('primary_properties');
                            } elseif ($current_mode === 'Resale') {
                                echo t('resale_properties');
                            } else {
                                echo t('rental_properties');
                            }
                        ?>
                    </div>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label><?php echo t('property_type'); ?>: *</label>
                    <div class="checkbox-group" style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0;">
                        <?php foreach ($property_types as $type): ?>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: #fff; padding: 8px 15px; border-radius: 6px; border: 1px solid #dee2e6; transition: all 0.2s;">
                                <input type="checkbox" name="property_type_ids[]" value="<?php echo $type['PropertyTypeID']; ?>" <?php echo (in_array($type['PropertyTypeID'], $property_type_ids ?? ($_POST['property_type_ids'] ?? []))) ? 'checked' : ''; ?>>
                                <?php 
                                if (get_lang_code() === 'ar' && !empty($type['TypeName_ar'])) {
                                    echo htmlspecialchars($type['TypeName_ar']);
                                } else {
                                    echo htmlspecialchars($type['TypeName']);
                                }
                                ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($current_mode === 'Primary'): ?>
                    <div class="form-group">
                        <label for="project_name"><?php echo t('project_name'); ?>: *</label>
                        <input type="text" id="project_name" name="project_name" class="form-input" required value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" placeholder="اسم المشروع / الكومباوند">
                    </div>
                    <div class="form-group">
                        <label for="phase_name"><?php echo t('phase_name'); ?>:</label>
                        <input type="text" id="phase_name" name="phase_name" class="form-input" value="<?php echo htmlspecialchars($_POST['phase_name'] ?? ''); ?>" placeholder="مثال: المرحلة الأولى، Phase 2...">
                    </div>
                    <div class="form-group">
                        <label for="company_name"><?php echo t('company'); ?> (المطور العقاري): *</label>
                        <input type="text" id="company_name" name="company_name" class="form-input" required value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" placeholder="اسم الشركة المطورة">
                    </div>
                    <div class="form-group">
                        <label for="title"><?php echo t('unit_title_optional'); ?>:</label>
                        <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="عنوان فرعي اختياري للمشروع أو النموذج">
                    </div>
                <?php else: ?>
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
                <?php endif; ?>
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

        <?php if ($current_mode === 'Primary'): ?>
        <!-- Developer & Management Contacts Section for Primary Mode -->
        <div class="form-section">
            <h3><?php echo t('developer_and_management_contacts'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="company_phone"><?php echo t('company_phone'); ?></label>
                    <div class="phone-input-group" style="display:flex; gap:10px;">
                        <select name="company_phone_code" id="company_phone_code" class="form-input" style="width:120px;"></select>
                        <input type="text" name="company_phone_number" id="company_phone_number" class="form-input" data-initial-code="+20" value="<?php echo htmlspecialchars($_POST['company_phone_number'] ?? ''); ?>" placeholder="رقم هاتف الشركة / الكول سنتر">
                    </div>
                </div>
                <div class="form-group">
                    <label for="sales_director_name"><?php echo t('sales_director_name'); ?></label>
                    <input type="text" id="sales_director_name" name="sales_director_name" class="form-input" value="<?php echo htmlspecialchars($_POST['sales_director_name'] ?? ''); ?>" placeholder="اسم رئيس قطاع المبيعات">
                </div>
                <div class="form-group">
                    <label for="sales_director_phone"><?php echo t('sales_director_phone'); ?></label>
                    <div class="phone-input-group" style="display:flex; gap:10px;">
                        <select name="sales_director_phone_code" id="sales_director_phone_code" class="form-input" style="width:120px;"></select>
                        <input type="text" name="sales_director_phone_number" id="sales_director_phone_number" class="form-input" data-initial-code="+20" value="<?php echo htmlspecialchars($_POST['sales_director_phone_number'] ?? ''); ?>" placeholder="رقم هاتف Sales Director">
                    </div>
                </div>
                <div class="form-group">
                    <label for="operation_manager_name"><?php echo t('operation_manager_name'); ?></label>
                    <input type="text" id="operation_manager_name" name="operation_manager_name" class="form-input" value="<?php echo htmlspecialchars($_POST['operation_manager_name'] ?? ''); ?>" placeholder="اسم مدير التشغيل / العمليات">
                </div>
                <div class="form-group">
                    <label for="operation_manager_phone"><?php echo t('operation_manager_phone'); ?></label>
                    <div class="phone-input-group" style="display:flex; gap:10px;">
                        <select name="operation_manager_phone_code" id="operation_manager_phone_code" class="form-input" style="width:120px;"></select>
                        <input type="text" name="operation_manager_phone_number" id="operation_manager_phone_number" class="form-input" data-initial-code="+20" value="<?php echo htmlspecialchars($_POST['operation_manager_phone_number'] ?? ''); ?>" placeholder="رقم هاتف Operation Manager">
                    </div>
                </div>
                <div class="form-group">
                    <label for="sales_manager_name"><?php echo t('sales_manager_name_label'); ?></label>
                    <input type="text" id="sales_manager_name" name="sales_manager_name" class="form-input" value="<?php echo htmlspecialchars($_POST['sales_manager_name'] ?? ''); ?>" placeholder="اسم مدير المبيعات">
                </div>
                <div class="form-group">
                    <label for="sales_manager_phone"><?php echo t('sales_manager_phone_label'); ?></label>
                    <div class="phone-input-group" style="display:flex; gap:10px;">
                        <select name="sales_manager_phone_code" id="sales_manager_phone_code" class="form-input" style="width:120px;"></select>
                        <input type="text" name="sales_manager_phone_number" id="sales_manager_phone_number" class="form-input" data-initial-code="+20" value="<?php echo htmlspecialchars($_POST['sales_manager_phone_number'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="salesperson_name"><?php echo t('salesperson_name_label'); ?></label>
                    <input type="text" id="salesperson_name" name="salesperson_name" class="form-input" value="<?php echo htmlspecialchars($_POST['salesperson_name'] ?? ''); ?>" placeholder="اسم مسؤول المبيعات">
                </div>
                <div class="form-group">
                    <label for="salesperson_phone"><?php echo t('salesperson_phone_label'); ?></label>
                    <div class="phone-input-group" style="display:flex; gap:10px;">
                        <select name="salesperson_phone_code" id="salesperson_phone_code" class="form-input" style="width:120px;"></select>
                        <input type="text" name="salesperson_phone_number" id="salesperson_phone_number" class="form-input" data-initial-code="+20" value="<?php echo htmlspecialchars($_POST['salesperson_phone_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Specs and Area Ranges for Primary -->
        <div class="form-section">
            <h3><?php echo t('project_specifications'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="compound_area"><?php echo t('compound_area'); ?>:</label>
                    <input type="number" step="0.01" id="compound_area" name="compound_area" class="form-input" value="<?php echo htmlspecialchars($_POST['compound_area'] ?? ''); ?>" placeholder="إجمالي مساحة المشروع بالفدان أو م²">
                </div>
                <div class="form-group">
                    <label for="min_area"><?php echo t('min_area_sqm'); ?>:</label>
                    <input type="number" step="0.01" id="min_area" name="min_area" class="form-input" value="<?php echo htmlspecialchars($_POST['min_area'] ?? ''); ?>" placeholder="المساحات تبدأ من">
                </div>
                <div class="form-group">
                    <label for="max_area"><?php echo t('max_area_sqm'); ?>:</label>
                    <input type="number" step="0.01" id="max_area" name="max_area" class="form-input" value="<?php echo htmlspecialchars($_POST['max_area'] ?? ''); ?>" placeholder="المساحات تصل إلى">
                </div>
                <div class="form-group">
                    <label for="avg_area"><?php echo t('avg_area_sqm'); ?>:</label>
                    <input type="number" step="0.01" id="avg_area" name="avg_area" class="form-input" value="<?php echo htmlspecialchars($_POST['avg_area'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="unit_design"><?php echo t('unit_design'); ?> (نماذج الوحدات):</label>
                    <input type="text" id="unit_design" name="unit_design" class="form-input" value="<?php echo htmlspecialchars($_POST['unit_design'] ?? ''); ?>" placeholder="مثال: شقق سكنية، دوبلكس، تاون هاوس، مكاتب...">
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
                    <label for="floors_count"><?php echo t('floors_count'); ?>:</label>
                    <input type="number" id="floors_count" name="floors_count" class="form-input" value="<?php echo htmlspecialchars($_POST['floors_count'] ?? ''); ?>" placeholder="عدد الأدوار في المباني">
                </div>
                <div class="form-group">
                    <label for="construction_status"><?php echo t('construction_status'); ?>:</label>
                    <input type="text" id="construction_status" name="construction_status" class="form-input" value="<?php echo htmlspecialchars($_POST['construction_status'] ?? ''); ?>" placeholder="مثال: إنشاءات 50% / حفر / خرسانات">
                </div>
                <div class="form-group">
                    <label for="delivery_status"><?php echo t('delivery_status'); ?>:</label>
                    <input type="text" id="delivery_status" name="delivery_status" class="form-input" value="<?php echo htmlspecialchars($_POST['delivery_status'] ?? ''); ?>" placeholder="مثال: استلام فوري / بعد سنتين / 2027">
                </div>
                <div class="form-group">
                    <label for="delivery_date"><?php echo t('delivery_date'); ?>:</label>
                    <input type="date" id="delivery_date" name="delivery_date" class="form-input" value="<?php echo htmlspecialchars($_POST['delivery_date'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Pricing Ranges & Payment Plans for Primary -->
        <div class="form-section">
            <h3><?php echo t('project_pricing_and_payment'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="currency"><?php echo t('currency'); ?>:</label>
                    <select id="currency" name="currency" class="form-input">
                        <option value="EGP" <?php echo (($_POST['currency'] ?? 'EGP') === 'EGP') ? 'selected' : ''; ?>><?php echo t('EGP'); ?></option>
                        <option value="USD" <?php echo (($_POST['currency'] ?? '') === 'USD') ? 'selected' : ''; ?>><?php echo t('USD'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="min_price"><?php echo t('min_price'); ?> (السعر الإجمالي يبدأ من):</label>
                    <input type="number" step="0.01" id="min_price" name="min_price" class="form-input" value="<?php echo htmlspecialchars($_POST['min_price'] ?? ''); ?>" placeholder="أقل سعر إجمالي في المشروع">
                </div>
                <div class="form-group">
                    <label for="max_price"><?php echo t('max_price'); ?> (السعر الإجمالي يصل إلى):</label>
                    <input type="number" step="0.01" id="max_price" name="max_price" class="form-input" value="<?php echo htmlspecialchars($_POST['max_price'] ?? ''); ?>" placeholder="أعلى سعر إجمالي في المشروع">
                </div>
                <div class="form-group">
                    <label for="min_meter_price"><?php echo t('min_meter_price'); ?>:</label>
                    <input type="number" step="0.01" id="min_meter_price" name="min_meter_price" class="form-input" value="<?php echo htmlspecialchars($_POST['min_meter_price'] ?? ''); ?>" placeholder="سعر المتر يبدأ من">
                </div>
                <div class="form-group">
                    <label for="max_meter_price"><?php echo t('max_meter_price'); ?>:</label>
                    <input type="number" step="0.01" id="max_meter_price" name="max_meter_price" class="form-input" value="<?php echo htmlspecialchars($_POST['max_meter_price'] ?? ''); ?>" placeholder="سعر المتر يصل إلى">
                </div>
                <div class="form-group">
                    <label for="down_payment_percentage"><?php echo t('down_payment_percentage'); ?> (%):</label>
                    <input type="number" step="0.01" id="down_payment_percentage" name="down_payment_percentage" class="form-input" value="<?php echo htmlspecialchars($_POST['down_payment_percentage'] ?? ''); ?>" placeholder="نسبة المقدم تبدأ من %">
                </div>
                <div class="form-group">
                    <label for="payment_years"><?php echo t('payment_years'); ?>:</label>
                    <input type="number" step="0.5" min="0.5" id="payment_years" name="payment_years" class="form-input" value="<?php echo isset($_POST['payment_years']) ? htmlspecialchars($_POST['payment_years']) : ''; ?>" placeholder="فترة السداد تصل إلى (سنوات)">
                </div>
                <div class="form-group">
                    <label for="cash_discount_percentage"><?php echo t('cash_discount_percentage'); ?>:</label>
                    <input type="number" step="0.01" id="cash_discount_percentage" name="cash_discount_percentage" class="form-input" value="<?php echo htmlspecialchars($_POST['cash_discount_percentage'] ?? ''); ?>" placeholder="مثال: 25% خصم للكاش">
                </div>
                <div class="form-group">
                    <label for="maintenance_percentage"><?php echo t('maintenance_percentage'); ?>:</label>
                    <input type="number" step="0.01" id="maintenance_percentage" name="maintenance_percentage" class="form-input" value="<?php echo htmlspecialchars($_POST['maintenance_percentage'] ?? ''); ?>" placeholder="مثال: 8% أو 10%">
                </div>
            </div>
        </div>

        <!-- Offers & Promotions Section for Primary -->
        <div class="form-section" style="border: 1px solid #f6c23e; background: #fffdf5; border-radius: 8px; padding: 20px;">
            <h3 style="color: #d39e00; display: flex; align-items: center; gap: 8px;">🎁 <?php echo t('offers_and_promotions'); ?></h3>
            <div class="form-grid">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="offer_title"><?php echo t('offer_title'); ?>:</label>
                    <input type="text" id="offer_title" name="offer_title" class="form-input" value="<?php echo htmlspecialchars($_POST['offer_title'] ?? ''); ?>" placeholder="مثال: خصم خاص 10% بمناسبة الافتتاح / 0% مقدم وتقسيط حتى 10 سنوات">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="offer_description"><?php echo t('offer_description'); ?>:</label>
                    <textarea id="offer_description" name="offer_description" class="form-input" rows="3" placeholder="تفاصيل وشروط العرض والاستثناءات"><?php echo htmlspecialchars($_POST['offer_description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="offer_start_date"><?php echo t('offer_start_date'); ?>:</label>
                    <input type="date" id="offer_start_date" name="offer_start_date" class="form-input" value="<?php echo htmlspecialchars($_POST['offer_start_date'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="offer_end_date"><?php echo t('offer_end_date'); ?>:</label>
                    <input type="date" id="offer_end_date" name="offer_end_date" class="form-input" value="<?php echo htmlspecialchars($_POST['offer_end_date'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Brochure & Masterplan Link -->
        <div class="form-section">
            <h3>📑 <?php echo t('brochure_url'); ?> &amp; <?php echo t('additional_info'); ?></h3>
            <div class="form-grid">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="brochure_url"><?php echo t('brochure_url'); ?>:</label>
                    <input type="url" id="brochure_url" name="brochure_url" class="form-input" value="<?php echo htmlspecialchars($_POST['brochure_url'] ?? ''); ?>" placeholder="https://drive.google.com/... أو رابط تحميل الـ PDF">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="description"><?php echo t('description'); ?>:</label>
                    <textarea id="description" name="description" class="form-input" rows="4" placeholder="وصف كامل ومفصل عن المشروع والمرافق والخدمات"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Resale & Rental Form Sections -->
        <div class="form-section">
            <h3><?php echo t('area_and_specs'); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="floors_count"><?php echo t('floors_count'); ?>:</label>
                    <input type="number" id="floors_count" name="floors_count" class="form-input" value="<?php echo htmlspecialchars($_POST['floors_count'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="unit_design"><?php echo t('unit_design'); ?>:</label>
                    <input type="text" id="unit_design" name="unit_design" class="form-input" value="<?php echo htmlspecialchars($_POST['unit_design'] ?? ''); ?>">
                </div>
                <div class="form-group" id="compound_area_group">
                    <label for="compound_area"><?php echo t('compound_area'); ?>:</label>
                    <input type="number" step="0.01" id="compound_area" name="compound_area" class="form-input" value="<?php echo htmlspecialchars($_POST['compound_area'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="rooms"><?php echo t('rooms'); ?>:</label>
                    <input type="number" id="rooms" name="rooms" class="form-input" value="<?php echo htmlspecialchars($_POST['rooms'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="bathrooms"><?php echo t('bathrooms'); ?>:</label>
                    <input type="number" id="bathrooms" name="bathrooms" class="form-input" value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="balconies"><?php echo t('balconies'); ?>:</label>
                    <input type="number" id="balconies" name="balconies" class="form-input" value="<?php echo htmlspecialchars($_POST['balconies'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="elevators"><?php echo t('elevators'); ?>:</label>
                    <input type="number" id="elevators" name="elevators" class="form-input" value="<?php echo htmlspecialchars($_POST['elevators'] ?? ''); ?>">
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
                    <label for="currency"><?php echo t('currency'); ?>:</label>
                    <select id="currency" name="currency" class="form-input">
                        <option value="EGP" selected><?php echo t('EGP'); ?></option>
                        <option value="USD"><?php echo t('USD'); ?></option>
                    </select>
                </div>
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
                    <label for="monthly_rent"><?php echo t('monthly_rent'); ?>:</label>
                    <input type="number" step="0.01" id="monthly_rent" name="monthly_rent" class="form-input" value="<?php echo htmlspecialchars($_POST['monthly_rent'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="daily_rent"><?php echo t('daily_rent'); ?>:</label>
                    <input type="number" step="0.01" id="daily_rent" name="daily_rent" class="form-input" value="<?php echo htmlspecialchars($_POST['daily_rent'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="yearly_rent"><?php echo t('yearly_rent'); ?>:</label>
                    <input type="number" step="0.01" id="yearly_rent" name="yearly_rent" class="form-input" value="<?php echo htmlspecialchars($_POST['yearly_rent'] ?? ''); ?>">
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
            </div>
        </div>

        <!-- Sales Information Section -->
        <div class="form-section">
            <h3><?php echo t("sales_information"); ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="sales_manager_name"><?php echo t("sales_manager_name_label"); ?></label>
                    <input type="text" id="sales_manager_name" name="sales_manager_name" class="form-input" value="<?php echo htmlspecialchars($_POST["sales_manager_name"] ?? ""); ?>">
                </div>
                <div class="form-group">
                    <label for="sales_manager_phone"><?php echo t("sales_manager_phone_label"); ?></label>
                    <div class="phone-input-group" style="display:flex; gap:10px;">
                        <select name="sales_manager_phone_code" id="sales_manager_phone_code" class="form-input" style="width:120px;"></select>
                        <input type="text" name="sales_manager_phone_number" id="sales_manager_phone_number" class="form-input <?php echo isset($errors["sales_manager_phone"]) ? "is-invalid" : ""; ?>" data-initial-code="+20" value="<?php echo htmlspecialchars($_POST["sales_manager_phone_number"] ?? ""); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="salesperson_name"><?php echo t("salesperson_name_label"); ?></label>
                    <input type="text" id="salesperson_name" name="salesperson_name" class="form-input" value="<?php echo htmlspecialchars($_POST["salesperson_name"] ?? ""); ?>">
                </div>
                <div class="form-group">
                    <label for="salesperson_phone"><?php echo t("salesperson_phone_label"); ?></label>
                    <div class="phone-input-group" style="display:flex; gap:10px;">
                        <select name="salesperson_phone_code" id="salesperson_phone_code" class="form-input" style="width:120px;"></select>
                        <input type="text" name="salesperson_phone_number" id="salesperson_phone_number" class="form-input <?php echo isset($errors["salesperson_phone"]) ? "is-invalid" : ""; ?>" data-initial-code="+20" value="<?php echo htmlspecialchars($_POST["salesperson_phone_number"] ?? ""); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Owner/Seller Information Section -->
        <div class="form-section" id="seller-section">
            <h3><?php echo t("owner_information"); ?></h3>
            <?php
                $seller_entries_for_render = $seller_entries;
                if (empty($seller_entries_for_render)) {
                    $seller_entries_for_render[] = ['seller_type' => $seller_type ?? '', 'seller_name' => $seller_name ?? '', 'seller_phone' => $seller_phone ?? ''];
                }
            ?>
            <div id="seller-entries">
                <?php foreach ($seller_entries_for_render as $idx => $seller_row): ?>
                <div class="seller-row">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo t("seller_type"); ?>:</label>
                            <select name="seller_type[]" class="form-input seller-type-input">
                                <option value=""><?php echo t('select_seller_type'); ?></option>
                                <option value="مالك" <?php echo (($seller_row['seller_type'] ?? '') == 'مالك') ? 'selected' : ''; ?>><?php echo t('seller_type_owner'); ?></option>
                                <option value="وسيط" <?php echo (($seller_row['seller_type'] ?? '') == 'وسيط') ? 'selected' : ''; ?>><?php echo t('seller_type_broker'); ?></option>
                                <option value="وكيل" <?php echo (($seller_row['seller_type'] ?? '') == 'وكيل') ? 'selected' : ''; ?>><?php echo t('seller_type_agent'); ?></option>
                                <option value="بواب" <?php echo (($seller_row['seller_type'] ?? '') == 'بواب') ? 'selected' : ''; ?>><?php echo t('seller_type_doorman'); ?></option>
                                <option value="شركة مطورة" <?php echo (($seller_row['seller_type'] ?? '') == 'شركة مطورة') ? 'selected' : ''; ?>><?php echo t('seller_type_developer_company'); ?></option>
                                <option value="شركة تشطيب" <?php echo (($seller_row['seller_type'] ?? '') == 'شركة تشطيب') ? 'selected' : ''; ?>><?php echo t('seller_type_finishing_company'); ?></option>
                                <option value="شركة تسويق" <?php echo (($seller_row['seller_type'] ?? '') == 'شركة تسويق') ? 'selected' : ''; ?>><?php echo t('seller_type_marketing_company'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo t("seller_name"); ?></label>
                            <input type="text" name="seller_name[]" class="form-input" value="<?php echo htmlspecialchars($seller_row["seller_name"] ?? ""); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo t("seller_phone"); ?></label>
                             <?php 
                                $sCodeId = "seller_phone_code_0";
                                $sNumId = "seller_phone_number_0";
                            ?>
                            <div class="phone-input-group" style="display:flex; gap:10px;">
                                <select name="seller_phone_code[]" id="<?php echo $sCodeId; ?>" class="form-input seller-phone-code" style="width:120px;"></select>
                                <input type="text" name="seller_phone_number[]" id="<?php echo $sNumId; ?>" class="form-input seller-phone-number" data-initial-code="+20">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger remove-seller" style="<?php echo $idx === 0 ? 'display:none;' : ''; ?>"><?php echo t('remove') ?: 'حذف'; ?></button>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:10px;">
                <button type="button" class="btn btn-secondary" id="add-seller-row">+ <?php echo t('add_seller') ?: 'إضافة بائع آخر'; ?></button>
            </div>
            <template id="seller-row-template">
                <div class="seller-row">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo t("seller_type"); ?>:</label>
                            <select name="seller_type[]" class="form-input seller-type-input">
                                <option value=""><?php echo t('select_seller_type'); ?></option>
                                <option value="مالك"><?php echo t('seller_type_owner'); ?></option>
                                <option value="وسيط"><?php echo t('seller_type_broker'); ?></option>
                                <option value="وكيل"><?php echo t('seller_type_agent'); ?></option>
                                <option value="بواب"><?php echo t('seller_type_doorman'); ?></option>
                                <option value="شركة مطورة"><?php echo t('seller_type_developer_company'); ?></option>
                                <option value="شركة تشطيب"><?php echo t('seller_type_finishing_company'); ?></option>
                                <option value="شركة تسويق"><?php echo t('seller_type_marketing_company'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo t("seller_name"); ?></label>
                            <input type="text" name="seller_name[]" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><?php echo t("seller_phone"); ?></label>
                            <div class="phone-input-group" style="display:flex; gap:10px;">
                                <select name="seller_phone_code[]" class="form-input seller-phone-code" style="width:120px;"></select>
                                <input type="text" name="seller_phone_number[]" class="form-input seller-phone-number" data-initial-code="+20">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger remove-seller"><?php echo t('remove') ?: 'حذف'; ?></button>
                </div>
            </template>
        </div>
        <?php endif; ?>

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

<script src="maps_loader.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapElement = document.getElementById('map');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const parseBtn = document.getElementById('parse_google_link');
    const apiKey = '<?php echo addslashes(GOOGLE_MAPS_API_KEY); ?>';
    const defaultLat = 30.0444;
    const defaultLng = 31.2357;
    let map;
    let marker;

    if (!mapElement || !latInput || !lngInput) {
        return;
    }

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

            // Do not attempt direct parsing on short links (they must be resolved by server)
            if (url.includes('maps.app.goo.gl') || url.includes('goo.gl/maps') || url.includes('bit.ly') || url.includes('tinyurl.com') || url.includes('t.co')) {
                return null;
            }

            // Pattern 1: !3dlat!4dlng (Highest precision pin marker)
            const dMatch = url.match(/!3d(-?[0-9.]+)!4d(-?[0-9.]+)/i);
            if (dMatch) {
                return { lat: parseFloat(dMatch[1]), lng: parseFloat(dMatch[2]) };
            }

            // Pattern 2: query=lat,lng or q=lat,lng or ll=lat,lng
            const qMatch = url.match(/[?&](?:query|q|ll|destination|loc|point)=\s*(-?[0-9.]+)(?:%2C|,)\s*(-?[0-9.]+)/i);
            if (qMatch) {
                return { lat: parseFloat(qMatch[1]), lng: parseFloat(qMatch[2]) };
            }

            // Pattern 3: /place/lat,lng or /search/lat,lng
            const pMatch = url.match(/\/(?:place|search|dir)\/(-?[0-9]{1,2}\.[0-9]+),(?:%2C|,)?\s*(-?[0-9]{1,3}\.[0-9]+)/i);
            if (pMatch) {
                return { lat: parseFloat(pMatch[1]), lng: parseFloat(pMatch[2]) };
            }

            // Pattern 4: /@lat,lng (Camera center)
            const atMatch = url.match(/@(-?[0-9.]+),(-?[0-9.]+)/);
            if (atMatch) {
                return { lat: parseFloat(atMatch[1]), lng: parseFloat(atMatch[2]) };
            }

            return null;
        } catch (e) {
            return null;
        }
    }

    function initMap() {
        const currentLat = parseFloat(latInput.value);
        const currentLng = parseFloat(lngInput.value);
        const hasCoordinates = !isNaN(currentLat) && !isNaN(currentLng);
        const center = {
            lat: hasCoordinates ? currentLat : defaultLat,
            lng: hasCoordinates ? currentLng : defaultLng
        };

        map = new google.maps.Map(mapElement, {
            center: center,
            zoom: hasCoordinates ? 16 : 10,
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

        function handleLinkResolution() {
            const input = document.getElementById('google_map_link');
            const val = input ? input.value.trim() : '';
            if (!val) {
                alert('يرجى إدخال رابط خرائط جوجل أولاً.');
                return;
            }

            const coords = extractLatLngFromGoogleUrl(val);
            if (coords && !isNaN(coords.lat) && !isNaN(coords.lng) && (coords.lat !== 0 || coords.lng !== 0)) {
                latInput.value = coords.lat.toFixed(6);
                lngInput.value = coords.lng.toFixed(6);
                placeMarker({ lat: coords.lat, lng: coords.lng });
                map.setZoom(16);
                return;
            }

            const originalText = parseBtn ? parseBtn.innerText : 'تحديد من الرابط';
            if (parseBtn) {
                parseBtn.disabled = true;
                parseBtn.innerText = 'جاري التحقق...';
            }

            fetch('resolve_map_url.php?url=' + encodeURIComponent(val))
                .then(response => response.json())
                .then(data => {
                    if (data && data.success && !isNaN(data.lat) && !isNaN(data.lng)) {
                        latInput.value = parseFloat(data.lat).toFixed(6);
                        lngInput.value = parseFloat(data.lng).toFixed(6);
                        placeMarker({ lat: parseFloat(data.lat), lng: parseFloat(data.lng) });
                        map.setZoom(16);
                    } else {
                        alert(data && data.message ? data.message : 'لم يتم العثور على إحداثيات في الرابط. الرجاء التحقق من أن الرابط من خرائط جوجل ويتضمن إحداثيات.');
                    }
                })
                .catch(err => {
                    console.error('Error resolving map URL:', err);
                    alert('حدث خطأ أثناء جلب إحداثيات الرابط. يرجى التأكد من أن الرابط صحيح أو من الاتصال بالإنترنت.');
                })
                .finally(() => {
                    if (parseBtn) {
                        parseBtn.disabled = false;
                        parseBtn.innerText = originalText;
                    }
                });
        }

        if (parseBtn) {
            parseBtn.addEventListener('click', handleLinkResolution);
        }

        const mapLinkInput = document.getElementById('google_map_link');
        if (mapLinkInput) {
            mapLinkInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleLinkResolution();
                }
            });
        }
    }

    function handleMapError(error) {
        console.error(error && error.message ? error.message : error);
        BroManMaps.showError(mapElement, 'يرجى التأكد من إعداد مفتاح Google Maps API بشكل صحيح.');
    }

    BroManMaps.loadGoogleMaps(apiKey, initMap, handleMapError);
});
</script>

<script src="js/phone_validation.js?v=<?php echo time(); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Init Company Phone
    const cpCode = document.getElementById('company_phone_code');
    const cpNum = document.getElementById('company_phone_number');
    if (cpCode && cpNum) {
        cpCode.innerHTML = getCountryOptionsHtml('+20');
        setupPhoneValidation('company_phone_code', 'company_phone_number');
    }

    // Init Sales Director Phone
    const sdCode = document.getElementById('sales_director_phone_code');
    const sdNum = document.getElementById('sales_director_phone_number');
    if (sdCode && sdNum) {
        sdCode.innerHTML = getCountryOptionsHtml('+20');
        setupPhoneValidation('sales_director_phone_code', 'sales_director_phone_number');
    }

    // Init Operation Manager Phone
    const omCode = document.getElementById('operation_manager_phone_code');
    const omNum = document.getElementById('operation_manager_phone_number');
    if (omCode && omNum) {
        omCode.innerHTML = getCountryOptionsHtml('+20');
        setupPhoneValidation('operation_manager_phone_code', 'operation_manager_phone_number');
    }

    // Init Sales Manager Phone
    const smCode = document.getElementById('sales_manager_phone_code');
    const smNum = document.getElementById('sales_manager_phone_number');
    if (smCode && smNum) {
        smCode.innerHTML = getCountryOptionsHtml('+20');
        setupPhoneValidation('sales_manager_phone_code', 'sales_manager_phone_number');
    }

    // Init Salesperson Phone
    const spCode = document.getElementById('salesperson_phone_code');
    const spNum = document.getElementById('salesperson_phone_number');
    if (spCode && spNum) {
        spCode.innerHTML = getCountryOptionsHtml('+20');
        setupPhoneValidation('salesperson_phone_code', 'salesperson_phone_number');
    }

    // Init First Seller
    const sCode = document.getElementById('seller_phone_code_0');
    const sNum = document.getElementById('seller_phone_number_0');
    if (sCode && sNum) {
        sCode.innerHTML = getCountryOptionsHtml('+20');
        setupPhoneValidation('seller_phone_code_0', 'seller_phone_number_0');
    }

    // Handle Dynamic Seller Addition
    var addSellerBtn = document.getElementById('add-seller-row');
    var sellerContainer = document.getElementById('seller-entries');
    var template = document.getElementById('seller-row-template');

    function addSellerRow() {
        if (!template || !sellerContainer) { return; }
        
        var clone = document.importNode(template.content, true);
        sellerContainer.appendChild(clone);
        
        refreshSellerControls();
        
        // Init phone for the new row
        const rows = document.querySelectorAll('#seller-entries .seller-row');
        const lastRow = rows[rows.length - 1];
        
        const codeSelect = lastRow.querySelector('.seller-phone-code');
        const numInput = lastRow.querySelector('.seller-phone-number');
        
        if (codeSelect && numInput) {
             const uniqueId = Date.now();
             codeSelect.id = `seller_phone_code_new_${uniqueId}`;
             numInput.id = `seller_phone_number_new_${uniqueId}`;
             
             codeSelect.innerHTML = getCountryOptionsHtml('+20');
             setupPhoneValidation(codeSelect.id, numInput.id);
        }
    }

    function refreshSellerControls() {
        var rows = sellerContainer ? sellerContainer.querySelectorAll('.seller-row') : [];
        rows.forEach(function(row, idx){
            var removeBtn = row.querySelector('.remove-seller');
            if (removeBtn) {
                removeBtn.style.display = (idx === 0) ? 'none' : 'inline-block';
                removeBtn.onclick = function(){
                    row.remove();
                    refreshSellerControls();
                };
            }
        });
    }

    if (addSellerBtn) {
        addSellerBtn.replaceWith(addSellerBtn.cloneNode(true));
        document.getElementById('add-seller-row').addEventListener('click', addSellerRow);
    }
});
</script>

<script src="prevent_double_submit.js"></script>

</body>
</html>


