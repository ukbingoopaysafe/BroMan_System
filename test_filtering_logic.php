<?php
/**
 * Test script to verify the filtering logic implemented in property listing pages.
 */

function t($key) { return $key; }

$test_cases = [
    ['mode' => 'Rental', 'min_price' => 5000, 'curr' => 'EGP'],
    ['mode' => 'Primary', 'min_price' => 1000000, 'curr' => 'USD'],
    ['mode' => 'Resale', 'min_area' => 100, 'max_area' => 200],
];

echo "VERIFYING FILTERING LOGIC:\n";
echo "==========================\n\n";

foreach ($test_cases as $case) {
    $current_mode = $case['mode'];
    $min_price_filter = $case['min_price'] ?? '';
    $max_price_filter = $case['max_price'] ?? '';
    $min_area_filter = $case['min_area'] ?? '';
    $max_area_filter = $case['max_area'] ?? '';
    $currency_filter = $case['curr'] ?? '';

    // --- LOGIC FROM THE MODIFIED FILES ---
    $sql_where = [];
    
    $areaExpr = "COALESCE(p.Area, p.min_area, p.net_area, p.avg_area)";
    if ($current_mode === 'Rental') {
        $priceExpr = "COALESCE(p.monthly_rent, p.daily_rent, p.yearly_rent, p.price_cash, p.price_installments)";
    } else {
        $priceExpr = "COALESCE(p.price_cash, p.price_installments, (p.meter_price_cash * p.Area), (p.meter_price_installments * p.Area))";
    }

    if (!empty($min_price_filter)) {
        $sql_where[] = "{$priceExpr} >= " . $min_price_filter;
    }
    if (!empty($max_price_filter)) {
        $sql_where[] = "{$priceExpr} <= " . $max_price_filter;
    }
    if (!empty($min_area_filter)) {
        $sql_where[] = "{$areaExpr} >= " . $min_area_filter;
    }
    if (!empty($max_area_filter)) {
        $sql_where[] = "{$areaExpr} <= " . $max_area_filter;
    }
    if (!empty($currency_filter)) {
        $sql_where[] = "p.currency = '{$currency_filter}'";
    }
    // ---------------------------------------

    echo "Mode: " . $current_mode . "\n";
    echo "Filters: " . json_encode($case) . "\n";
    echo "Generated SQL WHERE segments: " . implode(" AND ", $sql_where) . "\n";
    echo "--------------------------\n";
}
?>
