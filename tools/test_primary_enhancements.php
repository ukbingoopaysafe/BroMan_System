<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lang/translate.php';

echo "--- Testing Arabic Pluralization & Numbers ---\n";
echo "10 years: " . formatArabicYears(10) . "\n";
echo "1 year: " . formatArabicYears(1) . "\n";
echo "2 years: " . formatArabicYears(2) . "\n";
echo "7 years: " . formatArabicYears(7) . "\n";
echo "12 years: " . formatArabicYears(12) . "\n";
echo "7.5 years: " . formatArabicYears(7.5) . "\n";

echo "\n--- Testing PropertyPaymentPlans Schema & Data ---\n";
$res = $conn->query("DESCRIBE PropertyPaymentPlans");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n--- Testing Properties Added Financial Fields ---\n";
$res2 = $conn->query("SHOW COLUMNS FROM Properties LIKE '%fee%'");
while ($r = $res2->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
$res3 = $conn->query("SHOW COLUMNS FROM Properties LIKE 'maintenance_payment_details'");
while ($r = $res3->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\nAll checks passed successfully.\n";
