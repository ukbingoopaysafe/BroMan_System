<?php
session_start();
// Debug: log session id and any flash messages or legacy success message at the start of the page load
error_log("FLASH-DEBUG view_property ON LOAD session=" . session_id() . " name=" . session_name() . " save_path=" . session_save_path() . " flash=" . var_export(
    		session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['flash_messages'] ?? null) : null, true
    ) . " legacy=" . var_export($_SESSION['success_message'] ?? null, true));
require_once __DIR__ . '/lang/translate.php'; // Include translation handler
require_once 'session_check.php'; // Include session timeout check
require_once 'permissions.php';

// Enforce authentication and mode access
$current_mode = require_listing_context();
check_permission('view_properties');

require_once __DIR__ . '/db.php'; // Include database connection using absolute path
require_once __DIR__ . '/media_functions.php'; // Include media helper functions
require_once __DIR__ . '/log_property_visit.php'; // Include property visit logging

// Check if property ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index_properties.php");
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property details with proper joins for unit design and finishing type
$property = null;
$sql = "SELECT p.*, 
               pt.TypeName, 
               pt.TypeName_ar,
               e.FullName AS EmployeeName,
               p.unit_design AS UnitDesignName, 
               pft.FinishingName AS FinishingTypeName,
               pft.FinishingName_ar AS FinishingTypeName_ar
        FROM Properties p
        LEFT JOIN PropertyTypes pt ON p.PropertyTypeID = pt.PropertyTypeID
        LEFT JOIN Employees e ON p.EmployeeID = e.EmployeeID
        LEFT JOIN PropertyFinishingTypes pft ON p.finishing_type_id = pft.FinishingID
        WHERE p.PropertyID = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $property_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $property = $result->fetch_assoc();
            // Block access if the property listing type is not allowed for the current role
            if (!is_mode_allowed_for_role($property['listing_type'])) {
                $_SESSION["error_message"] = t('mode_not_allowed_for_role');
                header("Location: dashboard.php");
                exit;
            }
            // Prevent cross-mode access for non-managers
            if (!is_manager() && $property['listing_type'] !== $current_mode) {
                $_SESSION["error_message"] = t('mode_not_allowed');
                header("Location: dashboard.php");
                exit;
            }
            // Debug session data
            error_log("Session data in view_property.php: " . print_r($_SESSION, true));
            // Log the property visit ONLY if not redirected from add/edit property (avoid logging after adding/editing)
            // Check if 'added' or 'edited' parameter exists in URL - if so, skip visit logging
            $skip_visit_log = (isset($_GET['added']) && $_GET['added'] == '1') || (isset($_GET['edited']) && $_GET['edited'] == '1');
            
            if (!$skip_visit_log) {
                $visit_logged = log_property_visit($property_id);
                error_log("Property visit log result: " . ($visit_logged ? "success" : "failed"));
            } else {
                $reason = isset($_GET['added']) ? "add property" : "edit property";
                error_log("Skipping property visit log - redirected from " . $reason);
            }
        } else {
            // Property not found
            header("Location: index_properties.php");
            exit;
        }
    } else {
        // Error executing query
        header("Location: index_properties.php");
        exit;
    }
    $stmt->close();
} else {
    // Error preparing statement
    header("Location: index_properties.php");
    exit;
}

// Get property media
$property_images = getPropertyImages($property_id, $conn);
$property_audio_notes = getPropertyAudioNotes($property_id, $conn);
$property_videos = getPropertyVideos($property_id, $conn);
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

// Fetch all sellers linked to this property (new multi-seller table)
$property_sellers = [];
$seller_stmt = $conn->prepare("SELECT seller_type, seller_name, seller_phone, created_at FROM property_sellers WHERE property_id = ? ORDER BY id ASC");
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

// Fetch all linked property types for display pluralization
$linked_types = [];
$lt_sql = "SELECT pt.TypeName, pt.TypeName_ar 
           FROM property_type_links ptl
           JOIN PropertyTypes pt ON ptl.property_type_id = pt.PropertyTypeID
           WHERE ptl.property_id = ?";
if ($lt_stmt = $conn->prepare($lt_sql)) {
    $lt_stmt->bind_param("i", $property_id);
    if ($lt_stmt->execute()) {
        $lt_res = $lt_stmt->get_result();
        while ($lt_row = $lt_res->fetch_assoc()) {
            $linked_types[] = (get_lang_code() === 'ar' && !empty($lt_row['TypeName_ar'])) ? $lt_row['TypeName_ar'] : $lt_row['TypeName'];
        }
    }
    $lt_stmt->close();
}
// Fallback if no links exist (legacy)
if (empty($linked_types) && !empty($property['TypeName'])) {
    $linked_types[] = (get_lang_code() === 'ar' && !empty($property['TypeName_ar'])) ? $property['TypeName_ar'] : $property['TypeName'];
}
$types_display = implode(' ، ', array_map('htmlspecialchars', $linked_types));

// Format price for display
function formatPrice($property)
{
    // Get actual currency from property
    $currency_code = $property['currency'] ?? 'EGP';
    $currency_label = ($currency_code === 'USD') ? 'دولار' : 'جنيه مصري';
    
    if (isset($property['listing_type']) && $property['listing_type'] === 'Rental') {
        if (!empty($property['monthly_rent']) && is_numeric($property['monthly_rent'])) {
            return number_format(floatval($property['monthly_rent']), 0, '.', ',') . ' ' . $currency_label . ' / ' . t('per_month');
        } elseif (!empty($property['daily_rent']) && is_numeric($property['daily_rent'])) {
            return number_format(floatval($property['daily_rent']), 0, '.', ',') . ' ' . $currency_label . ' / ' . t('per_day');
        } elseif (!empty($property['yearly_rent']) && is_numeric($property['yearly_rent'])) {
            return number_format(floatval($property['yearly_rent']), 0, '.', ',') . ' ' . $currency_label . ' / ' . t('per_year');
        }
    }
    if (!empty($property['price_cash']) && is_numeric($property['price_cash'])) {
        return number_format(floatval($property['price_cash']), 0, '.', ',') . ' ' . $currency_label;
    } elseif (!empty($property['price_installments']) && is_numeric($property['price_installments'])) {
        return number_format(floatval($property['price_installments']), 0, '.', ',') . ' ' . $currency_label;
    } elseif (!empty($property['min_price']) && is_numeric($property['min_price']) && !empty($property['max_price']) && is_numeric($property['max_price'])) {
        return number_format(floatval($property['min_price']), 0, '.', ',') . ' - ' . number_format(floatval($property['max_price']), 0, '.', ',') . ' ' . $currency_label;
    } elseif (!empty($property['min_price']) && is_numeric($property['min_price'])) {
        return t('starts_from') . ' ' . number_format(floatval($property['min_price']), 0, '.', ',') . ' ' . $currency_label;
    } elseif (!empty($property['max_price']) && is_numeric($property['max_price'])) {
        return t('up_to') . ' ' . number_format(floatval($property['max_price']), 0, '.', ',') . ' ' . $currency_label;
    } elseif (!empty($property['avg_price']) && is_numeric($property['avg_price'])) {
        return number_format(floatval($property['avg_price']), 0, '.', ',') . ' ' . $currency_label . ' (' . t('average') . ')';
    }
    return t('price_not_specified');
}

// Format area for display
function formatArea($property)
{
    if (!empty($property['Area']) && is_numeric($property['Area'])) {
        return number_format(floatval($property['Area']), 0, '.', ',') . ' ' . t('sqm');
    } elseif (!empty($property['min_area']) && is_numeric($property['min_area']) && !empty($property['max_area']) && is_numeric($property['max_area'])) {
        return number_format(floatval($property['min_area']), 0, '.', ',') . ' - ' . number_format(floatval($property['max_area']), 0, '.', ',') . ' ' . t('sqm');
    } elseif (!empty($property['min_area']) && is_numeric($property['min_area'])) {
        return t('starts_from') . ' ' . number_format(floatval($property['min_area']), 0, '.', ',') . ' ' . t('sqm');
    } elseif (!empty($property['max_area']) && is_numeric($property['max_area'])) {
        return t('up_to') . ' ' . number_format(floatval($property['max_area']), 0, '.', ',') . ' ' . t('sqm');
    } elseif (!empty($property['avg_area']) && is_numeric($property['avg_area'])) {
        return number_format(floatval($property['avg_area']), 0, '.', ',') . ' ' . t('sqm') . ' (' . t('average') . ')';
    }
    return t('area_not_specified');
}

// Smart number formatter: trims unnecessary trailing zeros (e.g. 1.50 -> 1.5, 15.00 -> 15)
function formatSmartNumber($value, $maxDecimals = 2)
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_numeric($value)) {
        return htmlspecialchars((string)$value);
    }
    $float = floatval($value);
    // Format with fixed decimals then trim trailing zeros and dot
    $formatted = number_format($float, $maxDecimals, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted;
}

// Format payment terms
function formatPaymentTerms($property)
{
    $terms = [];
    
    if (!empty($property['down_payment_percentage']) && is_numeric($property['down_payment_percentage'])) {
        $terms[] = formatSmartNumber($property['down_payment_percentage'], 2) . '% ' . t('down_payment_percentage');
    }

    if (!empty($property['payment_years']) && is_numeric($property['payment_years'])) {
        // تحويل القيمة إلى عدد عشري
        $years = floatval($property['payment_years']);

        // تحديد عدد الخانات العشرية بناءً على وجود كسور
        $decimal_places = fmod($years, 1) !== 0.0 ? 1 : 0;

        // تنسيق الرقم مع إظهار الكسور إذا وجدت
        $years_formatted = formatSmartNumber($years, $decimal_places);

        // تحديد كلمة "سنة" أو "سنوات" بناءً على القيمة
        $year_word = (abs($years - 1.0) < 0.00001) ? 'سنة' : 'سنوات';

        $terms[] = $years_formatted . ' ' . $year_word;
    }
    
    return !empty($terms) ? implode(' + ', $terms) : t('undefined');
}

// Read flash messages using helper (robust short-lived flashes)
require_once __DIR__ . '/flash.php';
$flashes = get_flash_messages();

// --- Helper Functions Moved for Global Availability ---

// Helper: normalize phone numbers for tel: links
function normalize_phone_for_tel($raw) {
    $s = trim((string)$raw);
    if ($s === '') return '';
    $s = preg_replace('/[^0-9+]/', '', $s);
    if ($s === '') return '';
    if (strpos($s, '+') === 0) return $s;
    if (strpos($s, '00') === 0) return '+' . ltrim($s, '0');
    if (strpos($s, '0') === 0) {
        $without0 = substr($s, 1);
        return '+20' . $without0;
    }
    if (strpos($s, '20') === 0) return '+' . $s;
    return $s;
}

function translate_seller_type($val) {
    $v = $val === null ? '' : trim((string)$val);
    if ($v === '') return '';
    $map = [
        'مالك' => 'seller_type_owner', 'وسيط' => 'seller_type_broker', 'وكيل' => 'seller_type_agent',
        'بواب' => 'seller_type_doorman', 'شركة مطورة' => 'seller_type_developer_company',
        'شركة تشطيب' => 'seller_type_finishing_company', 'شركة تسويق' => 'seller_type_marketing_company',
        'Owner' => 'seller_type_owner', 'Broker' => 'seller_type_broker', 'Agent' => 'seller_type_agent',
        'Doorman' => 'seller_type_doorman', 'Developer Company' => 'seller_type_developer_company',
        'Finishing Company' => 'seller_type_finishing_company', 'Marketing Company' => 'seller_type_marketing_company',
    ];
    return isset($map[$v]) ? t($map[$v]) : $v;
}

function render_or_fallback($val, $is_seller_type = false) {
    if ($val !== null && trim((string)$val) !== '') {
        return $is_seller_type ? htmlspecialchars(translate_seller_type($val)) : htmlspecialchars(trim((string)$val));
    }
    return htmlspecialchars(t('undefined'));
}
// -----------------------------------------------------

// Page title
$page_title = sprintf(t('property_details_title'), htmlspecialchars($property['Title'] ?: $property['ProjectName'] ?: t('undefined')));

// Prepare WhatsApp share link (Arabic message, includes Google Maps link when coordinates exist)
$property_name = trim((string)($property['Title'] ?: $property['ProjectName'] ?: ''));
$property_address = '';
if (!empty($property['Location'])) {
    $property_address = $property['Location'];
} else {
    $parts = [];
    if (!empty($property['district'])) $parts[] = $property['district'];
    if (!empty($property['governorate'])) $parts[] = $property['governorate'];
    $property_address = implode('، ', $parts);
}
$map_link = '';
if (!empty($property['latitude']) && !empty($property['longitude'])) {
    $lat = $property['latitude'];
    $lng = $property['longitude'];
    $map_link = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
}
$wa_lines = [];
// Keep message minimal: only the site link is shared. If coordinates missing, share the property page URL as fallback.
$wa_lines[] = "مرحبًا، أشارك موقع هذا العقار:";
if ($map_link !== '') {
    $wa_lines[] = $map_link;
} else {
    // Build absolute URL to the current property page as a sensible fallback
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $request_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\\/');
    $property_page_url = $scheme . '://' . $host . $request_base . '/view_property.php?id=' . $property_id;
    $wa_lines[] = $property_page_url;
}
$wa_text = implode("\n", $wa_lines);
$whatsapp_link = 'https://wa.me/?text=' . rawurlencode($wa_text);
?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Broman Real Estate</title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="images/Logo%20Broman.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="css/premium_design.css">
    <link rel="stylesheet" href="lightbox.css?v=<?php echo filemtime('lightbox.css'); ?>">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <!-- Map provider: Google Maps -->
    <style>
        /* Ensure this page uses the site-wide Inter + Tajawal fonts (override any local rules) */
        html, body {
            font-family: 'Inter', 'Tajawal', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
    </style>
    <style>
        /* =====================================================
           PREMIUM PROPERTY VIEW - HERO + SIDEBAR LAYOUT
           ===================================================== */
        
        /* Main Layout Grid */
        .property-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            margin-bottom: 30px;
            align-items: start;
        }
        
        @media (max-width: 1024px) {
            .property-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Hero Image Gallery - Cinematic Blurred Background */
        .property-hero {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 380px;
        }
        
        /* Mobile: More square aspect ratio for better display */
        /* Mobile: Reset height/width */
        @media (max-width: 1024px) {
            .property-layout {
                grid-template-columns: 1fr;
            }
            .property-hero {
                height: auto;
                aspect-ratio: 4 / 3;
            }
        }
        
        #heroMainContainer {
            position: relative;
            flex: 1;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        /* Blurred Background Layer */
        .hero-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: blur(20px);
            opacity: 0.5;
            transform: scale(1.1);
            z-index: 1;
        }
        
        /* Main Content Layer - Full Image with Cinematic Background */
        .hero-main-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important; /* Full image visible */
            z-index: 2;
            cursor: pointer;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .hero-main-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain !important; /* Videos show complete without cropping */
            z-index: 2;
            cursor: pointer;
        }
        
        .hero-thumbnails {
            display: flex;
            gap: 6px;
            padding: 8px;
            background: #f0f0f0;
            overflow-x: auto;
        }
        
        .hero-thumb {
            width: 60px;
            height: 45px;
            border-radius: 6px;
            object-fit: cover;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            flex-shrink: 0;
        }
        
        .hero-thumb:hover, .hero-thumb.active {
            opacity: 1;
            border-color: #333;
        }
        
        /* Media thumbnails (video/audio) */
        .hero-media-thumb {
            position: relative;
            width: 60px;
            height: 45px;
            border-radius: 6px;
            cursor: pointer;
            opacity: 0.8;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            background: #222;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .hero-media-thumb:hover, .hero-media-thumb.active {
            opacity: 1;
            border-color: #333;
        }
        
        .hero-media-thumb i {
            color: #fff;
            font-size: 1.5rem;
        }
        
        .hero-media-thumb.video-thumb {
            background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
        }
        
        .hero-media-thumb.audio-thumb {
            background: linear-gradient(135deg, #3a1e5f 0%, #1b0d2a 100%);
        }
        
        /* Audio main container */
        
        .hero-main-audio {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #3a1e5f 0%, #1b0d2a 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .hero-main-audio i {
            font-size: 4rem;
            color: #fff;
            margin-bottom: 20px;
        }
        
        .hero-main-audio audio {
            width: 80%;
            max-width: 400px;
        }
        
        .hero-media-count {
            position: absolute;
            bottom: 80px;
            right: 16px;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            gap: 12px;
            direction: ltr; /* Force LTR for consistent Icon-Number pairing */
            z-index: 5;
        }
        
        .media-count-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }
        
        .hero-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(18, 18, 18, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: #fff;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            z-index: 10;
            letter-spacing: 0.5px;
        }

        .hero-badge i {
            font-size: 1rem;
        }
        
        .hero-badge.status-available i { color: #2ecc71; } /* Emerald Green */
        .hero-badge.status-sold i { color: #e74c3c; } /* Soft Red */
        .hero-badge.status-rented i { color: #3498db; } /* Bright Blue */
        .hero-badge.status-pending i { color: #f1c40f; } /* Yellow */
        
        .hero-image-count {
            position: absolute;
            bottom: 80px;
            right: 16px;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            backdrop-filter: blur(4px);
        }
        
        /* Sidebar Card */
        .property-sidebar {
            position: sticky;
            top: 80px;
        }
        
        .sidebar-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.06);
            overflow: hidden;
            height: 380px; /* Match property-hero height */
            overflow-y: auto; /* Allow scrolling if content exceeds height */
        }

        /* Customize scrollbar for sidebar card */
        .sidebar-card::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-card::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .sidebar-card::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }
        .sidebar-card::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }

        @media (max-width: 1024px) {
            .sidebar-card {
                height: auto;
                overflow-y: visible;
            }
        }
        
        .sidebar-header {
            background: #fafafa;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .sidebar-price {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .sidebar-price-note {
            font-size: 0.85rem;
            color: #666;
        }
        
        .sidebar-body {
            padding: 10px;
        }
        
        .sidebar-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .sidebar-stat:last-child {
            border-bottom: none;
        }
        
        .sidebar-stat-icon {
            width: 36px;
            height: 36px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1rem;
        }
        
        .sidebar-stat-content {
            flex: 1;
        }
        
        .sidebar-stat-label {
            font-size: 0.8rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sidebar-stat-value {
            font-size: 1rem;
            font-weight: 600;
            color: #020d24;
        }
        
        .sidebar-actions {
            padding: 16px 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .sidebar-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }
        
        .sidebar-btn-primary {
            background: #333;
            color: #fff;
        }
        
        .sidebar-btn-primary:hover {
            background: #444;
        }
        
        .sidebar-btn-whatsapp {
            background: #25D366;
            color: #fff;
        }
        
        .sidebar-btn-whatsapp:hover {
            background: #20bd5a;
            transform: translateY(-2px);
        }
        
        .sidebar-btn-outline {
            background: transparent;
            border: 2px solid #e5e7eb;
            color: #020d24;
        }
        
        .sidebar-btn-outline:hover {
            border-color: #b08a0e;
            color: #b08a0e;
        }
        
        /* Compact Info Cards Grid */
        .info-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        
        .info-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.06);
            overflow: hidden;
        }
        
        .info-card-header {
            background: linear-gradient(135deg, #020d24 0%, #0a1a3a 100%);
            padding: 12px 16px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card-header i {
            color: #b08a0e;
        }
        
        .info-card-body {
            padding: 16px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #020d24;
            font-weight: 500;
            font-size: 0.9rem;
            text-align: left;
        }
        
        .info-value a {
            color: #b08a0e;
            text-decoration: none;
        }
        
        .info-value a:hover {
            text-decoration: underline;
        }
        
        /* Description Card */
        .description-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.06);
        }
        
        .description-card h3 {
            color: #020d24;
            font-size: 1.1rem;
            margin: 0 0 12px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #b08a0e;
            display: inline-block;
        }
        
        .description-card p {
            color: #555;
            line-height: 1.7;
            margin: 0;
        }
        
        /* Sellers Grid */
        .sellers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        
        .seller-compact-card {
            background: linear-gradient(135deg, rgba(176, 138, 14, 0.05) 0%, rgba(176, 138, 14, 0.02) 100%);
            border: 1px solid rgba(176, 138, 14, 0.15);
            border-radius: 12px;
            padding: 16px;
        }
        
        .seller-compact-card .seller-type {
            color: #b08a0e;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .seller-compact-card .seller-name {
            color: #020d24;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        
        .seller-compact-card .seller-phone a {
            color: #b08a0e;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        /* Map Container */
        .map-container {
            border-radius: 12px;
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .map-container #property-map {
            height: 250px !important;
            border-radius: 0;
        }
        
        /* Media Section (compact) */
        .media-section {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .media-section h3 {
            color: #020d24;
            font-size: 1rem;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .media-thumbnails {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }
        
        .media-thumb {
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
        }
        
        .media-thumb img, .media-thumb video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Enhanced Property Details Styling */
        .property-details {
            margin-bottom: 40px;
        }
        
        .property-section {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .property-section h2 {
            background: #f5f5f5;
            padding: 14px 20px;
            margin: 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 1.15rem;
            color: #333;
            font-weight: 600;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            padding: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item.full-width {
            grid-column: 1 / -1;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        
        .detail-value {
            color: #333;
            font-size: 1.05rem;
            word-break: break-word;
        }
        /* Seller list styling */
        .seller-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .seller-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 12px;
            background: #fafafa;
        }
        .seller-card .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }
        
        .detail-value a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .detail-value a:hover {
            text-decoration: underline;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #020d24;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .actions .btn-primary {
            background: linear-gradient(135deg, #b08a0e 0%, #8a6b08 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(176, 138, 14, 0.25);
        }
        
        .actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(176, 138, 14, 0.35);
        }
        
        .actions .btn-secondary {
            background: #f3f4f6;
            color: #020d24;
            border: 1px solid rgba(0,0,0,0.08);
        }
        
        .actions .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }
        
        .actions .btn i {
            font-size: 0.9rem;
        }
        
        .property-description {
            padding: 20px;
            line-height: 1.6;
        }
        
        .property-description p {
            margin: 0;
            color: #444;
        }

        /* WhatsApp share button */
        .btn-whatsapp {
            background-color: #25D366;
            color: #fff;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .btn-whatsapp:hover, .btn-whatsapp:focus {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(37,211,102,0.18);
            text-decoration: none;
            color: #fff;
        }

        .btn-whatsapp .fab.fa-whatsapp {
            font-size: 1.1rem;
        }

        /* Tooltip using title attribute is sufficient for most devices; enhance with visually hidden text */
        .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        
        /* Media Gallery Styles */
        .media-gallery {
            padding: 20px;
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .property-image-clickable {
            width: 100%;
            height: 150px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .property-image-clickable:hover {
            transform: scale(1.05);
        }
        
        .image-primary-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: linear-gradient(135deg, #b08a0e 0%, #8a6b08 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(176, 138, 14, 0.3);
        }
        
        .audio-notes {
            padding: 20px;
        }
        
        .audio-item {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .audio-item audio {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .audio-info {
            font-size: 0.9rem;
            color: #666;
        }
        
        .audio-info div {
            margin-bottom: 5px;
        }
        
        /* Video Gallery Styles */
        .video-gallery {
            padding: 20px;
        }
        
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .video-item {
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .property-video {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .video-info {
            padding: 15px;
        }
        
        .video-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            word-break: break-word;
        }
        
        .video-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .video-details span {
            display: block;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
    <?php if (get_direction() === 'ltr'): ?>
    <style>
    /* LTR overrides for Property Details page when English is selected */
    /* Force left-to-right reading direction and left alignment for labels/values */
    .property-details,
    .property-section,
    .detail-grid,
    .detail-item,
    .page-header,
    .property-description,
    .media-gallery {
        direction: ltr !important;
        text-align: left !important;
    }

    .page-header { 
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .page-header h1 {
        text-align: left !important;
        margin-right: 0;
    }

    .page-header .actions {
        margin-left: auto; /* keep actions to the right in LTR */
        justify-content: flex-end;
    }

    /* Ensure labels and values align left */
    .detail-label, .detail-value {
        text-align: left !important;
    }

    /* Move the primary badge to the left for LTR */
    .image-primary-badge {
        left: 8px !important;
        right: auto !important;
    }

    /* Clickable phone should present LTR/left alignment */
    .detail-item.detail-phone .detail-value a {
        display: inline-block;
        width: 100%;
        text-align: left !important;
        direction: ltr !important;
    }

    @media (max-width: 768px) {
        .page-header {
            align-items: flex-start;
        }
        .page-header .actions {
            justify-content: flex-start;
            margin-left: 0;
        }
    }
    
    /* Mobile Safety Margins - prevent border clipping from overflow-x: hidden */
    @media (max-width: 992px) {
        .container {
            padding-left: 15px !important;
            padding-right: 15px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .property-layout {
            width: 100% !important;
            margin: 0 !important;
        }
        
        .property-hero,
        .sidebar-card,
        .info-card {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
    }
    </style>
    <?php endif; ?>
    <script src="session_timeout.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <?php
        // Display success message from session or short-lived flash (render once then remove)
        // Display any flash messages collected; clear the shown flash immediately so it
        // does not appear on subsequent pages. Also clear legacy session key if present.
        if (!empty($flashes) && is_array($flashes)) {
            // show the first flash only to keep UI similar to previous behavior
            $first_id = array_key_first($flashes);
            $first = $flashes[$first_id];
            $flash = htmlspecialchars((string)$first);
            echo "<div id='flash-message' class='message message-success' role='status' aria-live='polite' tabindex='-1'>" . $flash . "</div>";
            // Clear the flash we just displayed so it won't show on later pages
            if (function_exists('clear_flash')) {
                clear_flash($first_id);
            }
            // Also remove any legacy session success key to avoid duplicate messages
            if (!empty($_SESSION) && isset($_SESSION['success_message'])) {
                unset($_SESSION['success_message']);
            }
        } elseif (!empty($_SESSION) && isset($_SESSION['success_message'])) {
            // Fallback: if for some reason a legacy success_message exists, show it once and then clear it
            $flash = htmlspecialchars((string)$_SESSION['success_message']);
            echo "<div id='flash-message' class='message message-success' role='status' aria-live='polite' tabindex='-1'>" . $flash . "</div>";
            unset($_SESSION['success_message']);
        }
        // If the add flow used an alternate mechanism (query param) show success message as a reliable fallback
        elseif (isset($_GET['added']) && $_GET['added'] == '1') {
            // Use the localized string if available
            $flash = htmlspecialchars(t('property_added_success'));
            echo "<div id='flash-message' class='message message-success' role='status' aria-live='polite' tabindex='-1'>" . $flash . "</div>";
        }
        ?>
        <div class="page-header">
            <h1>
                <?php if(!empty($property['friendly_id'])): ?>
                    <span style="color: #666; font-size: 0.8em;">#<?php echo $property['friendly_id']; ?></span> - 
                <?php endif; ?>
                <?php echo $page_title; ?>
            </h1>
            <div class="actions">
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager', 'rental_admin'])): ?>
                <a href="edit_property.php?id=<?php echo $property_id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> <?php echo t('edit'); ?></a>
                <?php endif; ?>
                <!-- WhatsApp share button -->
                <a href="<?php echo htmlspecialchars($whatsapp_link); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp" title="مشاركة عبر واتساب" aria-label="مشاركة عبر واتساب">
                    <i class="fab fa-whatsapp"></i>
                    <span class="sr-only">مشاركة عبر واتساب</span>
                </a>
                <?php
                // Smart Back Button Logic
                $back_link = 'javascript:history.back()'; // Default to browser history (preserves filters/scroll)
                
                // If referer is present and indicates we came from an Add or Edit form, force link to Listings
                // This prevents "Back" from returning to a form submission or success page
                if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
                    $ref = $_SERVER['HTTP_REFERER'];
                    // Check for common add/edit script names
                    if (strpos($ref, 'add_') !== false || strpos($ref, 'edit_') !== false) {
                        $back_link = 'index_properties.php';
                    }
                } else {
                    // unexpected direct access or no referer, default safe listing link could be used,
                    // but usually history.back is okay or index.
                    // If no history, javascript:history.back() does nothing. 
                    // Let's default to index if strictly no referer? 
                    // Ideally check if history length > 1 but that's JS. 
                    // For now, let's keep default history.back() unless explicitly from add/edit.
                    // actually, better default might be index if we want to be safe?
                    // User request: "Back to listing... NOT add/edit".
                }
                ?>
                <a href="<?php echo $back_link; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo t('back'); ?></a>
            </div>
        </div>

        <!-- Hidden Gallery for Lightbox Navigation (Contains ALL property images) -->
        <div id="lightbox-gallery-source" style="display:none;">
            <?php foreach ($property_images as $img): ?>
                <img src="<?php echo htmlspecialchars($img['file_path']); ?>" 
                     data-src="<?php echo htmlspecialchars($img['file_path']); ?>"
                     class="property-image-clickable" 
                     alt="Property Image">
            <?php endforeach; ?>
        </div>

        <!-- NEW PREMIUM LAYOUT: Hero + Sidebar -->
        <div class="property-layout">
            <!-- Hero Image Gallery -->
            <div class="property-hero">
                <?php 
                // Build unified media array
                $all_media = [];
                
                // Add images
                foreach ($property_images as $index => $img) {
                    $all_media[] = [
                        'type' => 'image',
                        'path' => $img['file_path'],
                        'is_primary' => $img['is_primary'] ?? false
                    ];
                }
                
                // Add videos
                if (!empty($property_videos)) {
                    foreach ($property_videos as $video) {
                        $all_media[] = [
                            'type' => 'video',
                            'path' => $video['VideoPath'],
                            'name' => $video['VideoName'] ?? ''
                        ];
                    }
                }
                
                // Add audio notes
                if (!empty($property_audio_notes)) {
                    foreach ($property_audio_notes as $audio) {
                        $all_media[] = [
                            'type' => 'audio',
                            'path' => $audio['file_path']
                        ];
                    }
                }
                
                // Get primary/first media
                $primary_media = null;
                foreach ($all_media as $media) {
                    if ($media['type'] === 'image' && !empty($media['is_primary'])) {
                        $primary_media = $media;
                        break;
                    }
                }
                if (!$primary_media && !empty($all_media)) {
                    $primary_media = $all_media[0];
                }
                
                $total_media = count($all_media);
                $image_count = count($property_images);
                $video_count = count($property_videos ?? []);
                $audio_count = count($property_audio_notes ?? []);
                ?>
                
                <!-- Main Display Area - Cinematic Two-Layer System -->
                <div id="heroMainContainer">
                    <?php if ($primary_media): ?>
                        <?php if ($primary_media['type'] === 'image'): ?>
                            <!-- Blurred Background Layer -->
                            <img src="<?php echo htmlspecialchars($primary_media['path']); ?>" 
                                 class="hero-backdrop" 
                                 alt="">
                            <!-- Main Content Layer -->
                            <img src="<?php echo htmlspecialchars($primary_media['path']); ?>" 
                                 alt="<?php echo htmlspecialchars($property['Title'] ?? $property['ProjectName'] ?? 'Property'); ?>" 
                                 class="hero-main-image" 
                                 style="cursor: pointer;"
                                 onclick="console.log('Clicked image:', this.src); if(window.propertyLightbox) { console.log('Opening lightbox...'); window.propertyLightbox.openBySrc(this.src); } else { console.error('window.propertyLightbox is undefined!'); }"
                                 id="heroMainMedia">
                        <?php elseif ($primary_media['type'] === 'video'): ?>
                            <!-- Blurred Background Video (Static Freeze Frame) -->
                            <video class="hero-backdrop" muted preload="metadata">
                                <source src="<?php echo htmlspecialchars($primary_media['path']); ?>#t=0.1" type="video/mp4">
                            </video>
                            <!-- Main Content Video -->
                            <video controls class="hero-main-video" id="heroMainMedia">
                                <source src="<?php echo htmlspecialchars($primary_media['path']); ?>" type="video/mp4">
                            </video>
                        <?php elseif ($primary_media['type'] === 'audio'): ?>
                            <div class="hero-main-audio" id="heroMainMedia">
                                <i class="fas fa-microphone"></i>
                                <audio controls>
                                    <source src="<?php echo htmlspecialchars($primary_media['path']); ?>" type="audio/mpeg">
                                </audio>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="hero-no-image" style="min-height:300px; background:#222; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#666; padding:40px;">
                            <i class="fas fa-image fa-4x" style="margin-bottom:16px;"></i>
                            <span style="font-size:0.95rem;">لا توجد وسائط لهذا العقار</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($property['Status'])): 
                    // Determine Status Icon and Class
                    $s_lower = strtolower($property['Status']);
                    $s_icon = 'fa-info-circle';
                    $s_class = '';
                    
                    if ($s_lower === 'available' || $s_lower === 'متاح') {
                        $s_icon = 'fa-check-circle';
                        $s_class = 'status-available';
                    } elseif ($s_lower === 'sold' || $s_lower === 'مباع') {
                        $s_icon = 'fa-handshake';
                        $s_class = 'status-sold';
                    } elseif ($s_lower === 'rented' || $s_lower === 'مؤجر') {
                        $s_icon = 'fa-key';
                        $s_class = 'status-rented';
                    } elseif ($s_lower === 'pending') {
                        $s_icon = 'fa-clock';
                        $s_class = 'status-pending';
                    }
                ?>
                    <div class="hero-badge <?php echo $s_class; ?>">
                        <i class="fas <?php echo $s_icon; ?>"></i>
                        <?php echo t($property['Status']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Media Count Badge -->
                <?php if ($total_media > 1): ?>
                    <div class="hero-media-count">
                        <?php if ($image_count > 0): ?>
                            <span class="media-count-item"><i class="fas fa-images"></i> <?php echo $image_count; ?></span>
                        <?php endif; ?>
                        <?php if ($video_count > 0): ?>
                            <span class="media-count-item"><i class="fas fa-video"></i> <?php echo $video_count; ?></span>
                        <?php endif; ?>
                        <?php if ($audio_count > 0): ?>
                            <span class="media-count-item"><i class="fas fa-microphone"></i> <?php echo $audio_count; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Unified Media Thumbnails -->
                <?php if ($total_media > 1): ?>
                    <div class="hero-thumbnails">
                        <?php foreach ($all_media as $index => $media): ?>
                            <?php if ($media['type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($media['path']); ?>" 
                                     alt="صورة <?php echo $index + 1; ?>" 
                                     class="hero-thumb <?php echo ($primary_media && $media['path'] === $primary_media['path']) ? 'active' : ''; ?>"
                                     onclick="showMedia(this, 'image', '<?php echo htmlspecialchars($media['path']); ?>')">
                            <?php elseif ($media['type'] === 'video'): ?>
                                <video src="<?php echo htmlspecialchars($media['path']); ?>#t=0.5" 
                                       muted 
                                       preload="metadata"
                                       class="hero-thumb"
                                       style="object-fit: cover;"
                                       onclick="showMedia(this, 'video', '<?php echo htmlspecialchars($media['path']); ?>')"></video>
                            <?php elseif ($media['type'] === 'audio'): ?>
                                <div class="hero-media-thumb audio-thumb" 
                                     onclick="showMedia(this, 'audio', '<?php echo htmlspecialchars($media['path']); ?>')">
                                    <i class="fas fa-microphone"></i>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar with Key Info -->
            <div class="property-sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <div class="sidebar-price"><?php echo formatPrice($property); ?></div>
                        <?php if (!empty($property['listing_type']) && $property['listing_type'] === 'Rental'): ?>
                            <div class="sidebar-price-note"><?php echo t('rental_property'); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sidebar-body">
                        <!-- Area -->
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-icon"><i class="fas fa-ruler-combined"></i></div>
                            <div class="sidebar-stat-content">
                                <div class="sidebar-stat-label"><?php echo t('area'); ?></div>
                                <div class="sidebar-stat-value"><?php echo formatArea($property); ?></div>
                            </div>
                        </div>
                        
                        <!-- Rooms -->
                        <?php if (!empty($property['Bedrooms'])): ?>
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-icon"><i class="fas fa-bed"></i></div>
                            <div class="sidebar-stat-content">
                                <div class="sidebar-stat-label"><?php echo t('rooms'); ?></div>
                                <div class="sidebar-stat-value"><?php echo htmlspecialchars($property['Bedrooms']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Bathrooms -->
                        <?php if (!empty($property['Bathrooms'])): ?>
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-icon"><i class="fas fa-bath"></i></div>
                            <div class="sidebar-stat-content">
                                <div class="sidebar-stat-label"><?php echo t('bathrooms'); ?></div>
                                <div class="sidebar-stat-value"><?php echo htmlspecialchars($property['Bathrooms']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Location -->
                        <?php 
                        $location_display = '';
                        if (!empty($property['district'])) $location_display = $property['district'];
                        if (!empty($property['governorate'])) {
                            $location_display .= ($location_display ? '، ' : '') . $property['governorate'];
                        }
                        if ($location_display):
                        ?>
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="sidebar-stat-content">
                                <div class="sidebar-stat-label"><?php echo t('location'); ?></div>
                                <div class="sidebar-stat-value"><?php echo htmlspecialchars($location_display); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Finishing Type -->
                        <?php if (!empty($property['FinishingTypeName'])): ?>
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-icon"><i class="fas fa-paint-roller"></i></div>
                            <div class="sidebar-stat-content">
                                <div class="sidebar-stat-label"><?php echo t('finishing_type'); ?></div>
                                <div class="sidebar-stat-value">
                                    <?php 
                                    if (get_lang_code() === 'ar' && !empty($property['FinishingTypeName_ar'])) {
                                        echo htmlspecialchars($property['FinishingTypeName_ar']);
                                    } else {
                                        echo htmlspecialchars($property['FinishingTypeName']);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Owner Information (Sidebar) -->
                        <?php
                        // Prepare seller list for sidebar
                        $sb_render_sellers = $property_sellers ?? [];
                        // Check logic matches legacy seller check
                        $sb_legacy_seller_present = (!empty($property['seller_type']) || !empty($property['seller_name']) || !empty($property['seller_phone']) || !empty($property['owner_name']) || !empty($property['owner_phone']));
                        
                        if (empty($sb_render_sellers) && $sb_legacy_seller_present) {
                            $sb_render_sellers[] = [
                                'seller_type' => $property['seller_type'] ?? null,
                                'seller_name' => $property['seller_name'] ?? ($property['owner_name'] ?? null),
                                'seller_phone' => $property['seller_phone'] ?? ($property['owner_phone'] ?? null),
                            ];
                        }
                        
                        if (!empty($sb_render_sellers)):
                            foreach ($sb_render_sellers as $sb_seller):
                                // Determine display name
                                $sb_name = !empty($sb_seller['seller_name']) ? $sb_seller['seller_name'] : (!empty($sb_seller['seller_type']) ? translate_seller_type($sb_seller['seller_type']) : t('owner'));
                                $sb_phone = $sb_seller['seller_phone'] ?? '';
                                $sb_type_label = !empty($sb_seller['seller_type']) ? translate_seller_type($sb_seller['seller_type']) : '';
                        ?>
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="sidebar-stat-content">
                                <div class="sidebar-stat-label"><?php echo t('owner_information'); ?></div>
                                <div class="sidebar-stat-value">
                                    <?php if ($sb_type_label && $sb_type_label !== $sb_name): ?>
                                        <div style="font-size: 0.8rem; color: #b08a0e; margin-bottom: 2px;"><?php echo htmlspecialchars($sb_type_label); ?></div>
                                    <?php endif; ?>
                                    <div style="font-weight:600; font-size: 0.95rem;"><?php echo htmlspecialchars($sb_name); ?></div>
                                    <?php if (!empty($sb_phone)): 
                                        $sb_tel = normalize_phone_for_tel($sb_phone);
                                    ?>
                                    <a href="tel:<?php echo $sb_tel; ?>" style="color: var(--primary-color); direction: ltr; display: inline-block; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($sb_phone); ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </div>
                </div>
            </div>
        </div>





        <!-- Property Documents Section (user-facing PDFs) -->
        <?php if (!empty($property_documents)): ?>
        <div class="property-section">
            <h2>📄 Documents</h2>
            <div class="media-gallery">
                <div class="documents-list" style="padding:20px;">
                    <?php foreach ($property_documents as $doc): ?>
                        <div class="document-item" style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0f0f0;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-file-pdf fa-2x" style="color:#d9534f"></i>
                                <div>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($doc['file_name']); ?></div>
                                    <div style="font-size:0.9rem;color:#666;"><?php echo formatFileSize($doc['file_size']); ?> &middot; <?php echo date('Y-m-d H:i', strtotime($doc['upload_date'])); ?></div>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="view_document.php?id=<?php echo (int)$doc['document_id']; ?>" target="_blank" class="btn btn-primary" rel="noopener"><?php echo t('view'); ?></a>
                                <a href="serve_document.php?id=<?php echo (int)$doc['document_id']; ?>&download=1" class="btn btn-secondary"><?php echo t('download'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="property-details">
            <div class="property-section">
                <h2><?php echo t('basic_information'); ?></h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('property_id'); ?>:</span>
                        <span class="detail-value">#<?php echo htmlspecialchars($property['friendly_id'] ?? $property['PropertyID']); ?></span>
                    </div>
                    <?php if (!empty($property['Title'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('title'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['Title']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['ProjectName'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('project_name'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['ProjectName']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($linked_types)): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('property_type'); ?>:</span>
                        <span class="detail-value"><?php echo $types_display; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['Status'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('status'); ?>:</span>
                        <span class="detail-value"><?php echo t($property['Status']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('location_information'); ?></h2>
                <div class="detail-grid">
                    <?php if (!empty($property['governorate'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('governorate'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['governorate']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['district'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('district'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['district']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['Location'])): ?>
                    <div class="detail-item full-width">
                        <span class="detail-label"><?php echo t('detailed_address'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['Location']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Static Map Display -->
                    <?php if (!empty($property['latitude']) && !empty($property['longitude'])): ?>
                    <div class="detail-item full-width">
                        <span class="detail-label"><?php echo t('location_on_map'); ?>:</span>
                        <div id="property-map" style="height: 300px; width: 100%; border: 1px solid #ddd; border-radius: 4px; margin-top: 10px;"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('pricing_and_area'); ?></h2>
                <div class="detail-grid">
                    <?php 
                    $currency_code = $property['currency'] ?? 'EGP';
                    $currency_label = t($currency_code);
                    ?>
                    <?php if (!empty($property['price_cash'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('price_cash'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['price_cash'], 0, '.', ','); ?> <?php echo $currency_label; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['price_installments'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('price_installments'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['price_installments'], 0, '.', ','); ?> <?php echo $currency_label; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['monthly_rent'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('monthly_rent'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['monthly_rent'], 0, '.', ','); ?> <?php echo $currency_label; ?> / <?php echo t('per_month'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['daily_rent'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('daily_rent'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['daily_rent'], 0, '.', ','); ?> <?php echo $currency_label; ?> / <?php echo t('per_day'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['yearly_rent'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('yearly_rent'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['yearly_rent'], 0, '.', ','); ?> <?php echo $currency_label; ?> / <?php echo t('per_year'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['meter_price_cash'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('meter_price_cash'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['meter_price_cash'], 0, '.', ','); ?> <?php echo $currency_label; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['meter_price_installments'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('meter_price_installments'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['meter_price_installments'], 0, '.', ','); ?> <?php echo $currency_label; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($property['Area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('unit_area_sqm'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['Area'], 2); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['min_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('min_area'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['min_area'], 2); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['max_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('max_area'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['max_area'], 2); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['avg_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('avg_area_sqm'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['avg_area'], 2); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($property['compound_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('compound_area_faddan'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['compound_area'], 2); ?> <?php echo t('faddan'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['net_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('net_area'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['net_area'], 2); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['loading_percentage'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('loading_percentage'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['loading_percentage'], 2); ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('unit_details'); ?></h2>
                <div class="detail-grid">
                    <?php if (!empty($property['UnitDesignName'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('unit_design'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['UnitDesignName']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['Bedrooms'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('rooms'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['Bedrooms']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['Bathrooms'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('bathrooms'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['Bathrooms']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['balconies'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('balconies'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['balconies']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['elevators'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('elevators'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['elevators']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['FinishingTypeName'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('finishing_type'); ?>:</span>
                        <span class="detail-value">
                            <?php 
                            if (get_lang_code() === 'ar' && !empty($property['FinishingTypeName_ar'])) {
                                echo htmlspecialchars($property['FinishingTypeName_ar']);
                            } else {
                                echo htmlspecialchars($property['FinishingTypeName']);
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['FloorsCount'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('floors_count'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['FloorsCount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                    // Show raw year_built value whenever it is non-empty (allow numbers or text)
                    $year_raw = $property['year_built'] ?? null;
                    if ($year_raw !== null && trim((string)$year_raw) !== ''):
                    ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('year_built'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(trim((string)$year_raw)); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['unit_floor'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('unit_floor'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['unit_floor']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('payment_and_delivery'); ?></h2>
                <div class="detail-grid">
                    <?php if (!empty($property['delivery_date'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('delivery_date'); ?>:</span>
                        <span class="detail-value"><?php echo date("Y-m-d", strtotime($property['delivery_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['down_payment_percentage'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('down_payment_percentage'); ?>:</span>
                        <span class="detail-value"><?php echo formatSmartNumber($property['down_payment_percentage'], 2); ?>%</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['payment_years'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('payment_years'); ?>:</span>
                        <?php $years_display = formatSmartNumber($property['payment_years'], (fmod(floatval($property['payment_years']),1) !== 0.0) ? 1 : 0); ?>
                        <span class="detail-value"><?php echo $years_display . ' ' . (get_lang_code() === 'ar' ? t('year') : t('year')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['down_payment_percentage']) || !empty($property['payment_years'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('payment_terms'); ?>:</span>
                        <span class="detail-value"><?php echo formatPaymentTerms($property); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('company_information'); ?></h2>
                <div class="detail-grid">
                    <?php if (!empty($property['company_name'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('company'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['company_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php 
            // normalize_phone_for_tel moved to top of file
            // Check if there is any sales information to display before showing the section
            if (
                !empty($property['sales_manager_name']) || 
                !empty($property['sales_manager_phone']) || 
                !empty($property['salesperson_name']) || 
                !empty($property['salesperson_phone'])
            ): 
            ?>
            <div class="property-section">
                <h2><?php echo t('sales_information'); ?></h2>
                <div class="detail-grid">
                    <?php
                    // Prepare clickable phones for sales manager and salesperson (preserve RTL handling like seller phone)
                    $sales_manager_phone_html = null;
                    if (!empty($property['sales_manager_phone'])) {
                        $sm_raw = trim($property['sales_manager_phone']);
                        $sm_display = htmlspecialchars($sm_raw);
                        $sm_tel = normalize_phone_for_tel($sm_raw);
                        if (function_exists('get_direction') && get_direction() === 'rtl') {
                            $sales_manager_phone_html = '<a href="tel:' . $sm_tel . '" style="display:inline-block;width:100%;text-align:right;direction:rtl;">' . $sm_display . '</a>';
                        } else {
                            $sales_manager_phone_html = '<a href="tel:' . $sm_tel . '">' . $sm_display . '</a>';
                        }
                    }

                    $salesperson_phone_html = null;
                    if (!empty($property['salesperson_phone'])) {
                        $sp_raw = trim($property['salesperson_phone']);
                        $sp_display = htmlspecialchars($sp_raw);
                        $sp_tel = normalize_phone_for_tel($sp_raw);
                        if (function_exists('get_direction') && get_direction() === 'rtl') {
                            $salesperson_phone_html = '<a href="tel:' . $sp_tel . '" style="display:inline-block;width:100%;text-align:right;direction:rtl;">' . $sp_display . '</a>';
                        } else {
                            $salesperson_phone_html = '<a href="tel:' . $sp_tel . '">' . $sp_display . '</a>';
                        }
                    }
                    ?>

                    <?php if (!empty($property['sales_manager_name'])): ?>
                    <div class="detail-item detail-name">
                        <span class="detail-label"><?php echo t('sales_manager_name_label'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['sales_manager_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($sales_manager_phone_html !== null): ?>
                    <div class="detail-item detail-phone">
                        <span class="detail-label"><?php echo t('sales_manager_phone_label'); ?>:</span>
                        <span class="detail-value"><?php echo $sales_manager_phone_html; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($property['salesperson_name'])): ?>
                    <div class="detail-item detail-name">
                        <span class="detail-label"><?php echo t('salesperson_name_label'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['salesperson_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($salesperson_phone_html !== null): ?>
                    <div class="detail-item detail-phone">
                        <span class="detail-label"><?php echo t('salesperson_phone_label'); ?>:</span>
                        <span class="detail-value"><?php echo $salesperson_phone_html; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; // End of the main check for sales information ?>

            <?php
                $has_multi_sellers = !empty($property_sellers);
                $legacy_seller_present = (!empty($property['seller_type']) || !empty($property['seller_name']) || !empty($property['seller_phone']) || !empty($property['owner_name']) || !empty($property['owner_phone']));
                if ($has_multi_sellers || $legacy_seller_present):
            ?>
            <div class="property-section">
                <h2><?php echo t('owner_information'); ?></h2>
                <div class="seller-list">
                <?php
                // translate_seller_type, render_or_fallback moved to top of file

                $render_sellers = $property_sellers;
                if (empty($render_sellers) && $legacy_seller_present) {
                    $render_sellers[] = [
                        'seller_type' => $property['seller_type'] ?? null,
                        'seller_name' => $property['seller_name'] ?? ($property['owner_name'] ?? null),
                        'seller_phone' => $property['seller_phone'] ?? ($property['owner_phone'] ?? null),
                    ];
                }

                foreach ($render_sellers as $idx => $seller_row):
                    $seller_phone_html = null;
                    $seller_phone_raw = $seller_row['seller_phone'] ?? '';
                    if (!empty($seller_phone_raw)) {
                        $phone_display = htmlspecialchars(trim($seller_phone_raw));
                        $tel = normalize_phone_for_tel($seller_phone_raw);
                        if (function_exists('get_direction') && get_direction() === 'rtl') {
                            $seller_phone_html = '<a href="tel:' . $tel . '" style="display:inline-block;width:100%;text-align:right;direction:rtl;">' . $phone_display . '</a>';
                        } else {
                            $seller_phone_html = '<a href="tel:' . $tel . '">' . $phone_display . '</a>';
                        }
                    }
                ?>
                    <div class="seller-card">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label"><?php echo t('seller_type'); ?><?php echo count($render_sellers) > 1 ? ' #' . ($idx + 1) : ''; ?>:</span>
                                <span class="detail-value"><?php echo render_or_fallback($seller_row['seller_type'] ?? null, true); ?></span>
                            </div>
                            <div class="detail-item detail-name">
                                <span class="detail-label"><?php echo t('name'); ?>:</span>
                                <span class="detail-value"><?php echo render_or_fallback($seller_row['seller_name'] ?? null); ?></span>
                            </div>
                            <div class="detail-item detail-phone">
                                <span class="detail-label"><?php echo t('phone'); ?>:</span>
                                <span class="detail-value"><?php echo $seller_phone_html !== null ? $seller_phone_html : htmlspecialchars(t('undefined')); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
<div class="property-section">
    <h2><?php echo t('additional_information'); ?></h2>
    <div class="detail-grid">
        <?php if (!empty($property['EmployeeName'])): ?>
        <div class="detail-item">
            <span class="detail-label"><?php echo t('added_by_employee'); ?>:</span>
            <span class="detail-value"><?php echo htmlspecialchars($property['EmployeeName']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($property['DateAdded'])): ?>
        <div class="detail-item">
            <span class="detail-label"><?php echo t('date_added'); ?>:</span>
            <span class="detail-value"><?php echo date("Y-m-d", strtotime($property['DateAdded'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

            <?php if (!empty($property['Description'])): ?>
            <div class="property-section">
                <h2><?php echo t('description'); ?></h2>
                <div class="property-description">
                    <p><?php echo nl2br(htmlspecialchars($property['Description'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($property_attachments) && can_manage_properties()): ?>
            <div class="property-section">
                <h2>📎 <?php echo t('attachments'); ?></h2>
                <div class="media-gallery">
                    <div class="attachments-grid images-grid" style="margin-top:10px;">
                        <?php foreach ($property_attachments as $att): 
                            $ext = strtolower(pathinfo($att['FileName'], PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg','jpeg','png','webp','gif']);
                            $iconClass = 'fa-file';
                            if (in_array($ext, ['pdf'])) $iconClass = 'fa-file-pdf';
                            if (in_array($ext, ['doc','docx'])) $iconClass = 'fa-file-word';
                            if (in_array($ext, ['xls','xlsx','csv'])) $iconClass = 'fa-file-excel';
                            $secureUrl = 'serve_attachment.php?id=' . (int)$att['AttachmentID'];
                        ?>
                        <div class="image-item attachment-item" id="att-<?php echo (int)$att['AttachmentID']; ?>" style="padding:14px;display:flex;flex-direction:column;align-items:center;">
                            <div style="width:100%;height:120px;display:flex;align-items:center;justify-content:center;">
                                <?php if ($isImage): ?>
                                    <a href="<?php echo htmlspecialchars($secureUrl); ?>" class="attachment-image-clickable" data-src="<?php echo htmlspecialchars($secureUrl); ?>" data-alt="<?php echo htmlspecialchars($att['FileName']); ?>">
                                        <img src="<?php echo htmlspecialchars($secureUrl); ?>" alt="<?php echo htmlspecialchars($att['FileName']); ?>" style="max-width:100%;max-height:120px;object-fit:cover;border-radius:6px;">
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($secureUrl); ?>" target="_blank">
                                        <i class="fas <?php echo $iconClass; ?> fa-3x" aria-hidden="true" style="color:#3b3b3b"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:10px;text-align:center;">
                                <a href="<?php echo htmlspecialchars($secureUrl); ?>" style="font-weight:600;color:var(--primary-color);display:block;word-break:break-word;"><?php echo htmlspecialchars($att['FileName']); ?></a>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
                                    <div style="font-size:0.9rem;color:#666;margin-top:6px;"><?php echo htmlspecialchars(substr($att['UploadedAt'],0,19)); ?> - <?php echo htmlspecialchars($att['UploadedBy'] ?? t('unknown')); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="lightbox.js?v=<?php echo filemtime('lightbox.js'); ?>" defer></script>
    <script src="maps_loader.js"></script>
    
    <?php if (!empty($property['latitude']) && !empty($property['longitude'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapElement = document.getElementById('property-map');
            if (!mapElement) {
                return;
            }

            const apiKey = '<?php echo addslashes(GOOGLE_MAPS_API_KEY); ?>';
            const lat = <?php echo json_encode((float)$property['latitude']); ?>;
            const lng = <?php echo json_encode((float)$property['longitude']); ?>;
            const propertyTitle = <?php echo json_encode($property['Title'] ?? t('property_location')); ?>;
            const infoWindowContent = <?php echo json_encode('<div style="min-width:180px;"><strong>' . htmlspecialchars($property['Title'] ?? t('property_location'), ENT_QUOTES, 'UTF-8') . '</strong><br>' . htmlspecialchars($property['Location'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>'); ?>;

            function initMap() {
                const position = { lat: lat, lng: lng };

                const map = new google.maps.Map(mapElement, {
                    center: position,
                    zoom: 15,
                    gestureHandling: 'greedy',
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true
                });

                const marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    title: propertyTitle || undefined,
                    animation: google.maps.Animation.DROP
                });

                if (infoWindowContent) {
                    const infoWindow = new google.maps.InfoWindow({
                        content: infoWindowContent
                    });
                    infoWindow.open(map, marker);
                    marker.addListener('click', function () {
                        infoWindow.open(map, marker);
                    });
                }
            }

            function handleMapError(error) {
                console.error(error && error.message ? error.message : error);
                BroManMaps.showError(mapElement, 'تعذر تحميل خريطة الموقع. يرجى التحقق من مفتاح Google Maps API.');
            }

            if (typeof BroManMaps !== 'undefined') {
                BroManMaps.loadGoogleMaps(apiKey, initMap, handleMapError);
            } else {
                console.error('maps_loader.js لم يتم تحميله.');
            }
        });
    </script>
    <?php endif; ?>
    <script>
    // Improve flash message visibility: focus and add a short animation then auto-hide after 6s
    document.addEventListener('DOMContentLoaded', function() {
        const flash = document.getElementById('flash-message');
        if (flash) {
            // Focus for screen-readers and keyboard users
            flash.focus();
            // Slight highlight animation
            flash.style.transition = 'box-shadow 0.25s ease, transform 0.25s ease';
            flash.style.boxShadow = '0 0 0 4px rgba(40,167,69,0.12)';
            flash.style.transform = 'translateY(-4px)';
            setTimeout(() => {
                flash.style.boxShadow = '';
                flash.style.transform = '';
            }, 400);
            // Auto-hide after 6 seconds (keeps DOM message removal behavior intact)
            setTimeout(() => {
                try { flash.remove(); } catch(e) {}
            }, 6000);
        }
    });
    </script>
    <script>
    // Unified Media Gallery - Cinematic Two-Layer System
    function showMedia(thumbElement, mediaType, mediaSrc) {
        const container = document.getElementById('heroMainContainer');
        if (!container) return;
        
        let newContent = '';
        
        if (mediaType === 'image') {
            // Two-layer cinematic display for images
            newContent = `
                <img src="${mediaSrc}" class="hero-backdrop" alt="">
                <img src="${mediaSrc}" alt="Property Image" class="hero-main-image" style="cursor: pointer;" onclick="console.log('Clicked dynamic image:', this.src); if(window.propertyLightbox) { window.propertyLightbox.openBySrc(this.src); } else { console.error('window.propertyLightbox is undefined!'); }" id="heroMainMedia">
            `;
        } else if (mediaType === 'video') {
            // Two-layer cinematic display for videos (Static Background)
            newContent = `
                <video class="hero-backdrop" muted preload="metadata">
                    <source src="${mediaSrc}#t=0.1" type="video/mp4">
                </video>
                <video controls autoplay class="hero-main-video" id="heroMainMedia">
                    <source src="${mediaSrc}" type="video/mp4">
                </video>
            `;
        } else if (mediaType === 'audio') {
            // Single layer for audio (no backdrop needed)
            newContent = `
                <div class="hero-main-audio" id="heroMainMedia">
                    <i class="fas fa-microphone"></i>
                    <audio controls autoplay>
                        <source src="${mediaSrc}" type="audio/mpeg">
                    </audio>
                </div>
            `;
        }
        
        // Fade effect
        container.style.opacity = '0.5';
        setTimeout(() => {
            container.innerHTML = newContent;
            container.style.opacity = '1';
        }, 150);
        
        // Update active state on thumbnails
        document.querySelectorAll('.hero-thumb, .hero-media-thumb').forEach(t => t.classList.remove('active'));
        thumbElement.classList.add('active');
    }
    
    // Legacy function for backward compatibility
    function changeHeroImage(thumbElement, imageSrc) {
        showMedia(thumbElement, 'image', imageSrc);
    }
    </script>
</body>
</html>
