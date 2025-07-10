<?php
// This script updates the index_properties.php file to include the new fields from Excel data

// Read the original file
$original_content = file_get_contents('index_properties.php');

// Find the position of the SQL query for fetching properties
$sql_query_position = strpos($original_content, '$sql = "SELECT p.PropertyID, p.Title, pt.TypeName AS PropertyTypeName, p.Price, p.Location');

// Update the SQL query to include new fields
$new_sql_query = '$sql = "SELECT p.PropertyID, p.Title, pt.TypeName AS PropertyTypeName, p.Price, p.Location, 
               p.Description, p.Status, p.DateAdded, c.FullName AS ClientName, e.FullName AS EmployeeName,
               p.min_price, p.max_price, p.avg_price, p.min_area, p.max_area, p.avg_area,
               p.governorate, p.district, p.detailed_address, p.company_name,
               p.sales_manager, p.sales_manager_phone, p.sales_responsible, p.sales_responsible_phone,
               p.company_phone, p.company_address, p.project_url, p.notes
        FROM Properties p
        LEFT JOIN PropertyTypes pt ON p.PropertyTypeID = pt.PropertyTypeID
        LEFT JOIN Clients c ON p.ClientID = c.ClientID
        LEFT JOIN Employees e ON p.EmployeeID = e.EmployeeID"';

if ($sql_query_position !== false) {
    $sql_query_end_position = strpos($original_content, 'FROM Properties p', $sql_query_position);
    $updated_content = substr($original_content, 0, $sql_query_position)
                     . $new_sql_query
                     . substr($original_content, $sql_query_end_position + strlen('FROM Properties p') - 1);
    
    // Find the table header row in the HTML
    $table_header_position = strpos($updated_content, '<th>Actions</th>');
    
    // Add new columns to the table header
    $new_table_header = '<th>Company</th>
                    <th>Min-Max Area</th>
                    <th>District</th>
                    <th>Sales Contact</th>
                    <th>Actions</th>';
    
    if ($table_header_position !== false) {
        $updated_content = substr($updated_content, 0, $table_header_position)
                         . $new_table_header
                         . substr($updated_content, $table_header_position + strlen('<th>Actions</th>'));
    }
    
    // Find the table row data in the HTML
    $table_row_position = strpos($updated_content, '<td class="actions">');
    
    // Add new columns to the table row data
    $new_table_row = '<td><?php echo htmlspecialchars($property[\'company_name\'] ?: \'N/A\'); ?></td>
                        <td><?php 
                            if (!empty($property[\'min_area\']) || !empty($property[\'max_area\'])) {
                                echo htmlspecialchars($property[\'min_area\'] ?: \'-\') . \' - \' . htmlspecialchars($property[\'max_area\'] ?: \'-\') . \' m²\';
                            } else {
                                echo \'N/A\';
                            }
                        ?></td>
                        <td><?php echo htmlspecialchars($property[\'district\'] ?: ($property[\'governorate\'] ?: \'N/A\')); ?></td>
                        <td><?php 
                            if (!empty($property[\'sales_manager\'])) {
                                echo htmlspecialchars($property[\'sales_manager\']) . (!empty($property[\'sales_manager_phone\']) ? \' (\' . htmlspecialchars($property[\'sales_manager_phone\']) . \')\' : \'\');
                            } elseif (!empty($property[\'sales_responsible\'])) {
                                echo htmlspecialchars($property[\'sales_responsible\']) . (!empty($property[\'sales_responsible_phone\']) ? \' (\' . htmlspecialchars($property[\'sales_responsible_phone\']) . \')\' : \'\');
                            } else {
                                echo \'N/A\';
                            }
                        ?></td>
                        <td class="actions">';
    
    if ($table_row_position !== false) {
        $updated_content = substr($updated_content, 0, $table_row_position)
                         . $new_table_row
                         . substr($updated_content, $table_row_position + strlen('<td class="actions">'));
    }
    
    // Update the modal content to include more property details
    $modal_body_position = strpos($updated_content, '<div class="modal-body">');
    
    // Add new details to the modal body
    $new_modal_body = '<div class="modal-body">
            <div class="property-details">
                <h3>Basic Information</h3>
                <p><strong>Description:</strong> <span id="modalDesc"></span></p>
                
                <h3>Price Information</h3>
                <p><strong>Min Price:</strong> <span id="modalMinPrice"></span></p>
                <p><strong>Max Price:</strong> <span id="modalMaxPrice"></span></p>
                <p><strong>Average Price:</strong> <span id="modalAvgPrice"></span></p>
                
                <h3>Area Information</h3>
                <p><strong>Min Area:</strong> <span id="modalMinArea"></span></p>
                <p><strong>Max Area:</strong> <span id="modalMaxArea"></span></p>
                <p><strong>Average Area:</strong> <span id="modalAvgArea"></span></p>
                
                <h3>Location Information</h3>
                <p><strong>Governorate:</strong> <span id="modalGovernorate"></span></p>
                <p><strong>District:</strong> <span id="modalDistrict"></span></p>
                <p><strong>Detailed Address:</strong> <span id="modalDetailedAddress"></span></p>
                
                <h3>Contact Information</h3>
                <p><strong>Company:</strong> <span id="modalCompany"></span></p>
                <p><strong>Company Address:</strong> <span id="modalCompanyAddress"></span></p>
                <p><strong>Company Phone:</strong> <span id="modalCompanyPhone"></span></p>
                <p><strong>Sales Manager:</strong> <span id="modalSalesManager"></span></p>
                <p><strong>Sales Manager Phone:</strong> <span id="modalSalesManagerPhone"></span></p>
                <p><strong>Sales Responsible:</strong> <span id="modalSalesResponsible"></span></p>
                <p><strong>Sales Responsible Phone:</strong> <span id="modalSalesResponsiblePhone"></span></p>
                
                <h3>Additional Information</h3>
                <p><strong>Project URL:</strong> <span id="modalProjectUrl"></span></p>
                <p><strong>Notes:</strong> <span id="modalNotes"></span></p>
            </div>';
    
    if ($modal_body_position !== false) {
        $modal_body_end_position = strpos($updated_content, '</div>', $modal_body_position);
        $updated_content = substr($updated_content, 0, $modal_body_position)
                         . $new_modal_body
                         . substr($updated_content, $modal_body_end_position);
    }
    
    // Update the JavaScript to populate the modal with new fields
    $js_modal_position = strpos($updated_content, 'modalTitle.textContent = this.getAttribute(\'data-title\');');
    
    // Add new JavaScript for populating modal fields
    $new_js_modal = 'modalTitle.textContent = this.getAttribute(\'data-title\');
            modalDesc.textContent = this.getAttribute(\'data-desc\') || \'Not provided\';
            
            // Price information
            document.getElementById(\'modalMinPrice\').textContent = this.getAttribute(\'data-min-price\') || \'Not provided\';
            document.getElementById(\'modalMaxPrice\').textContent = this.getAttribute(\'data-max-price\') || \'Not provided\';
            document.getElementById(\'modalAvgPrice\').textContent = this.getAttribute(\'data-avg-price\') || \'Not provided\';
            
            // Area information
            document.getElementById(\'modalMinArea\').textContent = this.getAttribute(\'data-min-area\') ? this.getAttribute(\'data-min-area\') + \' m²\' : \'Not provided\';
            document.getElementById(\'modalMaxArea\').textContent = this.getAttribute(\'data-max-area\') ? this.getAttribute(\'data-max-area\') + \' m²\' : \'Not provided\';
            document.getElementById(\'modalAvgArea\').textContent = this.getAttribute(\'data-avg-area\') ? this.getAttribute(\'data-avg-area\') + \' m²\' : \'Not provided\';
            
            // Location information
            document.getElementById(\'modalGovernorate\').textContent = this.getAttribute(\'data-governorate\') || \'Not provided\';
            document.getElementById(\'modalDistrict\').textContent = this.getAttribute(\'data-district\') || \'Not provided\';
            document.getElementById(\'modalDetailedAddress\').textContent = this.getAttribute(\'data-detailed-address\') || \'Not provided\';
            
            // Contact information
            document.getElementById(\'modalCompany\').textContent = this.getAttribute(\'data-company\') || \'Not provided\';
            document.getElementById(\'modalCompanyAddress\').textContent = this.getAttribute(\'data-company-address\') || \'Not provided\';
            document.getElementById(\'modalCompanyPhone\').textContent = this.getAttribute(\'data-company-phone\') || \'Not provided\';
            document.getElementById(\'modalSalesManager\').textContent = this.getAttribute(\'data-sales-manager\') || \'Not provided\';
            document.getElementById(\'modalSalesManagerPhone\').textContent = this.getAttribute(\'data-sales-manager-phone\') || \'Not provided\';
            document.getElementById(\'modalSalesResponsible\').textContent = this.getAttribute(\'data-sales-responsible\') || \'Not provided\';
            document.getElementById(\'modalSalesResponsiblePhone\').textContent = this.getAttribute(\'data-sales-responsible-phone\') || \'Not provided\';
            
            // Additional information
            document.getElementById(\'modalProjectUrl\').textContent = this.getAttribute(\'data-project-url\') || \'Not provided\';
            document.getElementById(\'modalNotes\').textContent = this.getAttribute(\'data-notes\') || \'Not provided\';';
    
    if ($js_modal_position !== false) {
        $js_modal_end_position = strpos($updated_content, 'modal.style.display = \'block\';', $js_modal_position);
        $updated_content = substr($updated_content, 0, $js_modal_position)
                         . $new_js_modal
                         . substr($updated_content, $js_modal_end_position);
    }
    
    // Update the button attributes to include new data attributes
    $button_attr_position = strpos($updated_content, 'data-desc="<?php echo htmlspecialchars($property[\'Description\'], ENT_QUOTES); ?>"');
    
    // Add new data attributes to the button
    $new_button_attr = 'data-desc="<?php echo htmlspecialchars($property[\'Description\'], ENT_QUOTES); ?>"
                                    data-min-price="<?php echo htmlspecialchars($property[\'min_price\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-max-price="<?php echo htmlspecialchars($property[\'max_price\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-avg-price="<?php echo htmlspecialchars($property[\'avg_price\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-min-area="<?php echo htmlspecialchars($property[\'min_area\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-max-area="<?php echo htmlspecialchars($property[\'max_area\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-avg-area="<?php echo htmlspecialchars($property[\'avg_area\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-governorate="<?php echo htmlspecialchars($property[\'governorate\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-district="<?php echo htmlspecialchars($property[\'district\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-detailed-address="<?php echo htmlspecialchars($property[\'detailed_address\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-company="<?php echo htmlspecialchars($property[\'company_name\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-company-address="<?php echo htmlspecialchars($property[\'company_address\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-company-phone="<?php echo htmlspecialchars($property[\'company_phone\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-sales-manager="<?php echo htmlspecialchars($property[\'sales_manager\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-sales-manager-phone="<?php echo htmlspecialchars($property[\'sales_manager_phone\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-sales-responsible="<?php echo htmlspecialchars($property[\'sales_responsible\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-sales-responsible-phone="<?php echo htmlspecialchars($property[\'sales_responsible_phone\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-project-url="<?php echo htmlspecialchars($property[\'project_url\'] ?? \'\', ENT_QUOTES); ?>"
                                    data-notes="<?php echo htmlspecialchars($property[\'notes\'] ?? \'\', ENT_QUOTES); ?>"';
    
    if ($button_attr_position !== false) {
        $button_attr_end_position = strpos($updated_content, 'data-title="', $button_attr_position);
        $updated_content = substr($updated_content, 0, $button_attr_position)
                         . $new_button_attr
                         . substr($updated_content, $button_attr_position + strlen('data-desc="<?php echo htmlspecialchars($property[\'Description\'], ENT_QUOTES); ?>"'));
    }
    
    // Write the updated content back to the file
    file_put_contents('index_properties.php', $updated_content);
    
    echo "index_properties.php updated successfully.";
} else {
    echo "Error: Could not find the SQL query in index_properties.php";
}
