<?php
require_once 'db.php';
$result = $conn->query("DESCRIBE Properties");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . "\n";
}
?>
