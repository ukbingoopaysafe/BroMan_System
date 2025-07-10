<?php
// Helper function to generate sorting URL
function getSortUrl($column, $current_sort, $current_direction) {
    $new_direction = ($current_sort === $column && $current_direction === 'asc') ? 'desc' : 'asc';
    
    // Preserve current filters
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


