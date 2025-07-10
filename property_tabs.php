<?php
// Helper function to determine active tab based on current page
function getActiveTabClass($page_name) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page === $page_name) ? 'active' : '';
}
?>

<!-- Property Type Tabs -->
<div class="property-type-tabs">
    <a href="index_properties.php" class="<?php echo getActiveTabClass('index_properties.php'); ?>"><?php echo t('all_properties'); ?></a>
    <a href="residential_properties.php" class="<?php echo getActiveTabClass('residential_properties.php'); ?>"><?php echo t('residential_properties'); ?></a>
    <a href="coastal_properties.php" class="<?php echo getActiveTabClass('coastal_properties.php'); ?>"><?php echo t('coastal_properties'); ?></a>
    <a href="commercial_properties.php" class="<?php echo getActiveTabClass('commercial_properties.php'); ?>"><?php echo t('commercial_administrative_medical_hotel'); ?></a>
</div>

