<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check for errors from Google
if (isset($_GET['error'])) {
    redirect('/auth/login.php');
}

// Verify state token
if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    redirect('/auth/login.php');
}

// Clear state
unset($_SESSION['google_oauth_state']);

// Get authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    redirect('/auth/login.php');
}

// Exchange code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("Google OAuth curl error: " . $curl_error);
    redirect('/auth/login.php?error=curl');
}

$token_response = json_decode($response, true);

if (!isset($token_response['access_token'])) {
    error_log("Google OAuth token error: " . print_r($token_response, true));
    redirect('/auth/login.php?error=token');
}

$access_token = $token_response['access_token'];

// Get user info from Google
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_info_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
$user_info_response = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($user_info_response, true);

if (!isset($user_info['email'])) {
    redirect('/auth/login.php?error=email');
}

$google_id = $user_info['id'];
$email = $user_info['email'];
$full_name = $user_info['name'] ?? '';
$avatar = $user_info['picture'] ?? '';

// Check if user exists by Google ID
$stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$google_id]);
$user = $stmt->fetch();

if ($user) {
    // User exists, log them in
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_verified'] = true;

    // If user is a vendor, fetch and store vendor_id
    if ($user['role'] === ROLE_VENDOR) {
        $vendorStmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ? AND status = 'approved'");
        $vendorStmt->execute([$user['id']]);
        $vendor = $vendorStmt->fetch();
        if ($vendor) {
            $_SESSION['vendor_id'] = $vendor['id'];
        }
    }

    // Check if came from mobile and redirect back there
    $redirectUrl = '/';
    if (isset($_SESSION['login_from_mobile']) && $_SESSION['login_from_mobile']) {
        $redirectUrl = '/mobile/home.php';
        unset($_SESSION['login_from_mobile']);
    }

    // Redirect based on role
    if ($user['role'] === ROLE_ADMIN) {
        redirect('/admin/');
    } elseif ($user['role'] === ROLE_VENDOR) {
        redirect('/seller/');
    } else {
        redirect($redirectUrl);
    }
} else {
    // Check if email already exists (linked to regular account)
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch();

    if ($existing_user) {
        // Link Google account to existing user
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$google_id, $avatar, $existing_user['id']]);

        session_regenerate_id(true);

        $_SESSION['user_id'] = $existing_user['id'];
        $_SESSION['user_name'] = $existing_user['full_name'];
        $_SESSION['user_email'] = $existing_user['email'];
        $_SESSION['user_role'] = $existing_user['role'];
        $_SESSION['user_verified'] = true;

        // Check if came from mobile
        $redirectUrl = isset($_SESSION['login_from_mobile']) && $_SESSION['login_from_mobile'] ? '/mobile/home.php' : '/';
        unset($_SESSION['login_from_mobile']);
        redirect($redirectUrl);
    } else {
        // Create new user account
        $stmt = $conn->prepare("INSERT INTO users (email, full_name, google_id, avatar, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$email, $full_name, $google_id, $avatar, ROLE_CUSTOMER]);

        $user_id = $conn->lastInsertId();

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = ROLE_CUSTOMER;
        $_SESSION['user_verified'] = true;

        // Check if came from mobile
        $redirectUrl = isset($_SESSION['login_from_mobile']) && $_SESSION['login_from_mobile'] ? '/mobile/home.php' : '/';
        unset($_SESSION['login_from_mobile']);
        redirect($redirectUrl);
    }
}
