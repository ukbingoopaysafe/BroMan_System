<?php
session_start();
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

// Check if property ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index_properties.php");
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property details with proper joins for unit design and finishing type
$property = null;
$sql = "SELECT p.*, pt.TypeName, 
               e.FullName AS EmployeeName,
               pud.DesignName AS UnitDesignName, pft.FinishingName AS FinishingTypeName
        FROM Properties p
        LEFT JOIN PropertyTypes pt ON p.PropertyTypeID = pt.PropertyTypeID
        LEFT JOIN Employees e ON p.EmployeeID = e.EmployeeID
        LEFT JOIN PropertyUnitDesigns pud ON p.unit_design_id = pud.DesignID
        LEFT JOIN PropertyFinishingTypes pft ON p.finishing_type_id = pft.FinishingID
        WHERE p.PropertyID = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $property_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $property = $result->fetch_assoc();
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

// Format price for display
function formatPrice($property)
{
    if (!empty($property['Price']) && is_numeric($property['Price'])) {
        return number_format(floatval($property['Price']), 0, '.', ',') . ' ' . t('currency');
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

// Format payment terms
function formatPaymentTerms($property)
{
    $terms = [];
    
    if (!empty($property['down_payment_percentage']) && is_numeric($property['down_payment_percentage'])) {
        $terms[] = number_format($property['down_payment_percentage'], 0) . '% ' . t('down_payment_percentage');
    }
    
    if (!empty($property['payment_years']) && is_numeric($property['payment_years'])) {
        $terms[] = $property['payment_years'] . ' ' . t('years');
    }
    
    return !empty($terms) ? implode(' + ', $terms) : t('undefined');
}

// Page title
$page_title = sprintf(t('property_details_title'), htmlspecialchars($property['Title'] ?: $property['ProjectName'] ?: t('undefined')));
?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Broman Real Estate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="lightbox.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
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
    <script src="session_timeout.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><?php echo $page_title; ?></h1>
            <div class="actions">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="edit_property.php?id=<?php echo $property_id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> <?php echo t('edit'); ?></a>
                <?php endif; ?>
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
                        <div class="audio-info">
                            <div>Uploaded: <?php echo date('Y-m-d H:i', strtotime($audio['upload_date'])); ?></div>
                            <?php if ($audio['duration']): ?>
                                <div>Duration: <?php echo formatDuration($audio['duration']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('title'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['Title'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('project_name'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['ProjectName'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('property_type'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['TypeName'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('status'); ?>:</span>
                        <span class="detail-value"><?php echo t($property['Status'] ?: 'undefined'); ?></span>
                    </div>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('location_information'); ?></h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('governorate'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['governorate'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('district'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['district'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item full-width">
                        <span class="detail-label"><?php echo t('detailed_address'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['Location'] ?: t('undefined')); ?></span>
                    </div>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('pricing_and_area'); ?></h2>
                <div class="detail-grid">
                    <?php if (!empty($property['Price'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('unit_price_egp'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['Price'], 0, '.', ','); ?> <?php echo t('currency'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['min_price'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('min_price'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['min_price'], 0, '.', ','); ?> <?php echo t('currency'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['max_price'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('max_price'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['max_price'], 0, '.', ','); ?> <?php echo t('currency'); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($property['Area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('unit_area_sqm'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['Area'], 2, '.', ','); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['min_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('min_area'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['min_area'], 2, '.', ','); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['max_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('max_area'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['max_area'], 2, '.', ','); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['avg_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('avg_area_sqm'); ?>:</span>
                        <span class="detail-value"><?php echo number_format($property['avg_area'], 2, '.', ','); ?> <?php echo t('sqm'); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($property['compound_area'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('compound_area_faddan'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['compound_area']); ?> <?php echo t('faddan'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('unit_details'); ?></h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('unit_design'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['UnitDesignName'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('finishing_type'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['FinishingTypeName'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('floors_count'); ?>:</span>
                        <span class="detail-value"><?php echo !empty($property['FloorsCount']) ? htmlspecialchars($property['FloorsCount']) : t('undefined'); ?></span>
                    </div>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('payment_and_delivery'); ?></h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('delivery_date'); ?>:</span>
                        <span class="detail-value"><?php echo !empty($property['delivery_date']) ? date("Y-m-d", strtotime($property['delivery_date'])) : t('undefined'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('down_payment_percentage'); ?>:</span>
                        <span class="detail-value"><?php echo !empty($property['down_payment_percentage']) ? htmlspecialchars($property['down_payment_percentage']) . '%' : t('undefined'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('payment_years'); ?>:</span>
                        <span class="detail-value"><?php echo !empty($property['payment_years']) ? htmlspecialchars($property['payment_years']) : t('undefined'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('payment_terms'); ?>:</span>
                        <span class="detail-value"><?php echo formatPaymentTerms($property); ?></span>
                    </div>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('company_information'); ?></h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('company'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['company_name'] ?? t('undefined')); ?></span>
                    </div>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('sales_information'); ?></h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('sales_manager_name_label'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['sales_manager_name'] ?? t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('sales_manager_phone_label'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['sales_manager_phone'] ?? t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('salesperson_name_label'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['salesperson_name'] ?? t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('salesperson_phone_label'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['salesperson_phone'] ?? t('undefined')); ?></span>
                    </div>
                </div>
            </div>

            <div class="property-section">
                <h2><?php echo t('additional_information'); ?></h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('added_by_employee'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['EmployeeName'] ?: t('undefined')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo t('date_added'); ?>:</span>
                        <span class="detail-value"><?php echo !empty($property['DateAdded']) ? date("Y-m-d", strtotime($property['DateAdded'])) : t('undefined'); ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($property['Description'])): ?>
            <div class="property-section">
                <h2><?php echo t('description'); ?></h2>
                <div class="property-description">
                    <p><?php echo nl2br(htmlspecialchars($property['Description'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="lightbox.js"></script>
</body>
</html>


