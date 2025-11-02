<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

function log_property_visit($property_id) {
    global $conn;
    
    error_log("Starting log_property_visit for property_id: " . $property_id);
    error_log("Session data: " . print_r($_SESSION, true));
    
    if (!isset($_SESSION['employee_id'])) {
        error_log("No employee_id in session");
        return false;
    }
    
    $employee_id = $_SESSION['employee_id'];
    error_log("Employee ID from session: " . $employee_id);
    
    // Get property details for logging
    $stmt = $conn->prepare("SELECT p.PropertyID, p.Title, pt.TypeName_ar as property_type 
                           FROM properties p 
                           LEFT JOIN propertytypes pt ON p.PropertyTypeID = pt.PropertyTypeID 
                           WHERE p.PropertyID = ?");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $property = $result->fetch_assoc();
    
    if (!$property) {
        return false;
    }
    
    // Create details for activity log
    $details = [
        'property_id' => $property['PropertyID'],
        'property_type' => $property['property_type'] ?? 'غير محدد',
        'property_title' => $property['Title']
    ];
    
    error_log("Property details: " . print_r($details, true));
    
    // Log the visit
    error_log("Attempting to log activity for employee_id: " . $employee_id);
    $result = log_activity($conn, $employee_id, 'property_visit', $details);
    error_log("Log activity result: " . ($result ? "success" : "failed"));
    
    return true;
}