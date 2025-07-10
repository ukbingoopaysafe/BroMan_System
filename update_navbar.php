<?php
// Add the import Excel link to the navbar.php file
$navbar_content = file_get_contents('navbar.php');

// Find the position to insert the new link (before the closing </ul>)
$insert_position = strrpos($navbar_content, '</ul>');

if ($insert_position !== false) {
    // Only add for admin users
    $new_link = '
            <?php if(isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
            <li><a href="import_excel.php">استيراد بيانات إكسيل</a></li>
            <?php endif; ?>';
    
    // Insert the new link
    $updated_navbar = substr($navbar_content, 0, $insert_position) . $new_link . substr($navbar_content, $insert_position);
    
    // Write the updated content back to the file
    file_put_contents('navbar.php', $updated_navbar);
    
    echo "Navbar updated successfully.";
} else {
    echo "Error: Could not find the insertion point in navbar.php";
}
