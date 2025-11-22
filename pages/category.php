<?php
require_once '../config/config.php';
require_once '../config/database.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    redirect('/pages/products.php');
}

// Redirect to products page with category filter
redirect('/pages/products.php?slug=' . urlencode($slug));
?>
