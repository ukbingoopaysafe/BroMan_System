<?php
// Corrected include path for translation handler
require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once 'permissions.php'; // Include permissions system
require_once __DIR__ . '/property_changes_tracker.php'; // Include property changes tracker

// Check permissions - only manager and admin can edit properties
$current_mode = require_listing_context();
check_permission('manage_properties');

require_once 'db.php'; // Database connection
require_once 'media_functions.php'; // Media helper functions
// Flash helper (used by view_property.php to show short-lived messages)
require_once __DIR__ . '/flash.php';

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
$rooms = null; // Added
$bathrooms = null; // Added
$balconies = null; // Added
$elevators = null; // Added
$unit_design = "";
$finishing_type_id = null;
$compound_area = $down_payment_percentage = null;
$compound_area_unit = 'faddan';
$cash_discount_percentage = $maintenance_percentage = null;
$payment_years = null;
$payment_plans_notes = null;
$property_payment_plans = [];
$delivery_date = $delivery_status = $construction_status = "";
$project_name = $phase_name = "";
$min_area = $max_area = $avg_area = null;
$district = $governorate = $company_name = $company_phone = $company_phone_2 = "";
$sales_director_name = $sales_director_phone = "";
$operation_manager_name = $operation_manager_phone = "";
$floors_count = null;
$sales_manager_name = $sales_manager_phone = $salesperson_name = $salesperson_phone = "";
$year_built = $unit_floor = "";
$net_area = $loading_percentage = null;
$price_cash = $price_installments = $monthly_rent = $daily_rent = $yearly_rent = $meter_price_cash = $meter_price_installments = null;
$min_price = $max_price = $min_meter_price = $max_meter_price = null;
$seller_type = $seller_name = $seller_phone = "";
$offer_title = $offer_description = $offer_start_date = $offer_end_date = $brochure_url = "";
$latitude = $longitude = null;
$property_data_source = '';
$property_sellers = [];

$errors = []; // Array to hold errors

// --- Fetch data for dropdowns ---

// Property Types
$property_types_list = [];
$sql_property_types = "SELECT PropertyTypeID, TypeName, TypeName_ar FROM PropertyTypes ORDER BY PropertyTypeID";
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
$sql_finishing_types = "SELECT FinishingID, FinishingName, FinishingName_ar FROM PropertyFinishingTypes ORDER BY sort_order ASC";
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
                $linked_property_types = [];
                $sql_pt = "SELECT property_type_id FROM property_type_links WHERE property_id = ?";
                if ($stmt_pt = $conn->prepare($sql_pt)) {
                    $stmt_pt->bind_param("i", $property_id);
                    $stmt_pt->execute();
                    $res_pt = $stmt_pt->get_result();
                    while ($pt_row = $res_pt->fetch_assoc()) {
                        $linked_property_types[] = (int)$pt_row['property_type_id'];
                    }
                    $stmt_pt->close();
                }
                // Fallback for non-migrated properties
                if (empty($linked_property_types) && $property_type_id) {
                    $linked_property_types[] = (int)$property_type_id;
                }
                $title = $row["Title"] ?? '';
                $description = $row["Description"] ?? '';
                $location = $row["Location"] ?? '';
                $area = $row["Area"];
                $rooms = $row["Bedrooms"]; // Mapped to rooms
                $bathrooms = $row["Bathrooms"];
                $balconies = ($row["balconies"] == 0) ? '' : $row["balconies"];
                $elevators = $row["elevators"];
                $status = $row["Status"] ?? 'available';
                $unit_design = $row["unit_design"] ?? '';
                $finishing_type_id = $row["finishing_type_id"];
                $compound_area = $row["compound_area"];
                $compound_area_unit = $row["compound_area_unit"] ?? 'faddan';
                $down_payment_percentage = $row["down_payment_percentage"];
                $cash_discount_percentage = $row["cash_discount_percentage"] ?? null;
                $maintenance_percentage = $row["maintenance_percentage"] ?? null;
                $clubhouse_fee = $row["clubhouse_fee"] ?? '';
                $garage_fee = $row["garage_fee"] ?? '';
                $transfer_fee_percentage = $row["transfer_fee_percentage"] ?? null;
                $maintenance_payment_details = $row["maintenance_payment_details"] ?? '';
                $payment_years = $row["payment_years"];
                $payment_plans_notes = $row["payment_plans_notes"] ?? '';
                $delivery_date = $row["delivery_date"] ?? '';
                $delivery_status = $row["delivery_status"] ?? '';
                $construction_status = $row["construction_status"] ?? '';
                $project_name = $row["ProjectName"] ?? '';
                $phase_name = $row["phase_name"] ?? '';
                $min_area = $row["min_area"];
                $max_area = $row["max_area"];
                $avg_area = $row["avg_area"];
                $district = $row["district"] ?? '';
                $governorate = $row["governorate"] ?? '';
                $company_name = $row["company_name"] ?? '';
                $company_phone = $row["company_phone"] ?? '';
                $company_phone_2 = $row["company_phone_2"] ?? '';
                $sales_director_name = $row["sales_director_name"] ?? '';
                $sales_director_phone = $row["sales_director_phone"] ?? '';
                $operation_manager_name = $row["operation_manager_name"] ?? '';
                $operation_manager_phone = $row["operation_manager_phone"] ?? '';
                $floors_count = $row["FloorsCount"] ?? null;
                $sales_manager_name = $row["sales_manager_name"] ?? '';
                $sales_manager_phone = $row["sales_manager_phone"] ?? '';
                $salesperson_name = $row["salesperson_name"] ?? '';
                $salesperson_phone = $row["salesperson_phone"] ?? '';
                $listing_type = $row["listing_type"] ?? 'Primary';
                $latitude = $row["latitude"] ?? '';
                $longitude = $row["longitude"] ?? '';
                $offer_title = $row["offer_title"] ?? '';
                $offer_description = $row["offer_description"] ?? '';
                $offer_start_date = $row["offer_start_date"] ?? '';
                $offer_end_date = $row["offer_end_date"] ?? '';
                $brochure_url = $row["brochure_url"] ?? '';
                $property_data_source = $row["property_data_source"] ?? '';

                // Fetch Existing Payment Plans
                $property_payment_plans = [];
                $plan_stmt = $conn->prepare("SELECT * FROM PropertyPaymentPlans WHERE PropertyID = ? ORDER BY display_order ASC, plan_id ASC");
                if ($plan_stmt) {
                    $plan_stmt->bind_param('i', $property_id);
                    if ($plan_stmt->execute()) {
                        $plan_res = $plan_stmt->get_result();
                        while ($prow = $plan_res->fetch_assoc()) {
                            $property_payment_plans[] = $prow;
                        }
                    }
                    $plan_stmt->close();
                }

                if (!is_mode_allowed_for_role($listing_type)) {
                    $_SESSION["error_message"] = t('mode_not_allowed_for_role');
                    header("Location: dashboard.php");
                    exit();
                }

                if (!is_manager() && $listing_type !== $current_mode) {
                    $_SESSION["error_message"] = t('mode_not_allowed');
                    header("Location: dashboard.php");
                    exit();
                }
                
                // New Phase 2 fields
                $year_built = $row["year_built"] ?? null;
                $unit_floor = $row["unit_floor"] ?? null;
                $net_area = $row["net_area"] ?? null;
                $loading_percentage = $row["loading_percentage"] ?? null;
                $price_cash = $row["price_cash"] ?? null;
                $price_installments = $row["price_installments"] ?? null;
                $min_price = $row["min_price"] ?? null;
                $max_price = $row["max_price"] ?? null;
                $monthly_rent = $row["monthly_rent"] ?? null;
                $daily_rent = $row["daily_rent"] ?? null;
                $yearly_rent = $row["yearly_rent"] ?? null;
                $meter_price_cash = $row["meter_price_cash"] ?? null;
                $meter_price_installments = $row["meter_price_installments"] ?? null;
                $min_meter_price = $row["min_meter_price"] ?? null;
                $max_meter_price = $row["max_meter_price"] ?? null;
                $seller_type = $row["seller_type"] ?? null;
                $seller_name = $row["seller_name"] ?? null;
                $seller_phone = $row["seller_phone"] ?? null;
                $currency = $row["currency"] ?? 'EGP'; // Initialize currency
                // Load multi-sellers from property_sellers table
                $seller_stmt = $conn->prepare("SELECT seller_type, seller_name, seller_phone FROM property_sellers WHERE property_id = ? ORDER BY id ASC");
                if ($seller_stmt) {
                    $seller_stmt->bind_param('i', $property_id);
                    if ($seller_stmt->execute()) {
                        $seller_res = $seller_stmt->get_result();
                        while ($s = $seller_res->fetch_assoc()) {
                            $property_sellers[] = $s;
                        }
                    }
                    $seller_stmt->close();
                }
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
        'Bedrooms' => $rooms, // Added
        'Bathrooms' => $bathrooms, // Added
        'balconies' => $balconies, // Added
        'elevators' => $elevators, // Added
        'Status' => $status,
        'unit_design' => $unit_design,
        'compound_area' => $compound_area,
        'compound_area_unit' => $compound_area_unit,
        'down_payment_percentage' => $down_payment_percentage,
        'cash_discount_percentage' => $cash_discount_percentage,
        'maintenance_percentage' => $maintenance_percentage,
        'payment_years' => $payment_years,
        'payment_plans_notes' => $payment_plans_notes,
        'delivery_date' => $delivery_date,
        'delivery_status' => $delivery_status,
        'construction_status' => $construction_status,
        'ProjectName' => $project_name,
        'phase_name' => $phase_name,
        'min_area' => $min_area,
        'max_area' => $max_area,
        'avg_area' => $avg_area,
        'district' => $district,
        'governorate' => $governorate,
        'company_name' => $company_name,
        'company_phone' => $company_phone,
        'company_phone_2' => $company_phone_2,
        'FloorsCount' => $floors_count,
        'sales_director_name' => $sales_director_name,
        'sales_director_phone' => $sales_director_phone,
        'operation_manager_name' => $operation_manager_name,
        'operation_manager_phone' => $operation_manager_phone,
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
        'min_price' => $min_price,
        'max_price' => $max_price,
        'meter_price_cash' => $meter_price_cash,
        'meter_price_installments' => $meter_price_installments,
        'min_meter_price' => $min_meter_price,
        'max_meter_price' => $max_meter_price,
        'seller_type' => $seller_type,
        'seller_name' => $seller_name,
        'seller_phone' => $seller_phone,
        'offer_title' => $offer_title,
        'offer_description' => $offer_description,
        'offer_start_date' => $offer_start_date,
        'offer_end_date' => $offer_end_date,
        'brochure_url' => $brochure_url,
        'daily_rent' => $daily_rent
    ];

    $property_id_post = trim($_POST["property_id"]);
    if (empty($property_id_post) || $property_id_post != $property_id) {
        $errors['general'] = t('invalid_property_id');
    }

    $property_data_source_posted = trim($_POST['property_data_source'] ?? '');

    // --- Data Sanitization ---
    // Now using checkboxes for all property types (no dropdown)
    $property_type_ids_posted = $_POST['property_type_ids'] ?? [];
    if (!is_array($property_type_ids_posted)) { $property_type_ids_posted = [$property_type_ids_posted]; }
    $property_type_ids_posted = array_filter(array_map('intval', $property_type_ids_posted));
    
    // Use first selected type as primary PropertyTypeID for legacy column
    $property_type_id_posted = !empty($property_type_ids_posted) ? reset($property_type_ids_posted) : null;
    
    // Ensure unique and sorted
    sort($property_type_ids_posted);
    $property_type_ids_posted = array_unique($property_type_ids_posted);

    $title_posted = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description_posted = isset($_POST['description']) ? trim($_POST['description']) : null;
    $location_posted = isset($_POST['location']) ? trim($_POST['location']) : null;
    $area_posted = isset($_POST["area"]) && trim($_POST["area"]) !== "" ? trim($_POST["area"]) : null;
    $status_posted = isset($_POST["status"]) ? trim($_POST["status"]) : "available";
    // Determine property type category for field selection
    $is_commercial_type = in_array($property_type_id_posted, [3, 4, 5, 6]);

    // Unit Design
    $unit_design_source = $is_commercial_type ? 'unit_design_commercial' : 'unit_design';
    $unit_design_posted = isset($_POST[$unit_design_source]) && trim($_POST[$unit_design_source]) !== '' ? trim($_POST[$unit_design_source]) : null;
    $finishing_type_id_posted = isset($_POST['finishing_type_id']) && trim($_POST['finishing_type_id']) !== '' ? trim($_POST['finishing_type_id']) : null;
    $compound_area_posted = isset($_POST['compound_area']) && trim($_POST['compound_area']) !== '' ? trim($_POST['compound_area']) : null;
    $compound_area_unit_posted = in_array($_POST['compound_area_unit'] ?? '', ['faddan', 'sqm']) ? $_POST['compound_area_unit'] : 'faddan';
    $down_payment_percentage_posted = isset($_POST['down_payment_percentage']) && trim($_POST['down_payment_percentage']) !== '' ? trim($_POST['down_payment_percentage']) : null;
    $cash_discount_percentage_posted = isset($_POST['cash_discount_percentage']) && trim($_POST['cash_discount_percentage']) !== '' ? trim($_POST['cash_discount_percentage']) : null;
    $maintenance_percentage_posted = isset($_POST['maintenance_percentage']) && trim($_POST['maintenance_percentage']) !== '' ? trim($_POST['maintenance_percentage']) : null;
    $payment_years_posted = isset($_POST['payment_years']) && trim($_POST['payment_years']) !== '' ? trim($_POST['payment_years']) : null;
    $payment_plans_notes_posted = isset($_POST['payment_plans_notes']) && trim($_POST['payment_plans_notes']) !== '' ? trim($_POST['payment_plans_notes']) : null;

    // Multi-Payment Plans Parsing
    // Primary Market Payment Plans and Financial Terms
    $plan_titles = $_POST['plan_title'] ?? [];
    $plan_down_payments = $_POST['plan_down_payment'] ?? [];
    $plan_installment_years = $_POST['plan_installment_years'] ?? [];
    $plan_installment_types = $_POST['plan_installment_type'] ?? [];
    $plan_delivery_payments = $_POST['plan_delivery_payment'] ?? [];
    $plan_roi_notes = $_POST['plan_roi_notes'] ?? [];
    $plan_notes_arr = $_POST['plan_notes'] ?? [];
    if (!is_array($plan_titles)) { $plan_titles = [$plan_titles]; }
    if (!is_array($plan_down_payments)) { $plan_down_payments = [$plan_down_payments]; }
    if (!is_array($plan_installment_years)) { $plan_installment_years = [$plan_installment_years]; }
    if (!is_array($plan_installment_types)) { $plan_installment_types = [$plan_installment_types]; }
    if (!is_array($plan_delivery_payments)) { $plan_delivery_payments = [$plan_delivery_payments]; }
    if (!is_array($plan_roi_notes)) { $plan_roi_notes = [$plan_roi_notes]; }
    if (!is_array($plan_notes_arr)) { $plan_notes_arr = [$plan_notes_arr]; }

    $clubhouse_fee_posted = isset($_POST['clubhouse_fee']) && trim($_POST['clubhouse_fee']) !== '' ? trim($_POST['clubhouse_fee']) : null;
    $garage_fee_posted = isset($_POST['garage_fee']) && trim($_POST['garage_fee']) !== '' ? trim($_POST['garage_fee']) : null;
    $transfer_fee_percentage_posted = isset($_POST['transfer_fee_percentage']) && trim($_POST['transfer_fee_percentage']) !== '' && is_numeric($_POST['transfer_fee_percentage']) ? floatval($_POST['transfer_fee_percentage']) : null;
    $maintenance_payment_details_posted = isset($_POST['maintenance_payment_details']) && trim($_POST['maintenance_payment_details']) !== '' ? trim($_POST['maintenance_payment_details']) : null;

    if ($down_payment_percentage_posted === null && !empty($plan_down_payments)) {
        $valid_dps = array_filter(array_map('floatval', $plan_down_payments), fn($v) => $v > 0);
        if (!empty($valid_dps)) {
            $down_payment_percentage_posted = min($valid_dps);
        }
    }
    if ($payment_years_posted === null && !empty($plan_installment_years)) {
        $valid_iys = array_filter(array_map('floatval', $plan_installment_years), fn($v) => $v > 0);
        if (!empty($valid_iys)) {
            $payment_years_posted = max($valid_iys);
        }
    }

    $delivery_date_posted = isset($_POST['delivery_date']) && trim($_POST['delivery_date']) !== '' ? trim($_POST['delivery_date']) : null;
    $delivery_status_posted = isset($_POST['delivery_status']) ? trim($_POST['delivery_status']) : null;
    $construction_status_posted = isset($_POST['construction_status']) ? trim($_POST['construction_status']) : null;
    $project_name_posted = isset($_POST['project_name']) ? trim($_POST['project_name']) : null;
    $phase_name_posted = isset($_POST['phase_name']) ? trim($_POST['phase_name']) : null;
    $min_area_posted = isset($_POST['min_area']) && trim($_POST['min_area']) !== '' ? trim($_POST['min_area']) : null;
    $max_area_posted = isset($_POST['max_area']) && trim($_POST['max_area']) !== '' ? trim($_POST['max_area']) : null;
    $avg_area_posted = isset($_POST['avg_area']) && trim($_POST['avg_area']) !== '' ? trim($_POST['avg_area']) : null;
    $district_posted = isset($_POST['district']) ? trim($_POST['district']) : null;
    $governorate_posted = isset($_POST['governorate']) ? trim($_POST['governorate']) : null;
    $company_name_posted = isset($_POST['company_name']) ? trim($_POST['company_name']) : null;
    
    // Concatenate Company Phone
    $cp_code = $_POST['company_phone_code'] ?? '';
    $cp_number = $_POST['company_phone_number'] ?? '';
    $company_phone_posted = ($cp_code !== '' && $cp_number !== '') ? $cp_code . $cp_number : null;

    $company_phone_2_posted = isset($_POST['company_phone_2']) && trim($_POST['company_phone_2']) !== '' ? trim($_POST['company_phone_2']) : null;

    $sales_director_name_posted = isset($_POST['sales_director_name']) ? trim($_POST['sales_director_name']) : null;
    $sd_code = $_POST['sales_director_phone_code'] ?? '';
    $sd_number = $_POST['sales_director_phone_number'] ?? '';
    $sales_director_phone_posted = ($sd_code !== '' && $sd_number !== '') ? $sd_code . $sd_number : null;

    $operation_manager_name_posted = isset($_POST['operation_manager_name']) ? trim($_POST['operation_manager_name']) : null;
    $om_code = $_POST['operation_manager_phone_code'] ?? '';
    $om_number = $_POST['operation_manager_phone_number'] ?? '';
    $operation_manager_phone_posted = ($om_code !== '' && $om_number !== '') ? $om_code . $om_number : null;

    $floors_count_posted = isset($_POST["floors_count"]) && trim($_POST["floors_count"]) !== '' ? trim($_POST["floors_count"]) : null;
    $sales_manager_name_posted = isset($_POST['sales_manager_name']) ? trim($_POST['sales_manager_name']) : null;
    
    // Concatenate Sales Manager Phone
    $sm_code = $_POST['sales_manager_phone_code'] ?? '';
    $sm_number = $_POST['sales_manager_phone_number'] ?? '';
    $sales_manager_phone_posted = ($sm_code !== '' && $sm_number !== '') ? $sm_code . $sm_number : null;

    $salesperson_name_posted = isset($_POST['salesperson_name']) ? trim($_POST['salesperson_name']) : null;
    
    // Concatenate Salesperson Phone
    $sp_code = $_POST['salesperson_phone_code'] ?? '';
    $sp_number = $_POST['salesperson_phone_number'] ?? '';
    $salesperson_phone_posted = ($sp_code !== '' && $sp_number !== '') ? $sp_code . $sp_number : null;
    $latitude_posted = isset($_POST['latitude']) && trim($_POST['latitude']) !== '' ? filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $longitude_posted = isset($_POST['longitude']) && trim($_POST['longitude']) !== '' ? filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    
    // Offers & Brochure
    $offer_title_posted = isset($_POST['offer_title']) ? trim($_POST['offer_title']) : null;
    $offer_description_posted = isset($_POST['offer_description']) ? trim($_POST['offer_description']) : null;
    $offer_start_date_posted = !empty($_POST['offer_start_date']) ? trim($_POST['offer_start_date']) : null;
    $offer_end_date_posted = !empty($_POST['offer_end_date']) ? trim($_POST['offer_end_date']) : null;
    $brochure_url_posted = isset($_POST['brochure_url']) ? trim($_POST['brochure_url']) : null;

    // New Phase 2 fields
    $year_built_posted = isset($_POST['year_built']) ? trim($_POST['year_built']) : null;
    // Multi-seller parsing
    $posted_seller_types = $_POST['seller_type'] ?? [];
    $posted_seller_names = $_POST['seller_name'] ?? [];
    $posted_seller_phones = $_POST['seller_phone'] ?? [];
    if (!is_array($posted_seller_types)) { $posted_seller_types = [$posted_seller_types]; }
    if (!is_array($posted_seller_names)) { $posted_seller_names = [$posted_seller_names]; }
    if (!is_array($posted_seller_phones)) { $posted_seller_phones = [$posted_seller_phones]; }
    $seller_entries_post = [];
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
        $seller_entries_post[] = [
            'seller_type' => ($stype === '') ? null : $stype,
            'seller_name' => ($sname === '') ? null : $sname,
            'seller_phone' => ($sphone === '') ? null : $sphone,
        ];
    }
    if (empty($seller_entries_post)) {
        $seller_entries_post[] = ['seller_type' => null, 'seller_name' => null, 'seller_phone' => null];
    }
    $seller_type_posted = $seller_entries_post[0]['seller_type'];
    $unit_floor_posted = isset($_POST['unit_floor']) ? trim($_POST['unit_floor']) : null;
    $net_area_posted = isset($_POST['net_area']) && trim($_POST['net_area']) !== '' ? filter_input(INPUT_POST, 'net_area', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $loading_percentage_posted = isset($_POST['loading_percentage']) && trim($_POST['loading_percentage']) !== '' ? filter_input(INPUT_POST, 'loading_percentage', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $price_cash_posted = isset($_POST['price_cash']) && trim($_POST['price_cash']) !== '' ? filter_input(INPUT_POST, 'price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $price_installments_posted = isset($_POST['price_installments']) && trim($_POST['price_installments']) !== '' ? filter_input(INPUT_POST, 'price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $min_price_posted = isset($_POST['min_price']) && trim($_POST['min_price']) !== '' ? filter_input(INPUT_POST, 'min_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $max_price_posted = isset($_POST['max_price']) && trim($_POST['max_price']) !== '' ? filter_input(INPUT_POST, 'max_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $monthly_rent_posted = isset($_POST['monthly_rent']) && trim($_POST['monthly_rent']) !== '' ? filter_input(INPUT_POST, 'monthly_rent', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $daily_rent_posted = isset($_POST['daily_rent']) && trim($_POST['daily_rent']) !== '' ? filter_input(INPUT_POST, 'daily_rent', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $yearly_rent_posted = isset($_POST['yearly_rent']) && trim($_POST['yearly_rent']) !== '' ? filter_input(INPUT_POST, 'yearly_rent', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $meter_price_cash_posted = isset($_POST['meter_price_cash']) && trim($_POST['meter_price_cash']) !== '' ? filter_input(INPUT_POST, 'meter_price_cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $meter_price_installments_posted = isset($_POST['meter_price_installments']) && trim($_POST['meter_price_installments']) !== '' ? filter_input(INPUT_POST, 'meter_price_installments', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $min_meter_price_posted = isset($_POST['min_meter_price']) && trim($_POST['min_meter_price']) !== '' ? filter_input(INPUT_POST, 'min_meter_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $max_meter_price_posted = isset($_POST['max_meter_price']) && trim($_POST['max_meter_price']) !== '' ? filter_input(INPUT_POST, 'max_meter_price', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
    $seller_name_posted = $seller_entries_post[0]['seller_name'] ?? null;
    $seller_phone_posted = $seller_entries_post[0]['seller_phone'] ?? null;
    // Bedrooms/Rooms
    $rooms_source = $is_commercial_type ? 'rooms_commercial' : 'rooms';
    $bedrooms_posted = isset($_POST[$rooms_source]) ? filter_input(INPUT_POST, $rooms_source, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;

    // Bathrooms
    $bathrooms_source = $is_commercial_type ? 'bathrooms_commercial' : 'bathrooms';
    $bathrooms_posted = isset($_POST[$bathrooms_source]) ? filter_input(INPUT_POST, $bathrooms_source, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;

    // Balconies
    $balconies_source = $is_commercial_type ? 'balconies_commercial' : 'balconies';
    $balconies_posted = isset($_POST[$balconies_source]) ? filter_input(INPUT_POST, $balconies_source, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;

    // Elevators
    $elevators_source = $is_commercial_type ? 'elevators_commercial' : 'elevators';
    $elevators_posted = isset($_POST[$elevators_source]) ? filter_input(INPUT_POST, $elevators_source, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;
    $currency = $_POST['currency'] ?? 'EGP';
   
    // --- Validation ---
    if (empty($governorate_posted)) {
        $errors['governorate'] = t('governorate_required');
    }
    if (empty($district_posted)) {
        $errors['district'] = t('district_required');
    }
    
    if ($area_posted !== null && (!is_numeric($area_posted) || $area_posted < 0)) {
        $errors["area"] = t("enter_valid_area");
    }
    if ($bedrooms_posted !== null && !filter_var($bedrooms_posted, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]])) {
        $errors["bedrooms"] = t("enter_valid_bedrooms");
    }
    if ($bathrooms_posted !== null && !filter_var($bathrooms_posted, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]])) {
        $errors["bathrooms"] = t("enter_valid_bathrooms");
    }
    if ($balconies_posted !== null && !filter_var($balconies_posted, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]])) {
        $errors["balconies"] = t("enter_valid_balconies");
    }
    if ($elevators_posted !== null && !filter_var($elevators_posted, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]])) {
        $errors["elevators"] = t("enter_valid_elevators");
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

    // Phone validation: relaxed strict 11-digit check for international numbers
    function isValidPhoneFormat($phone) {
        $clean = preg_replace('/[^0-9]/', '', (string)$phone);
        if (strpos($clean, '20') === 0) {
             return strlen($clean) === 12; // 20 + 10 digits
        }
        return strlen($clean) >= 8 && strlen($clean) <= 15;
    }

    // Phone & Hotline validator (accepts 4-6 digits hotline or standard phone)
    function isValidPhoneOrHotline($phone) {
        $clean = preg_replace('/[^0-9]/', '', (string)$phone);
        if (strlen($clean) >= 4 && strlen($clean) <= 6) {
            return true; // Hotline like 19xxx, 16xxx
        }
        return isValidPhoneFormat($phone);
    }

    foreach ($seller_entries_post as $idx => $seller_row) {
        $phone_val = $seller_row['seller_phone'] ?? '';
        if ($phone_val !== null && trim($phone_val) !== '') {
            if (!isValidPhoneFormat($phone_val)) {
                $errors['seller_phone_' . $idx] = 'رقم الهاتف غير صحيح (يجب أن يكون متوافقاً مع مفتاح الدولة).';
            }
        }
    }
    if (!empty($sales_manager_phone_posted) && !isValidPhoneFormat($sales_manager_phone_posted)) {
        $errors['sales_manager_phone'] = 'رقم هاتف مدير المبيعات غير صحيح.';
    }
    if (!empty($salesperson_phone_posted) && !isValidPhoneFormat($salesperson_phone_posted)) {
        $errors['salesperson_phone'] = 'رقم هاتف مسؤول المبيعات غير صحيح.';
    }
    if (!empty($company_phone_posted) && !isValidPhoneOrHotline($company_phone_posted)) {
        $errors['company_phone'] = 'رقم هاتف الشركة المطورة غير صحيح.';
    }
    if (!empty($company_phone_2_posted) && !isValidPhoneOrHotline($company_phone_2_posted)) {
        $errors['company_phone_2'] = 'الخط الساخن / الهاتف الإضافي للشركة غير صحيح.';
    }
    if (!empty($sales_director_phone_posted) && !isValidPhoneFormat($sales_director_phone_posted)) {
        $errors['sales_director_phone'] = 'رقم هاتف رئيس قطاع المبيعات غير صحيح.';
    }
    if (!empty($operation_manager_phone_posted) && !isValidPhoneFormat($operation_manager_phone_posted)) {
        $errors['operation_manager_phone'] = 'رقم هاتف مدير العمليات غير صحيح.';
    }

    // If no errors, proceed with update
    if (empty($errors)) {
        $sql_update = "UPDATE Properties SET 
            PropertyTypeID = ?, Title = ?, Description = ?, Location = ?, Area = ?, Bedrooms = ?,
            Status = ?, unit_design = ?, finishing_type_id = ?, 
            compound_area = ?, compound_area_unit = ?, down_payment_percentage = ?, cash_discount_percentage = ?, maintenance_percentage = ?, clubhouse_fee = ?, garage_fee = ?, transfer_fee_percentage = ?, maintenance_payment_details = ?, payment_years = ?, payment_plans_notes = ?, delivery_date = ?, delivery_status = ?, construction_status = ?,
            ProjectName = ?, phase_name = ?, min_area = ?, max_area = ?, avg_area = ?, 
            district = ?, governorate = ?, company_name = ?, company_phone = ?, company_phone_2 = ?, FloorsCount = ?,
            sales_director_name = ?, sales_director_phone = ?, operation_manager_name = ?, operation_manager_phone = ?,
            sales_manager_name = ?, sales_manager_phone = ?, salesperson_name = ?, salesperson_phone = ?,
            year_built = ?, unit_floor = ?, net_area = ?, loading_percentage = ?, 
            price_cash = ?, price_installments = ?, min_price = ?, max_price = ?,
            meter_price_cash = ?, meter_price_installments = ?, min_meter_price = ?, max_meter_price = ?,
            monthly_rent = ?, daily_rent = ?, yearly_rent = ?,
            seller_type = ?, seller_name = ?, seller_phone = ?,
            offer_title = ?, offer_description = ?, offer_start_date = ?, offer_end_date = ?, brochure_url = ?,
            latitude = ?, longitude = ?, Bathrooms = ?, balconies = ?, elevators = ?, property_data_source = ?, currency = ?
            WHERE PropertyID = ?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            // Convert empty strings to null for numeric fields
            $area_val = empty($area_posted) ? null : $area_posted;
            $bedrooms_val = empty($bedrooms_posted) ? null : $bedrooms_posted;
            $bathrooms_val = empty($bathrooms_posted) ? null : $bathrooms_posted;
            $balconies_val = empty($balconies_posted) ? null : $balconies_posted;
            $elevators_val = empty($elevators_posted) ? null : $elevators_posted;
            $compound_area_val = empty($compound_area_posted) ? null : $compound_area_posted;
            $down_payment_val = empty($down_payment_percentage_posted) ? null : $down_payment_percentage_posted;
            $cash_discount_val = empty($cash_discount_percentage_posted) ? null : $cash_discount_percentage_posted;
            $maintenance_val = empty($maintenance_percentage_posted) ? null : $maintenance_percentage_posted;
            $payment_years_val = empty($payment_years_posted) ? null : $payment_years_posted;
            $min_area_val = empty($min_area_posted) ? null : $min_area_posted;
            $max_area_val = empty($max_area_posted) ? null : $max_area_posted;
            $avg_area_val = empty($avg_area_posted) ? null : $avg_area_posted;
            $floors_count_val = empty($floors_count_posted) ? null : $floors_count_posted;
            $net_area_val = empty($net_area_posted) ? null : $net_area_posted;
            $loading_percentage_val = empty($loading_percentage_posted) ? null : $loading_percentage_posted;
            $price_cash_val = empty($price_cash_posted) ? null : $price_cash_posted;
            $price_installments_val = empty($price_installments_posted) ? null : $price_installments_posted;
            $min_price_val = empty($min_price_posted) ? null : $min_price_posted;
            $max_price_val = empty($max_price_posted) ? null : $max_price_posted;
            $monthly_rent_val = empty($monthly_rent_posted) ? null : $monthly_rent_posted;
            $daily_rent_val = empty($daily_rent_posted) ? null : $daily_rent_posted;
            $yearly_rent_val = empty($yearly_rent_posted) ? null : $yearly_rent_posted;
            $meter_price_cash_val = empty($meter_price_cash_posted) ? null : $meter_price_cash_posted;
            $meter_price_installments_val = empty($meter_price_installments_posted) ? null : $meter_price_installments_posted;
            $min_meter_price_val = empty($min_meter_price_posted) ? null : $min_meter_price_posted;
            $max_meter_price_val = empty($max_meter_price_posted) ? null : $max_meter_price_posted;
            $latitude_val = empty($latitude_posted) ? null : $latitude_posted;
            $longitude_val = empty($longitude_posted) ? null : $longitude_posted;
            $finishing_type_id_val = empty($finishing_type_id_posted) ? null : intval($finishing_type_id_posted);

            // Total 72 parameters + 1 PropertyID = 73
            $types = str_repeat('s', 72) . 'i';
            $stmt_update->bind_param(
                $types,
                $property_type_id_posted,
                $title_posted,
                $description_posted,
                $location_posted,
                $area_val,
                $bedrooms_val,
                $status_posted,
                $unit_design_posted,
                $finishing_type_id_val,
                $compound_area_val,
                $compound_area_unit_posted,
                $down_payment_val,
                $cash_discount_val,
                $maintenance_val,
                $clubhouse_fee_posted,
                $garage_fee_posted,
                $transfer_fee_percentage_posted,
                $maintenance_payment_details_posted,
                $payment_years_val,
                $payment_plans_notes_posted,
                $delivery_date_posted,
                $delivery_status_posted,
                $construction_status_posted,
                $project_name_posted,
                $phase_name_posted,
                $min_area_val,
                $max_area_val,
                $avg_area_val,
                $district_posted,
                $governorate_posted,
                $company_name_posted,
                $company_phone_posted,
                $company_phone_2_posted,
                $floors_count_val,
                $sales_director_name_posted,
                $sales_director_phone_posted,
                $operation_manager_name_posted,
                $operation_manager_phone_posted,
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
                $min_price_val,
                $max_price_val,
                $meter_price_cash_val,
                $meter_price_installments_val,
                $min_meter_price_val,
                $max_meter_price_val,
                $monthly_rent_val,
                $daily_rent_val,
                $yearly_rent_val,
                $seller_type_posted,
                $seller_name_posted,
                $seller_phone_posted,
                $offer_title_posted,
                $offer_description_posted,
                $offer_start_date_posted,
                $offer_end_date_posted,
                $brochure_url_posted,
                $latitude_val,
                $longitude_val,
                $bathrooms_val,
                $balconies_val,
                $elevators_val,
                $property_data_source_posted,
                $currency,
                $property_id
            );

            if ($stmt_update->execute()) {
                // Sync PropertyPaymentPlans table
                $conn->query("DELETE FROM PropertyPaymentPlans WHERE PropertyID = " . intval($property_id));
                if (!empty($plan_down_payments) && is_array($plan_down_payments)) {
                    $stmt_plan = $conn->prepare("INSERT INTO PropertyPaymentPlans (PropertyID, plan_title, down_payment_percentage, installment_years, installment_type, delivery_payment_percentage, roi_notes, plan_notes, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt_plan) {
                        $order = 1;
                        foreach ($plan_down_payments as $idx => $dp_raw) {
                            $p_title = trim((string)($plan_titles[$idx] ?? '')) !== '' ? trim((string)$plan_titles[$idx]) : null;
                            $dp_val = (trim((string)$dp_raw) !== '' && is_numeric($dp_raw)) ? floatval($dp_raw) : null;
                            $iy_raw = $plan_installment_years[$idx] ?? '';
                            $iy_val = (trim((string)$iy_raw) !== '' && is_numeric($iy_raw)) ? floatval($iy_raw) : null;
                            $i_type = trim((string)($plan_installment_types[$idx] ?? 'quarterly')) ?: 'quarterly';
                            $del_raw = $plan_delivery_payments[$idx] ?? '';
                            $del_val = (trim((string)$del_raw) !== '' && is_numeric($del_raw)) ? floatval($del_raw) : null;
                            $roi_val = trim((string)($plan_roi_notes[$idx] ?? '')) !== '' ? trim((string)$plan_roi_notes[$idx]) : null;
                            $notes_val = trim((string)($plan_notes_arr[$idx] ?? '')) !== '' ? trim((string)$plan_notes_arr[$idx]) : null;

                            if ($dp_val !== null || $iy_val !== null || $roi_val !== null || $notes_val !== null || $del_val !== null || $p_title !== null) {
                                $stmt_plan->bind_param("isdssdssi", $property_id, $p_title, $dp_val, $iy_val, $i_type, $del_val, $roi_val, $notes_val, $order);
                                $stmt_plan->execute();
                                $order++;
                            }
                        }
                        $stmt_plan->close();
                    }
                }

                // Sync property_type_links
                $sql_del = "DELETE FROM property_type_links WHERE property_id = ?";
                if ($stmt_del = $conn->prepare($sql_del)) {
                    $stmt_del->bind_param("i", $property_id);
                    $stmt_del->execute();
                    $stmt_del->close();
                }
                if (!empty($property_type_ids_posted)) {
                    $sql_ins = "INSERT INTO property_type_links (property_id, property_type_id) VALUES (?, ?)";
                    if ($stmt_ins = $conn->prepare($sql_ins)) {
                        foreach ($property_type_ids_posted as $tid) {
                            $stmt_ins->bind_param("ii", $property_id, $tid);
                            $stmt_ins->execute();
                        }
                        $stmt_ins->close();
                    }
                }

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

                // Sync multi-sellers table
                ensurePropertySellersTable($conn);
                // Clear existing rows then insert posted sellers
                if ($delStmt = $conn->prepare("DELETE FROM property_sellers WHERE property_id = ?")) {
                    $delStmt->bind_param('i', $property_id);
                    $delStmt->execute();
                    $delStmt->close();
                }
                if (!empty($seller_entries_post)) {
                    if ($insSeller = $conn->prepare("INSERT INTO property_sellers (property_id, seller_type, seller_name, seller_phone) VALUES (?, ?, ?, ?)")) {
                        foreach ($seller_entries_post as $row) {
                            $stype = $row['seller_type'];
                            $sname = $row['seller_name'];
                            $sphone = $row['seller_phone'];
                            $insSeller->bind_param("isss", $property_id, $stype, $sname, $sphone);
                            $insSeller->execute();
                        }
                        $insSeller->close();
                    }
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
                    'cash_discount_percentage' => $cash_discount_val,
                    'maintenance_percentage' => $maintenance_val,
                    'payment_years' => $payment_years_val,
                    'delivery_date' => $delivery_date_posted,
                    'delivery_status' => $delivery_status_posted,
                    'construction_status' => $construction_status_posted,
                    'ProjectName' => $project_name_posted,
                    'phase_name' => $phase_name_posted,
                    'min_area' => $min_area_val,
                    'max_area' => $max_area_val,
                    'avg_area' => $avg_area_val,
                    'district' => $district_posted,
                    'governorate' => $governorate_posted,
                    'company_name' => $company_name_posted,
                    'company_phone' => $company_phone_posted,
                    'FloorsCount' => $floors_count_val,
                    'sales_director_name' => $sales_director_name_posted,
                    'sales_director_phone' => $sales_director_phone_posted,
                    'operation_manager_name' => $operation_manager_name_posted,
                    'operation_manager_phone' => $operation_manager_phone_posted,
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
                    'min_price' => $min_price_val,
                    'max_price' => $max_price_val,
                    'meter_price_cash' => $meter_price_cash_val,
                    'meter_price_installments' => $meter_price_installments_val,
                    'min_meter_price' => $min_meter_price_val,
                    'max_meter_price' => $max_meter_price_val,
                    'seller_type' => $seller_type_posted,
                    'seller_name' => $seller_name_posted,
                    'seller_phone' => $seller_phone_posted,
                    'offer_title' => $offer_title_posted,
                    'offer_description' => $offer_description_posted,
                    'offer_start_date' => $offer_start_date_posted,
                    'offer_end_date' => $offer_end_date_posted,
                    'brochure_url' => $brochure_url_posted,
                    'daily_rent' => $daily_rent_val
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
    <link rel="stylesheet" href="css/premium_design.css">
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
            color: #020d24;
            border-bottom: 2px solid #b08a0e;
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
            border-color: #b08a0e;
            box-shadow: 0 0 0 2px rgba(176, 138, 14, 0.2);
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

        <?php
            if (!function_exists('parsePhoneForInput')) {
                function parsePhoneForInput($fullPhone) {
                    $codes = ['+20', '+966', '+971', '+965', '+974', '+973', '+968', '+1', '+44'];
                    $fullPhone = trim($fullPhone ?? '');
                    if ($fullPhone === '') return ['code' => '+20', 'number' => ''];
                    
                    foreach ($codes as $c) {
                        if (strpos($fullPhone, $c) === 0) {
                            return ['code' => $c, 'number' => substr($fullPhone, strlen($c))];
                        }
                    }
                    if (strpos($fullPhone, '01') === 0 && strlen($fullPhone) === 11) {
                        return ['code' => '+20', 'number' => substr($fullPhone, 1)];
                    }
                    return ['code' => '+20', 'number' => $fullPhone]; 
                }
            }

            $parsedCP = parsePhoneForInput($company_phone);
            $parsedSD = parsePhoneForInput($sales_director_phone);
            $parsedOM = parsePhoneForInput($operation_manager_phone);
            $parsedSM = parsePhoneForInput($sales_manager_phone);
            $parsedSP = parsePhoneForInput($salesperson_phone);

            // Prepare seller rows for rendering in the form
            $seller_entries_for_render = $seller_entries_post ?? $property_sellers;
            if (empty($seller_entries_for_render)) {
                $seller_entries_for_render[] = [
                    'seller_type' => $seller_type ?? '',
                    'seller_name' => $seller_name ?? '',
                    'seller_phone' => $seller_phone ?? ''
                ];
            }
        ?>

        <!-- Unified Media Upload Section -->
        <div class="form-section">
            <?php include 'unified_media_uploader.php'; ?>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $property_id; ?>" method="post" class="form-container" data-prevent-multi-submit="1">
            <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
            <?php echo csrf_input(); ?>

            <?php if ($listing_type === 'Primary'): ?>
            <!-- ===================== PRIMARY PROJECT EDIT FORM ===================== -->
            
            <!-- 1. Basic Project Info -->
            <div class="form-section">
                <h3><i class="fas fa-building" style="margin-left: 8px; color: #3b82f6;"></i><?php echo t('project_info') ?: 'معلومات المشروع والمطور'; ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('listing_type'); ?>:</label>
                        <span class="form-display" style="font-weight: bold; color: #2563eb;"><?php echo t('primary_properties'); ?></span>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label><?php echo t('property_type'); ?>: *</label>
                        <div id="property_type_checkboxes" class="checkbox-group" style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0;">
                            <?php foreach ($property_types_list as $type): ?>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: #fff; padding: 8px 15px; border-radius: 6px; border: 1px solid #dee2e6; transition: all 0.2s;">
                                    <input type="checkbox" name="property_type_ids[]" value="<?php echo $type['PropertyTypeID']; ?>" <?php echo (in_array($type['PropertyTypeID'], $linked_property_types)) ? 'checked' : ''; ?>>
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
                        <input type="hidden" name="property_type_id" id="property_type_id" value="<?php echo $property_type_id; ?>">
                        <?php if (isset($errors['property_type_id'])) {
                            echo "<span class='error-message'>" . $errors['property_type_id'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="project_name"><?php echo t('project_name'); ?>: *</label>
                        <input type="text" id="project_name" name="project_name" class="form-input" value="<?php echo htmlspecialchars($project_name); ?>" required>
                        <?php if (isset($errors['project_name'])) {
                            echo "<span class='error-message'>" . $errors['project_name'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="property_data_source"><?php echo t('property_data_source'); ?>:</label>
                        <input type="text" id="property_data_source" name="property_data_source" class="form-input" value="<?php echo htmlspecialchars($_POST['property_data_source'] ?? $property_data_source); ?>" placeholder="مثال: أحمد محمد">
                    </div>
                    <div class="form-group">
                        <label for="phase_name"><?php echo t('phase_name'); ?>:</label>
                        <input type="text" id="phase_name" name="phase_name" class="form-input" value="<?php echo htmlspecialchars($phase_name); ?>" placeholder="مثال: المرحلة الأولى / Cluster A">
                    </div>
                    <div class="form-group">
                        <label for="company_name"><?php echo t('developer_company'); ?>: *</label>
                        <input type="text" id="company_name" name="company_name" class="form-input" value="<?php echo htmlspecialchars($company_name); ?>" required>
                        <?php if (isset($errors['company_name'])) {
                            echo "<span class='error-message'>" . $errors['company_name'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="status"><?php echo t('status'); ?>:</label>
                        <select id="status" name="status" class="form-input">
                            <option value="available" <?php echo ($status == 'available') ? 'selected' : ''; ?>><?php echo t('available'); ?></option>
                            <option value="sold" <?php echo ($status == 'sold') ? 'selected' : ''; ?>><?php echo t('sold'); ?></option>
                            <option value="unavailable" <?php echo ($status == 'unavailable') ? 'selected' : ''; ?>><?php echo t('unavailable'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 2. Developer & Management Contacts -->
            <div class="form-section" style="border-right: 4px solid #10b981;">
                <h3><i class="fas fa-address-book" style="margin-left: 8px; color: #10b981;"></i><?php echo t('developer_contacts'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="company_phone"><?php echo t('company_phone'); ?> (موبايل / أرضي):</label>
                        <div class="phone-input-group" style="display:flex; gap:10px;">
                            <select name="company_phone_code" id="company_phone_code" class="form-input" style="width:120px;"></select>
                            <input type="text" name="company_phone_number" id="company_phone_number" class="form-input" value="<?php echo htmlspecialchars($parsedCP['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedCP['code']); ?>" placeholder="10xxxxxxxx">
                        </div>
                        <?php if (isset($errors['company_phone'])) { echo "<span class='error-message'>" . $errors['company_phone'] . "</span>"; } ?>
                    </div>

                    <div class="form-group">
                        <label for="company_phone_2"><?php echo t('company_phone_2'); ?>:</label>
                        <input type="text" id="company_phone_2" name="company_phone_2" class="form-input" value="<?php echo htmlspecialchars($company_phone_2); ?>" placeholder="مثال: الخط الساخن 19xxx أو هاتف إضافي">
                        <?php if (isset($errors['company_phone_2'])) { echo "<span class='error-message'>" . $errors['company_phone_2'] . "</span>"; } ?>
                    </div>

                    <div class="form-group">
                        <label for="sales_director_name"><?php echo t('sales_director_name'); ?>:</label>
                        <input type="text" id="sales_director_name" name="sales_director_name" class="form-input" value="<?php echo htmlspecialchars($sales_director_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sales_director_phone"><?php echo t('sales_director_phone'); ?>:</label>
                        <div class="phone-input-group" style="display:flex; gap:10px;">
                            <select name="sales_director_phone_code" id="sales_director_phone_code" class="form-input" style="width:120px;"></select>
                            <input type="text" name="sales_director_phone_number" id="sales_director_phone_number" class="form-input" value="<?php echo htmlspecialchars($parsedSD['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedSD['code']); ?>" placeholder="10xxxxxxxx">
                        </div>
                        <?php if (isset($errors['sales_director_phone'])) { echo "<span class='error-message'>" . $errors['sales_director_phone'] . "</span>"; } ?>
                    </div>

                    <div class="form-group">
                        <label for="operation_manager_name"><?php echo t('operation_manager_name'); ?>:</label>
                        <input type="text" id="operation_manager_name" name="operation_manager_name" class="form-input" value="<?php echo htmlspecialchars($operation_manager_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="operation_manager_phone"><?php echo t('operation_manager_phone'); ?>:</label>
                        <div class="phone-input-group" style="display:flex; gap:10px;">
                            <select name="operation_manager_phone_code" id="operation_manager_phone_code" class="form-input" style="width:120px;"></select>
                            <input type="text" name="operation_manager_phone_number" id="operation_manager_phone_number" class="form-input" value="<?php echo htmlspecialchars($parsedOM['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedOM['code']); ?>" placeholder="10xxxxxxxx">
                        </div>
                        <?php if (isset($errors['operation_manager_phone'])) { echo "<span class='error-message'>" . $errors['operation_manager_phone'] . "</span>"; } ?>
                    </div>

                    <div class="form-group">
                        <label for="sales_manager_name"><?php echo t("sales_manager_name_label"); ?>:</label>
                        <input type="text" id="sales_manager_name" name="sales_manager_name" class="form-input" value="<?php echo htmlspecialchars($sales_manager_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sales_manager_phone"><?php echo t("sales_manager_phone"); ?>:</label>
                        <div class="phone-input-group" style="display:flex; gap:10px;">
                            <select name="sales_manager_phone_code" id="sales_manager_phone_code" class="form-input" style="width:120px;"></select>
                            <input type="text" name="sales_manager_phone_number" id="sales_manager_phone_number" class="form-input" value="<?php echo htmlspecialchars($parsedSM['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedSM['code']); ?>" placeholder="10xxxxxxxx">
                        </div>
                        <?php if (isset($errors['sales_manager_phone'])) { echo "<span class='error-message'>" . $errors['sales_manager_phone'] . "</span>"; } ?>
                    </div>

                    <div class="form-group">
                        <label for="salesperson_name"><?php echo t("salesperson_name"); ?>:</label>
                        <input type="text" name="salesperson_name" id="salesperson_name" class="form-input" value="<?php echo htmlspecialchars($salesperson_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="salesperson_phone"><?php echo t("salesperson_phone"); ?>:</label>
                        <div class="phone-input-group" style="display:flex; gap:10px;">
                            <select name="salesperson_phone_code" id="salesperson_phone_code" class="form-input" style="width:120px;"></select>
                            <input type="text" name="salesperson_phone_number" id="salesperson_phone_number" class="form-input" value="<?php echo htmlspecialchars($parsedSP['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedSP['code']); ?>" placeholder="10xxxxxxxx">
                        </div>
                        <?php if (isset($errors['salesperson_phone'])) { echo "<span class='error-message'>" . $errors['salesperson_phone'] . "</span>"; } ?>
                    </div>
                </div>
            </div>

            <!-- 3. Location Info -->
            <div class="form-section">
                <h3><i class="fas fa-map-marker-alt" style="margin-left: 8px; color: #ef4444;"></i><?php echo t('location_info'); ?></h3>
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
                    <input type="text" id="location" name="location" class="form-input" value="<?php echo htmlspecialchars($location); ?>" placeholder="مثال: التجمع الخامس - بجوار الجامعة الأمريكية">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1; margin-top: 15px;">
                    <label><?php echo t('select_location_on_map'); ?>:</label>
                    <div style="margin:8px 0; display:flex; gap:8px; align-items:center;">
                        <input type="text" id="google_map_link" class="form-input" style="flex:1;" placeholder="https://www.google.com/maps/..." value="<?php echo htmlspecialchars($google_map_link ?? ''); ?>">
                        <button type="button" id="parse_google_link" class="btn">تحديد من الرابط</button>
                    </div>
                    <small>لصق رابط خرائط جوجل ثم اضغط "تحديد من الرابط"</small>
                    <div id="map" style="height: 350px; width: 100%; border: 1px solid #ddd; border-radius: 6px; margin-top: 8px;"></div>
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude ?? ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude ?? ''); ?>">
                </div>
            </div>

            <!-- 4. Project Specs & Area Ranges -->
            <div class="form-section">
                <h3><i class="fas fa-ruler-combined" style="margin-left: 8px; color: #8b5cf6;"></i><?php echo t('project_specs_and_areas'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="compound_area"><?php echo t('compound_area'); ?>:</label>
                        <div style="display:flex; gap:8px;">
                            <input type="number" step="1" id="compound_area" name="compound_area" class="form-input" style="flex:1;" value="<?php echo formatInputValue($compound_area); ?>" placeholder="مساحة المشروع">
                            <select name="compound_area_unit" id="compound_area_unit" class="form-input" style="width:130px;">
                                <option value="faddan" <?php echo ($compound_area_unit === 'faddan') ? 'selected' : ''; ?>><?php echo t('unit_faddan'); ?></option>
                                <option value="sqm" <?php echo ($compound_area_unit === 'sqm') ? 'selected' : ''; ?>><?php echo t('unit_sqm'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="delivery_date"><?php echo t('delivery_date'); ?>:</label>
                        <input type="date" id="delivery_date" name="delivery_date" class="form-input" value="<?php echo htmlspecialchars($delivery_date); ?>">
                    </div>
                    <div class="form-group">
                        <label for="delivery_status"><?php echo t('delivery_status'); ?>:</label>
                        <select id="delivery_status" name="delivery_status" class="form-input">
                            <option value=""><?php echo t('select'); ?></option>
                            <option value="فوري" <?php echo ($delivery_status === 'فوري') ? 'selected' : ''; ?>><?php echo t('delivery_status_ready'); ?></option>
                            <option value="خلال سنة" <?php echo ($delivery_status === 'خلال سنة') ? 'selected' : ''; ?>><?php echo t('delivery_status_1year'); ?></option>
                            <option value="خلال سنتين" <?php echo ($delivery_status === 'خلال سنتين') ? 'selected' : ''; ?>><?php echo t('delivery_status_2years'); ?></option>
                            <option value="خلال 3 سنوات" <?php echo ($delivery_status === 'خلال 3 سنوات') ? 'selected' : ''; ?>><?php echo t('delivery_status_3years'); ?></option>
                            <option value="خلال 4 سنوات" <?php echo ($delivery_status === 'خلال 4 سنوات') ? 'selected' : ''; ?>><?php echo t('delivery_status_4years'); ?></option>
                            <option value="تحت الإنشاء" <?php echo ($delivery_status === 'تحت الإنشاء') ? 'selected' : ''; ?>><?php echo t('delivery_status_under_construction'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="construction_status"><?php echo t('construction_status'); ?>:</label>
                        <input type="text" id="construction_status" name="construction_status" class="form-input" value="<?php echo htmlspecialchars($construction_status); ?>" placeholder="مثال: تم إنجاز 70% من الأعمال الإنشائية">
                    </div>
                    <div class="form-group">
                        <label for="min_area"><?php echo t('min_area_sqm'); ?>:</label>
                        <input type="number" step="1" id="min_area" name="min_area" class="form-input" value="<?php echo formatInputValue($min_area); ?>" placeholder="مثال: 120">
                    </div>
                    <div class="form-group">
                        <label for="max_area"><?php echo t('max_area_sqm'); ?>:</label>
                        <input type="number" step="1" id="max_area" name="max_area" class="form-input" value="<?php echo formatInputValue($max_area); ?>" placeholder="مثال: 350">
                    </div>
                    <div class="form-group">
                        <label for="avg_area"><?php echo t('avg_area_sqm'); ?>:</label>
                        <input type="number" step="1" id="avg_area" name="avg_area" class="form-input" value="<?php echo formatInputValue($avg_area); ?>">
                    </div>
                    <div class="form-group">
                        <label for="finishing_type_id"><?php echo t('finishing_type'); ?>:</label>
                        <select id="finishing_type_id" name="finishing_type_id" class="form-input">
                            <option value=""><?php echo t('select_finishing_type'); ?></option>
                            <?php foreach ($finishing_types_list as $finish): ?>
                                <option value="<?php echo $finish['FinishingID']; ?>" <?php echo ($finishing_type_id == $finish['FinishingID']) ? 'selected' : ''; ?>>
                                    <?php echo (get_lang_code() === 'ar' && !empty($finish['FinishingName_ar'])) ? htmlspecialchars($finish['FinishingName_ar']) : htmlspecialchars($finish['FinishingName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 5. Pricing Ranges & Multiple Payment Plans -->
            <div class="form-section" style="border-right: 4px solid #2563eb;">
                <h3><i class="fas fa-coins" style="margin-left: 8px; color: #f59e0b;"></i><?php echo t('pricing_and_payment'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="currency"><?php echo t('currency'); ?>:</label>
                        <select id="currency" name="currency" class="form-input">
                            <option value="EGP" <?php echo ($currency == 'EGP') ? 'selected' : ''; ?>><?php echo t('EGP'); ?></option>
                            <option value="USD" <?php echo ($currency == 'USD') ? 'selected' : ''; ?>><?php echo t('USD'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="min_price"><?php echo t('min_price'); ?>:</label>
                        <input type="number" step="1" id="min_price" name="min_price" class="form-input" value="<?php echo formatInputValue($min_price); ?>" placeholder="مثال: 3000000">
                    </div>
                    <div class="form-group">
                        <label for="max_price"><?php echo t('max_price'); ?>:</label>
                        <input type="number" step="1" id="max_price" name="max_price" class="form-input" value="<?php echo formatInputValue($max_price); ?>" placeholder="مثال: 12000000">
                    </div>
                    <div class="form-group">
                        <label for="min_meter_price"><?php echo t('min_meter_price'); ?>:</label>
                        <input type="number" step="1" id="min_meter_price" name="min_meter_price" class="form-input" value="<?php echo formatInputValue($min_meter_price); ?>" placeholder="مثال: 25000">
                    </div>
                    <div class="form-group">
                        <label for="max_meter_price"><?php echo t('max_meter_price'); ?>:</label>
                        <input type="number" step="1" id="max_meter_price" name="max_meter_price" class="form-input" value="<?php echo formatInputValue($max_meter_price); ?>" placeholder="مثال: 45000">
                    </div>
                </div>

                <!-- Project-Wide Financial Terms & Fees -->
                <div style="margin-top: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <h4 style="margin: 0 0 14px 0; font-size: 1rem; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-file-invoice-dollar" style="color: #10b981;"></i> <?php echo t('project_financial_terms'); ?>
                    </h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cash_discount_percentage"><?php echo t('cash_discount_percentage'); ?>:</label>
                            <input type="number" step="1" id="cash_discount_percentage" name="cash_discount_percentage" class="form-input" min="0" max="100" value="<?php echo formatInputValue($cash_discount_percentage ?? ''); ?>" placeholder="مثال: 30">
                        </div>
                        <div class="form-group">
                            <label for="maintenance_percentage"><?php echo t('maintenance_percentage'); ?>:</label>
                            <input type="number" step="1" id="maintenance_percentage" name="maintenance_percentage" class="form-input" min="0" max="100" value="<?php echo formatInputValue($maintenance_percentage ?? ''); ?>" placeholder="مثال: 8 أو 10">
                        </div>
                        <div class="form-group">
                            <label for="maintenance_payment_details"><?php echo t('maintenance_payment_details'); ?>:</label>
                            <input type="text" id="maintenance_payment_details" name="maintenance_payment_details" class="form-input" value="<?php echo htmlspecialchars($maintenance_payment_details ?? ''); ?>" placeholder="<?php echo t('maintenance_payment_details_placeholder'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="clubhouse_fee"><?php echo t('clubhouse_fee'); ?>:</label>
                            <input type="text" id="clubhouse_fee" name="clubhouse_fee" class="form-input" value="<?php echo htmlspecialchars($clubhouse_fee ?? ''); ?>" placeholder="<?php echo t('clubhouse_fee_placeholder'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="garage_fee"><?php echo t('garage_fee'); ?>:</label>
                            <input type="text" id="garage_fee" name="garage_fee" class="form-input" value="<?php echo htmlspecialchars($garage_fee ?? ''); ?>" placeholder="<?php echo t('garage_fee_placeholder'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="transfer_fee_percentage"><?php echo t('transfer_fee_percentage'); ?>:</label>
                            <input type="number" step="1" id="transfer_fee_percentage" name="transfer_fee_percentage" class="form-input" value="<?php echo formatInputValue($transfer_fee_percentage ?? ''); ?>" placeholder="<?php echo t('transfer_fee_placeholder'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Dynamic Payment Plans Repeater -->
                <div style="margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h4 style="margin:0; font-size:1.1rem; color:#1e293b; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-credit-card" style="color:#2563eb;"></i> <?php echo t('payment_plans_title'); ?>
                        </h4>
                        <button type="button" id="add-payment-plan-btn" class="btn btn-primary" style="padding:6px 14px; font-size:0.9rem;">
                            <i class="fas fa-plus"></i> <?php echo t('add_payment_plan'); ?>
                        </button>
                    </div>

                    <div id="payment-plans-container">
                        <?php
                        $plans_to_render = [];
                        if (!empty($_POST['plan_down_payment']) && is_array($_POST['plan_down_payment'])) {
                            foreach ($_POST['plan_down_payment'] as $p_i => $p_dp) {
                                $plans_to_render[] = [
                                    'plan_title' => $_POST['plan_title'][$p_i] ?? '',
                                    'down_payment_percentage' => $p_dp,
                                    'installment_years' => $_POST['plan_installment_years'][$p_i] ?? '',
                                    'installment_type' => $_POST['plan_installment_type'][$p_i] ?? 'quarterly',
                                    'delivery_payment_percentage' => $_POST['plan_delivery_payment'][$p_i] ?? '',
                                    'roi_notes' => $_POST['plan_roi_notes'][$p_i] ?? '',
                                    'plan_notes' => $_POST['plan_notes'][$p_i] ?? '',
                                ];
                            }
                        } elseif (!empty($property_payment_plans)) {
                            $plans_to_render = $property_payment_plans;
                        } else {
                            // Fallback single row from legacy property fields if present
                            $plans_to_render = [[
                                'plan_title' => '',
                                'down_payment_percentage' => $down_payment_percentage ?? '',
                                'installment_years' => $payment_years ?? '',
                                'installment_type' => 'quarterly',
                                'delivery_payment_percentage' => '',
                                'roi_notes' => '',
                                'plan_notes' => '',
                            ]];
                        }

                        $plan_count = max(1, count($plans_to_render));

                        for ($pi = 0; $pi < $plan_count; $pi++):
                            $cur_plan = $plans_to_render[$pi] ?? [];
                        ?>
                        <div class="payment-plan-row" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px; margin-bottom:16px; position:relative;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:10px; flex-wrap:wrap;">
                                <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:250px;">
                                    <span style="font-weight:700; color:#2563eb; font-size:0.95rem; white-space:nowrap;">
                                        <i class="fas fa-layer-group"></i> <?php echo t('plan_number'); ?> #<span class="plan-idx"><?php echo $pi + 1; ?></span>
                                    </span>
                                    <input type="text" name="plan_title[]" class="form-input plan-title" style="flex:1; max-width:320px; font-size:0.9rem;" value="<?php echo htmlspecialchars($cur_plan['plan_title'] ?? ''); ?>" placeholder="<?php echo t('plan_title_placeholder'); ?>">
                                </div>
                                <button type="button" class="btn remove-plan-btn" style="background:#fee2e2; color:#ef4444; border:none; padding:5px 12px; border-radius:6px; cursor:pointer; font-size:0.85rem; <?php echo ($pi === 0 && $plan_count === 1) ? 'display:none;' : ''; ?>">
                                    <i class="fas fa-trash"></i> <?php echo t('remove_plan'); ?>
                                </button>
                            </div>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:12px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label><?php echo t('down_payment_percentage'); ?>:</label>
                                    <input type="number" step="1" min="0" max="100" name="plan_down_payment[]" class="form-input plan-dp" value="<?php echo formatInputValue($cur_plan['down_payment_percentage'] ?? ''); ?>" placeholder="مثال: 0 أو 5 أو 10">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label><?php echo t('payment_years'); ?>:</label>
                                    <input type="number" step="0.5" min="0.5" max="30" name="plan_installment_years[]" class="form-input plan-years" value="<?php echo formatInputValue($cur_plan['installment_years'] ?? ''); ?>" placeholder="مثال: 7 أو 10">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label><?php echo t('installment_type'); ?>:</label>
                                    <select name="plan_installment_type[]" class="form-input plan-type">
                                        <option value="quarterly" <?php echo (($cur_plan['installment_type'] ?? 'quarterly') === 'quarterly') ? 'selected' : ''; ?>><?php echo t('installment_type_quarterly'); ?></option>
                                        <option value="monthly" <?php echo (($cur_plan['installment_type'] ?? '') === 'monthly') ? 'selected' : ''; ?>><?php echo t('installment_type_monthly'); ?></option>
                                        <option value="semi_annual" <?php echo (($cur_plan['installment_type'] ?? '') === 'semi_annual') ? 'selected' : ''; ?>><?php echo t('installment_type_semi_annual'); ?></option>
                                        <option value="annual" <?php echo (($cur_plan['installment_type'] ?? '') === 'annual') ? 'selected' : ''; ?>><?php echo t('installment_type_annual'); ?></option>
                                        <option value="escalating" <?php echo (($cur_plan['installment_type'] ?? '') === 'escalating') ? 'selected' : ''; ?>><?php echo t('installment_type_escalating'); ?></option>
                                        <option value="bulk" <?php echo (($cur_plan['installment_type'] ?? '') === 'bulk') ? 'selected' : ''; ?>><?php echo t('installment_type_bulk'); ?></option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label><?php echo t('delivery_payment_percentage'); ?>:</label>
                                    <input type="number" step="1" min="0" max="50" name="plan_delivery_payment[]" class="form-input plan-del" value="<?php echo formatInputValue($cur_plan['delivery_payment_percentage'] ?? ''); ?>" placeholder="<?php echo t('delivery_payment_placeholder'); ?>">
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label><?php echo t('roi_notes'); ?>:</label>
                                    <input type="text" name="plan_roi_notes[]" class="form-input" value="<?php echo htmlspecialchars($cur_plan['roi_notes'] ?? ''); ?>" placeholder="<?php echo t('roi_notes_placeholder'); ?>">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label><?php echo t('plan_notes'); ?>:</label>
                                    <input type="text" name="plan_notes[]" class="form-input" value="<?php echo htmlspecialchars($cur_plan['plan_notes'] ?? ''); ?>" placeholder="<?php echo t('plan_notes_placeholder'); ?>">
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label for="payment_plans_notes"><?php echo t('payment_plans_notes'); ?>:</label>
                        <textarea id="payment_plans_notes" name="payment_plans_notes" class="form-input" rows="3" placeholder="<?php echo t('payment_plans_notes_placeholder'); ?>"><?php echo htmlspecialchars($_POST['payment_plans_notes'] ?? $payment_plans_notes); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 6. Active Offers & Promotions -->
            <div class="form-section" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 20px;">
                <h3 style="color: #b45309;"><i class="fas fa-gift" style="margin-left: 8px; color: #f59e0b;"></i><?php echo t('offers_and_promotions'); ?></h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="offer_title"><?php echo t('offer_title'); ?>:</label>
                        <input type="text" id="offer_title" name="offer_title" class="form-input" value="<?php echo htmlspecialchars($offer_title); ?>" placeholder="مثال: خصم خاص 10% بمناسبة إطلاق المرحلة الجديدة + مقدم 5% فقط">
                    </div>
                    <div class="form-group">
                        <label for="offer_start_date"><?php echo t('offer_start_date'); ?>:</label>
                        <input type="date" id="offer_start_date" name="offer_start_date" class="form-input" value="<?php echo htmlspecialchars($offer_start_date); ?>">
                    </div>
                    <div class="form-group">
                        <label for="offer_end_date"><?php echo t('offer_end_date'); ?>:</label>
                        <input type="date" id="offer_end_date" name="offer_end_date" class="form-input" value="<?php echo htmlspecialchars($offer_end_date); ?>">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="offer_description"><?php echo t('offer_description'); ?>:</label>
                        <textarea id="offer_description" name="offer_description" class="form-input" rows="3" placeholder="تفاصيل العرض، الشروط، طرق السداد الخاصة بالعرض..."><?php echo htmlspecialchars($offer_description); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 7. Brochure & Project Description -->
            <div class="form-section">
                <h3><i class="fas fa-file-pdf" style="margin-left: 8px; color: #dc2626;"></i><?php echo t('project_brochure_and_details') ?: 'البروشور والتفاصيل الإضافية'; ?></h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="brochure_url"><?php echo t('brochure_url'); ?>:</label>
                        <input type="url" id="brochure_url" name="brochure_url" class="form-input" value="<?php echo htmlspecialchars($brochure_url); ?>" placeholder="https://drive.google.com/... أو رابط ملف PDF">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="description"><?php echo t('description'); ?>:</label>
                        <textarea id="description" name="description" class="form-input" rows="5" placeholder="وصف كامل للمشروع، الخدمات، وسائل الراحة، الماستر بلان..."><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- ===================== RESALE / RENTAL EDIT FORM ===================== -->

            <div class="form-section">
                <h3><?php echo t('basic_info'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('listing_type'); ?>:</label>
                        <span class="form-display"><?php echo t($listing_type === 'Rental' ? 'rental_properties' : 'resale_properties'); ?></span>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label><?php echo t('property_type'); ?>: *</label>
                        <div id="property_type_checkboxes" class="checkbox-group" style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0;">
                            <?php foreach ($property_types_list as $type): ?>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: #fff; padding: 8px 15px; border-radius: 6px; border: 1px solid #dee2e6; transition: all 0.2s;">
                                    <input type="checkbox" name="property_type_ids[]" value="<?php echo $type['PropertyTypeID']; ?>" <?php echo (in_array($type['PropertyTypeID'], $linked_property_types)) ? 'checked' : ''; ?>>
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
                        <input type="hidden" name="property_type_id" id="property_type_id" value="<?php echo $property_type_id; ?>">
                        <?php if (isset($errors['property_type_id'])) {
                            echo "<span class='error-message'>" . $errors['property_type_id'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="project_name"><?php echo t('project_name'); ?></label>
                        <input type="text" id="project_name" name="project_name" value="<?php echo htmlspecialchars($project_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="property_data_source"><?php echo t('property_data_source'); ?>:</label>
                        <input type="text" id="property_data_source" name="property_data_source" class="form-input" value="<?php echo htmlspecialchars($_POST['property_data_source'] ?? $property_data_source); ?>" placeholder="مثال: أحمد محمد">
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
                    <div style="margin:8px 0; display:flex; gap:8px; align-items:center;">
                        <input type="text" id="google_map_link" class="form-input" style="flex:1;" placeholder="https://www.google.com/maps/..." value="<?php echo htmlspecialchars($google_map_link ?? ''); ?>">
                        <button type="button" id="parse_google_link" class="btn">تحديد من الرابط</button>
                    </div>
                    <small>لصق رابط خرائط جوجل ثم اضغط "تحديد من الرابط"</small>
                    <div id="map" style="height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 4px;"></div>
                    <small><?php echo t('click_map_to_set_location'); ?></small>
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
                            <input type="number" step="1" id="compound_area" name="compound_area" class="form-input" value="<?php echo htmlspecialchars($compound_area); ?>">
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
                            <label for="unit_design"><?php echo t('unit_design'); ?>:</label>
                            <input type="text" id="unit_design" name="unit_design" class="form-input" value="<?php echo htmlspecialchars($unit_design); ?>">
                            <?php if (isset($errors['unit_design'])) {
                                echo "<span class='error-message'>" . $errors['unit_design'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="rooms"><?php echo t('rooms'); ?>:</label>
                            <input type="number" id="rooms" name="rooms" class="form-input" value="<?php echo htmlspecialchars($rooms); ?>">
                             <?php if (isset($errors['bedrooms'])) {
                                echo "<span class='error-message'>" . $errors['bedrooms'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="bathrooms"><?php echo t('bathrooms'); ?>:</label>
                            <input type="number" id="bathrooms" name="bathrooms" class="form-input" value="<?php echo htmlspecialchars($bathrooms); ?>">
                            <?php if (isset($errors['bathrooms'])) {
                                echo "<span class='error-message'>" . $errors['bathrooms'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="balconies"><?php echo t('balconies'); ?>:</label>
                            <input type="number" id="balconies" name="balconies" class="form-input" value="<?php echo htmlspecialchars($balconies); ?>">
                            <?php if (isset($errors['balconies'])) {
                                echo "<span class='error-message'>" . $errors['balconies'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="elevators"><?php echo t('elevators'); ?>:</label>
                            <input type="number" id="elevators" name="elevators" class="form-input" value="<?php echo htmlspecialchars($elevators); ?>">
                            <?php if (isset($errors['elevators'])) {
                                echo "<span class='error-message'>" . $errors['elevators'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="finishing_type_id"><?php echo t('finishing_type'); ?>:</label>
                            <select id="finishing_type_id" name="finishing_type_id" class="form-input">
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
                        <div class="form-group">
                            <label for="unit_design_commercial"><?php echo t('unit_design'); ?>:</label>
                            <input type="text" id="unit_design_commercial" name="unit_design_commercial" class="form-input" value="<?php echo htmlspecialchars($unit_design); ?>">
                            <?php if (isset($errors['unit_design'])) {
                                echo "<span class='error-message'>" . $errors['unit_design'] . "</span>";
                            } ?>
                        </div>
                        <div class="form-group">
                            <label for="rooms_commercial"><?php echo t('rooms'); ?>:</label>
                            <input type="number" id="rooms_commercial" name="rooms_commercial" class="form-input" value="<?php echo htmlspecialchars($rooms); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bathrooms_commercial"><?php echo t('bathrooms'); ?>:</label>
                            <input type="number" id="bathrooms_commercial" name="bathrooms_commercial" class="form-input" value="<?php echo htmlspecialchars($bathrooms); ?>">
                        </div>
                        <div class="form-group">
                            <label for="balconies_commercial"><?php echo t('balconies'); ?>:</label>
                            <input type="number" id="balconies_commercial" name="balconies_commercial" class="form-input" value="<?php echo htmlspecialchars($balconies); ?>">
                        </div>
                        <div class="form-group">
                            <label for="elevators_commercial"><?php echo t('elevators'); ?>:</label>
                            <input type="number" id="elevators_commercial" name="elevators_commercial" class="form-input" value="<?php echo htmlspecialchars($elevators); ?>">
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
                        <input type="number" step="1" id="area" name="area" class="form-input" value="<?php echo formatInputValue($area); ?>">
                        <small><?php echo t('specific_area_note'); ?></small>
                        <?php if (isset($errors['area'])) {
                            echo "<span class='error-message'>" . $errors['area'] . "</span>";
                        } ?>
                    </div>
                    <div class="form-group">
                        <label for="min_area"><?php echo t('min_area_sqm'); ?>:</label>
                        <input type="number" step="1" id="min_area" name="min_area" class="form-input" value="<?php echo formatInputValue($min_area); ?>">
                    </div>
                    <div class="form-group">
                        <label for="max_area"><?php echo t('max_area_sqm'); ?>:</label>
                        <input type="number" step="1" id="max_area" name="max_area" class="form-input" value="<?php echo formatInputValue($max_area); ?>">
                    </div>
                    <div class="form-group">
                        <label for="avg_area"><?php echo t('avg_area_sqm'); ?>:</label>
                        <input type="number" step="1" id="avg_area" name="avg_area" class="form-input" value="<?php echo formatInputValue($avg_area); ?>">
                    </div>
                    <div class="form-group">
                        <label for="net_area"><?php echo t('net_area'); ?>:</label>
                        <input type="number" step="1" id="net_area" name="net_area" class="form-input" value="<?php echo formatInputValue($net_area); ?>">
                    </div>
                    <div class="form-group">
                        <label for="loading_percentage"><?php echo t('loading_percentage'); ?>:</label>
                        <input type="number" step="1" id="loading_percentage" name="loading_percentage" class="form-input" min="0" max="100" value="<?php echo formatInputValue($loading_percentage); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><?php echo t('pricing_and_payment'); ?></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="currency"><?php echo t('currency'); ?>:</label>
                        <select id="currency" name="currency" class="form-input">
                            <option value="EGP" <?php echo ($currency == 'EGP') ? 'selected' : ''; ?>><?php echo t('EGP'); ?></option>
                            <option value="USD" <?php echo ($currency == 'USD') ? 'selected' : ''; ?>><?php echo t('USD'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="price_cash"><?php echo t('price_cash'); ?>:</label>
                        <input type="number" step="1" id="price_cash" name="price_cash" class="form-input" value="<?php echo formatInputValue($price_cash); ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_installments"><?php echo t('price_installments'); ?>:</label>
                        <input type="number" step="1" id="price_installments" name="price_installments" class="form-input" value="<?php echo formatInputValue($price_installments); ?>">
                    </div>
                    <div class="form-group">
                        <label for="monthly_rent"><?php echo t('monthly_rent'); ?>:</label>
                        <input type="number" step="1" id="monthly_rent" name="monthly_rent" class="form-input" value="<?php echo formatInputValue($monthly_rent); ?>">
                    </div>
                    <div class="form-group">
                        <label for="daily_rent"><?php echo t('daily_rent'); ?>:</label>
                        <input type="number" step="1" id="daily_rent" name="daily_rent" class="form-input" value="<?php echo formatInputValue($daily_rent); ?>">
                    </div>
                    <div class="form-group">
                        <label for="yearly_rent"><?php echo t('yearly_rent'); ?>:</label>
                        <input type="number" step="1" id="yearly_rent" name="yearly_rent" class="form-input" value="<?php echo formatInputValue($yearly_rent); ?>">
                    </div>
                    <div class="form-group">
                        <label for="meter_price_cash"><?php echo t('meter_price_cash'); ?>:</label>
                        <input type="number" step="1" id="meter_price_cash" name="meter_price_cash" class="form-input" value="<?php echo formatInputValue($meter_price_cash); ?>">
                    </div>
                    <div class="form-group">
                        <label for="meter_price_installments"><?php echo t('meter_price_installments'); ?>:</label>
                        <input type="number" step="1" id="meter_price_installments" name="meter_price_installments" class="form-input" value="<?php echo formatInputValue($meter_price_installments); ?>">
                    </div>
                    <div class="form-group">
                        <label for="down_payment_percentage"><?php echo t('down_payment_percentage'); ?> (%):</label>
                        <input type="number" step="1" id="down_payment_percentage" name="down_payment_percentage" class="form-input" value="<?php echo formatInputValue($down_payment_percentage); ?>">
                    </div>
                    <div class="form-group">
                        <label for="payment_years"><?php echo t('payment_years'); ?>:</label>
                        <input type="number" id="payment_years" name="payment_years" class="form-input" step="0.5" min="0.5" value="<?php echo formatInputValue($payment_years); ?>" placeholder="مثال: 1.5 للسنة والنصف">
                    </div>
                    <div class="form-group">
                        <label for="delivery_date"><?php echo t('delivery_date'); ?>:</label>
                        <input type="date" id="delivery_date" name="delivery_date" class="form-input" value="<?php echo htmlspecialchars($delivery_date); ?>">
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
                        <label for="sales_manager_phone"><?php echo t("sales_manager_phone"); ?></label>
                        <div class="phone-input-group" style="display:flex; gap:10px;">
                            <select name="sales_manager_phone_code" id="sales_manager_phone_code" class="form-input" style="width:120px;"></select>
                            <input type="text" name="sales_manager_phone_number" id="sales_manager_phone_number" class="form-input" value="<?php echo htmlspecialchars($parsedSM['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedSM['code']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="salesperson_name"><?php echo t("salesperson_name"); ?></label>
                        <input type="text" name="salesperson_name" id="salesperson_name" class="form-input" value="<?php echo htmlspecialchars($salesperson_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="salesperson_phone"><?php echo t("salesperson_phone"); ?></label>
                        <div class="phone-input-group" style="display:flex; gap:10px;">
                             <select name="salesperson_phone_code" id="salesperson_phone_code" class="form-input" style="width:120px;"></select>
                             <input type="text" name="salesperson_phone_number" id="salesperson_phone_number" class="form-input" value="<?php echo htmlspecialchars($parsedSP['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedSP['code']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Owner/Seller Information Section -->
            <div class="form-section" id="seller-section">
                <h3><?php echo t("owner_information"); ?></h3>
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
                                    $parsedSeller = parsePhoneForInput($seller_row["seller_phone"]); 
                                    $sCodeId = "seller_phone_code_" . $idx;
                                    $sNumId = "seller_phone_number_" . $idx;
                                ?>
                                <div class="phone-input-group" style="display:flex; gap:10px;">
                                    <select name="seller_phone_code[]" id="<?php echo $sCodeId; ?>" class="form-input seller-phone-code" style="width:120px;"></select>
                                    <input type="text" name="seller_phone_number[]" id="<?php echo $sNumId; ?>" class="form-input seller-phone-number" value="<?php echo htmlspecialchars($parsedSeller['number']); ?>" data-initial-code="<?php echo htmlspecialchars($parsedSeller['code']); ?>">
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
                                    <input type="text" name="seller_phone_number[]" class="form-input seller-phone-number">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger remove-seller"><?php echo t('remove') ?: 'حذف'; ?></button>
                    </div>
                </template>
            </div>
            <?php endif; ?>

            <div class="form-actions" style="margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 16px;"><?php echo t('update_property_button'); ?></button>
                <a href="view_property.php?id=<?php echo $property_id; ?>" class="btn btn-secondary" style="padding: 10px 24px; font-size: 16px;"><?php echo t('cancel'); ?></a>
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
            // Property type checkboxes instead of dropdown
            const propertyTypeCheckboxes = document.querySelectorAll('input[name="property_type_ids[]"]');
            const hiddenPropertyTypeId = document.getElementById('property_type_id');
            const residentialCoastalFields = document.getElementById('residential_coastal_fields');
            const commercialFields = document.getElementById('commercial_fields');

            const RESIDENTIAL_ID = <?php echo RESIDENTIAL_PROPERTY_TYPE_ID; ?>;
            const COASTAL_ID = <?php echo COASTAL_PROPERTY_TYPE_ID; ?>;
            const COMMERCIAL_ID = <?php echo COMMERCIAL_PROPERTY_TYPE_ID; ?>;
            const ADMINISTRATIVE_ID = <?php echo ADMINISTRATIVE_PROPERTY_TYPE_ID; ?>;
            const HOTEL_ID = <?php echo HOTEL_PROPERTY_TYPE_ID; ?>;
            const MEDICAL_ID = <?php echo MEDICAL_PROPERTY_TYPE_ID; ?>;

            // Get all currently selected property type IDs
            function getSelectedTypeIds() {
                const selected = [];
                propertyTypeCheckboxes.forEach(cb => {
                    if (cb.checked) {
                        selected.push(parseInt(cb.value));
                    }
                });
                return selected;
            }

            // Update hidden field with first selected type
            function updateHiddenField() {
                const selected = getSelectedTypeIds();
                if (hiddenPropertyTypeId) {
                    hiddenPropertyTypeId.value = selected.length > 0 ? selected[0] : '';
                }
            }

            function togglePropertyTypeFields() {
                if (!residentialCoastalFields || !commercialFields) {
                    return;
                }
                const selectedIds = getSelectedTypeIds();
                const residentialCoastalIds = [RESIDENTIAL_ID, COASTAL_ID];
                const commercialIds = [COMMERCIAL_ID, ADMINISTRATIVE_ID, HOTEL_ID, MEDICAL_ID];
                
                const hasResidentialCoastal = selectedIds.some(id => residentialCoastalIds.includes(id));
                const hasCommercial = selectedIds.some(id => commercialIds.includes(id));

                // Show appropriate field sections based on selections
                if (hasResidentialCoastal && !hasCommercial) {
                    residentialCoastalFields.classList.add('active');
                    commercialFields.classList.remove('active');

                    // Enable residential fields
                    const unitDesignField = document.getElementById('unit_design');
                    const finishingTypeField = document.getElementById('finishing_type_id');
                    const finishingTypeCommercialField = document.getElementById('finishing_type_id_commercial');
                    
                    if (unitDesignField) { unitDesignField.disabled = false; }
                    if (finishingTypeField) { finishingTypeField.disabled = false; }
                    if (finishingTypeCommercialField) { finishingTypeCommercialField.disabled = true; }

                    // Disable commercial-only fields
                    const unitDesignCommercial = document.getElementById('unit_design_commercial');
                    const unitFloorCommercial = document.getElementById('unit_floor_commercial');
                    const floorsCountCommercial = document.getElementById('floors_count_commercial');
                    if (unitDesignCommercial) unitDesignCommercial.disabled = true;
                    if (unitFloorCommercial) unitFloorCommercial.disabled = true;
                    if (floorsCountCommercial) floorsCountCommercial.disabled = true;

                } else if (hasCommercial) {
                    // Commercial takes precedence if mixed, or is only selection
                    residentialCoastalFields.classList.remove('active');
                    commercialFields.classList.add('active');

                    const unitDesignField = document.getElementById('unit_design');
                    const finishingTypeField = document.getElementById('finishing_type_id');
                    const finishingTypeCommercialField = document.getElementById('finishing_type_id_commercial');
                    
                    if (unitDesignField) { unitDesignField.disabled = true; }
                    if (finishingTypeField) { finishingTypeField.disabled = true; }
                    if (finishingTypeCommercialField) { finishingTypeCommercialField.disabled = false; }

                    // Enable commercial-only fields
                    const unitDesignCommercial = document.getElementById('unit_design_commercial');
                    const unitFloorCommercial = document.getElementById('unit_floor_commercial');
                    const floorsCountCommercial = document.getElementById('floors_count_commercial');
                    if (unitDesignCommercial) unitDesignCommercial.disabled = false;
                    if (unitFloorCommercial) unitFloorCommercial.disabled = false;
                    if (floorsCountCommercial) floorsCountCommercial.disabled = false;

                } else {
                    // No types selected - hide both sections
                    residentialCoastalFields.classList.remove('active');
                    commercialFields.classList.remove('active');

                    const unitDesignField = document.getElementById('unit_design');
                    const finishingTypeField = document.getElementById('finishing_type_id');
                    const finishingTypeCommercialField = document.getElementById('finishing_type_id_commercial');
                    
                    if (unitDesignField) { unitDesignField.disabled = true; }
                    if (finishingTypeField) { finishingTypeField.disabled = true; }
                    if (finishingTypeCommercialField) { finishingTypeCommercialField.disabled = true; }

                    const unitDesignCommercial = document.getElementById('unit_design_commercial');
                    const unitFloorCommercial = document.getElementById('unit_floor_commercial');
                    const floorsCountCommercial = document.getElementById('floors_count_commercial');
                    if (unitDesignCommercial) unitDesignCommercial.disabled = true;
                    if (unitFloorCommercial) unitFloorCommercial.disabled = true;
                    if (floorsCountCommercial) floorsCountCommercial.disabled = true;
                }
            }

            // Initial call to set correct fields based on loaded property type
            if (residentialCoastalFields && commercialFields) {
                togglePropertyTypeFields();
                propertyTypeCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        updateHiddenField();
                        togglePropertyTypeFields();
                    });
                });
            } else {
                propertyTypeCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        updateHiddenField();
                    });
                });
            }
            
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

    <script src="prevent_double_submit.js"></script>
    <script src="js/phone_validation.js?v=<?php echo time(); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Init Company Phone
        const cpCode = document.getElementById('company_phone_code');
        const cpNum = document.getElementById('company_phone_number');
        if (cpCode && cpNum) {
            cpCode.innerHTML = getCountryOptionsHtml(cpNum.dataset.initialCode || '+20');
            setupPhoneValidation('company_phone_code', 'company_phone_number');
        }

        // Init Sales Director Phone
        const sdCode = document.getElementById('sales_director_phone_code');
        const sdNum = document.getElementById('sales_director_phone_number');
        if (sdCode && sdNum) {
            sdCode.innerHTML = getCountryOptionsHtml(sdNum.dataset.initialCode || '+20');
            setupPhoneValidation('sales_director_phone_code', 'sales_director_phone_number');
        }

        // Init Operation Manager Phone
        const omCode = document.getElementById('operation_manager_phone_code');
        const omNum = document.getElementById('operation_manager_phone_number');
        if (omCode && omNum) {
            omCode.innerHTML = getCountryOptionsHtml(omNum.dataset.initialCode || '+20');
            setupPhoneValidation('operation_manager_phone_code', 'operation_manager_phone_number');
        }

        // Init Sales Manager Phone
        const smCode = document.getElementById('sales_manager_phone_code');
        const smNum = document.getElementById('sales_manager_phone_number');
        if (smCode && smNum) {
            smCode.innerHTML = getCountryOptionsHtml(smNum.dataset.initialCode || '+20');
            setupPhoneValidation('sales_manager_phone_code', 'sales_manager_phone_number');
        }

        // Init Salesperson Phone
        const spCode = document.getElementById('salesperson_phone_code');
        const spNum = document.getElementById('salesperson_phone_number');
        if (spCode && spNum) {
            spCode.innerHTML = getCountryOptionsHtml(spNum.dataset.initialCode || '+20');
            setupPhoneValidation('salesperson_phone_code', 'salesperson_phone_number');
        }

        // Init Existing Sellers
        document.querySelectorAll('.seller-row').forEach((row, index) => {
            const codeSelect = row.querySelector('.seller-phone-code');
            const numInput = row.querySelector('.seller-phone-number');
            if (codeSelect && numInput) {
                if (!codeSelect.id) codeSelect.id = `seller_phone_code_row_${index}`;
                if (!numInput.id) numInput.id = `seller_phone_number_row_${index}`;
                
                codeSelect.innerHTML = getCountryOptionsHtml(numInput.dataset.initialCode || '+20');
                setupPhoneValidation(codeSelect.id, numInput.id);
            }
        });

        // Handle Dynamic Seller Addition
        const addSellerBtn = document.getElementById('add-seller-row');
        if (addSellerBtn) {
            addSellerBtn.addEventListener('click', function() {
                 const template = document.getElementById('seller-row-template');
                 const container = document.getElementById('seller-entries');
                 if (!template || !container) return;
                 const clone = template.content.cloneNode(true);
                 container.appendChild(clone);
                 
                 setTimeout(() => {
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
                 }, 50);
            });
        }

        const sellerEntries = document.getElementById('seller-entries');
        if (sellerEntries) {
            sellerEntries.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-seller')) {
                    const row = e.target.closest('.seller-row');
                    if (row) {
                        row.remove();
                    }
                }
            });
        }

        // Handle Dynamic Payment Plans Repeater
        const addPlanBtn = document.getElementById('add-payment-plan-btn');
        const plansContainer = document.getElementById('payment-plans-container');

        function refreshPaymentPlans() {
            if (!plansContainer) return;
            const rows = plansContainer.querySelectorAll('.payment-plan-row');
            rows.forEach((row, idx) => {
                const idxSpan = row.querySelector('.plan-idx');
                if (idxSpan) idxSpan.textContent = (idx + 1);
                const removeBtn = row.querySelector('.remove-plan-btn');
                if (removeBtn) {
                    removeBtn.style.display = (rows.length > 1) ? 'inline-block' : 'none';
                    removeBtn.onclick = function() {
                        row.remove();
                        refreshPaymentPlans();
                    };
                }
            });
        }

        if (addPlanBtn && plansContainer) {
            addPlanBtn.addEventListener('click', function() {
                const rowCount = plansContainer.querySelectorAll('.payment-plan-row').length + 1;
                const newRow = document.createElement('div');
                newRow.className = 'payment-plan-row';
                newRow.style.cssText = 'background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px; margin-bottom:16px; position:relative;';
                newRow.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:10px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:250px;">
                            <span style="font-weight:700; color:#2563eb; font-size:0.95rem; white-space:nowrap;">
                                <i class="fas fa-layer-group"></i> <?php echo t('plan_number'); ?> #<span class="plan-idx">${rowCount}</span>
                            </span>
                            <input type="text" name="plan_title[]" class="form-input plan-title" style="flex:1; max-width:320px; font-size:0.9rem;" placeholder="<?php echo t('plan_title_placeholder'); ?>">
                        </div>
                        <button type="button" class="btn remove-plan-btn" style="background:#fee2e2; color:#ef4444; border:none; padding:5px 12px; border-radius:6px; cursor:pointer; font-size:0.85rem;">
                            <i class="fas fa-trash"></i> <?php echo t('remove_plan'); ?>
                        </button>
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label><?php echo t('down_payment_percentage'); ?>:</label>
                            <input type="number" step="1" min="0" max="100" name="plan_down_payment[]" class="form-input plan-dp" placeholder="مثال: 0 أو 5 أو 10">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label><?php echo t('payment_years'); ?>:</label>
                            <input type="number" step="0.5" min="0.5" max="30" name="plan_installment_years[]" class="form-input plan-years" placeholder="مثال: 7 أو 10">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label><?php echo t('installment_type'); ?>:</label>
                            <select name="plan_installment_type[]" class="form-input plan-type">
                                <option value="quarterly"><?php echo t('installment_type_quarterly'); ?></option>
                                <option value="monthly"><?php echo t('installment_type_monthly'); ?></option>
                                <option value="semi_annual"><?php echo t('installment_type_semi_annual'); ?></option>
                                <option value="annual"><?php echo t('installment_type_annual'); ?></option>
                                <option value="escalating"><?php echo t('installment_type_escalating'); ?></option>
                                <option value="bulk"><?php echo t('installment_type_bulk'); ?></option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label><?php echo t('delivery_payment_percentage'); ?>:</label>
                            <input type="number" step="1" min="0" max="50" name="plan_delivery_payment[]" class="form-input plan-del" placeholder="<?php echo t('delivery_payment_placeholder'); ?>">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label><?php echo t('roi_notes'); ?>:</label>
                            <input type="text" name="plan_roi_notes[]" class="form-input" placeholder="<?php echo t('roi_notes_placeholder'); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label><?php echo t('plan_notes'); ?>:</label>
                            <input type="text" name="plan_notes[]" class="form-input" placeholder="<?php echo t('plan_notes_placeholder'); ?>">
                        </div>
                    </div>
                `;
                plansContainer.appendChild(newRow);
                refreshPaymentPlans();
            });

            refreshPaymentPlans();
        }
    });
    </script>
</body>
</html>
<?php 
// Close the database connection at the very end
if (isset($conn)) {
    $conn->close();
}
?>
