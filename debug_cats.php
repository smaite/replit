<?php
require_once 'config/database.php';

echo "<h3>All Categories:</h3>";
$r = $conn->query("SELECT id, name, status, image, parent_id FROM categories ORDER BY id");
$cats = $r->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($cats);
echo "</pre>";

echo "<h3>Active Categories Only:</h3>";
$r = $conn->query("SELECT id, name, status FROM categories WHERE status = 'active'");
$active = $r->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($active);
echo "</pre>";
