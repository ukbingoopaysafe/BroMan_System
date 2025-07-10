<?php
/**
 * Property Image Helper Functions
 * 
 * This file contains helper functions for retrieving and displaying property images
 * in card-based views. It provides fallback logic for primary images.
 */

/**
 * Get the primary image for a property (for card view display)
 * Returns the primary image path, or the first available image if no primary is set
 * 
 * @param int $property_id The property ID
 * @param mysqli $conn Database connection
 * @return string|null The image path or null if no images exist
 */
function getPropertyCardImage($property_id, $conn) {
    // First, try to get the primary image
    $sql = "SELECT file_path FROM property_images WHERE property_id = ? AND is_primary = 1 LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['file_path'];
        }
        $stmt->close();
    }
    
    // If no primary image, get the first available image
    $sql = "SELECT file_path FROM property_images WHERE property_id = ? ORDER BY upload_date ASC LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['file_path'];
        }
        $stmt->close();
    }
    
    return null; // No images found
}

/**
 * Get property type icon class based on property type ID
 * 
 * @param int $property_type_id The property type ID
 * @return string The Font Awesome icon class
 */
function getPropertyTypeIcon($property_type_id) {
    $type_icons = [
        1 => 'fas fa-home',           // Residential
        2 => 'fas fa-umbrella-beach', // Coastal
        3 => 'fas fa-building',       // Administrative
        4 => 'fas fa-store',          // Commercial
        5 => 'fas fa-hotel',          // Hotel
        6 => 'fas fa-hospital'        // Medical
    ];
    
    return $type_icons[$property_type_id] ?? 'fas fa-landmark';
}

/**
 * Generate the image HTML for property cards
 * Shows actual image if available, otherwise shows property type icon
 * 
 * @param array $property The property data array
 * @param mysqli $conn Database connection
 * @return string HTML for the property image/icon
 */
function generatePropertyCardImageHTML($property, $conn) {
    $image_path = getPropertyCardImage($property['PropertyID'], $conn);
    
    if ($image_path && file_exists($image_path)) {
        // Display actual property image
        return '<img src="' . htmlspecialchars($image_path) . '" alt="Property Image" class="property-card-image">';
    } else {
        // Display property type icon as fallback
        $icon_class = getPropertyTypeIcon($property['PropertyTypeID']);
        return '<div class="property-icon-fallback"><i class="' . $icon_class . '"></i></div>';
    }
}
?>

