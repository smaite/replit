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
            case 'become-vendor':
                handleBecomeVendor();
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
    case 'PUT':
        if ($action === 'profile') {
            handleUpdateProfile();
        } else {
            jsonError('Invalid action');
        }
        break;
    default:
        jsonError('Method not allowed', 405);
}

function handleLogin()
{
    global $conn;

    $data = getJsonBody();
    validateRequired($data, ['email', 'password']);

    $email = strtolower(trim($data['email']));
    $password = $data['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
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

function handleRegister()
{
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
            INSERT INTO users (full_name, email, password, phone, api_token, role, created_at) 
            VALUES (?, ?, ?, ?, ?, 'customer', NOW())
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

function handleLogout()
{
    global $conn;

    $user = getAuthUser();
    if ($user) {
        // Clear API token
        $stmt = $conn->prepare("UPDATE users SET api_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
    }

    jsonSuccess(null, 'Logged out successfully');
}

function handleProfile()
{
    $user = requireAuth();

    // Don't return sensitive data
    unset($user['password']);
    unset($user['api_token']);

    jsonSuccess($user);
}

function handleUpdateProfile()
{
    global $conn;

    $user = requireAuth();
    $data = getJsonBody();

    $updates = [];
    $params = [];

    // Update name
    if (isset($data['name']) && !empty(trim($data['name']))) {
        $updates[] = 'full_name = ?';
        $params[] = trim($data['name']);
    }

    // Update phone
    if (isset($data['phone'])) {
        $updates[] = 'phone = ?';
        $params[] = trim($data['phone']);
    }

    // Update password (requires current password verification)
    if (isset($data['new_password']) && !empty($data['new_password'])) {
        if (!isset($data['current_password']) || empty($data['current_password'])) {
            jsonError('Current password is required to change password');
        }

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($data['current_password'], $userData['password'])) {
            jsonError('Current password is incorrect', 401);
        }

        if (strlen($data['new_password']) < 6) {
            jsonError('New password must be at least 6 characters');
        }

        $updates[] = 'password = ?';
        $params[] = password_hash($data['new_password'], PASSWORD_DEFAULT);
    }

    if (empty($updates)) {
        jsonError('No fields to update');
    }

    try {
        $params[] = $user['id'];
        $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);

        // Get updated user
        $userStmt = $conn->prepare("SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = ?");
        $userStmt->execute([$user['id']]);
        $updatedUser = $userStmt->fetch(PDO::FETCH_ASSOC);

        jsonSuccess(['user' => $updatedUser], 'Profile updated successfully');
    } catch (Exception $e) {
        jsonError('Failed to update profile: ' . $e->getMessage());
    }
}

function handleBecomeVendor()
{
    global $conn;
    
    $user = requireAuth();
    
    // Check if user is already a vendor
    $checkStmt = $conn->prepare("SELECT id, status FROM vendors WHERE user_id = ?");
    $checkStmt->execute([$user['id']]);
    $existingVendor = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingVendor) {
        if ($existingVendor['status'] === 'approved') {
            jsonError('You are already a registered vendor');
        } elseif ($existingVendor['status'] === 'pending') {
            jsonError('Your vendor application is already pending review');
        }
    }
    
    // Get form data (multipart/form-data)
    $shopName = trim($_POST['shop_name'] ?? '');
    $shopDescription = trim($_POST['shop_description'] ?? '');
    $businessWebsite = trim($_POST['business_website'] ?? '');
    $businessLocation = trim($_POST['business_location'] ?? '');
    $businessCity = trim($_POST['business_city'] ?? '');
    $businessState = trim($_POST['business_state'] ?? '');
    $businessPostalCode = trim($_POST['business_postal_code'] ?? '');
    $businessPhone = trim($_POST['business_phone'] ?? '');
    $bankAccountName = trim($_POST['bank_account_name'] ?? '');
    $bankAccountNumber = trim($_POST['bank_account_number'] ?? '');
    
    // Validate required fields
    if (empty($shopName) || empty($shopDescription) || empty($businessLocation) ||
        empty($businessCity) || empty($businessPhone) || empty($bankAccountName) ||
        empty($bankAccountNumber)) {
        jsonError('Please fill in all required fields');
    }
    
    // Validate document uploads
    $requiredDocs = ['national_id_front', 'national_id_back', 'pan_vat_document', 'business_registration'];
    foreach ($requiredDocs as $doc) {
        if (!isset($_FILES[$doc]) || $_FILES[$doc]['error'] !== UPLOAD_ERR_OK) {
            jsonError("Please upload all required documents ($doc is missing)");
        }
    }
    
    try {
        // Create upload directory
        $uploadDir = '../uploads/vendor-documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $uploadedFiles = [];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Process file uploads
        foreach ($requiredDocs as $doc) {
            $file = $_FILES[$doc];
            
            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                jsonError("$doc: Only JPEG, PNG, and PDF files are allowed");
            }
            
            // Validate file size
            if ($file['size'] > $maxSize) {
                jsonError("$doc: File size must not exceed 5MB");
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . $user['id'] . '_' . $doc . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploadedFiles[$doc] = $filepath;
            } else {
                // Clean up previously uploaded files
                foreach ($uploadedFiles as $uploaded) {
                    if (file_exists($uploaded)) unlink($uploaded);
                }
                jsonError("Failed to upload $doc");
            }
        }
        
        // Insert vendor record
        $stmt = $conn->prepare("
            INSERT INTO vendors (user_id, shop_name, shop_description, business_website,
                                 business_location, business_city, business_state,
                                 business_postal_code, business_phone,
                                 bank_account_name, bank_account_number, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $user['id'], $shopName, $shopDescription, $businessWebsite,
            $businessLocation, $businessCity, $businessState,
            $businessPostalCode, $businessPhone,
            $bankAccountName, $bankAccountNumber
        ]);
        $vendorId = $conn->lastInsertId();
        
        // Store document file paths
        $docStmt = $conn->prepare("INSERT INTO vendor_documents (vendor_id, document_type, document_url, created_at) VALUES (?, ?, ?, NOW())");
        foreach ($uploadedFiles as $docType => $filePath) {
            $docStmt->execute([$vendorId, $docType, $filePath]);
        }
        
        jsonSuccess([
            'vendor_id' => $vendorId,
            'status' => 'pending'
        ], 'Vendor application submitted successfully! We will review it within 3-5 business days.');
        
    } catch (Exception $e) {
        // Clean up uploaded files on error
        foreach ($uploadedFiles ?? [] as $file) {
            if (file_exists($file)) unlink($file);
        }
        jsonError('Failed to submit application: ' . $e->getMessage());
    }
}
