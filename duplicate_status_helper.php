<?php
/**
 * Duplicate Status Helper Functions
 * 
 * This file contains helper functions for managing duplicate property review status.
 */

require_once __DIR__ . '/db.php';

/**
 * Normalize phone number for comparison (same logic as in admin_duplicate_properties.php)
 * 
 * @param string|null $phone The phone number to normalize
 * @return string|null Normalized phone number
 */
function normalize_phone_for_status($phone) {
    if ($phone === null) return null;
    $s = trim($phone);
    if ($s === '') return '';
    // unify egypt code +20/0020 to 0
    if (strpos($s, '+20') === 0) {
        $s = '0' . substr($s, 3);
    } elseif (strpos($s, '0020') === 0) {
        $s = '0' . substr($s, 4);
    }
    // remove symbols and spaces
    $s = str_replace(['+', ' ', '-', '(', ')'], '', $s);
    return $s;
}

/**
 * Update duplicate status for all properties sharing the same phone number
 * 
 * When a new property is added with a phone number that already exists,
 * this function marks ALL properties in that group as "Needs Review" (status = 1).
 * 
 * This ensures that if a group was previously reviewed (status = 2),
 * adding a new property to that group will reset it to "Needs Review".
 * 
 * @param int $property_id The newly added property ID
 * @param mysqli $conn Database connection
 * @return bool True on success, false on failure
 */
function updateDuplicateStatusByPhone($property_id, $conn) {
    // Get the newly added property's phone numbers
    $sql = "SELECT seller_phone, sales_manager_phone, salesperson_phone 
            FROM properties 
            WHERE PropertyID = ?";
    
    if (!$stmt = $conn->prepare($sql)) {
        error_log("Failed to prepare query in updateDuplicateStatusByPhone: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('i', $property_id);
    if (!$stmt->execute()) {
        error_log("Failed to execute query in updateDuplicateStatusByPhone: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return false; // Property not found
    }
    
    $property = $result->fetch_assoc();
    $stmt->close();
    
    // Collect all normalized phone numbers from this property
    $normalized_phones = [];
    
    foreach (['seller_phone', 'sales_manager_phone', 'salesperson_phone'] as $field) {
        $phone = $property[$field];
        if (!empty($phone)) {
            $normalized = normalize_phone_for_status($phone);
            if (!empty($normalized)) {
                $normalized_phones[] = $normalized;
            }
        }
    }
    
    if (empty($normalized_phones)) {
        // No phone numbers to check, property is unique
        return true;
    }
    
    // Find all properties that share any of these normalized phone numbers
    // Using the same normalization logic as the view
    $placeholders = implode(',', array_fill(0, count($normalized_phones), '?'));
    $types = str_repeat('s', count($normalized_phones));
    
    // We need to check against the normalized view logic
    // Since we can't directly query the view in UPDATE, we'll use a subquery approach
    // or we can use the view to get property IDs first
    
    // Get all property IDs that share these phone numbers using the view
    $sql_ids = "SELECT DISTINCT PropertyID 
                FROM v_property_phones_normalized 
                WHERE normalized_phone IN ($placeholders)";
    
    if (!$stmt = $conn->prepare($sql_ids)) {
        error_log("Failed to prepare ID query in updateDuplicateStatusByPhone: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param($types, ...$normalized_phones);
    if (!$stmt->execute()) {
        error_log("Failed to execute ID query in updateDuplicateStatusByPhone: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $property_ids = [];
    while ($row = $result->fetch_assoc()) {
        $property_ids[] = (int)$row['PropertyID'];
    }
    $stmt->close();
    
    if (empty($property_ids)) {
        // No duplicates found, property is unique
        return true;
    }
    
    // If we have duplicates (more than just the new property), mark all as "Needs Review"
    if (count($property_ids) > 1) {
        $ids_placeholders = implode(',', array_fill(0, count($property_ids), '?'));
        $ids_types = str_repeat('i', count($property_ids));
        
        $sql_update = "UPDATE properties 
                      SET duplicate_status = 1 
                      WHERE PropertyID IN ($ids_placeholders)";
        
        if (!$stmt = $conn->prepare($sql_update)) {
            error_log("Failed to prepare UPDATE query in updateDuplicateStatusByPhone: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param($ids_types, ...$property_ids);
        if (!$stmt->execute()) {
            error_log("Failed to execute UPDATE query in updateDuplicateStatusByPhone: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        error_log("updateDuplicateStatusByPhone: Marked " . count($property_ids) . " properties as 'Needs Review' for property ID $property_id");
        return true;
    }
    
    // Only one property (the new one), it's unique
    return true;
}

