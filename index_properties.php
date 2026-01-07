<?php
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once __DIR__ . '/lang/translate.php'; // Include translation handler
require_once __DIR__ . '/db.php'; // Database connection
require_once __DIR__ . '/sorting_helpers.php'; // Include sorting helper functions
require_once __DIR__ . '/property_image_helper.php'; // Include image helper functions
require_once __DIR__ . '/property_display_helper.php'; // Include display helper functions
require_once __DIR__ . '/security.php';
require_once 'permissions.php'; // Include permissions system

// Check permissions - all logged in users can view properties
$current_mode = require_listing_context();
check_permission('view_properties');

// Initialize search and filter variables
$search_term = $governorate_filter = $company_filter = $status_filter = $property_type_filter = "";
$seller_type_filter = "";
$year_built_filter = "";
$min_price_filter = $max_price_filter = $min_area_filter = $max_area_filter = $down_payment_filter = $payment_period_filter = "";
$unit_design_filter = "";
$view_mode = isset($_GET['view']) && $_GET['view'] === 'card' ? 'card' : 'table';

// Initialize sorting variables
$sort_column = isset($_GET['sort']) ? trim($_GET['sort']) : 'friendly_id';
$sort_direction = isset($_GET['direction']) && $_GET['direction'] === 'asc' ? 'asc' : 'desc'; // Default to DESC

// Validate sort column to prevent SQL injection
$allowed_sort_columns = [
    'PropertyID' => 'p.PropertyID',
    'friendly_id' => 'p.friendly_id',
    'DateAdded' => 'p.DateAdded',
    'Price' => 'COALESCE(p.price_cash, p.price_installments)',
    'Area' => 'COALESCE(p.Area, p.avg_area)',
    'ProjectName' => 'p.ProjectName',
    'Title' => 'p.Title',
    'governorate' => 'p.governorate',
    'Status' => 'p.Status'
];

if (!array_key_exists($sort_column, $allowed_sort_columns)) {
    $sort_column = 'friendly_id';
}

// Process search and filter inputs
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $governorate_filter = isset($_GET['governorate']) ? trim($_GET['governorate']) : '';
    $district_filter = isset($_GET['district']) ? trim($_GET['district']) : '';
    $company_filter = isset($_GET['company']) ? trim($_GET['company']) : '';
    $unit_design_filter = isset($_GET['unit_design']) ? trim($_GET['unit_design']) : '';
    $seller_type_filter = isset($_GET['seller_type']) ? trim($_GET['seller_type']) : '';
    $year_built_filter = isset($_GET['year_built']) ? trim($_GET['year_built']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $property_type_filter = isset($_GET['property_type']) ? trim($_GET['property_type']) : '';

    // Advanced filters
    $min_price_filter = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? trim($_GET['min_price']) : '';
    $max_price_filter = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? trim($_GET['max_price']) : '';
    $min_area_filter = isset($_GET['min_area']) && is_numeric($_GET['min_area']) ? trim($_GET['min_area']) : '';
    $max_area_filter = isset($_GET['max_area']) && is_numeric($_GET['max_area']) ? trim($_GET['max_area']) : '';
    $currency_filter = isset($_GET['currency_filter']) ? trim($_GET['currency_filter']) : '';
    $down_payment_filter = isset($_GET['down_payment']) ? trim($_GET['down_payment']) : '';
    $payment_period_filter = isset($_GET['payment_period']) ? trim($_GET['payment_period']) : '';
    
    // New filters
    $bedrooms_filter = isset($_GET['bedrooms']) ? trim($_GET['bedrooms']) : '';
    $bathrooms_filter = isset($_GET['bathrooms']) ? trim($_GET['bathrooms']) : '';
    $balconies_filter = isset($_GET['balconies']) ? trim($_GET['balconies']) : '';
}

// Fetch distinct governorates for the filter dropdown (filtered by current listing mode)
$governorates_list = [];
$sql_governorates = "SELECT DISTINCT governorate FROM Properties WHERE governorate IS NOT NULL AND governorate != '' AND listing_type = ? ORDER BY governorate";
if ($stmt_gov = $conn->prepare($sql_governorates)) {
    $stmt_gov->bind_param('s', $current_mode);
    if ($stmt_gov->execute()) {
        $result_governorates = $stmt_gov->get_result();
        while ($row_gov = $result_governorates->fetch_assoc()) {
            $governorates_list[] = $row_gov['governorate'];
        }
    }
    $stmt_gov->close();
}

// Fetch distinct companies for the filter dropdown (filtered by current listing mode)
$companies_list = [];
$sql_companies = "SELECT DISTINCT company_name FROM Properties WHERE company_name IS NOT NULL AND company_name != '' AND listing_type = ? ORDER BY company_name";
if ($stmt_comp = $conn->prepare($sql_companies)) {
    $stmt_comp->bind_param('s', $current_mode);
    if ($stmt_comp->execute()) {
        $result_companies = $stmt_comp->get_result();
        while ($row_comp = $result_companies->fetch_assoc()) {
            $companies_list[] = $row_comp['company_name'];
        }
    }
    $stmt_comp->close();
}

// Fetch unit designs for the filter dropdown (filtered by current listing mode)
$unit_designs = [];
$sql_unit_designs = "SELECT DISTINCT p.unit_design AS DesignName 
                     FROM Properties p 
                     WHERE p.unit_design IS NOT NULL AND p.unit_design != '' AND p.listing_type = ?
                     ORDER BY p.unit_design";
if ($stmt_designs = $conn->prepare($sql_unit_designs)) {
    $stmt_designs->bind_param('s', $current_mode);
    if ($stmt_designs->execute()) {
        $result_designs = $stmt_designs->get_result();
        while ($row_design = $result_designs->fetch_assoc()) {
            $unit_designs[] = $row_design['DesignName'];
        }
    }
    $stmt_designs->close();
}

// Fetch distinct year_built values for the filter dropdown (filtered by current listing mode)
$year_built_list = [];
$sql_years = "SELECT DISTINCT year_built FROM Properties 
              WHERE year_built IS NOT NULL AND year_built != '' AND year_built <> '0' AND listing_type = ?
              ORDER BY year_built DESC";
if ($stmt_years = $conn->prepare($sql_years)) {
    $stmt_years->bind_param('s', $current_mode);
    if ($stmt_years->execute()) {
        $result_years = $stmt_years->get_result();
        while ($row_year = $result_years->fetch_assoc()) {
            $year_built_list[] = $row_year['year_built'];
        }
    }
    $stmt_years->close();
}

// Fetch property types for the filter dropdown
$property_types = [];
$sql_property_types = "SELECT PropertyTypeID, TypeName FROM PropertyTypes ORDER BY TypeName";
if ($stmt_types = $conn->prepare($sql_property_types)) {
    if ($stmt_types->execute()) {
        $result_types = $stmt_types->get_result();
        while ($row_type = $result_types->fetch_assoc()) {
            $property_types[$row_type['PropertyTypeID']] = $row_type['TypeName'];
        }
    }
    $stmt_types->close();
}

// Build the main SQL query with proper joins for unit design and finishing type
$lang = get_lang_code();
$sql_select = "SELECT p.*, pt.TypeName AS PropertyTypeName, e.FullName AS EmployeeName,
               p.unit_design AS UnitDesignName, pft.FinishingName AS FinishingTypeName,
               (SELECT GROUP_CONCAT(ptl2.property_type_id ORDER BY ptl2.property_type_id) 
                FROM property_type_links ptl2 
                WHERE ptl2.property_id = p.PropertyID) AS AllPropertyTypeIDs,
               (SELECT GROUP_CONCAT(pt2.TypeName ORDER BY pt2.PropertyTypeID) 
                FROM property_type_links ptl2 
                JOIN PropertyTypes pt2 ON ptl2.property_type_id = pt2.PropertyTypeID 
                WHERE ptl2.property_id = p.PropertyID) AS AllPropertyTypeNames
               FROM Properties p
               LEFT JOIN PropertyTypes pt ON p.PropertyTypeID = pt.PropertyTypeID
               LEFT JOIN Employees e ON p.EmployeeID = e.EmployeeID
               LEFT JOIN PropertyFinishingTypes pft ON p.finishing_type_id = pft.FinishingID";
$sql_where = [];
$params = [];
$types = "";

// Add listing_type filter (mandatory based on session mode)
$sql_where[] = "p.listing_type = ?";
$params[] = $current_mode;
$types .= "s";

// Add search term filter
if (!empty($search_term)) {
    // unit design is stored on Properties.unit_design (text), so reference p.unit_design here
    // Also search in seller_name for owner/seller search
    $sql_where[] = "(p.Title LIKE ? OR p.ProjectName LIKE ? OR p.governorate LIKE ? OR p.district LIKE ? OR p.Location LIKE ? OR p.company_name LIKE ? OR p.unit_design LIKE ? OR p.seller_name LIKE ?)";
    $search_param = "%" . $search_term . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    $types .= "ssssssss";
}

// Add property type filter
if (!empty($property_type_filter)) {
    $sql_where[] = "EXISTS (SELECT 1 FROM property_type_links ptl WHERE ptl.property_id = p.PropertyID AND ptl.property_type_id = ?)";
    $params[] = $property_type_filter;
    $types .= "i";
}

// Add governorate filter
if (!empty($governorate_filter)) {
    $sql_where[] = "p.governorate = ?";
    $params[] = $governorate_filter;
    $types .= "s";
}

// Add district filter
if (!empty($district_filter)) {
    $sql_where[] = "p.district = ?";
    $params[] = $district_filter;
    $types .= "s";
}

// Add company filter
if (!empty($company_filter)) {
    $sql_where[] = "p.company_name = ?";
    $params[] = $company_filter;
    $types .= "s";
}

// Add seller type filter
if (!empty($seller_type_filter)) {
    $sql_where[] = "p.seller_type = ?";
    $params[] = $seller_type_filter;
    $types .= "s";
}

// Add year built filter (allow "0" as valid value, so check against empty string instead of empty())
if ($year_built_filter !== '') {
    $sql_where[] = "p.year_built = ?";
    $params[] = $year_built_filter;
    $types .= "s";
}

// Add unit design filter
if (!empty($unit_design_filter)) {
    $sql_where[] = "p.unit_design = ?";
    $params[] = $unit_design_filter;
    $types .= "s";
}

// Add status filter
if (!empty($status_filter)) {
    $sql_where[] = "p.Status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add advanced filters
if (!empty($min_price_filter)) {
    $sql_where[] = "COALESCE(p.price_cash, p.price_installments) >= ?";
    $params[] = $min_price_filter;
    $types .= "d";
}
if (!empty($max_price_filter)) {
    $sql_where[] = "COALESCE(p.price_cash, p.price_installments) <= ?";
    $params[] = $max_price_filter;
    $types .= "d";
}
if (!empty($min_area_filter)) {
    $sql_where[] = "COALESCE(p.Area, p.min_area) >= ?";
    $params[] = $min_area_filter;
    $types .= "d";
}
if (!empty($max_area_filter)) {
    $sql_where[] = "COALESCE(p.Area, p.max_area) <= ?";
    $params[] = $max_area_filter;
    $types .= "d";
}
    if (!empty($down_payment_filter)) {
    $sql_where[] = "p.down_payment_percentage = ?";
    $params[] = $down_payment_filter;
    $types .= "d";
}
if (!empty($payment_period_filter)) {
    $sql_where[] = "p.payment_years = ?";
    $params[] = $payment_period_filter;
    $types .= "d"; // treat payment_years as decimal/double
}

// Add currency filter
if (!empty($currency_filter)) {
    $sql_where[] = "p.currency = ?";
    $params[] = $currency_filter;
    $types .= "s";
}

// Add bedrooms filter
if ($bedrooms_filter !== '') {
    if ($bedrooms_filter === '6+') {
        $sql_where[] = "p.Bedrooms >= 6";
    } else {
        $sql_where[] = "p.Bedrooms = ?";
        $params[] = $bedrooms_filter;
        $types .= "i";
    }
}

// Add bathrooms filter
if ($bathrooms_filter !== '') {
    if ($bathrooms_filter === '6+') {
        $sql_where[] = "p.Bathrooms >= 6";
    } else {
        $sql_where[] = "p.Bathrooms = ?";
        $params[] = $bathrooms_filter;
        $types .= "i";
    }
}

// Add balconies filter
if ($balconies_filter !== '') {
    if ($balconies_filter === '4+') {
        $sql_where[] = "p.Balconies >= 4";
    } else {
        $sql_where[] = "p.Balconies = ?";
        $params[] = $balconies_filter;
        $types .= "i";
    }
}

// Combine SQL parts
$sql = $sql_select;
if (!empty($sql_where)) {
    $sql .= " WHERE " . implode(" AND ", $sql_where);
}

// Add sorting
$sql .= " ORDER BY " . $allowed_sort_columns[$sort_column] . " " . strtoupper($sort_direction);

// Prepare and execute the query
$properties = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $properties[] = $row;
        }
    } else {
        error_log("Error executing property query: " . $stmt->error . " | SQL: " . $sql . " | Params: " . print_r($params, true));
        $_SESSION['error_message'] = t('database_error');
    }
    $stmt->close();
} else {
    error_log("Error preparing property query: " . $conn->error . " | SQL: " . $sql);
    $_SESSION['error_message'] = t('db_error_prepare');
}

// Display functions moved to property_display_helper.php

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('all_properties'); ?></title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="images/Logo%20Broman.svg">
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <link rel="stylesheet" href="enhanced_property_table_styles.css">
    <link rel="stylesheet" href="clickable_rows.css">
    <link rel="stylesheet" href="property_card_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Mobile Responsive Table - Professional Card Design -->
    <style>
    @media (max-width: 992px) {
        /* Container Reset */
        .table-responsive {
            overflow: visible !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        
        /* Table Structure Reset */
        .properties-table {
            display: block !important;
            width: 100% !important;
            min-width: unset !important;
        }
        .properties-table thead {
            display: none !important;
        }
        .properties-table tbody {
            display: block !important;
        }
        
        /* === PROPERTY CARD === */
        .properties-table tbody tr {
            display: block !important;
            position: relative !important;
            overflow: hidden !important;
            background: #ffffff !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid #e0e0e0 !important;
            margin-bottom: 20px !important;
            padding: 0 !important;
        }
        .properties-table tbody tr:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12) !important;
            transform: none !important;
        }
        
        /* === ALL CELLS BASE === */
        .properties-table tbody td {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 12px 16px !important;
            border-bottom: 1px dashed #eee !important;
            margin: 0 !important;
            min-width: unset !important;
            max-width: unset !important;
            width: auto !important;
        }
        .properties-table tbody td:last-child {
            border-bottom: none !important;
        }
        
        /* Label (from data-label) - Right side */
        .properties-table tbody td::before {
            content: attr(data-label);
            font-weight: 500 !important;
            color: #666 !important;
            font-size: 0.85rem !important;
            flex-shrink: 0 !important;
            text-align: right !important;
        }
        
        /* Value - Left side */
        .properties-table tbody td > * {
            color: #333 !important;
            font-weight: 500 !important;
        }
        
        /* RTL Support */
        html[dir="rtl"] .properties-table tbody td {
            flex-direction: row !important;
        }
        html[dir="rtl"] .properties-table tbody td::before {
            text-align: right !important;
            order: 1 !important;
        }
        
        /* === ID CELL - Badge Style === */
        .properties-table tbody td.cell-id {
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%) !important;
            border-bottom: 2px solid #b08a0e !important;
            padding: 14px 16px !important;
            justify-content: flex-start !important;
            gap: 10px !important;
        }
        .properties-table tbody td.cell-id::before {
            content: "رقم العقار:" !important;
            background: #b08a0e !important;
            color: #fff !important;
            padding: 4px 10px !important;
            border-radius: 4px !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            order: 0 !important;
        }
        .properties-table tbody td.cell-id strong {
            font-size: 1.2rem !important;
            font-weight: 700 !important;
            color: #020d24 !important;
            order: 1 !important;
        }
        
        /* === PROJECT/TITLE CELL === */
        .properties-table tbody td.cell-project {
            background: #fafafa !important;
            padding: 16px !important;
            border-bottom: 1px solid #e0e0e0 !important;
            flex-direction: column !important;
            align-items: flex-start !important;
        }
        .properties-table tbody td.cell-project::before {
            display: none !important;
        }
        .properties-table tbody td.cell-project .project-title {
            font-size: 1.15rem !important;
            font-weight: 700 !important;
            color: #1a1a2e !important;
            margin-bottom: 4px !important;
        }
        .properties-table tbody td.cell-project .project-subtitle {
            font-size: 0.9rem !important;
            color: #666 !important;
        }
        
        /* === PRICE CELL - Highlighted === */
        .properties-table tbody td.cell-price {
            background: linear-gradient(135deg, rgba(176, 138, 14, 0.08) 0%, rgba(176, 138, 14, 0.03) 100%) !important;
            border-bottom: 1px solid rgba(176, 138, 14, 0.2) !important;
            padding: 14px 16px !important;
        }
        .properties-table tbody td.cell-price::before {
            color: #8a6b08 !important;
            font-weight: 600 !important;
        }
        .properties-table tbody td.cell-price > * {
            color: #b08a0e !important;
            font-weight: 700 !important;
            font-size: 1.05rem !important;
        }
        
        /* === STATUS CELL === */
        .properties-table tbody td.cell-status {
            padding: 14px 16px !important;
        }
        .properties-table tbody td.cell-status .status-badge {
            padding: 6px 14px !important;
            font-size: 0.8rem !important;
        }
        
        /* === ACTIONS CELL === */
        .properties-table tbody td.cell-actions {
            background: linear-gradient(135deg, #fafafa 0%, #f0f0f0 100%) !important;
            border-top: 1px solid #e0e0e0 !important;
            border-bottom: none !important;
            padding: 14px 16px !important;
            justify-content: center !important;
        }
        .properties-table tbody td.cell-actions::before {
            display: none !important;
        }
        .properties-table .table-actions {
            display: flex !important;
            flex-direction: row !important;
            width: 100% !important;
            gap: 12px !important;
        }
        .properties-table .table-actions a,
        .properties-table .table-actions button,
        .properties-table .table-actions form {
            flex: 1 !important;
        }
        .properties-table .table-actions a,
        .properties-table .table-actions button {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 12px 16px !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            min-width: unset !important;
        }
        
        /* === TYPE, AREA, LOCATION, COMPANY CELLS === */
        .properties-table tbody td.cell-type,
        .properties-table tbody td.cell-design,
        .properties-table tbody td.cell-area,
        .properties-table tbody td.cell-location,
        .properties-table tbody td.cell-company {
            padding: 12px 16px !important;
        }
        
        /* Hide company on very small screens */
        @media (max-width: 576px) {
            .properties-table tbody td.cell-company {
                display: none !important;
            }
        }
    }
    
    /* Extra small screens */
    @media (max-width: 480px) {
        .properties-table tbody td {
            padding: 10px 12px !important;
            font-size: 0.9rem !important;
        }
        .properties-table tbody td::before {
            font-size: 0.8rem !important;
        }
        .properties-table tbody td.cell-id strong {
            font-size: 1.1rem !important;
        }
        .properties-table tbody td.cell-project .project-title {
            font-size: 1.05rem !important;
        }
        .properties-table .table-actions a,
        .properties-table .table-actions button {
            padding: 10px 12px !important;
            font-size: 0.85rem !important;
        }
    }
    
    /* Mobile Sort Container */
    .mobile-sort-container {
        display: none;
    }
    @media (max-width: 992px) {
        .mobile-sort-container {
            display: flex !important;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            border: 1px solid #e0e0e0;
        }
        .mobile-sort-container label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .mobile-sort-select {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: #fff;
            font-size: 0.9rem;
            color: #333;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 12px center;
            padding-left: 32px;
        }
        html[dir="rtl"] .mobile-sort-select {
            background-position: right 12px center;
            padding-left: 14px;
            padding-right: 32px;
        }
        .mobile-sort-select:focus {
            outline: none;
            border-color: #b08a0e;
            box-shadow: 0 0 0 3px rgba(176, 138, 14, 0.15);
        }
    }
    
    /* Mobile Filter Toggle */
    .mobile-filter-toggle {
        display: none;
    }
    @media (max-width: 992px) {
        .mobile-filter-toggle {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 14px 18px;
            background: linear-gradient(135deg, #b08a0e 0%, #d4a817 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 0;
            transition: all 0.3s ease;
        }
        .mobile-filter-toggle:hover {
            background: linear-gradient(135deg, #9a7a0c 0%, #c49915 100%);
        }
        .mobile-filter-toggle i:first-child {
            margin-left: 8px;
        }
        .mobile-filter-toggle span {
            flex: 1;
            text-align: right;
        }
        .mobile-filter-toggle .toggle-icon {
            transition: transform 0.3s ease;
        }
        .mobile-filter-toggle.active .toggle-icon {
            transform: rotate(180deg);
        }
        
        /* Filter Form - Hidden by default on mobile */
        .filter-section .filter-form {
            display: none;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e0e0e0;
            animation: slideDown 0.3s ease;
        }
        .filter-section .filter-form.show {
            display: block !important;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    }
    </style>
    
    <script>
    function toggleMobileFilters() {
        var btn = document.querySelector('.mobile-filter-toggle');
        var form = document.querySelector('.filter-section .filter-form');
        btn.classList.toggle('active');
        form.classList.toggle('show');
    }
    </script>

</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; // Include the navigation bar?>

<div class="container">
    <?php
    // Display success messages via the flash helper (preferred) and keep error_message behavior
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
    <!-- Page Header and Property Type Tabs -->
    <?php require_once __DIR__ . '/property_header_tabs.php'; ?>

    <!-- Enhanced Filter Section -->
    <div class="filter-section">
        <!-- Mobile Filter Toggle Button -->
        <button type="button" class="mobile-filter-toggle" onclick="toggleMobileFilters()">
            <i class="fas fa-filter"></i>
            <span><?php echo t('filters') ?? 'الفلاتر'; ?></span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        
        <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="filter-form">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
            
            <!-- Basic Filters (Row 1) -->
            <div class="filter-row">
                <div class="filter-item">
                    <label for="search"><?php echo t('search'); ?>:</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="<?php echo t('search_properties_placeholder'); ?>" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <!-- Moved property_type from row 1, keeping it here -->
                <div class="filter-item">
                    <label for="property_type"><?php echo t('property_type'); ?>:</label>
                    <select id="property_type" name="property_type" class="form-control">
                        <option value=""><?php echo t('all_types'); ?></option>
                        <?php foreach ($property_types as $type_id => $type_name): ?>
                            <option value="<?php echo $type_id; ?>" <?php echo $property_type_filter == $type_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Moved status from row 1, keeping it here -->
                <div class="filter-item">
                    <label for="status"><?php echo t('status'); ?>:</label>
                    <select id="status" name="status" class="form-control">
                        <option value=""><?php echo t('all_statuses'); ?></option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>><?php echo t('status_available'); ?></option>
                        
                        <?php if ($current_mode !== 'Rental'): ?>
                            <option value="sold" <?php echo $status_filter === 'sold' ? 'selected' : ''; ?>><?php echo t('status_sold'); ?></option>
                        <?php endif; ?>
                        
                        <?php if ($current_mode === 'Rental'): ?>
                            <option value="rented" <?php echo $status_filter === 'rented' ? 'selected' : ''; ?>><?php echo t('status_rented'); ?></option>
                        <?php endif; ?>
                        
                        <option value="unavailable" <?php echo $status_filter === 'unavailable' ? 'selected' : ''; ?>><?php echo t('status_unavailable'); ?></option>
                    </select>
                </div>
                <!-- Year Built Moved to Row 1 -->
                <div class="filter-item">
                    <label for="year_built"><?php echo t('year_built'); ?>:</label>
                    <select id="year_built" name="year_built" class="form-control">
                        <option value=""><?php echo t('all_years'); ?></option>
                        <?php foreach ($year_built_list as $yb): ?>
                            <option value="<?php echo htmlspecialchars($yb); ?>" <?php echo $year_built_filter === $yb ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($yb); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Governorate, Region, Company, Seller Type -->
            <div class="filter-row">
                <div class="filter-item">
                    <label for="governorate"><?php echo t('governorate'); ?>:</label>
                    <select id="governorate" name="governorate" class="form-control">
                        <option value=""><?php echo t('all_governorates'); ?></option>
                        <?php foreach ($governorates_list as $gov): ?>
                            <option value="<?php echo htmlspecialchars($gov); ?>" <?php echo $governorate_filter === $gov ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($gov); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- New Region (District) Filter -->
                <div class="filter-item">
                    <label for="district"><?php echo t('district'); ?>:</label>
                    <select id="district" name="district" class="form-control" 
                            data-selected="<?php echo htmlspecialchars($district_filter); ?>"
                            data-default-text="<?php echo t('all') . ' ' . t('district'); ?>"
                            data-prompt-text="<?php echo t('select_governorate_first') ?? 'اختر المحافظة أولاً'; ?>"
                            data-loading-text="<?php echo t('loading') ?? 'جاري التحميل...'; ?>">
                        <option value=""><?php echo t('select_governorate_first') ?? 'اختر المحافظة أولاً'; ?></option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="company"><?php echo t('company'); ?>:</label>
                    <select id="company" name="company" class="form-control">
                        <option value=""><?php echo t('all_companies'); ?></option>
                        <?php foreach ($companies_list as $company): ?>
                            <option value="<?php echo htmlspecialchars($company); ?>" <?php echo $company_filter === $company ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="seller_type"><?php echo t('seller_type'); ?>:</label>
                    <select id="seller_type" name="seller_type" class="form-control">
                        <option value=""><?php echo t('all_seller_types'); ?></option>
                        <?php foreach (allowed_seller_types() as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $seller_type_filter === $st ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 3: Currency, Bedrooms, Bathrooms, Balconies -->
            <div class="filter-row">
                <div class="filter-item">
                    <label for="currency"><?php echo t('currency'); ?>:</label>
                    <select id="currency" name="currency_filter" class="form-control">
                        <option value=""><?php echo t('all_currencies') ?? t('all'); ?></option>
                        <option value="EGP" <?php echo $currency_filter === 'EGP' ? 'selected' : ''; ?>><?php echo t('EGP'); ?></option>
                        <option value="USD" <?php echo $currency_filter === 'USD' ? 'selected' : ''; ?>><?php echo t('USD'); ?></option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="bedrooms"><?php echo t('bedrooms_short'); ?>:</label>
                    <select id="bedrooms" name="bedrooms" class="form-control">
                        <option value=""><?php echo t('all'); ?></option>
                        <?php for($i=1; $i<=5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $bedrooms_filter == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                        <option value="6+" <?php echo $bedrooms_filter === '6+' ? 'selected' : ''; ?>>6+</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="bathrooms"><?php echo t('bathrooms_short'); ?>:</label>
                    <select id="bathrooms" name="bathrooms" class="form-control">
                        <option value=""><?php echo t('all'); ?></option>
                        <?php for($i=1; $i<=5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $bathrooms_filter == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                        <option value="6+" <?php echo $bathrooms_filter === '6+' ? 'selected' : ''; ?>>6+</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="balconies"><?php echo t('balconies'); ?>:</label>
                    <select id="balconies" name="balconies" class="form-control">
                        <option value=""><?php echo t('all'); ?></option>
                        <option value="0" <?php echo $balconies_filter === '0' ? 'selected' : ''; ?>>0</option>
                        <option value="1" <?php echo $balconies_filter == 1 ? 'selected' : ''; ?>>1</option>
                        <option value="2" <?php echo $balconies_filter == 2 ? 'selected' : ''; ?>>2</option>
                        <option value="3" <?php echo $balconies_filter == 3 ? 'selected' : ''; ?>>3</option>
                        <option value="4+" <?php echo $balconies_filter === '4+' ? 'selected' : ''; ?>>4+</option>
                    </select>
                </div>
            </div>

            <!-- Advanced Filters Toggle -->
            <div class="advanced-filters-toggle" onclick="toggleAdvancedFilters()">
                <i class="fas fa-sliders-h"></i> <?php echo t('advanced_filters'); ?>
            </div>

            <!-- Advanced Filters -->
            <div id="advancedFilters" class="advanced-filters">
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="min_area"><?php echo t('min_area'); ?>:</label>
                        <input type="number" id="min_area" name="min_area" class="form-control" value="<?php echo htmlspecialchars($min_area_filter); ?>" min="0">
                    </div>
                    <div class="filter-item">
                        <label for="max_area"><?php echo t('max_area'); ?>:</label>
                        <input type="number" id="max_area" name="max_area" class="form-control" value="<?php echo htmlspecialchars($max_area_filter); ?>" min="0">
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="min_price"><?php echo t('min_price'); ?>:</label>
                        <input type="number" id="min_price" name="min_price" class="form-control" value="<?php echo htmlspecialchars($min_price_filter); ?>" min="0">
                    </div>
                    <div class="filter-item">
                        <label for="max_price"><?php echo t('max_price'); ?>:</label>
                        <input type="number" id="max_price" name="max_price" class="form-control" value="<?php echo htmlspecialchars($max_price_filter); ?>" min="0">
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="down_payment"><?php echo t('down_payment_percentage'); ?>:</label>
                        <input type="number" id="down_payment" name="down_payment" class="form-control" value="<?php echo htmlspecialchars($down_payment_filter); ?>" min="0" max="100">
                    </div>
                    <div class="filter-item">
                        <label for="payment_period"><?php echo t('payment_years'); ?>:</label>
                            <input type="number" id="payment_period" name="payment_period" class="form-control" value="<?php echo htmlspecialchars($payment_period_filter); ?>" min="0" step="0.5">
                    </div>
                    <div class="filter-item">
                        <label for="unit_design"><?php echo t('unit_design'); ?>:</label>
                        <select id="unit_design" name="unit_design" class="form-control">
                            <option value=""><?php echo t('all_designs'); ?></option>
                            <?php foreach ($unit_designs as $design_name): ?>
                                <option value="<?php echo htmlspecialchars($design_name); ?>" <?php echo $unit_design_filter === $design_name ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($design_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> <?php echo t('filter'); ?>
                </button>
                <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?view=<?php echo $view_mode; ?>" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> <?php echo t('reset'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- View Toggle -->
    <div class="view-toggle">
        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?view=table<?php echo !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'view=') === false ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn <?php echo $view_mode === 'table' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fas fa-list"></i> <?php echo t('table_view'); ?>
        </a>
        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?view=card<?php echo !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'view=') === false ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn <?php echo $view_mode === 'card' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fas fa-th"></i> <?php echo t('card_view'); ?>
        </a>
    </div>

    <?php if (empty($properties)): ?>
        <div class="no-properties">
            <i class="fas fa-search"></i>
            <h3><?php echo t('no_properties_found'); ?></h3>
            <p><?php echo t('no_properties_matching_filters'); ?></p>
        </div>
    <?php elseif ($view_mode === 'card'): ?>
        <!-- Card View -->
        <div class="property-cards">
            <?php foreach ($properties as $property): ?>
                <div class="property-card" data-property-id="<?php echo $property['PropertyID']; ?>" data-property-type="<?php echo $property['PropertyTypeID']; ?>">
                    <div class="property-image">
                        <?php echo generatePropertyCardImageHTML($property, $conn); ?>
                        <div class="property-status-badge status-<?php echo $property['Status']; ?>">
                            <?php echo t('status_' . $property['Status']); ?>
                        </div>
                    </div>
                    <div class="property-content">
                        <div class="property-title">
                            <?php
                                $proj = trim((string)($property['ProjectName'] ?? ''));
                                $ttl = trim((string)($property['Title'] ?? ''));
                                if ($proj !== '') {
                                    echo '<strong>' . htmlspecialchars($proj) . '</strong>';
                                    if ($ttl !== '') {
                                        echo '<br><span class="project-card-sub">' . htmlspecialchars($ttl) . '</span>';
                                    }
                                } elseif ($ttl !== '') {
                                    echo '<strong>' . htmlspecialchars($ttl) . '</strong>';
                                } else {
                                    echo '-';
                                }
                            ?>
                        </div>
                        
                        <div class="property-details">
                            <div class="property-info">
                                <i class="fas fa-tag"></i>
                                <span><?php echo formatUnitModel($property); ?></span>
                            </div>

                            <div class="property-info">
                                <i class="fas fa-dollar-sign"></i>
                                <span><?php echo formatDisplayPrice($property); ?></span>
                            </div>

                            <div class="property-info">
                                <i class="fas fa-ruler-combined"></i>
                                <span><?php echo formatDisplayArea($property); ?></span>
                            </div>

                            <div class="property-info">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($property['governorate'] ?: t('undefined')); ?></span>
                            </div>

                            <div class="property-info">
                                <i class="fas fa-building"></i>
                                <span><?php echo htmlspecialchars($property['company_name'] ?? t('undefined')); ?></span>
                            </div>
                        </div>
                        
                        <div class="property-actions">
                            <?php if (can_manage_properties()): ?>
                            <a href="edit_property.php?id=<?php echo $property['PropertyID']; ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i> <?php echo t('edit'); ?>
                            </a>
                            <?php endif; ?>
                            <?php if (can_delete_properties()): ?>
                            <form method="POST" action="delete_property.php" style="display:inline" onsubmit="return confirm('<?php echo t('confirm_delete_property'); ?>');">
                                <input type="hidden" name="id" value="<?php echo (int)$property['PropertyID']; ?>">
                                <?php echo csrf_input(); ?>
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> <?php echo t('delete'); ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Enhanced Table View -->
        
        <!-- Mobile Sort Dropdown (visible only on mobile) -->
        <div class="mobile-sort-container">
            <label for="mobile-sort"><?php echo t('sort_by') ?? 'ترتيب حسب'; ?>:</label>
            <select id="mobile-sort" class="mobile-sort-select" onchange="window.location.href=this.value">
                <option value=""><?php echo t('select_sort') ?? 'اختر الترتيب'; ?></option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'friendly_id', 'direction' => 'desc'])); ?>" <?php echo ($sort_column === 'friendly_id' && $sort_direction === 'desc') ? 'selected' : ''; ?>>
                    # <?php echo t('table_header_id') ?? 'رقم'; ?> (الأحدث أولاً)
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'friendly_id', 'direction' => 'asc'])); ?>" <?php echo ($sort_column === 'friendly_id' && $sort_direction === 'asc') ? 'selected' : ''; ?>>
                    # <?php echo t('table_header_id') ?? 'رقم'; ?> (الأقدم أولاً)
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price', 'direction' => 'desc'])); ?>" <?php echo ($sort_column === 'price' && $sort_direction === 'desc') ? 'selected' : ''; ?>>
                    <?php echo t('table_header_price') ?? 'السعر'; ?> (الأعلى أولاً)
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price', 'direction' => 'asc'])); ?>" <?php echo ($sort_column === 'price' && $sort_direction === 'asc') ? 'selected' : ''; ?>>
                    <?php echo t('table_header_price') ?? 'السعر'; ?> (الأقل أولاً)
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'area', 'direction' => 'desc'])); ?>" <?php echo ($sort_column === 'area' && $sort_direction === 'desc') ? 'selected' : ''; ?>>
                    <?php echo t('table_header_area_sqm') ?? 'المساحة'; ?> (الأكبر أولاً)
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'area', 'direction' => 'asc'])); ?>" <?php echo ($sort_column === 'area' && $sort_direction === 'asc') ? 'selected' : ''; ?>>
                    <?php echo t('table_header_area_sqm') ?? 'المساحة'; ?> (الأصغر أولاً)
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'project_or_title', 'direction' => 'asc'])); ?>" <?php echo ($sort_column === 'project_or_title' && $sort_direction === 'asc') ? 'selected' : ''; ?>>
                    <?php echo t('table_header_project_name') ?? 'المشروع'; ?> (أ-ي)
                </option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'project_or_title', 'direction' => 'desc'])); ?>" <?php echo ($sort_column === 'project_or_title' && $sort_direction === 'desc') ? 'selected' : ''; ?>>
                    <?php echo t('table_header_project_name') ?? 'المشروع'; ?> (ي-أ)
                </option>
            </select>
        </div>
        
        <div class="table-responsive">
            <table class="properties-table">
                <thead>
                    <tr>
                        <th class="cell-id sortable" onclick="window.location.href='<?php echo getSortUrl('friendly_id', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_id'); ?> <?php echo getSortIcon('friendly_id', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-project sortable" onclick="window.location.href='<?php echo getSortUrl('project_or_title', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_project_name'); ?> <?php echo getSortIcon('project_or_title', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-type sortable" onclick="window.location.href='<?php echo getSortUrl('type_name', $sort_column, $sort_direction); ?>'">
                            <?php echo t('property_type'); ?> <?php echo getSortIcon('type_name', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-model sortable" onclick="window.location.href='<?php echo getSortUrl('unit_design', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_unit_design'); ?> <?php echo getSortIcon('unit_design', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-price sortable" onclick="window.location.href='<?php echo getSortUrl('price', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_price'); ?> <?php echo getSortIcon('price', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-area sortable" onclick="window.location.href='<?php echo getSortUrl('area', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_area_sqm'); ?> <?php echo getSortIcon('area', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-location sortable" onclick="window.location.href='<?php echo getSortUrl('governorate', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_governorate'); ?> <?php echo getSortIcon('governorate', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-company sortable" onclick="window.location.href='<?php echo getSortUrl('company_name', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_company'); ?> <?php echo getSortIcon('company_name', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-status sortable" onclick="window.location.href='<?php echo getSortUrl('Status', $sort_column, $sort_direction); ?>'">
                            <?php echo t('table_header_status'); ?> <?php echo getSortIcon('Status', $sort_column, $sort_direction); ?>
                        </th>
                        <?php if (can_manage_properties()): ?>
                        <th class="cell-actions"><?php echo t('actions'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php // Display PropertyID instead of a sequential row counter ?>
                    <?php foreach ($properties as $property): ?>
                        <tr class="property-row" data-property-id="<?php echo $property['PropertyID']; ?>">
                            <td class="cell-id" data-label="#">
                                <strong><?php echo (int)($property['friendly_id'] ?? $property['PropertyID']); ?></strong>
                            </td>
                            <td class="cell-project" data-label="<?php echo t('table_header_project_name'); ?>">
                                <?php
                                    $proj = trim((string)($property['ProjectName'] ?? ''));
                                    $ttl = trim((string)($property['Title'] ?? ''));
                                ?>
                                <div class="project-title">
                                    <?php
                                        if ($proj !== '') {
                                            echo '<strong>' . htmlspecialchars($proj) . '</strong>';
                                        } elseif ($ttl !== '') {
                                            echo '<strong>' . htmlspecialchars($ttl) . '</strong>';
                                        } else {
                                            echo '-';
                                        }
                                    ?>
                                </div>
                                <?php if ($proj !== '' && $ttl !== ''): ?>
                                <div class="project-subtitle">
                                    <?php echo htmlspecialchars($ttl); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="cell-type" data-label="<?php echo t('property_type'); ?>">
                                <?php echo getPropertyTypeBadges($property); ?>
                            </td>
                            <td class="cell-design" data-label="<?php echo t('table_header_unit_design'); ?>">
                                <?php echo formatUnitModel($property); ?>
                            </td>
                            <td class="cell-price" data-label="<?php echo t('table_header_price'); ?>">
                                <?php echo formatDisplayPrice($property); ?>
                            </td>
                            <td class="cell-area" data-label="<?php echo t('table_header_area_sqm'); ?>">
                                <?php echo formatDisplayArea($property); ?>
                            </td>
                            <td class="cell-location" data-label="<?php echo t('table_header_governorate'); ?>">
                                <div class="location-main">
                                    <?php echo htmlspecialchars($property["governorate"] ?: t("undefined")); ?>
                                </div>
                                <?php if (!empty($property["district"]) && $property["district"] !== $property["governorate"]): ?>
                                <div class="location-sub">
                                    <?php echo htmlspecialchars($property["district"]); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="cell-company" data-label="<?php echo t('table_header_company'); ?>">
                                <?php echo htmlspecialchars($property['company_name'] ?? t('undefined')); ?>
                            </td>
                            <td class="cell-status" data-label="<?php echo t('table_header_status'); ?>">
                                <?php echo getStatusBadge($property['Status']); ?>
                            </td>
                            <?php if (can_manage_properties()): ?>
                            <td class="cell-actions" data-label="">
                                <div class="table-actions">
                                    <a href="edit_property.php?id=<?php echo $property['PropertyID']; ?>" class="btn-edit" title="<?php echo t('edit'); ?>">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (can_delete_properties()): ?>
                                    <form method="POST" action="delete_property.php" style="display:inline" onsubmit="return confirm('<?php echo t('confirm_delete_property'); ?>');">
                                        <input type="hidden" name="id" value="<?php echo (int)$property['PropertyID']; ?>">
                                        <?php echo csrf_input(); ?>
                                        <button type="submit" class="btn-delete" title="<?php echo t('delete'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleAdvancedFilters() {
        const advancedFilters = document.getElementById('advancedFilters');
        const toggleButton = document.querySelector('.advanced-filters-toggle');
        
        if (advancedFilters.classList.contains('show')) {
            advancedFilters.classList.remove('show');
            toggleButton.innerHTML = '<i class="fas fa-sliders-h"></i> <?php echo t('advanced_filters'); ?>';
        } else {
            advancedFilters.classList.add('show');
            toggleButton.innerHTML = '<i class="fas fa-times"></i> <?php echo t('hide_advanced_filters'); ?>';
        }
    }
    
    // Advanced filters are now opened/closed only when the user clicks the toggle.
</script>

<script src="property_row_click.js"></script>
<script src="filter_regions.js"></script>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>

