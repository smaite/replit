<?php
/**
 * Mobile Login Page - SASTO Hub
 * Mobile-optimized login with guest continue option
 */
require_once '../config/config.php';
require_once '../config/database.php';

// If already logged in, redirect to home
if (isLoggedIn()) {
    header('Location: home.php');
    exit;
}

$error = '';
$success = isset($_GET['registered']) ? 'Registration successful! Please login.' : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = validateInput($_POST['email'] ?? '', 'email');
        $password = $_POST['password'] ?? '';
        
        if (!$email || empty($password)) {
            $error = 'Please fill in all fields';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_verified'] = true;
                
                // Redirect to mobile home
                header('Location: home.php');
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        }
    }
}

// Handle guest mode
if (isset($_GET['guest'])) {
    $_SESSION['guest_mode'] = true;
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2196F3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-page">
        <!-- Header with gradient background -->
        <div class="login-header">
            <button class="close-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
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
        
        <!-- Login Form -->
        <div class="login-content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="johndoe@example.com" required>
                    <div class="form-link">
                        <a href="#">or, Login with a phone number</a>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                    <div class="form-link">
                        <a href="#">Forgot Password?</a>
                    </div>
                </div>
                
                <button type="submit" class="login-btn">Log in</button>
                
                <a href="?guest=1" class="guest-btn" style="display: block; text-align: center; margin-top: 12px;">
                    Continue as Guest
                </a>
            </form>
            
            <div class="signup-link">
                or, create a new account? <a href="register.php">Sign up</a>
            </div>
            
            <div class="social-login">
                <p>Login with</p>
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
