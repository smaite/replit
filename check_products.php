<?php
require_once 'config/database.php';

echo "<b>Vendors table structure:</b><br>";
$stmt = $conn->query("DESCRIBE vendors");
$cols = $stmt->fetchAll();
foreach ($cols as $col) {
    echo $col['Field'] . " | " . $col['Type'] . "<br>";
}
?>
