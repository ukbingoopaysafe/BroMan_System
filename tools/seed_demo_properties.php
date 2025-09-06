<?php
require_once __DIR__ . '/../db.php';

// Seeder runs without session/security to avoid header/session issues when executed from CLI

// Simple seeder for demo properties tailored for Egyptian market
// Run from CLI (php tools/seed_demo_properties.php) or include via browser (not recommended on production)

$now = date('Y-m-d H:i:s');

$demo = [
    [
        'PropertyTypeID' => 1, // Residential
        'Title' => 'شقة 3 غرف في مصر الجديدة',
        'Description' => 'شقة واسعة بالبرج الرابع قريب من الخدمات والمدارس.',
        'Location' => 'شارع عثمان بن عفان، مصر الجديدة',
        'Area' => 120.0,
        'Bedrooms' => null,
        'Status' => 'available',
        'listing_type' => 'Resale',
        'EmployeeID' => 1,
        'unit_design' => 'Classic',
        'finishing_type_id' => 1,
        'compound_area' => null,
        'down_payment_percentage' => null,
        'payment_years' => null,
        'delivery_date' => null,
        'ProjectName' => 'مبنى الحرية',
        'min_area' => null,
        'max_area' => null,
        'avg_area' => null,
        'district' => 'مصر الجديدة',
        'governorate' => 'القاهرة',
        'company_name' => null,
        'FloorsCount' => 10,
        'sales_manager_name' => null,
        'sales_manager_phone' => null,
        'salesperson_name' => null,
        'salesperson_phone' => null,
        'year_built' => 2010,
        'unit_floor' => '4',
        'net_area' => 118.0,
        'loading_percentage' => null,
        'price_cash' => 2500000.00,
        'price_installments' => null,
        'meter_price_cash' => 20833.33,
        'meter_price_installments' => null,
        'seller_type' => 'مالك',
        'seller_name' => 'محمود علي',
        'seller_phone' => '01001234567',
        'latitude' => 30.06263,
        'longitude' => 31.31248,
    ],
    [
        'PropertyTypeID' => 2, // Coastal
        'Title' => 'فيلا على البحر في الساحل الشمالي',
        'Description' => 'فيلا فاخرة على البحر بإطلالة مباشرة ومسبح خاص.',
        'Location' => 'الساحل الشمالي - كورنيش البحر',
        'Area' => 450.0,
        'Bedrooms' => null,
        'Status' => 'available',
        'listing_type' => 'Primary',
        'EmployeeID' => 1,
        'unit_design' => 'Modern',
        'finishing_type_id' => 2,
        'compound_area' => 1000.0,
        'down_payment_percentage' => 10.0,
        'payment_years' => 5,
        'delivery_date' => null,
        'ProjectName' => 'لازوردي',
        'min_area' => null,
        'max_area' => null,
        'avg_area' => null,
        'district' => 'الساحل الشمالي',
        'governorate' => 'الإسكندرية',
        'company_name' => 'شركة البحر الأزرق',
        'FloorsCount' => 2,
        'sales_manager_name' => 'مريم',
        'sales_manager_phone' => '01205551234',
        'salesperson_name' => 'أحمد',
        'salesperson_phone' => '01119998877',
        'year_built' => 2023,
        'unit_floor' => null,
        'net_area' => 440.0,
        'loading_percentage' => 5.0,
        'price_cash' => 18000000.00,
        'price_installments' => 20000000.00,
        'meter_price_cash' => 40000.00,
        'meter_price_installments' => 45454.54,
        'seller_type' => 'شركة مطورة',
        'seller_name' => 'شركة البحر الأزرق',
        'seller_phone' => '01209990000',
        'latitude' => 31.2001,
        'longitude' => 29.9187,
    ],
    [
        'PropertyTypeID' => 3, // Commercial
        'Title' => 'محل تجاري في مول بالقاهرة الجديدة',
        'Description' => 'محل واجهة رئيسية في مول تجاري، مناسب للمتاجر والمطاعم.',
        'Location' => 'القاهرة الجديدة - التسعين',
        'Area' => 60.0,
        'Bedrooms' => null,
        'Status' => 'available',
        'listing_type' => 'Resale',
        'EmployeeID' => 1,
        'unit_design' => 'Retail',
        'finishing_type_id' => 1,
        'compound_area' => null,
        'down_payment_percentage' => null,
        'payment_years' => null,
        'delivery_date' => null,
        'ProjectName' => null,
        'min_area' => null,
        'max_area' => null,
        'avg_area' => null,
        'district' => 'التسعين',
        'governorate' => 'القاهرة',
        'company_name' => null,
        'FloorsCount' => 1,
        'sales_manager_name' => null,
        'sales_manager_phone' => null,
        'salesperson_name' => null,
        'salesperson_phone' => null,
        'year_built' => 2015,
        'unit_floor' => 'Ground',
        'net_area' => 60.0,
        'loading_percentage' => null,
        'price_cash' => 1200000.00,
        'price_installments' => null,
        'meter_price_cash' => 20000.00,
        'meter_price_installments' => null,
        'seller_type' => 'وسيط',
        'seller_name' => 'شركة تسويق',
        'seller_phone' => '01002223344',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
    ],
    [
        'PropertyTypeID' => 4, // Office
        'Title' => 'مبنى إداري للإيجار بالعاصمة الإدارية',
        'Description' => 'مكاتب حديثة بمساحات مرنة في منطقة الأعمال بالعاصمة الإدارية.',
        'Location' => 'العاصمة الإدارية - المنطقة المالية',
        'Area' => 300.0,
        'Bedrooms' => null,
        'Status' => 'available',
        'listing_type' => 'Primary',
        'EmployeeID' => 1,
        'unit_design' => 'Office Suite',
        'finishing_type_id' => 2,
        'compound_area' => null,
        'down_payment_percentage' => null,
        'payment_years' => null,
        'delivery_date' => null,
        'ProjectName' => null,
        'min_area' => null,
        'max_area' => null,
        'avg_area' => null,
        'district' => 'المنطقة المالية',
        'governorate' => 'القاهرة',
        'company_name' => null,
        'FloorsCount' => 8,
        'sales_manager_name' => null,
        'sales_manager_phone' => null,
        'salesperson_name' => null,
        'salesperson_phone' => null,
        'year_built' => 2020,
        'unit_floor' => null,
        'net_area' => 300.0,
        'loading_percentage' => null,
        'price_cash' => 12000000.00,
        'price_installments' => null,
        'meter_price_cash' => 40000.00,
        'meter_price_installments' => null,
        'seller_type' => 'مالك',
        'seller_name' => 'مطور محلي',
        'seller_phone' => '01005556677',
        'latitude' => 30.0131,
        'longitude' => 31.2106,
    ],
    [
        'PropertyTypeID' => 5, // Medical
        'Title' => 'عيادة طبية للبيع في المهندسين',
        'Description' => 'عيادة مجهزة بالقرب من المستشفيات ومراكز التشخيص.',
        'Location' => 'المهندسين - شارع السودان',
        'Area' => 80.0,
        'Bedrooms' => null,
        'Status' => 'available',
        'listing_type' => 'Resale',
        'EmployeeID' => 1,
        'unit_design' => 'Clinic',
        'finishing_type_id' => 1,
        'compound_area' => null,
        'down_payment_percentage' => null,
        'payment_years' => null,
        'delivery_date' => null,
        'ProjectName' => null,
        'min_area' => null,
        'max_area' => null,
        'avg_area' => null,
        'district' => 'المهندسين',
        'governorate' => 'القاهرة',
        'company_name' => null,
        'FloorsCount' => 1,
        'sales_manager_name' => null,
        'sales_manager_phone' => null,
        'salesperson_name' => null,
        'salesperson_phone' => null,
        'year_built' => 2005,
        'unit_floor' => '2',
        'net_area' => 78.0,
        'loading_percentage' => null,
        'price_cash' => 2200000.00,
        'price_installments' => null,
        'meter_price_cash' => 27500.00,
        'meter_price_installments' => null,
        'seller_type' => 'وكيل',
        'seller_name' => 'مكتب العقار',
        'seller_phone' => '01112223344',
        'latitude' => 30.0442,
        'longitude' => 31.2233,
    ],
];

// Build and execute dynamic insert per demo entry to avoid column/placeholder mismatches
try {
    $conn->begin_transaction();
    foreach ($demo as $d) {
        // Prepare columns and values dynamically
        $cols = array_keys($d);
        $vals = array_values($d);

        // Append DateAdded as NOW()
        $colsSql = implode(', ', $cols) . ', DateAdded';

        $placeholders = implode(', ', array_fill(0, count($vals), '?')) . ', NOW()';

        $sql = "INSERT INTO Properties ({$colsSql}) VALUES ({$placeholders})";
        $stmt = $conn->prepare($sql);

        // Build types string
        $types = '';
        $bindVars = [];
        foreach ($vals as $v) {
            if (is_int($v)) { $types .= 'i'; }
            elseif (is_float($v)) { $types .= 'd'; }
            else { $types .= 's'; }
            // Ensure NULLs are passed as null variables
            $bindVars[] = $v;
        }

        // Call safe_bind_param with dynamic types
        $callArgs = array_merge([$stmt, $types], $bindVars);
        // Use call_user_func_array to forward arguments to safe_bind_param
        call_user_func_array('safe_bind_param', $callArgs);
        $stmt->execute();
        $id = $conn->insert_id;
        echo "Inserted property ID: $id\n";

        // Create placeholder media files and insert rows
        $baseDir = __DIR__ . '/../uploads/';
        @mkdir($baseDir . 'images/', 0755, true);
        @mkdir($baseDir . 'audio/', 0755, true);
        @mkdir($baseDir . 'videos/', 0755, true);

        $imgName = 'property_' . $id . '_placeholder.jpg';
        file_put_contents($baseDir . 'images/' . $imgName, 'placeholder');
        $imgRel = 'uploads/images/' . $imgName;
        $insImg = $conn->prepare("INSERT INTO property_images (property_id, file_path, is_primary) VALUES (?, ?, ?)");
        $is_primary = 1;
        $insImg->bind_param("isi", $id, $imgRel, $is_primary);
        $insImg->execute();
        $insImg->close();

        $vidName = 'property_' . $id . '_placeholder.mp4';
        file_put_contents($baseDir . 'videos/' . $vidName, 'placeholder');
        $vidRel = 'uploads/videos/' . $vidName;
        $insVid = $conn->prepare("INSERT INTO property_videos (PropertyID, VideoPath, VideoName, VideoSize, EmployeeID) VALUES (?, ?, ?, ?, ?)");
        $vsize = filesize($baseDir . 'videos/' . $vidName);
        $emp = 1;
        $insVid->bind_param("issii", $id, $vidRel, $vidName, $vsize, $emp);
        $insVid->execute();
        $insVid->close();

        $audName = 'property_' . $id . '_placeholder.mp3';
        file_put_contents($baseDir . 'audio/' . $audName, 'placeholder');
        $audRel = 'uploads/audio/' . $audName;
        $insAud = $conn->prepare("INSERT INTO property_audio_notes (property_id, file_path, duration) VALUES (?, ?, ?)");
        $dur = null;
        $insAud->bind_param("isi", $id, $audRel, $dur);
        $insAud->execute();
        $insAud->close();
    }
    $conn->commit();
    echo "Seeding completed.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

?>
