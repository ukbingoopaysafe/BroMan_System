<?php
require_once __DIR__ . '/security.php';
ensure_secure_session_started();
require_once __DIR__ . '/lang/translate.php'; // Include translation handler
require_once 'session_check.php'; // Include session timeout check
require_once __DIR__ . '/db.php'; // Database connection
require_once __DIR__ . '/sorting_helpers.php'; // Include sorting helper functions
require_once 'permissions.php'; // Include permissions system
require_once __DIR__ . '/property_image_helper.php'; // Include image helper functions

// Check permissions - all logged in users can view properties
check_permission('view_properties');

// Enforce mode selection - redirect to dashboard if no mode is set
if (!isset($_SESSION['listing_context']) || ($_SESSION['listing_context'] !== 'Primary' && $_SESSION['listing_context'] !== 'Resale')) {
    header('Location: dashboard.php');
    exit();
}

$current_mode = $_SESSION['listing_context'];

// Define the Property Type IDs for this page (Commercial/Administrative/Medical/Hotel - VERIFY IN DB)
const CURRENT_PROPERTY_TYPE_IDS = [3, 4, 5, 6]; // Adjust based on your database

// Initialize search and filter variables
$search_term = $governorate_filter = $company_filter = $status_filter = $property_type_filter = "";
$min_price_filter = $max_price_filter = $min_area_filter = $max_area_filter = $down_payment_filter = $payment_period_filter = "";
$unit_design_filter = "";
$view_mode = isset($_GET['view']) && $_GET['view'] === 'card' ? 'card' : 'table';

// Initialize sorting variables
$sort_column = isset($_GET['sort']) ? trim($_GET['sort']) : 'DateAdded';
$sort_direction = isset($_GET['direction']) && $_GET['direction'] === 'desc' ? 'desc' : 'asc';

// Validate sort column to prevent SQL injection
$allowed_sort_columns = [
    'PropertyID' => 'p.PropertyID',
    'DateAdded' => 'p.DateAdded',
    'Price' => 'COALESCE(p.Price, p.min_price)',
    'Area' => 'COALESCE(p.Area, p.avg_area)',
    'ProjectName' => 'p.ProjectName',
    'Title' => 'p.Title',
    'governorate' => 'p.governorate',
    'Status' => 'p.Status'
];

if (!array_key_exists($sort_column, $allowed_sort_columns)) {
    $sort_column = 'DateAdded';
}

// Process search and filter inputs
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $governorate_filter = isset($_GET['governorate']) ? trim($_GET['governorate']) : '';
    $company_filter = isset($_GET['company']) ? trim($_GET['company']) : '';
    $unit_design_filter = isset($_GET['unit_design']) ? trim($_GET['unit_design']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $property_type_filter = isset($_GET['property_type']) ? trim($_GET['property_type']) : '';

    // Advanced filters
    $min_price_filter = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? trim($_GET['min_price']) : '';
    $max_price_filter = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? trim($_GET['max_price']) : '';
    $min_area_filter = isset($_GET['min_area']) && is_numeric($_GET['min_area']) ? trim($_GET['min_area']) : '';
    $max_area_filter = isset($_GET['max_area']) && is_numeric($_GET['max_area']) ? trim($_GET['max_area']) : '';
    $down_payment_filter = isset($_GET['down_payment']) ? trim($_GET['down_payment']) : '';
    $payment_period_filter = isset($_GET['payment_period']) ? trim($_GET['payment_period']) : '';
}

// Fetch distinct governorates for the filter dropdown
$governorates_list = [];
$sql_governorates = "SELECT DISTINCT governorate FROM Properties WHERE governorate IS NOT NULL AND governorate != '' ORDER BY governorate";
if ($stmt_gov = $conn->prepare($sql_governorates)) {
    if ($stmt_gov->execute()) {
        $result_governorates = $stmt_gov->get_result();
        while ($row_gov = $result_governorates->fetch_assoc()) {
            $governorates_list[] = $row_gov['governorate'];
        }
    }
    $stmt_gov->close();
}

// Fetch distinct companies for the filter dropdown
$companies_list = [];
$sql_companies = "SELECT DISTINCT company_name FROM Properties WHERE company_name IS NOT NULL AND company_name != '' ORDER BY company_name";
if ($stmt_comp = $conn->prepare($sql_companies)) {
    if ($stmt_comp->execute()) {
        $result_companies = $stmt_comp->get_result();
        while ($row_comp = $result_companies->fetch_assoc()) {
            $companies_list[] = $row_comp['company_name'];
        }
    }
    $stmt_comp->close();
}

// Fetch unit designs for the filter dropdown
$unit_designs = [];
$sql_unit_designs = "SELECT DISTINCT p.unit_design AS DesignName 
                     FROM Properties p 
                     WHERE p.unit_design IS NOT NULL AND p.unit_design != ''
                     ORDER BY p.unit_design";
if ($stmt_designs = $conn->prepare($sql_unit_designs)) {
    if ($stmt_designs->execute()) {
        $result_designs = $stmt_designs->get_result();
        while ($row_design = $result_designs->fetch_assoc()) {
            $unit_designs[] = $row_design['DesignName'];
        }
    }
    $stmt_designs->close();
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
$sql_select = "SELECT p.*, pt.TypeName AS PropertyTypeName, e.FullName AS EmployeeName,
               p.unit_design AS UnitDesignName, pft.FinishingName AS FinishingTypeName
               FROM Properties p
               LEFT JOIN PropertyTypes pt ON p.PropertyTypeID = pt.PropertyTypeID
               LEFT JOIN Employees e ON p.EmployeeID = e.EmployeeID
               -- Unit design is now a text field, no join needed
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
    $sql_where[] = "(p.Title LIKE ? OR p.ProjectName LIKE ? OR p.governorate LIKE ? OR p.district LIKE ? OR p.Location LIKE ? OR p.company_name LIKE ? OR pud.DesignName LIKE ?)";
    $search_param = "%" . $search_term . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    $types .= "sssssss";
}

// Add property type filter
if (!empty($property_type_filter)) {
    $sql_where[] = "p.PropertyTypeID = ?";
    $params[] = $property_type_filter;
    $types .= "i";
}

// Add governorate filter
if (!empty($governorate_filter)) {
    $sql_where[] = "p.governorate = ?";
    $params[] = $governorate_filter;
    $types .= "s";
}

// Add company filter
if (!empty($company_filter)) {
    $sql_where[] = "p.company_name = ?";
    $params[] = $company_filter;
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
    $sql_where[] = "COALESCE(p.Price, p.min_price) >= ?";
    $params[] = $min_price_filter;
    $types .= "d";
}
if (!empty($max_price_filter)) {
    $sql_where[] = "COALESCE(p.Price, p.max_price) <= ?";
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
    $types .= "i";
}

// Add property type filter for commercial properties (multiple types)
$placeholders = str_repeat('?,', count(CURRENT_PROPERTY_TYPE_IDS) - 1) . '?';
$sql_where[] = "p.PropertyTypeID IN ($placeholders)";
foreach (CURRENT_PROPERTY_TYPE_IDS as $type_id) {
    $params[] = $type_id;
    $types .= "i";
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

// Function to format price for display
function formatDisplayPrice($row)
{
    if (!empty($row['Price']) && is_numeric($row['Price'])) {
        return number_format(floatval($row['Price']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($row['min_price']) && is_numeric($row['min_price']) && !empty($row['max_price']) && is_numeric($row['max_price'])) {
        return number_format(floatval($row['min_price']), 0, '.', ',') . ' - ' . number_format(floatval($row['max_price']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($row['min_price']) && is_numeric($row['min_price'])) {
        return t('starts_from') . ' ' . number_format(floatval($row['min_price']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($row['max_price']) && is_numeric($row['max_price'])) {
        return t('up_to') . ' ' . number_format(floatval($row['max_price']), 0, '.', ',') . ' ' . t('currency');
    }
    return t('price_not_specified');
}

// Function to format area for display
function formatDisplayArea($row)
{
    if (!empty($row['Area']) && is_numeric($row['Area'])) {
        return number_format(floatval($row['Area']), 0, '.', ',') . ' ' . t('sqm');
    } elseif (!empty($row['min_area']) && is_numeric($row['min_area']) && !empty($row['max_area']) && is_numeric($row['max_area'])) {
        return number_format(floatval($row['min_area']), 0, '.', ',') . ' - ' . number_format(floatval($row['max_area']), 0, '.', ',') . ' ' . t('sqm');
    } elseif (!empty($row['min_area']) && is_numeric($row['min_area'])) {
        return t("starts_from") . " " . number_format(floatval($row["min_area"]), 0, ".", ",") . " " . t("sqm");
    } elseif (!empty($row['max_area']) && is_numeric($row['max_area'])) {
        return t("up_to") . " " . number_format(floatval($row["max_area"]), 0, ".", ",") . " " . t("sqm");
    }
    return t('area_not_specified');
}

// Function to format payment terms
function formatPaymentTerms($row)
{
    $terms = [];
    
    if (!empty($row['down_payment_percentage']) && is_numeric($row['down_payment_percentage'])) {
        $terms[] = number_format($row['down_payment_percentage'], 0) . '% ' . t('down_payment_percentage');
    }
    
    if (!empty($row['payment_years']) && is_numeric($row['payment_years'])) {
        $terms[] = $row['payment_years'] . ' ' . t('years');
    }
    
    return !empty($terms) ? implode(' + ', $terms) : t('undefined');
}

// Function to get status badge HTML
function getStatusBadge($status) {
    $status_classes = [
        'available' => 'status-available',
        'sold' => 'status-sold', 
        'rented' => 'status-rented',
        'unavailable' => 'status-unavailable'
    ];
    
    $status_icons = [
        'available' => 'fas fa-check-circle',
        'sold' => 'fas fa-handshake',
        'rented' => 'fas fa-key',
        'unavailable' => 'fas fa-times-circle'
    ];
    
    $class = $status_classes[$status] ?? 'status-unavailable';
    $icon = $status_icons[$status] ?? 'fas fa-question-circle';
    $text = t('status_' . $status);
    
    return "<span class=\"status-badge {$class}\"><i class=\"{$icon}\"></i> {$text}</span>";
}

// Function to get property type badge HTML
function getPropertyTypeBadge($typeId, $typeName) {
    $type_icons = [
        1 => 'fas fa-home',      // سكني
        2 => 'fas fa-umbrella-beach', // ساحلي
        3 => 'fas fa-building',  // إداري
        4 => 'fas fa-store',     // تجاري
        5 => 'fas fa-hotel',     // فندقي
        6 => 'fas fa-hospital'   // طبي
    ];
    
    $icon = $type_icons[$typeId] ?? 'fas fa-landmark';
    
    return "<span class=\"property-type-badge\"><i class=\"{$icon}\"></i> {$typeName}</span>";
}

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('commercial_properties'); ?></title>
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <link rel="stylesheet" href="enhanced_property_table_styles.css">
    <link rel="stylesheet" href="clickable_rows.css">
    <link rel="stylesheet" href="property_card_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <script src="session_timeout.js"></script>
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; // Include the navigation bar?>

<div class="container">
    <?php
    // Display success or error messages from session if they exist
    if (isset($_SESSION["success_message"])) {
        echo "<div class='message message-success'>" . htmlspecialchars($_SESSION["success_message"]) . "</div>";
        unset($_SESSION["success_message"]);
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
        <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
            
            <!-- Basic Filters -->
            <div class="filter-row">
                <div class="filter-item">
                    <label for="search"><?php echo t('search'); ?>:</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="<?php echo t('search_properties_placeholder'); ?>" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
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
                <div class="filter-item">
                    <label for="status"><?php echo t('status'); ?>:</label>
                    <select id="status" name="status" class="form-control">
                        <option value=""><?php echo t('all_statuses'); ?></option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>><?php echo t('status_available'); ?></option>
                        <option value="sold" <?php echo $status_filter === 'sold' ? 'selected' : ''; ?>><?php echo t('status_sold'); ?></option>
                        <option value="rented" <?php echo $status_filter === 'rented' ? 'selected' : ''; ?>><?php echo t('status_rented'); ?></option>
                        <option value="unavailable" <?php echo $status_filter === 'unavailable' ? 'selected' : ''; ?>><?php echo t('status_unavailable'); ?></option>
                    </select>
                </div>
            </div>

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
                    <label for="unit_design"><?php echo t('unit_design'); ?>:</label>
                    <select id="unit_design" name="unit_design" class="form-control">
                        <option value=""><?php echo t('all_designs'); ?></option>
                        <?php foreach ($unit_designs as $design_id => $design_name): ?>
                            <option value="<?php echo $design_id; ?>" <?php echo $unit_design_filter == $design_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($design_name); ?>
                            </option>
                        <?php endforeach; ?>
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
                        <input type="number" id="payment_period" name="payment_period" class="form-control" value="<?php echo htmlspecialchars($payment_period_filter); ?>" min="0">
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
                            <?php echo htmlspecialchars($property['ProjectName'] ?: $property['Title'] ?: t('undefined')); ?>
                        </div>
                        
                        <div class="property-details">
                            <div class="property-info">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($property['UnitDesignName'] ?? t('undefined')); ?></span>
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
        <div class="table-responsive">
            <table class="properties-table">
                <thead>
                    <tr>
                        <th class="cell-id sortable" onclick="window.location.href='<?php echo getSortUrl('PropertyID', $sort_column, $sort_direction); ?>'">
                            <i class="fas fa-hashtag"></i> <?php echo t('table_header_id'); ?>
                            <?php echo getSortIcon('PropertyID', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-project sortable" onclick="window.location.href='<?php echo getSortUrl('ProjectName', $sort_column, $sort_direction); ?>'">
                            <i class="fas fa-building"></i> <?php echo t('table_header_project_name'); ?>
                            <?php echo getSortIcon('ProjectName', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-type">
                            <i class="fas fa-tag"></i> <?php echo t('property_type'); ?>
                        </th>
                        <th class="cell-design">
                            <i class="fas fa-home"></i> <?php echo t('table_header_unit_design'); ?>
                        </th>
                        <th class="cell-price sortable" onclick="window.location.href='<?php echo getSortUrl('Price', $sort_column, $sort_direction); ?>'">
                            <i class="fas fa-dollar-sign"></i> <?php echo t('table_header_price'); ?>
                            <?php echo getSortIcon('Price', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-area sortable" onclick="window.location.href='<?php echo getSortUrl('Area', $sort_column, $sort_direction); ?>'">
                            <i class="fas fa-ruler-combined"></i> <?php echo t('table_header_area_sqm'); ?>
                            <?php echo getSortIcon('Area', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-location sortable" onclick="window.location.href='<?php echo getSortUrl('governorate', $sort_column, $sort_direction); ?>'">
                            <i class="fas fa-map-marker-alt"></i> <?php echo t('table_header_governorate'); ?>
                            <?php echo getSortIcon('governorate', $sort_column, $sort_direction); ?>
                        </th>
                        <th class="cell-company">
                            <i class="fas fa-industry"></i> <?php echo t('table_header_company'); ?>
                        </th>
                        <th class="cell-status sortable" onclick="window.location.href='<?php echo getSortUrl('Status', $sort_column, $sort_direction); ?>'">
                            <i class="fas fa-info-circle"></i> <?php echo t('status'); ?>
                            <?php echo getSortIcon('Status', $sort_column, $sort_direction); ?>
                        </th>
                        <?php if (can_manage_properties()): ?>
                        <th class="cell-actions">
                            <i class="fas fa-cogs"></i> <?php echo t('table_header_actions'); ?>
                        </th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = 1; ?>
                    <?php foreach ($properties as $property): ?>
                        <tr class="property-row" data-property-id="<?php echo $property['PropertyID']; ?>">
                            <td class="cell-id">
                                <strong><?php echo $row_number; ?></strong>
                            </td>
                            <td class="cell-project">
                                <div class="project-title">
                                    <?php echo htmlspecialchars($property["ProjectName"] ?: t("undefined")); ?>
                                </div>
                                <div class="project-subtitle">
                                    <?php echo htmlspecialchars($property["Title"] ?: t("undefined")); ?>
                                </div>
                            </td>
                            <td class="cell-type">
                                <?php echo getPropertyTypeBadge($property['PropertyTypeID'], $property['PropertyTypeName']); ?>
                            </td>
                            <td class="cell-design">
                                <?php echo htmlspecialchars($property['UnitDesignName'] ?? t('undefined')); ?>
                            </td>
                            <td class="cell-price">
                                <?php echo formatDisplayPrice($property); ?>
                            </td>
                            <td class="cell-area">
                                <?php echo formatDisplayArea($property); ?>
                            </td>
                            <td class="cell-location">
                                <div class="location-main">
                                    <?php echo htmlspecialchars($property["governorate"] ?: t("undefined")); ?>
                                </div>
                                <?php if (!empty($property["district"]) && $property["district"] !== $property["governorate"]): ?>
                                <div class="location-sub">
                                    <?php echo htmlspecialchars($property["district"]); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="cell-company">
                                <?php echo htmlspecialchars($property['company_name'] ?? t('undefined')); ?>
                            </td>
                            <td class="cell-status">
                                <?php echo getStatusBadge($property['Status']); ?>
                            </td>
                            <?php if (can_manage_properties()): ?>
                            <td class="cell-actions">
                                <div class="table-actions">
                                    <a href="edit_property.php?id=<?php echo $property['PropertyID']; ?>" class="btn-edit" title="<?php echo t('edit'); ?>">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="delete_property.php" style="display:inline" onsubmit="return confirm('<?php echo t('confirm_delete_property'); ?>');">
                                        <input type="hidden" name="id" value="<?php echo (int)$property['PropertyID']; ?>">
                                        <?php echo csrf_input(); ?>
                                        <button type="submit" class="btn-delete" title="<?php echo t('delete'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php $row_number++; ?>
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
    
    // Show advanced filters if any of them are set
    document.addEventListener('DOMContentLoaded', function() {
        const hasAdvancedFilters = <?php echo
            !empty($min_price_filter) ||
            !empty($max_price_filter) ||
            !empty($min_area_filter) ||
            !empty($max_area_filter) ||
            !empty($down_payment_filter) ||
            !empty($payment_period_filter) ? 'true' : 'false';
        ?>;
        
        if (hasAdvancedFilters) {
            toggleAdvancedFilters();
        }
    });
</script>

<script src="property_row_click.js"></script>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>

