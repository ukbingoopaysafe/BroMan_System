<?php
// This script updates the add_property.php file to include the new fields from Excel data

// Read the original file
$original_content = file_get_contents('add_property.php');

// Find the position to insert new form fields (before the submit button)
$submit_button_position = strpos($original_content, '<div>
        <input type="submit" value="Add Property"');

// Define new form fields to add
$new_form_fields = '
    <div>
        <label for="min_price">Minimum Price:</label>
        <input type="number" id="min_price" name="min_price" step="0.01" class="form-input" value="<?php echo htmlspecialchars($min_price ?? \'\'); ?>">
    </div>

    <div>
        <label for="max_price">Maximum Price:</label>
        <input type="number" id="max_price" name="max_price" step="0.01" class="form-input" value="<?php echo htmlspecialchars($max_price ?? \'\'); ?>">
    </div>

    <div>
        <label for="avg_price">Average Price:</label>
        <input type="number" id="avg_price" name="avg_price" step="0.01" class="form-input" value="<?php echo htmlspecialchars($avg_price ?? \'\'); ?>">
    </div>

    <div>
        <label for="min_area">Minimum Area:</label>
        <input type="number" id="min_area" name="min_area" step="0.01" class="form-input" value="<?php echo htmlspecialchars($min_area ?? \'\'); ?>">
    </div>

    <div>
        <label for="max_area">Maximum Area:</label>
        <input type="number" id="max_area" name="max_area" step="0.01" class="form-input" value="<?php echo htmlspecialchars($max_area ?? \'\'); ?>">
    </div>

    <div>
        <label for="avg_area">Average Area:</label>
        <input type="number" id="avg_area" name="avg_area" step="0.01" class="form-input" value="<?php echo htmlspecialchars($avg_area ?? \'\'); ?>">
    </div>

    <div>
        <label for="governorate">Governorate:</label>
        <input type="text" id="governorate" name="governorate" class="form-input" value="<?php echo htmlspecialchars($governorate ?? \'\'); ?>">
    </div>

    <div>
        <label for="district">District:</label>
        <input type="text" id="district" name="district" class="form-input" value="<?php echo htmlspecialchars($district ?? \'\'); ?>">
    </div>

    <div>
        <label for="detailed_address">Detailed Address:</label>
        <textarea id="detailed_address" name="detailed_address" rows="3" class="form-input"><?php echo htmlspecialchars($detailed_address ?? \'\'); ?></textarea>
    </div>

    <div>
        <label for="company_name">Company Name:</label>
        <input type="text" id="company_name" name="company_name" class="form-input" value="<?php echo htmlspecialchars($company_name ?? \'\'); ?>">
    </div>

    <div>
        <label for="sales_manager">Sales Manager:</label>
        <input type="text" id="sales_manager" name="sales_manager" class="form-input" value="<?php echo htmlspecialchars($sales_manager ?? \'\'); ?>">
    </div>

    <div>
        <label for="sales_manager_phone">Sales Manager Phone:</label>
        <input type="text" id="sales_manager_phone" name="sales_manager_phone" class="form-input" value="<?php echo htmlspecialchars($sales_manager_phone ?? \'\'); ?>">
    </div>

    <div>
        <label for="sales_responsible">Sales Responsible:</label>
        <input type="text" id="sales_responsible" name="sales_responsible" class="form-input" value="<?php echo htmlspecialchars($sales_responsible ?? \'\'); ?>">
    </div>

    <div>
        <label for="sales_responsible_phone">Sales Responsible Phone:</label>
        <input type="text" id="sales_responsible_phone" name="sales_responsible_phone" class="form-input" value="<?php echo htmlspecialchars($sales_responsible_phone ?? \'\'); ?>">
    </div>

    <div>
        <label for="company_phone">Company Phone:</label>
        <input type="text" id="company_phone" name="company_phone" class="form-input" value="<?php echo htmlspecialchars($company_phone ?? \'\'); ?>">
    </div>

    <div>
        <label for="company_address">Company Address:</label>
        <textarea id="company_address" name="company_address" rows="3" class="form-input"><?php echo htmlspecialchars($company_address ?? \'\'); ?></textarea>
    </div>

    <div>
        <label for="project_url">Project URL:</label>
        <input type="text" id="project_url" name="project_url" class="form-input" value="<?php echo htmlspecialchars($project_url ?? \'\'); ?>">
    </div>

    <div>
        <label for="notes">Notes:</label>
        <textarea id="notes" name="notes" rows="3" class="form-input"><?php echo htmlspecialchars($notes ?? \'\'); ?></textarea>
    </div>
';

// Insert the new form fields before the submit button
if ($submit_button_position !== false) {
    $updated_content = substr($original_content, 0, $submit_button_position) . $new_form_fields . substr($original_content, $submit_button_position);
    
    // Update the PHP processing section to handle new fields
    // Find the position after the variable initialization
    $init_position = strpos($updated_content, '$title = $property_type_id = $price = $location = $description = $client_id = "";');
    
    // Add new variable initializations
    $new_init = '
// Initialize additional variables for new fields
$min_price = $max_price = $avg_price = $min_area = $max_area = $avg_area = "";
$governorate = $district = $detailed_address = $company_name = "";
$sales_manager = $sales_manager_phone = $sales_responsible = $sales_responsible_phone = "";
$company_phone = $company_address = $project_url = $notes = "";';
    
    if ($init_position !== false) {
        $updated_content = substr($updated_content, 0, $init_position + strlen('$title = $property_type_id = $price = $location = $description = $client_id = "";'))
                         . $new_init
                         . substr($updated_content, $init_position + strlen('$title = $property_type_id = $price = $location = $description = $client_id = "";'));
    }
    
    // Find the position of the SQL insert statement
    $sql_insert_position = strpos($updated_content, '$sql = "INSERT INTO Properties (Title, PropertyTypeID, Price, Location, Description, ClientID, EmployeeID, Status)');
    
    // Update the SQL insert statement to include new fields
    $new_sql_insert = '$sql = "INSERT INTO Properties (
                Title, PropertyTypeID, Price, Location, Description, ClientID, EmployeeID, Status,
                min_price, max_price, avg_price, min_area, max_area, avg_area,
                governorate, district, detailed_address, company_name,
                sales_manager, sales_manager_phone, sales_responsible, sales_responsible_phone,
                company_phone, company_address, project_url, notes
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, \'available\',
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?
            )";';
    
    if ($sql_insert_position !== false) {
        $sql_end_position = strpos($updated_content, ';', $sql_insert_position);
        $updated_content = substr($updated_content, 0, $sql_insert_position)
                         . $new_sql_insert
                         . substr($updated_content, $sql_end_position + 1);
    }
    
    // Find the position of the bind_param statement
    $bind_param_position = strpos($updated_content, '$stmt->bind_param("ssdssii"');
    
    // Update the bind_param statement to include new fields
    $new_bind_param = '$stmt->bind_param("ssdssiiddddddssssssssssss", 
                $param_title, $param_property_type_id, $param_price, $param_location, 
                $param_description, $param_client_id, $param_employee_id,
                $param_min_price, $param_max_price, $param_avg_price, 
                $param_min_area, $param_max_area, $param_avg_area,
                $param_governorate, $param_district, $param_detailed_address, $param_company_name,
                $param_sales_manager, $param_sales_manager_phone, $param_sales_responsible, $param_sales_responsible_phone,
                $param_company_phone, $param_company_address, $param_project_url, $param_notes
            );';
    
    if ($bind_param_position !== false) {
        $bind_param_end_position = strpos($updated_content, ';', $bind_param_position);
        $updated_content = substr($updated_content, 0, $bind_param_position)
                         . $new_bind_param
                         . substr($updated_content, $bind_param_end_position + 1);
    }
    
    // Find the position after the parameter assignments
    $param_assignments_position = strpos($updated_content, '$param_employee_id = $employee_id;');
    
    // Add new parameter assignments
    $new_param_assignments = '
            // New field parameters
            $param_min_price = !empty($_POST["min_price"]) ? $_POST["min_price"] : null;
            $param_max_price = !empty($_POST["max_price"]) ? $_POST["max_price"] : null;
            $param_avg_price = !empty($_POST["avg_price"]) ? $_POST["avg_price"] : null;
            $param_min_area = !empty($_POST["min_area"]) ? $_POST["min_area"] : null;
            $param_max_area = !empty($_POST["max_area"]) ? $_POST["max_area"] : null;
            $param_avg_area = !empty($_POST["avg_area"]) ? $_POST["avg_area"] : null;
            $param_governorate = !empty($_POST["governorate"]) ? trim($_POST["governorate"]) : null;
            $param_district = !empty($_POST["district"]) ? trim($_POST["district"]) : null;
            $param_detailed_address = !empty($_POST["detailed_address"]) ? trim($_POST["detailed_address"]) : null;
            $param_company_name = !empty($_POST["company_name"]) ? trim($_POST["company_name"]) : null;
            $param_sales_manager = !empty($_POST["sales_manager"]) ? trim($_POST["sales_manager"]) : null;
            $param_sales_manager_phone = !empty($_POST["sales_manager_phone"]) ? trim($_POST["sales_manager_phone"]) : null;
            $param_sales_responsible = !empty($_POST["sales_responsible"]) ? trim($_POST["sales_responsible"]) : null;
            $param_sales_responsible_phone = !empty($_POST["sales_responsible_phone"]) ? trim($_POST["sales_responsible_phone"]) : null;
            $param_company_phone = !empty($_POST["company_phone"]) ? trim($_POST["company_phone"]) : null;
            $param_company_address = !empty($_POST["company_address"]) ? trim($_POST["company_address"]) : null;
            $param_project_url = !empty($_POST["project_url"]) ? trim($_POST["project_url"]) : null;
            $param_notes = !empty($_POST["notes"]) ? trim($_POST["notes"]) : null;';
    
    if ($param_assignments_position !== false) {
        $updated_content = substr($updated_content, 0, $param_assignments_position + strlen('$param_employee_id = $employee_id;'))
                         . $new_param_assignments
                         . substr($updated_content, $param_assignments_position + strlen('$param_employee_id = $employee_id;'));
    }
    
    // Write the updated content back to the file
    file_put_contents('add_property.php', $updated_content);
    
    echo "add_property.php updated successfully.";
} else {
    echo "Error: Could not find the insertion point in add_property.php";
}
