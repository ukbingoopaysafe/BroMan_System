<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Ensure user is admin
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    $_SESSION["error_message"] = "You do not have permission to access this page.";
    header("Location: dashboard.php");
    exit;
}

require_once 'db.php'; // Database connection
require_once 'navbar.php'; // Navigation bar

// Helper function to parse numeric values from Excel for DB storage
function parseNumericValueForDB($value_from_excel, $is_price_field = false)
{
    if ($value_from_excel === null || $value_from_excel === '') {
        return null;
    }
    // Remove dots (thousand separators) and any other non-numeric characters except a single decimal point for actual decimals
    $cleaned_value = str_replace('.', '', (string)$value_from_excel);
    // If the original had a comma for decimal, switch it to dot for floatval
    // For this specific Excel, it seems like numbers are 2.200.000 or 160. We assume dots are thousand separators.
    
    if (!is_numeric($cleaned_value)) {
        // Attempt to clean further if it's something like "2,5" for 2.5
        $cleaned_value_comma_decimal = str_replace(',', '.', (string)$value_from_excel);
        if (is_numeric($cleaned_value_comma_decimal)) {
            $numeric_val = floatval($cleaned_value_comma_decimal);
        } else {
            return null; // Cannot parse
        }
    } else {
        $numeric_val = floatval($cleaned_value);
    }

    // Return exact value without division for all fields including price fields
    return $numeric_val;
}

$import_message = "";
$import_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $target_dir = "uploads/";
    
    // Create directory with secure permissions if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $target_file = $target_dir . basename($_FILES["excel_file"]["name"]);
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if file is an Excel file
    if ($fileType != "xlsx" && $fileType != "xls") {
        $import_error = "Only Excel files (.xlsx or .xls) are allowed.";
    } else {
        if (move_uploaded_file($_FILES["excel_file"]["tmp_name"], $target_file)) {
            // File uploaded successfully, now process it
            require 'vendor/autoload.php';
            
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($target_file);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                // Skip header row
                array_shift($rows);
                
                // Begin transaction
                $conn->begin_transaction();
                
                $properties_added = 0;
                $properties_updated = 0;
                
                foreach ($rows as $row) {
                    // Skip empty rows
                    if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                        continue;
                    }
                    
                    // Check if property exists by ID or title
                    $property_id = !empty($row[0]) ? intval($row[0]) : null;
                    $title = !empty($row[1]) ? $row[1] : null;
                    
                    $property_exists = false;
                    $existing_id = null;
                    
                    if ($property_id) {
                        $check_sql = "SELECT PropertyID FROM Properties WHERE PropertyID = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("i", $property_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $property_exists = true;
                            $existing_id = $property_id;
                        }
                        $check_stmt->close();
                    }
                    
                    if (!$property_exists && $title) {
                        $check_sql = "SELECT PropertyID FROM Properties WHERE Title = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("s", $title);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $property_exists = true;
                            $row_data = $check_result->fetch_assoc();
                            $existing_id = $row_data['PropertyID'];
                        }
                        $check_stmt->close();
                    }
                    
                    // Parse numeric values
                    $price = parseNumericValueForDB($row[3], true);
                    $area = parseNumericValueForDB($row[4]);
                    $bedrooms = !empty($row[5]) ? intval($row[5]) : null;
                    
                    if ($property_exists) {
                        // Update existing property
                        $update_sql = "UPDATE Properties SET 
                                      Title = ?, 
                                      ProjectName = ?, 
                                      Price = ?, 
                                      Area = ?, 
                                      Bedrooms = ?, 
                                      Location = ?, 
                                      Status = ?, 
                                      governorate = ?, 
                                      district = ? 
                                      WHERE PropertyID = ?";
                        
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param(
                            "ssddiisssi",
                            $row[1], // Title
                            $row[2], // ProjectName
                            $price,  // Price
                            $area,   // Area
                            $bedrooms, // Bedrooms
                            $row[7], // Location
                            $row[8], // Status
                            $row[9], // governorate
                            $row[10], // district
                            $existing_id // PropertyID
                        );
                        
                        if ($update_stmt->execute()) {
                            $properties_updated++;
                        } else {
                            throw new Exception("Error updating property: " . $update_stmt->error);
                        }
                        $update_stmt->close();
                    } else {
                        // Insert new property
                        $insert_sql = "INSERT INTO Properties (Title, ProjectName, Price, Area, Bedrooms, Location, Status, governorate, district, DateAdded, EmployeeID) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                        
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bind_param(
                            "ssddiisssi",
                            $row[1], // Title
                            $row[2], // ProjectName
                            $price,  // Price
                            $area,   // Area
                            $bedrooms, // Bedrooms
                            $row[7], // Location
                            $row[8], // Status
                            $row[9], // governorate
                            $row[10], // district
                            $_SESSION["id"] // EmployeeID
                        );
                        
                        if ($insert_stmt->execute()) {
                            $properties_added++;
                        } else {
                            throw new Exception("Error inserting property: " . $insert_stmt->error);
                        }
                        $insert_stmt->close();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                $import_message = "Import completed successfully. Added $properties_added new properties and updated $properties_updated existing properties.";
                
                // Delete the uploaded file after processing
                unlink($target_file);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $import_error = "Error processing Excel file: " . $e->getMessage();
            }
        } else {
            $import_error = "Sorry, there was an error uploading your file.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'en'; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Excel - Broman Real Estate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="modern_styles.css">
    <?php if ($_SESSION['lang'] == 'ar'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <h1>Import Properties from Excel</h1>
        
        <?php if (!empty($import_message)): ?>
            <div class="alert alert-success">
                <?php echo $import_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($import_error)): ?>
            <div class="alert alert-danger">
                <?php echo $import_error; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <h2>Upload Excel File</h2>
                <p>Please upload an Excel file (.xlsx or .xls) with property data.</p>
                <p>The file should have the following columns:</p>
                <ol>
                    <li>ID (optional, for updating existing properties)</li>
                    <li>Title</li>
                    <li>Project Name</li>
                    <li>Price</li>
                    <li>Area</li>
                    <li>Bedrooms</li>
                    <li>Location</li>
                    <li>Status (available, sold, rented, unavailable)</li>
                    <li>Governorate</li>
                    <li>District</li>
                </ol>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="excel_file">Select Excel File:</label>
                        <input type="file" name="excel_file" id="excel_file" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload and Import</button>
                </form>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="index_properties.php" class="btn btn-secondary">Back to Properties</a>
        </div>
    </div>
</body>
</html>
