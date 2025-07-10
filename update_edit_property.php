<?php
// This script updates the edit_property.php file to include the new fields from Excel data

// Read the original file
$original_content = file_get_contents('edit_property.php');

// Find the position to insert new form fields (before the submit button)
$submit_button_position = strpos($original_content, '<div>
        <input type="submit" value="Update Property"');

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
    $init_position = strpos($updated_content, '$title = $property_type_id = $price = $location = $description = $client_id = $status = "";');
    
    // Add new variable initializations
    $new_init = '
// Initialize additional variables for new fields
$min_price = $max_price = $avg_price = $min_area = $max_area = $avg_area = "";
$governorate = $district = $detailed_address = $company_name = "";
$sales_manager = $sales_manager_phone = $sales_responsible = $sales_responsible_phone = "";
$company_phone = $company_address = $project_url = $notes = "";';
    
    if ($init_position !== false) {
        $updated_content = substr($updated_content, 0, $init_position + strlen('$title = $property_type_id = $price = $location = $description = $client_id = $status = "";'))
                         . $new_init
                         . substr($updated_content, $init_position + strlen('$title = $property_type_id = $price = $location = $description = $client_id = $status = "";'));
    }
    
    // Find the position of the SQL select statement
    $sql_select_position = strpos($updated_content, '$sql = "SELECT * FROM Properties WHERE PropertyID = ?";');
    
    // Update the SQL select statement to include new fields
    $new_sql_select = '$sql = "SELECT * FROM Properties WHERE PropertyID = ?";';
    
    // Find the position after fetching property data
    $fetch_position = strpos($updated_content, '$title = $row["Title"];');
    
    // Add new field assignments
    $new_fetch_assignments = '
            $min_price = $row["min_price"] ?? null;
            $max_price = $row["max_price"] ?? null;
            $avg_price = $row["avg_price"] ?? null;
            $min_area = $row["min_area"] ?? null;
            $max_area = $row["max_area"] ?? null;
            $avg_area = $row["avg_area"] ?? null;
            $governorate = $row["governorate"] ?? null;
            $district = $row["district"] ?? null;
            $detailed_address = $row["detailed_address"] ?? null;
            $company_name = $row["company_name"] ?? null;
            $sales_manager = $row["sales_manager"] ?? null;
            $sales_manager_phone = $row["sales_manager_phone"] ?? null;
            $sales_responsible = $row["sales_responsible"] ?? null;
            $sales_responsible_phone = $row["sales_responsible_phone"] ?? null;
            $company_phone = $row["company_phone"] ?? null;
            $company_address = $row["company_address"] ?? null;
            $project_url = $row["project_url"] ?? null;
            $notes = $row["notes"] ?? null;';
    
    if ($fetch_position !== false) {
        // Find the end of the existing assignments
        $fetch_end_position = strpos($updated_content, '$status = $row["Status"];');
        $updated_content = substr($updated_content, 0, $fetch_end_position + strlen('$status = $row["Status"];'))
                         . $new_fetch_assignments
                         . substr($updated_content, $fetch_end_position + strlen('$status = $row["Status"];'));
    }
    
    // Find the position of the SQL update statement
    $sql_update_position = strpos($updated_content, '$sql = "UPDATE Properties SET Title = ?, PropertyTypeID = ?, Price = ?, Location = ?, Description = ?, ClientID = ?, Status = ? WHERE PropertyID = ?";');
    
    // Update the SQL update statement to include new fields
    $new_sql_update = '$sql = "UPDATE Properties SET 
                Title = ?, PropertyTypeID = ?, Price = ?, Location = ?, Description = ?, ClientID = ?, Status = ?,
                min_price = ?, max_price = ?, avg_price = ?, 
                min_area = ?, max_area = ?, avg_area = ?,
                governorate = ?, district = ?, detailed_address = ?, company_name = ?,
                sales_manager = ?, sales_manager_phone = ?, sales_responsible = ?, sales_responsible_phone = ?,
                company_phone = ?, company_address = ?, project_url = ?, notes = ?
                WHERE PropertyID = ?";';
    
    if ($sql_update_position !== false) {
        $sql_update_end_position = strpos($updated_content, ';', $sql_update_position);
        $updated_content = substr($updated_content, 0, $sql_update_position)
                         . $new_sql_update
                         . substr($updated_content, $sql_update_end_position + 1);
    }
    
    // Find the position of the bind_param statement
    $bind_param_position = strpos($updated_content, '$stmt->bind_param("sidssis"');
    
    // Update the bind_param statement to include new fields
    $new_bind_param = '$stmt->bind_param("sidssisddddddsssssssssssssi", 
                $param_title, $param_property_type_id, $param_price, $param_location, 
                $param_description, $param_client_id, $param_status,
                $param_min_price, $param_max_price, $param_avg_price, 
                $param_min_area, $param_max_area, $param_avg_area,
                $param_governorate, $param_district, $param_detailed_address, $param_company_name,
                $param_sales_manager, $param_sales_manager_phone, $param_sales_responsible, $param_sales_responsible_phone,
                $param_company_phone, $param_company_address, $param_project_url, $param_notes,
                $param_id
            );';
    
    if ($bind_param_position !== false) {
        $bind_param_end_position = strpos($updated_content, ';', $bind_param_position);
        $updated_content = substr($updated_content, 0, $bind_param_position)
                         . $new_bind_param
                         . substr($updated_content, $bind_param_end_position + 1);
    }
    
    // Find the position after the parameter assignments
    $param_assignments_position = strpos($updated_content, '$param_status = $status;');
    
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
        $updated_content = substr($updated_content, 0, $param_assignments_position + strlen('$param_status = $status;'))
                         . $new_param_assignments
                         . substr($updated_content, $param_assignments_position + strlen('$param_status = $status;'));
    }
    
    // Write the updated content back to the file
    file_put_contents('edit_property.php', $updated_content);
    
    echo "edit_property.php updated successfully.";
} else {
    echo "Error: Could not find the insertion point in edit_property.php";
}
