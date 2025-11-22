<?php
require_once '../config/config.php';
require_once '../config/database.php';

$query = $_GET['q'] ?? '';

if (empty($query)) {
    redirect('/pages/products.php');
}

// Redirect to products page with search query
redirect('/pages/products.php?q=' . urlencode($query));
?>
