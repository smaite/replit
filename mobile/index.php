<?php
/**
 * Mobile Entry Point - SASTO Hub
 * Redirects to login page or home page based on login status
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in or has guest session
if (isLoggedIn() || isset($_SESSION['guest_mode'])) {
    header('Location: home.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
?>
