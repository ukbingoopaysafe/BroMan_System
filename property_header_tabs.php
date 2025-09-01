<?php
// Helper function to get page title based on current page and mode
function getPageTitle() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_mode = $_SESSION['listing_context'] ?? '';
    $mode_text = ($current_mode === 'Primary') ? t('primary_properties') : t('resale_properties');
    
    switch ($current_page) {
        case 'index_properties.php':
            return t('all_properties') . ' - ' . $mode_text;
        case 'residential_properties.php':
            return t('residential_properties') . ' - ' . $mode_text;
        case 'coastal_properties.php':
            return t('coastal_properties') . ' - ' . $mode_text;
        case 'commercial_properties.php':
            return t('commercial_administrative_medical_hotel') . ' - ' . $mode_text;
        default:
            return t('all_properties') . ' - ' . $mode_text;
    }
}

// Helper function to determine active tab based on current page
function getActiveTabClass($page_name) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page === $page_name) ? 'active' : '';
}
?>

<!-- Page Header with Dynamic Title -->
<div class="page-header">
    <h1><?php echo getPageTitle(); ?></h1>
</div>

<!-- Property Type Tabs -->
<div class="property-type-tabs">
    <a href="index_properties.php" class="<?php echo getActiveTabClass('index_properties.php'); ?>"><?php echo t('all_properties'); ?></a>
    <a href="residential_properties.php" class="<?php echo getActiveTabClass('residential_properties.php'); ?>"><?php echo t('residential_properties'); ?></a>
    <a href="coastal_properties.php" class="<?php echo getActiveTabClass('coastal_properties.php'); ?>"><?php echo t('coastal_properties'); ?></a>
    <a href="commercial_properties.php" class="<?php echo getActiveTabClass('commercial_properties.php'); ?>"><?php echo t('commercial_administrative_medical_hotel'); ?></a>
</div>

