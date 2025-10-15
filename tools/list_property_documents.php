<?php
require_once __DIR__ . '/../db.php';
$pid = $argv[1] ?? null;
if (!$pid) { echo "Usage: php list_property_documents.php PROPERTY_ID\n"; exit(1);} 
$stmt = $conn->prepare('SELECT * FROM property_documents WHERE property_id=?');
$stmt->bind_param('i', $pid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
}
$stmt->close();
?>