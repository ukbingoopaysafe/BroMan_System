<?php
// Helper function to generate sorting URL
function getSortUrl($column, $current_sort, $current_direction) {
    // If clicking the same column, toggle its direction.
    // If switching to a different column, set the new column direction to the
    // opposite of the currently visible ordering so a single click reverses
    // the listing immediately (matches user expectation).
    if ($current_sort === $column) {
        $new_direction = ($current_direction === 'asc') ? 'desc' : 'asc';
    } else {
        // Use the opposite of the current listing direction for the new column
        // so one click flips order immediately.
        $new_direction = ($current_direction === 'asc') ? 'desc' : 'asc';
    }

    // Preserve current filters and set sorting params
    $params = $_GET;
    $params['sort'] = $column;
    $params['direction'] = $new_direction;

    return '?' . http_build_query($params);
}

// Helper function to get sort icon
function getSortIcon($column, $current_sort, $current_direction) {
    if ($current_sort !== $column) {
        return '<i class="fas fa-sort sort-icon"></i>';
    }
    
    if ($current_direction === 'asc') {
        return '<i class="fas fa-sort-up sort-icon active"></i>';
    } else {
        return '<i class="fas fa-sort-down sort-icon active"></i>';
    }
}


