<?php
session_start();
// Debug: log session id and any flash messages or legacy success message at the start of the page load
error_log("FLASH-DEBUG view_property ON LOAD session=" . session_id() . " name=" . session_name() . " save_path=" . session_save_path() . " flash=" . var_export(
    		session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['flash_messages'] ?? null) : null, true
    ) . " legacy=" . var_export($_SESSION['success_message'] ?? null, true));
require_once __DIR__ . 
'/lang/translate.php'; // Include translation handler
require_once 'session_check.php'; // Include session timeout check

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

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

// Format price for display
function formatPrice($property)
{
    if (!empty($property['price_cash']) && is_numeric($property['price_cash'])) {
        return number_format(floatval($property['price_cash']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($property['price_installments']) && is_numeric($property['price_installments'])) {
        return number_format(floatval($property['price_installments']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($property['min_price']) && is_numeric($property['min_price']) && !empty($property['max_price']) && is_numeric($property['max_price'])) {
        return number_format(floatval($property['min_price']), 0, '.', ',') . ' - ' . number_format(floatval($property['max_price']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($property['min_price']) && is_numeric($property['min_price'])) {
        return t('starts_from') . ' ' . number_format(floatval($property['min_price']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($property['max_price']) && is_numeric($property['max_price'])) {
        return t('up_to') . ' ' . number_format(floatval($property['max_price']), 0, '.', ',') . ' ' . t('currency');
    } elseif (!empty($property['avg_price']) && is_numeric($property['avg_price'])) {
        return number_format(floatval($property['avg_price']), 0, '.', ',') . ' ' . t('currency') . ' (' . t('average') . ')';
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
    <link rel="stylesheet" href="lightbox.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <!-- Map provider: Leaflet + OpenStreetMap (Leaflet-only) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
    <style>
        /* Ensure this page uses the site-wide Inter + Tajawal fonts (override any local rules) */
        html, body {
            font-family: 'Inter', 'Tajawal', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
    </style>
    <style>
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
            background-color: #f8f9fa;
            padding: 15px 20px;
            margin: 0;
            border-bottom: 1px solid #eee;
            font-size: 1.3rem;
            color: #333;
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
            color: #333;
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
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.2s ease;
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
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
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
            <h1><?php echo $page_title; ?></h1>
            <div class="actions">
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <a href="edit_property.php?id=<?php echo $property_id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> <?php echo t('edit'); ?></a>
                <?php endif; ?>
                <!-- WhatsApp share button -->
                <a href="<?php echo htmlspecialchars($whatsapp_link); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp" title="مشاركة عبر واتساب" aria-label="مشاركة عبر واتساب">
                    <i class="fab fa-whatsapp"></i>
                    <span class="sr-only">مشاركة عبر واتساب</span>
                </a>
                <a href="javascript:history.back()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo t('back'); ?></a>
            </div>
        </div>

        <!-- Property Images Section -->
        <?php if (!empty($property_images)): ?>
        <div class="property-section">
            <h2>📷 Property Images</h2>
            <div class="media-gallery">
                <div class="images-grid">
                    <?php foreach ($property_images as $index => $image): ?>
                        <div class="image-item">
                            <img src="<?php echo htmlspecialchars($image['file_path']); ?>" 
                                 alt="Property Image" 
                                 class="property-image-clickable" 
                                 data-image-index="<?php echo $index; ?>">
                            <?php if ($image['is_primary']): ?>
                                <div class="image-primary-badge">Main Image</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Property Audio Notes Section -->
        <?php if (!empty($property_audio_notes)): ?>
        <div class="property-section">
            <h2>🎤 Voice Notes</h2>
            <div class="audio-notes">
                <?php foreach ($property_audio_notes as $audio): ?>
                    <div class="audio-item">
                        <audio controls>
                            <source src="<?php echo htmlspecialchars($audio['file_path']); ?>" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
                        <div class="audio-info">
                            <div>Uploaded: <?php echo date('Y-m-d H:i', strtotime($audio['upload_date'])); ?></div>
                            <?php if ($audio['duration']): ?>
                                <div>Duration: <?php echo formatDuration($audio['duration']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Property Videos Section -->
        <?php if (!empty($property_videos)): ?>
        <div class="property-section">
            <h2>🎥 <?php echo t('property_videos'); ?></h2>
            <div class="video-gallery">
                <div class="videos-grid">
                    <?php foreach ($property_videos as $video): ?>
                        <div class="video-item">
                            <video controls preload="metadata" class="property-video">
                                <source src="<?php echo htmlspecialchars($video['VideoPath']); ?>" type="video/mp4">
                                Your browser does not support the video element.
                            </video>
                            <div class="video-info">
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
                                <div class="video-name"><?php echo htmlspecialchars($video['VideoName']); ?></div>
                                <div class="video-details">
                                    <span><?php echo t('video_size'); ?>: <?php echo formatFileSize($video['VideoSize']); ?></span>
                                    <span><?php echo t('uploaded_on'); ?>: <?php echo date('Y-m-d H:i', strtotime($video['UploadDate'])); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                        <span class="detail-value"><?php echo htmlspecialchars($property['PropertyID']); ?></span>
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
                    <?php if (!empty($property['TypeName'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('property_type'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['TypeName']); ?></span>
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
                    <?php if (!empty($property['price_cash'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('price_cash'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['price_cash'], 0, '.', ','); ?> <?php echo t('currency'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['price_installments'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('price_installments'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['price_installments'], 0, '.', ','); ?> <?php echo t('currency'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['meter_price_cash'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('meter_price_cash'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['meter_price_cash'], 0, '.', ','); ?> <?php echo t('currency'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['meter_price_installments'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('meter_price_installments'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['meter_price_installments'], 0, '.', ','); ?> <?php echo t('currency'); ?></span>
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
                    // Normalize and validate year_built before display. Treat NULL, empty, 0 or unrealistic values as not set.
                    $year_raw = $property['year_built'] ?? null;
                    $show_year = false;
                    if ($year_raw !== null && trim((string)$year_raw) !== '') {
                        $year_int = intval($year_raw);
                        $current_year = intval(date('Y'));
                        // Consider valid years between 1900 and next year
                        if ($year_int >= 1900 && $year_int <= ($current_year + 1)) {
                            $show_year = true;
                        }
                    }
                    if ($show_year): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('year_built'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars((string)$year_int); ?></span>
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
            // Helper: normalize phone numbers for tel: links so that apps
            // (e.g., WhatsApp on desktop) receive an international format.
            // Rules applied (best-effort):
            // - Keep numbers that already start with '+' as-is.
            // - Convert leading '00' to leading '+'.
            // - If the number starts with a single leading '0' (local Egyptian style
            //   like 010..., 011..., 012..., 015...), replace it with '+20' + rest.
            // - If the number starts with '20' (country code present but no '+'),
            //   prefix with '+'.
            // - Otherwise, return digits-only string (best-effort).
            function normalize_phone_for_tel($raw) {
                $s = trim((string)$raw);
                if ($s === '') return '';
                // keep plus sign and digits only
                $s = preg_replace('/[^0-9+]/', '', $s);
                if ($s === '') return '';

                // already international with plus
                if (strpos($s, '+') === 0) {
                    return $s;
                }

                // leading 00 -> +
                if (strpos($s, '00') === 0) {
                    return '+' . ltrim($s, '0');
                }

                // local Egyptian numbers: start with single 0 e.g. 010..., 011..., 012..., 015...
                if (strpos($s, '0') === 0) {
                    $without0 = substr($s, 1);
                    // common mobile patterns in Egypt have 10 digits with leading 0
                    // so after removing 0 we expect 9 digits; still, add +20 in any case
                    return '+20' . $without0;
                }

                // if it already starts with country code like 20..., add +
                if (strpos($s, '20') === 0) {
                    return '+' . $s;
                }

                // fallback: return digits-only (no plus)
                return $s;
            }
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

            <?php if (!empty($property['seller_type']) || !empty($property['seller_name']) || !empty($property['seller_phone']) || !empty($property['owner_name']) || !empty($property['owner_phone'])): ?>
            <div class="property-section">
                <h2><?php echo t('owner_information'); ?></h2>
                <div class="detail-grid detail-grid--inline">
                <?php
                // Seller/owner section: render three inline columns: type, name, phone.
                $seller_type_raw = $property['seller_type'] ?? null;
                $seller_name_raw = $property['seller_name'] ?? null;
                $seller_phone_raw = $property['seller_phone'] ?? null;

                // Helper: translate seller_type stored values to localized labels
                function translate_seller_type($val) {
                    $v = $val === null ? '' : trim((string)$val);
                    if ($v === '') return '';
                    // map common stored values (Arabic and English variants) to translation keys
                    $map = [
                        // Arabic values (database canonical values)
                        'مالك' => 'seller_type_owner',
                        'وسيط' => 'seller_type_broker',
                        'وكيل' => 'seller_type_agent',
                        'بواب' => 'seller_type_doorman',
                        'شركة مطورة' => 'seller_type_developer_company',
                        'شركة تشطيب' => 'seller_type_finishing_company',
                        'شركة تسويق' => 'seller_type_marketing_company',
                        // English fallbacks (in case any rows were stored in English)
                        'Owner' => 'seller_type_owner',
                        'Broker' => 'seller_type_broker',
                        'Agent' => 'seller_type_agent',
                        'Doorman' => 'seller_type_doorman',
                        'Developer Company' => 'seller_type_developer_company',
                        'Finishing Company' => 'seller_type_finishing_company',
                        'Marketing Company' => 'seller_type_marketing_company',
                    ];

                    if (isset($map[$v])) {
                        // Use translation helper to get localized label
                        return t($map[$v]);
                    }

                    // Unknown value: return raw string so admin can see it
                    return $v;
                }

                // Helper to render a value or a fallback. If $is_seller_type is true,
                // attempt to translate the stored seller_type value.
                function render_or_fallback($val, $is_seller_type = false) {
                    if ($val !== null && trim((string)$val) !== '') {
                        if ($is_seller_type) {
                            return htmlspecialchars(translate_seller_type($val));
                        }
                        return htmlspecialchars(trim((string)$val));
                    }
                    return htmlspecialchars(t('undefined'));
                }

                // Prepare clickable phone if available
                $seller_phone_html = null;
                if (!empty($seller_phone_raw)) {
                    $phone_display = htmlspecialchars(trim($seller_phone_raw));
                    $tel = normalize_phone_for_tel($seller_phone_raw);
                    // Force RTL inline styling for links to avoid stylesheet specificity/order conflicts
                    if (function_exists('get_direction') && get_direction() === 'rtl') {
                        $seller_phone_html = '<a href="tel:' . $tel . '" style="display:inline-block;width:100%;text-align:right;direction:rtl;">' . $phone_display . '</a>';
                    } else {
                        $seller_phone_html = '<a href="tel:' . $tel . '">' . $phone_display . '</a>';
                    }
                }
                ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('seller_type'); ?>:</span>
                        <span class="detail-value"><?php echo render_or_fallback($seller_type_raw, true); ?></span>
                    </div>
                    <div class="detail-item detail-name">
                        <span class="detail-label"><?php echo t('name'); ?>:</span>
                        <span class="detail-value"><?php echo render_or_fallback($seller_name_raw); ?></span>
                    </div>
                    <div class="detail-item detail-phone">
                        <span class="detail-label"><?php echo t('phone'); ?>:</span>
                        <span class="detail-value"><?php echo $seller_phone_html !== null ? $seller_phone_html : htmlspecialchars(t('undefined')); ?></span>
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

    <script src="lightbox.js" defer></script>
    
    <?php if (!empty($property['latitude']) && !empty($property['longitude'])): ?>
    <script>
        // Initialize static map for property location (Leaflet-only)
        document.addEventListener('DOMContentLoaded', function() {
            const lat = <?php echo $property['latitude']; ?>;
            const lng = <?php echo $property['longitude']; ?>;

            if (typeof L === 'undefined') {
                console.error('Leaflet is not loaded.');
                return;
            }

            const map = L.map('property-map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            const marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup('<?php echo addslashes($property['Title']); ?>').openPopup();
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
</body>
</html>
