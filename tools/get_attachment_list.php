<?php
require_once __DIR__ . '/../db.php';
$prop = $argv[1] ?? 8;
$prop = intval($prop);
$stmt = $conn->prepare('SELECT AttachmentID, FileName, FilePath, FileType FROM property_attachments WHERE PropertyID = ?');
$stmt->bind_param('i', $prop);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['AttachmentID']} | Name: {$row['FileName']} | Path: {$row['FilePath']} | Type: {$row['FileType']}\n";
}
$stmt->close();
?>