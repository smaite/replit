
<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('/');
}

// Check if Google OAuth is configured
if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    $page_title = 'Google Login - SASTO Hub';
    include '../includes/header.php';
    ?>
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-xl p-8 text-center">
            <i class="fas fa-info-circle text-6xl text-primary mb-4"></i>
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Google OAuth Not Configured</h1>
            <p class="text-gray-600 mb-6">
                Please configure Google OAuth credentials in <code class="bg-gray-100 px-2 py-1 rounded">config/config.php</code>
            </p>
            <a href="/auth/login.php" class="inline-block bg-primary hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
    <?php
    include '../includes/footer.php';
    exit();
}

// Generate state token for CSRF protection
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));

// Build Google OAuth URL
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $_SESSION['google_oauth_state'],
    'access_type' => 'online',
    'prompt' => 'select_account'
];

$google_oauth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

// Redirect to Google
header('Location: ' . $google_oauth_url);
exit();
?>
