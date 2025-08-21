<?php
$active_tab = $_GET['listing_type_tab'] ?? 'all';

// Preserve current query params except listing_type_tab
$query = $_GET;
unset($query['listing_type_tab']);
$baseUrl = basename($_SERVER['PHP_SELF']);

function buildTabUrl($baseUrl, $query, $tabValue) {
    $params = $query;
    $params['listing_type_tab'] = $tabValue;
    return $baseUrl . '?' . http_build_query($params);
}
?>
<div class="property-type-tabs" style="margin-bottom: 12px;">
    <a href="<?= htmlspecialchars(buildTabUrl($baseUrl, $query, 'all')) ?>" class="<?= $active_tab == 'all' ? 'active' : '' ?>"><?= t('all') ?></a>
    <a href="<?= htmlspecialchars(buildTabUrl($baseUrl, $query, 'Primary')) ?>" class="<?= $active_tab == 'Primary' ? 'active' : '' ?>"><?= t('primary') ?></a>
    <a href="<?= htmlspecialchars(buildTabUrl($baseUrl, $query, 'Resale')) ?>" class="<?= $active_tab == 'Resale' ? 'active' : '' ?>"><?= t('resale') ?></a>
</div>