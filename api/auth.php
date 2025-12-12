<?php
/**
 * Sasto Hub Auth API
 * Login, Register, Profile endpoints
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        switch ($action) {
            case 'login':
                handleLogin();
                break;
            case 'register':
                handleRegister();
                break;
            case 'logout':
                handleLogout();
                break;
            default:
                jsonError('Invalid action');
        }
        break;
    case 'GET':
        if ($action === 'profile') {
            handleProfile();
        } else {
            jsonError('Invalid action');
        }
        break;
    default:
        jsonError('Method not allowed', 405);
}

function handleLogin() {
    global $conn;
    
    $data = getJsonBody();
    validateRequired($data, ['email', 'password']);
    
    $email = strtolower(trim($data['email']));
    $password = $data['password'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password'])) {
            jsonError('Invalid email or password', 401);
        }
        
        // Generate API token
        $token = generateApiToken();
        
        // Save token to database
        $updateStmt = $conn->prepare("UPDATE users SET api_token = ?, last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$token, $user['id']]);
        
        // Don't return sensitive data
        unset($user['password']);
        unset($user['api_token']);
        
        jsonSuccess([
            'user' => $user,
            'token' => $token
        ], 'Login successful');
        
    } catch (Exception $e) {
        jsonError('Login failed. Please try again.');
    }
}

function handleRegister() {
    global $conn;
    
    $data = getJsonBody();
    validateRequired($data, ['name', 'email', 'password']);
    
    $name = trim($data['name']);
    $email = strtolower(trim($data['email']));
    $password = $data['password'];
    $phone = $data['phone'] ?? null;
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email address');
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        jsonError('Password must be at least 6 characters');
    }
    
    try {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonError('Email already registered');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate API token
        $token = generateApiToken();
        
        // Create user
        $stmt = $conn->prepare("
            INSERT INTO users (full_name, email, password, phone, api_token, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'customer', 'active', NOW())
        ");
        $stmt->execute([$name, $email, $hashedPassword, $phone, $token]);
        $userId = $conn->lastInsertId();
        
        // Get created user
        $userStmt = $conn->prepare("SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        jsonSuccess([
            'user' => $user,
            'token' => $token
        ], 'Registration successful');
        
    } catch (Exception $e) {
        jsonError('Registration failed. Please try again.');
    }
}

function handleLogout() {
    global $conn;
    
    $user = getAuthUser();
    if ($user) {
        // Clear API token
        $stmt = $conn->prepare("UPDATE users SET api_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
    }
    
    jsonSuccess(null, 'Logged out successfully');
}

function handleProfile() {
    $user = requireAuth();
    
    // Don't return sensitive data
    unset($user['password']);
    unset($user['api_token']);
    
    jsonSuccess($user);
}
