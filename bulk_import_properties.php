<?php
/**
 * Bulk Property Import from Excel
 * 
 * Allows managers to import properties from Excel files with flexible sheet configurations.
 * Each sheet can have different property types assigned.
 * 
 * Features:
 * - Upload any Excel file (.xlsx or .xls)
 * - Configure multiple sheets with different property types
 * - Select listing type (Primary/Resale/Rental)
 * - Automatic friendly_id calculation per listing_type
 * - All properties set to 'available' status
 * 
 * Access: Manager role only
 */

session_start();

// Ensure user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Ensure user is manager only
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "manager") {
    $_SESSION["error_message"] = "ليس لديك صلاحية للوصول إلى هذه الصفحة. هذه الخاصية متاحة للمديرين فقط.";
    header("Location: dashboard.php");
    exit;
}

require_once 'db.php';
require_once 'security.php'; // Include security for CSRF
require_once 'navbar.php';

// Fetch property types for the form
$property_types = [];
$sql_types = "SELECT PropertyTypeID, TypeName, TypeName_ar FROM PropertyTypes ORDER BY PropertyTypeID";
if ($result = $conn->query($sql_types)) {
    while ($row = $result->fetch_assoc()) {
        $property_types[] = $row;
    }
    $result->free();
}

$import_message = "";
$import_error = "";
$import_details = [];

/**
 * Get the next friendly_id for a given listing_type
 */
function getNextFriendlyId($conn, $listing_type, &$counter_cache) {
    if (!isset($counter_cache[$listing_type])) {
        $stmt = $conn->prepare("SELECT MAX(friendly_id) as max_id FROM Properties WHERE listing_type = ?");
        $stmt->bind_param("s", $listing_type);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $counter_cache[$listing_type] = ($result['max_id'] ?? 0);
        $stmt->close();
    }
    $counter_cache[$listing_type]++;
    return $counter_cache[$listing_type];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Security validation failed (CSRF). Please refresh the page and try again.");
    }

    $target_dir = "uploads/temp/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $target_file = $target_dir . basename($_FILES["excel_file"]["name"]);
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Validate file type
    if ($fileType != "xlsx" && $fileType != "xls") {
        $import_error = "يُسمح فقط بملفات Excel (.xlsx أو .xls).";
    } else {
        if (move_uploaded_file($_FILES["excel_file"]["tmp_name"], $target_file)) {
            require 'vendor/autoload.php';
            
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($target_file);
                
                // Get sheet configurations from POST
                $sheet_names = $_POST['sheet_name'] ?? [];
                $sheet_types = $_POST['sheet_types'] ?? [];
                $listing_type = $_POST['listing_type'] ?? 'Primary';
                
                // Validate listing_type
                if (!in_array($listing_type, ['Primary', 'Resale', 'Rental'])) {
                    $listing_type = 'Primary';
                }
                
                // Begin transaction
                $conn->begin_transaction();
                
                $total_added = 0;
                $total_skipped = 0;
                $friendly_id_cache = [];
                $employee_id = $_SESSION['id'] ?? $_SESSION['employee_id'] ?? null;
                
                foreach ($sheet_names as $index => $sheet_name) {
                    $sheet_name = trim($sheet_name);
                    if (empty($sheet_name)) {
                        continue;
                    }
                    
                    // Get property types for this sheet
                    $selected_types = $sheet_types[$index] ?? [];
                    if (!is_array($selected_types)) {
                        $selected_types = [$selected_types];
                    }
                    $selected_types = array_map('intval', $selected_types);
                    
                    if (empty($selected_types)) {
                        $import_details[] = "تم تخطي Sheet '{$sheet_name}': لم يتم تحديد أنواع العقارات.";
                        continue;
                    }
                    
                    // Try to get the sheet
                    try {
                        $worksheet = $spreadsheet->getSheetByName($sheet_name);
                        if ($worksheet === null) {
                            $import_details[] = "تم تخطي Sheet '{$sheet_name}': الـ Sheet غير موجود في الملف.";
                            continue;
                        }
                    } catch (Exception $e) {
                        $import_details[] = "تم تخطي Sheet '{$sheet_name}': خطأ في قراءة الـ Sheet.";
                        continue;
                    }
                    
                    $rows = $worksheet->toArray();
                    
                    // Skip header row
                    if (count($rows) > 0) {
                        array_shift($rows);
                    }
                    
                    $sheet_added = 0;
                    $sheet_skipped = 0;
                    
                    foreach ($rows as $row) {
                        // Skip empty rows
                        if (empty(trim($row[0] ?? '')) && empty(trim($row[1] ?? '')) && 
                            empty(trim($row[2] ?? '')) && empty(trim($row[3] ?? ''))) {
                            continue;
                        }
                        
                        // Map columns: A=Company, B=Project, C=District, D=Governorate
                        $company_name = trim($row[0] ?? '');
                        $project_name = trim($row[1] ?? '');
                        $district = trim($row[2] ?? '');
                        $governorate = trim($row[3] ?? '');
                        
                        // Skip if essential data is missing
                        if (empty($project_name) && empty($company_name)) {
                            $sheet_skipped++;
                            continue;
                        }
                        
                        // Calculate friendly_id
                        $friendly_id = getNextFriendlyId($conn, $listing_type, $friendly_id_cache);
                        
                        // Primary property type (first selected)
                        $primary_type_id = $selected_types[0];
                        
                        // Insert into Properties table
                        $insert_sql = "INSERT INTO Properties (
                            PropertyTypeID, ProjectName, company_name, district, governorate,
                            Status, listing_type, friendly_id, EmployeeID, DateAdded
                        ) VALUES (?, ?, ?, ?, ?, 'available', ?, ?, ?, NOW())";
                        
                        $insert_stmt = $conn->prepare($insert_sql);
                        if ($insert_stmt) {
                            $insert_stmt->bind_param(
                                "isssssii",
                                $primary_type_id,
                                $project_name,
                                $company_name,
                                $district,
                                $governorate,
                                $listing_type,
                                $friendly_id,
                                $employee_id
                            );
                            
                            if ($insert_stmt->execute()) {
                                $new_property_id = $conn->insert_id;
                                
                                // Insert property type links for ALL selected types
                                $link_sql = "INSERT INTO property_type_links (property_id, property_type_id) VALUES (?, ?)";
                                $link_stmt = $conn->prepare($link_sql);
                                
                                if ($link_stmt) {
                                    foreach ($selected_types as $type_id) {
                                        $link_stmt->bind_param("ii", $new_property_id, $type_id);
                                        $link_stmt->execute();
                                    }
                                    $link_stmt->close();
                                }
                                
                                $sheet_added++;
                                $total_added++;
                            } else {
                                $sheet_skipped++;
                                $total_skipped++;
                            }
                            $insert_stmt->close();
                        } else {
                            $sheet_skipped++;
                            $total_skipped++;
                        }
                    }
                    
                    $type_names = [];
                    foreach ($selected_types as $tid) {
                        foreach ($property_types as $pt) {
                            if ($pt['PropertyTypeID'] == $tid) {
                                $type_names[] = $pt['TypeName_ar'] ?? $pt['TypeName'];
                                break;
                            }
                        }
                    }
                    $import_details[] = "Sheet '{$sheet_name}': تمت إضافة {$sheet_added} عقار (" . implode(', ', $type_names) . ")";
                }
                
                // Commit transaction
                $conn->commit();
                
                $listing_type_ar = [
                    'Primary' => 'العقارات الأساسية',
                    'Resale' => 'إعادة البيع',
                    'Rental' => 'الإيجار'
                ];
                
                $import_message = "تم الاستيراد بنجاح! تمت إضافة {$total_added} عقار إلى " . ($listing_type_ar[$listing_type] ?? $listing_type) . ".";
                
                if ($total_skipped > 0) {
                    $import_message .= " (تم تخطي {$total_skipped} صف).";
                }
                
                // Delete the uploaded file after processing
                unlink($target_file);
                
            } catch (Exception $e) {
                $conn->rollback();
                $import_error = "خطأ في معالجة ملف Excel: " . $e->getMessage();
            }
        } else {
            $import_error = "حدث خطأ في رفع الملف.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد العقارات من Excel</title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="rtl_styles.css">
    <style>
        .import-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .import-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .import-header h1 {
            color: #020d24;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .import-header p {
            color: #666;
        }
        
        .section-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2em;
            color: #020d24;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #b08a0e;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-input:focus, .form-select:focus {
            border-color: #b08a0e;
            outline: none;
        }
        
        .sheet-config {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .sheet-title {
            font-weight: 600;
            color: #020d24;
        }
        
        .remove-sheet-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .remove-sheet-btn:hover {
            background: #c82333;
        }
        
        .property-types-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
        }
        
        .type-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .type-checkbox:hover {
            border-color: #b08a0e;
            background: #fffbeb;
        }
        
        .type-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #b08a0e;
        }
        
        .add-sheet-btn {
            background: linear-gradient(135deg, #b08a0e 0%, #8a6b08 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 20px auto;
        }
        
        .add-sheet-btn:hover {
            background: linear-gradient(135deg, #c9a011 0%, #b08a0e 100%);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #020d24 0%, #0a1f4a 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform 0.2s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-info {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        
        .import-details {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .import-details ul {
            margin: 0;
            padding-right: 20px;
        }
        
        .import-details li {
            margin-bottom: 5px;
        }
        
        .note-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.95em;
        }
        
        .note-box strong {
            color: #856404;
        }
        
        .user-info {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="import-container">
    <div class="import-header">
        <h1>📦 استيراد العقارات من Excel</h1>
        <p>استيراد عقارات من أي ملف Excel مع تحديد أنواع العقارات لكل Sheet</p>
        <div class="user-info">
            User: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?> | 
            Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Unknown'); ?> | 
            ID: <?php echo htmlspecialchars($_SESSION['id'] ?? $_SESSION['employee_id'] ?? 'N/A'); ?> |
            Property Types: <?php echo count($property_types); ?>
        </div>
    </div>

    <?php if (!empty($import_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($import_message); ?>
            <?php if (!empty($import_details)): ?>
                <div class="import-details">
                    <ul>
                        <?php foreach ($import_details as $detail): ?>
                            <li><?php echo htmlspecialchars($detail); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($import_error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($import_error); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="importForm">
        <?php echo csrf_input(); ?>
        
        <!-- File & Listing Type Section -->
        <div class="section-card">
            <div class="section-title">
                📁 الملف و نوع القائمة
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="excel_file">ملف Excel:</label>
                    <input type="file" name="excel_file" id="excel_file" class="form-input" accept=".xlsx,.xls" required>
                </div>
                
                <div class="form-group">
                    <label for="listing_type">نوع العقارات:</label>
                    <select name="listing_type" id="listing_type" class="form-select" required>
                        <option value="Primary">العقارات الأساسية (Primary)</option>
                        <option value="Resale">إعادة البيع (Resale)</option>
                        <option value="Rental">الإيجار (Rental)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sheets Configuration Section -->
        <div class="section-card">
            <div class="section-title">
                📋 إعدادات الـ Sheets
            </div>
            
            <div class="note-box">
                <strong>ملاحظة:</strong> يجب أن يكون ترتيب الأعمدة في الملف كالتالي:<br>
                A: اسم الشركة | B: اسم المشروع | C: اسم المنطقة | D: المحافظة
            </div>
            
            <div id="sheetsContainer">
                <!-- Sheet 1 -->
                <div class="sheet-config" data-sheet-index="0">
                    <div class="sheet-header">
                        <span class="sheet-title">Sheet 1</span>
                    </div>
                    
                    <div class="form-group">
                        <label>اسم الـ Sheet:</label>
                        <input type="text" name="sheet_name[]" class="form-input" placeholder="مثال: سكني العاصمه" required>
                    </div>
                    
                    <div class="form-group">
                        <label>أنواع العقارات:</label>
                        <div class="property-types-grid">
                            <?php foreach ($property_types as $type): ?>
                                <label class="type-checkbox">
                                    <input type="checkbox" name="sheet_types[0][]" value="<?php echo $type['PropertyTypeID']; ?>">
                                    <?php echo htmlspecialchars($type['TypeName_ar'] ?? $type['TypeName']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="button" class="add-sheet-btn" id="addSheetBtn">
                + إضافة Sheet آخر
            </button>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="submit-btn">
            🚀 بدء الاستيراد
        </button>
    </form>
</div>

<script>
let sheetCounter = 1;

document.getElementById('addSheetBtn').addEventListener('click', function() {
    sheetCounter++;
    const container = document.getElementById('sheetsContainer');
    
    const sheetConfig = document.createElement('div');
    sheetConfig.className = 'sheet-config';
    sheetConfig.dataset.sheetIndex = sheetCounter - 1;
    
    let typesHtml = '';
    <?php foreach ($property_types as $type): ?>
    typesHtml += `
        <label class="type-checkbox">
            <input type="checkbox" name="sheet_types[${sheetCounter - 1}][]" value="<?php echo $type['PropertyTypeID']; ?>">
            <?php echo htmlspecialchars($type['TypeName_ar'] ?? $type['TypeName']); ?>
        </label>
    `;
    <?php endforeach; ?>
    
    sheetConfig.innerHTML = `
        <div class="sheet-header">
            <span class="sheet-title">Sheet ${sheetCounter}</span>
            <button type="button" class="remove-sheet-btn" onclick="removeSheet(this)">حذف</button>
        </div>
        
        <div class="form-group">
            <label>اسم الـ Sheet:</label>
            <input type="text" name="sheet_name[]" class="form-input" placeholder="مثال: اداري و تجاري العاصمه">
        </div>
        
        <div class="form-group">
            <label>أنواع العقارات:</label>
            <div class="property-types-grid">
                ${typesHtml}
            </div>
        </div>
    `;
    
    container.appendChild(sheetConfig);
});

function removeSheet(btn) {
    const sheetConfig = btn.closest('.sheet-config');
    sheetConfig.remove();
    
    // Update sheet titles
    const sheets = document.querySelectorAll('.sheet-config');
    sheets.forEach((sheet, index) => {
        sheet.querySelector('.sheet-title').textContent = `Sheet ${index + 1}`;
    });
}

// Form validation
document.getElementById('importForm').addEventListener('submit', function(e) {
    const file = document.getElementById('excel_file').files[0];
    if (!file) {
        alert('يرجى اختيار ملف Excel');
        e.preventDefault();
        return;
    }
    
    const sheetNames = document.querySelectorAll('input[name="sheet_name[]"]');
    let hasValidSheet = false;
    
    sheetNames.forEach((input, index) => {
        if (input.value.trim()) {
            const checkboxes = document.querySelectorAll(`input[name="sheet_types[${index}][]"]:checked`);
            if (checkboxes.length > 0) {
                hasValidSheet = true;
            }
        }
    });
    
    if (!hasValidSheet) {
        alert('يرجى إدخال اسم Sheet واحد على الأقل واختيار نوع عقار واحد على الأقل');
        e.preventDefault();
    }
});
</script>

</body>
</html>
