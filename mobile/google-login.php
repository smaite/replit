<?php
/**
 * Mobile Google Login Handler
 * Sets mobile flag before redirecting to Google OAuth
 */
require_once '../config/config.php';

// Set flag so callback knows to redirect back to mobile
$_SESSION['login_from_mobile'] = true;

// Redirect to main Google login handler
header('Location: ../auth/google-login.php');
exit;
