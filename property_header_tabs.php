<?php
// Helper function to get page title based on current page and listing type
function getPageTitle() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $listing_type = isset($_GET['listing_type']) ? $_GET['listing_type'] : '';
    
    $base_title = '';
    switch ($current_page) {
        case 'index_properties.php':
            $base_title = t('all_properties');
            break;
        case 'residential_properties.php':
            $base_title = t('residential_properties');
            break;
        case 'coastal_properties.php':
            $base_title = t('coastal_properties');
            break;
        case 'commercial_properties.php':
            $base_title = t('commercial_administrative_medical_hotel');
            break;
        default:
            $base_title = t('all_properties');
    }
    
    // Add listing type prefix if present
    if ($listing_type === 'Primary') {
        return t('primary') . ' ' . $base_title;
    } elseif ($listing_type === 'Resale') {
        return t('resale') . ' ' . $base_title;
    }
    
    return $base_title;
}

// Helper function to determine active tab based on current page
function getActiveTabClass($page_name) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page === $page_name) ? 'active' : '';
}

// Helper function to build URL with preserved parameters
function buildTabUrl($page_name) {
    $listing_type = isset($_GET['listing_type']) ? $_GET['listing_type'] : '';
    $url = $page_name;
    
    if ($listing_type) {
        $url .= '?listing_type=' . urlencode($listing_type);
    }
    
    return $url;
}
?>

<!-- Page Header with Dynamic Title -->
<div class="page-header">
    <h1><?php echo getPageTitle(); ?></h1>
</div>

<!-- Property Type Tabs -->
<div class="property-type-tabs">
    <a href="<?php echo buildTabUrl('index_properties.php'); ?>" class="<?php echo getActiveTabClass('index_properties.php'); ?>"><?php echo t('all_properties'); ?></a>
    <a href="<?php echo buildTabUrl('residential_properties.php'); ?>" class="<?php echo getActiveTabClass('residential_properties.php'); ?>"><?php echo t('residential_properties'); ?></a>
    <a href="<?php echo buildTabUrl('coastal_properties.php'); ?>" class="<?php echo getActiveTabClass('coastal_properties.php'); ?>"><?php echo t('coastal_properties'); ?></a>
    <a href="<?php echo buildTabUrl('commercial_properties.php'); ?>" class="<?php echo getActiveTabClass('commercial_properties.php'); ?>"><?php echo t('commercial_administrative_medical_hotel'); ?></a>
</div>

