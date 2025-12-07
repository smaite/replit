<?php
/**
 * Mobile Registration Page - SASTO Hub
 * Mobile-optimized registration form
 */
require_once '../config/config.php';
require_once '../config/database.php';

// If already logged in, redirect to home
if (isLoggedIn()) {
    header('Location: home.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $fullName = validateInput($_POST['full_name'] ?? '', 'text', 100);
        $email = validateInput($_POST['email'] ?? '', 'email');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!$fullName || !$email) {
            $error = 'Please fill in all fields correctly';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Create user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, created_at) VALUES (?, ?, ?, 'customer', NOW())");
                
                if ($stmt->execute([$fullName, $email, $hashedPassword])) {
                    header('Location: login.php?registered=1');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2196F3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Sign Up - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-page">
        <!-- Header with gradient background -->
        <div class="login-header">
            <button class="close-btn" onclick="window.location.href='login.php'">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </button>
            
            <!-- Logo -->
            <div class="login-logo">
                <svg viewBox="0 0 24 24">
                    <path d="M19.5 8.5L12 4L4.5 8.5V15.5L12 20L19.5 15.5V8.5Z" stroke="currentColor" stroke-width="2" fill="none"/>
                    <path d="M12 4V20" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="9" r="2" fill="currentColor"/>
                    <path d="M9 13L15 13" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
        </div>
        
        <!-- Registration Form -->
        <div class="login-content">
            <h2 style="text-align: center; margin-bottom: 24px; color: #333;">Create Account</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="John Doe" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="johndoe@example.com" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Minimum 6 characters" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Re-enter password" required>
                </div>
                
                <button type="submit" class="login-btn">Sign Up</button>
            </form>
            
            <div class="signup-link">
                Already have an account? <a href="login.php">Log in</a>
            </div>
            
            <div class="social-login">
                <p>Or sign up with</p>
                <div class="social-icons">
                    <a href="../auth/google-login.php" class="social-btn">
                        <svg viewBox="0 0 24 24" width="28" height="28">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-btn">
                        <svg viewBox="0 0 24 24" width="28" height="28">
                            <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
