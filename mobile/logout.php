<?php
/**
 * Mobile Logout - SASTO Hub
 */
require_once '../config/config.php';

// Clear all session data
session_unset();
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>
