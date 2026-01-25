<?php
/**
 * Test script to verify the sorting logic implemented in property listing pages.
 * This simulates the logic without needing a full database connection or browser.
 */

function t($key) { return $key; } // Mock translation

$test_cases = [
    ['mode' => 'Rental', 'sort' => 'price', 'dir' => 'desc'],
    ['mode' => 'Primary', 'sort' => 'price', 'dir' => 'asc'],
    ['mode' => 'Rental', 'sort' => 'project_or_title', 'dir' => 'asc'],
    ['mode' => 'Primary', 'sort' => 'project_or_title', 'dir' => 'desc'],
    ['mode' => 'Primary', 'sort' => 'area', 'dir' => 'desc'],
];

echo "VERIFYING SORTING LOGIC:\n";
echo "========================\n\n";

foreach ($test_cases as $case) {
    $current_mode = $case['mode'];
    $sort_column = $case['sort'];
    $sort_direction = $case['dir'];

    // --- LOGIC FROM THE MODIFIED FILES ---
    $projectNameSort = "COALESCE(NULLIF(p.ProjectName, ''), p.Title)";
    if ($current_mode === 'Rental') {
        $priceSort = "COALESCE(p.monthly_rent, p.daily_rent, p.yearly_rent, p.price_cash, p.price_installments)";
    } else {
        $priceSort = "COALESCE(p.price_cash, p.price_installments, (p.meter_price_cash * p.Area), (p.meter_price_installments * p.Area))";
    }

    $allowed_sort_columns = [
        'PropertyID'       => 'p.PropertyID',
        'propertyid'       => 'p.PropertyID',
        'friendly_id'      => 'p.friendly_id',
        'DateAdded'        => 'p.DateAdded',
        'Price'            => $priceSort,
        'price'            => $priceSort,
        'Area'             => 'COALESCE(p.Area, p.avg_area)',
        'area'             => 'COALESCE(p.Area, p.avg_area)',
        'ProjectName'      => $projectNameSort,
        'project_or_title' => $projectNameSort,
        'Title'            => 'p.Title',
        'governorate'      => 'p.governorate',
        'Status'           => 'p.Status'
    ];

    if (!array_key_exists($sort_column, $allowed_sort_columns)) {
        $sort_column_mapped = 'p.friendly_id';
    } else {
        $sort_column_mapped = $allowed_sort_columns[$sort_column];
    }
    
    $order_by = "ORDER BY " . $sort_column_mapped . " " . strtoupper($sort_direction);
    // ---------------------------------------

    echo "Mode: " . $current_mode . "\n";
    echo "Input Sort: " . $case['sort'] . " (" . $case['dir'] . ")\n";
    echo "Resulting SQL: " . $order_by . "\n";
    echo "------------------------\n";
}
?>
