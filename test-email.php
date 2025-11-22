<?php
/**
 * Email Testing Script
 * Run this to verify that the email system is working
 * 
 * IMPORTANT: This file should be deleted after testing for security!
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/mail.php';

$test_results = [];
$email_to_test = '';

// Check 1: Is mail.php loaded?
$test_results['mail_php_loaded'] = [
    'status' => function_exists('sendEmail'),
    'message' => function_exists('sendEmail') ? '✅ mail.php loaded successfully' : '❌ mail.php not loaded'
];

// Check 2: Is PHP mail() available?
$test_results['php_mail_available'] = [
    'status' => function_exists('mail'),
    'message' => function_exists('mail') ? '✅ PHP mail() function available' : '❌ PHP mail() not available'
];

// Check 3: Mail configuration
$config = defined('MAIL_FROM') ? [
    'from' => MAIL_FROM,
    'from_name' => MAIL_FROM_NAME,
    'host' => MAIL_HOST,
    'port' => MAIL_PORT
] : [];

$test_results['email_config'] = [
    'status' => !empty($config),
    'message' => 'Email From: ' . (MAIL_FROM ?? 'Not configured'),
    'details' => $config
];

// Check 4: Send test approval email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email'] ?? '');
    
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        // Create mock vendor and user data
        $vendor = [
            'shop_name' => 'Test Shop',
            'business_city' => 'Kathmandu',
            'business_state' => 'Bagmati'
        ];
        $user = [
            'full_name' => 'Test User',
            'email' => $test_email
        ];
        
        $result = sendVendorApprovalEmail($vendor, $user);
        
        $test_results['send_test'] = [
            'status' => $result,
            'message' => $result ? 
                '✅ Approval email sent to: ' . htmlspecialchars($test_email) : 
                '❌ Failed to send email to: ' . htmlspecialchars($test_email),
            'email_address' => $test_email
        ];
    } else {
        $test_results['send_test'] = [
            'status' => false,
            'message' => '❌ Invalid email address provided'
        ];
    }
}

// Check 5: Email log file
$log_file = __DIR__ . '/logs/email.log';
$test_results['email_log'] = [
    'status' => file_exists($log_file),
    'message' => file_exists($log_file) ? '✅ Email log file exists' : '⚠️ Email log file not created yet',
    'path' => $log_file
];

if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_lines = count(array_filter(explode("\n", trim($log_content))));
    $test_results['email_log']['entries'] = $log_lines . ' entries recorded';
    $test_results['email_log']['preview'] = substr($log_content, -500);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Test - SASTO Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .primary { color: #4F46E5; }
        .bg-primary { background-color: #4F46E5; }
        .hover\:bg-primary:hover { background-color: #4338CA; }
    </style>
</head>
<body class="bg-gray-100 py-8">
    <div class="container mx-auto px-4 max-w-4xl">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="mb-8 text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-envelope primary"></i> Email System Test
                </h1>
                <p class="text-gray-600">Verify that email notifications are working correctly</p>
            </div>

            <!-- Test Results -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php foreach ($test_results as $test_name => $result): ?>
                    <div class="border rounded-lg p-4 <?php echo $result['status'] ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50'; ?>">
                        <div class="flex items-start gap-3">
                            <i class="fas <?php echo $result['status'] ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600'; ?> text-xl mt-1"></i>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-900 mb-1 text-sm uppercase"><?php echo str_replace('_', ' ', $test_name); ?></h3>
                                <p class="text-sm <?php echo $result['status'] ? 'text-green-700' : 'text-red-700'; ?>">
                                    <?php echo htmlspecialchars($result['message']); ?>
                                </p>
                                <?php if (!empty($result['details'])): ?>
                                    <pre class="text-xs bg-white p-2 rounded mt-2 overflow-auto max-h-32"><code><?php echo htmlspecialchars(json_encode($result['details'], JSON_PRETTY_PRINT)); ?></code></pre>
                                <?php endif; ?>
                                <?php if (!empty($result['entries'])): ?>
                                    <p class="text-xs text-gray-600 mt-2"><?php echo htmlspecialchars($result['entries']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($result['preview'])): ?>
                                    <details class="mt-2 text-xs">
                                        <summary class="cursor-pointer text-gray-600">View log preview</summary>
                                        <pre class="bg-white p-2 rounded mt-2 overflow-auto max-h-40 text-gray-700"><code><?php echo htmlspecialchars($result['preview']); ?></code></pre>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Send Test Email Form -->
            <div class="border-t pt-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-paper-plane primary"></i> Send Test Email
                </h2>
                
                <form method="POST" class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <p class="text-blue-900 mb-4">Enter an email address to send a test approval email:</p>
                    
                    <div class="flex gap-2 mb-3">
                        <input type="email" name="test_email" required placeholder="test@example.com" value="<?php echo htmlspecialchars($email_to_test); ?>"
                               class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="submit" name="send_test" value="1"
                                class="bg-primary hover:bg-primary text-white px-6 py-3 rounded-lg font-medium transition">
                            <i class="fas fa-send"></i> Send Test
                        </button>
                    </div>
                    <p class="text-sm text-gray-600">This will send a test approval email to the address above.</p>
                </form>
            </div>

            <!-- Manual Test Instructions -->
            <div class="border-t pt-8 mt-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-book primary"></i> How to Manually Test
                </h2>
                
                <div class="space-y-4 text-gray-700">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-bold mb-2">Option 1: Using a Test Vendor</h3>
                        <ol class="list-decimal list-inside space-y-1 text-sm">
                            <li>Create a test vendor account with a real email you can check</li>
                            <li>Go to: <code class="bg-white px-2 py-1 rounded">/admin/vendors-verification.php</code></li>
                            <li>Find the test vendor and click "Approve"</li>
                            <li>Check your email inbox (may take 5-10 seconds)</li>
                            <li>If not in inbox, check spam/junk folder</li>
                        </ol>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-bold mb-2">Option 2: Check Error Logs</h3>
                        <ol class="list-decimal list-inside space-y-1 text-sm">
                            <li>Check PHP error log: <code class="bg-white px-2 py-1 rounded">/logs/error.log</code></li>
                            <li>Check email log: <code class="bg-white px-2 py-1 rounded">/logs/email.log</code></li>
                            <li>Look for "Email sent to" or error messages</li>
                        </ol>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-bold mb-2">Option 3: Using MailHog (Recommended)</h3>
                        <ol class="list-decimal list-inside space-y-1 text-sm">
                            <li>Download MailHog: <code class="bg-white px-2 py-1 rounded text-xs">https://github.com/mailhog/MailHog/releases</code></li>
                            <li>Run it: <code class="bg-white px-2 py-1 rounded">./MailHog</code> (Windows: MailHog.exe)</li>
                            <li>Configure PHP to use it (sendmail_path in php.ini)</li>
                            <li>Send test email using form above</li>
                            <li>View at: <code class="bg-white px-2 py-1 rounded">http://localhost:8025</code></li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="border-t pt-8 mt-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-wrench primary"></i> Troubleshooting
                </h2>
                
                <div class="space-y-3 text-sm">
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                        <p class="font-bold text-yellow-900 mb-2">✓ System is saying mail() is available but emails not arriving?</p>
                        <ul class="list-disc list-inside text-yellow-800 space-y-1">
                            <li>Check your email spam/junk folder first</li>
                            <li>Update "From" address in /config/mail.php to a real domain</li>
                            <li>XAMPP may not have mail configured - use MailHog instead</li>
                            <li>Or configure SMTP (Gmail, SendGrid, Mailgun) in mail.php</li>
                        </ul>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                        <p class="font-bold text-yellow-900 mb-2">✗ PHP mail() function not available?</p>
                        <ul class="list-disc list-inside text-yellow-800 space-y-1">
                            <li>Install sendmail/postfix on your system</li>
                            <li>Or use MailHog (easier for development)</li>
                            <li>Or configure SMTP in mail.php with real email service</li>
                        </ul>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                        <p class="font-bold text-yellow-900 mb-2">❌ Getting errors or white screen?</p>
                        <ul class="list-disc list-inside text-yellow-800 space-y-1">
                            <li>Check /logs/error.log for PHP errors</li>
                            <li>Verify database connection is working</li>
                            <li>Make sure vendor/user records exist in database</li>
                            <li>Check if mail.php functions are being called correctly</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Important Notice -->
            <div class="border-t pt-8 mt-8 bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-900 font-bold mb-2">
                    <i class="fas fa-exclamation-triangle"></i> SECURITY WARNING
                </p>
                <p class="text-red-700 text-sm">
                    This test file should be <strong>DELETED IMMEDIATELY AFTER TESTING</strong> for security reasons.
                    It's publicly accessible and could expose sensitive information.
                </p>
                <p class="text-red-700 text-sm mt-2">
                    After testing, delete: <code class="bg-white px-2 py-1 rounded">test-email.php</code>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
