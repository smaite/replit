<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/mail.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('/auth/login.php');
}

$result = false;
$to_email = '';
$test_email = 'firozshab123@gmail.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid.';
    } else {
        $to_email = sanitize($_POST['email'] ?? $test_email);
        
        // Test email body
        $subject = "🧪 SASTO Hub - Email System Test";
        
        $body = "
        <h2>Email System Test</h2>
        
        <p>Dear Admin,</p>
        
        <p>This is a <strong>test email</strong> from SASTO Hub's email system.</p>
        
        <div class='alert alert-success'>
            <p>✅ If you received this email, your email system is working correctly!</p>
        </div>
        
        <h3>Test Details:</h3>
        <ul>
            <li><strong>Sent At:</strong> " . date('Y-m-d H:i:s') . "</li>
            <li><strong>From:</strong> noreply@sastohub.com</li>
            <li><strong>Server:</strong> " . $_SERVER['SERVER_NAME'] . "</li>
            <li><strong>PHP Version:</strong> " . phpversion() . "</li>
        </ul>
        
        <p>If you did not expect this email, please disregard it.</p>
        
        <p><strong>SASTO Hub Team</strong></p>
        ";
        
        // Send test email
        $result = sendEmail($to_email, $subject, $body);
    }
}

$page_title = 'Test Email System - SASTO Hub';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <a href="/admin/" class="text-primary hover:text-indigo-700 font-medium mb-4 inline-block">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Email System Test</h1>
            <p class="text-gray-600 mt-2">Test vendor notification emails and diagnose issues</p>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])): ?>
            <?php if ($result): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-lg mb-6">
                    <i class="fas fa-check-circle text-2xl mb-2"></i>
                    <h3 class="text-xl font-bold mb-2">✅ Email Sent Successfully!</h3>
                    <p class="mb-2"><strong>To:</strong> <?php echo htmlspecialchars($to_email); ?></p>
                    <p class="mb-2"><strong>Subject:</strong> 🧪 SASTO Hub - Email System Test</p>
                    <p class="text-sm"><i class="fas fa-info-circle"></i> Check your inbox (or spam folder) for the test email.</p>
                </div>
            <?php else: ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                    <i class="fas fa-times-circle text-2xl mb-2"></i>
                    <h3 class="text-xl font-bold mb-2">❌ Email Failed to Send</h3>
                    <p class="mb-4"><strong>Recipient:</strong> <?php echo htmlspecialchars($to_email); ?></p>
                    
                    <div class="bg-red-100 border border-red-300 rounded p-4 mt-4 mb-4">
                        <h4 class="font-bold mb-2">Common Reasons for Failure:</h4>
                        <ul class="list-disc list-inside space-y-2 text-sm">
                            <li><strong>SMTP Not Configured:</strong> PHP mail() requires sendmail or SMTP server</li>
                            <li><strong>Invalid Email Address:</strong> Check the recipient email format</li>
                            <li><strong>Headers Issue:</strong> Invalid header format in the email</li>
                            <li><strong>Server Firewall:</strong> Port 25/587 might be blocked</li>
                            <li><strong>Relay Denied:</strong> Server may not allow external email sending</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Diagnostic Info -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-bold text-yellow-900 mb-4">📋 Diagnostic Information</h3>
                    <div class="space-y-3 text-sm font-mono bg-white p-4 rounded border border-yellow-200 overflow-x-auto">
                        <div><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
                        <div><strong>mail() Function:</strong> <?php echo (function_exists("mail") ? "✅ Available" : "❌ Not Available"); ?></div>
                        <div><strong>SMTP Server:</strong> <?php echo (ini_get("SMTP") ?: "Not configured"); ?></div>
                        <div><strong>SMTP Port:</strong> <?php echo (ini_get("smtp_port") ?: "Not configured"); ?></div>
                        <div><strong>Sendmail Path:</strong> <?php echo (ini_get("sendmail_path") ?: "Not configured"); ?></div>
                        <div><strong>From Address:</strong> noreply@sastohub.com</div>
                        <div><strong>Test Recipient:</strong> <?php echo htmlspecialchars($to_email); ?></div>
                        <div><strong>PHP Error Log:</strong> <?php echo (ini_get("error_log") ?: "Not configured"); ?></div>
                    </div>
                </div>

                <!-- Solutions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-bold text-blue-900 mb-4">🔧 How to Fix Email Issues</h3>
                    
                    <div class="space-y-6">
                        <!-- MailHog Solution -->
                        <div class="border-b border-blue-200 pb-6">
                            <h4 class="font-bold text-blue-900 mb-2">✅ Solution 1: Use MailHog (Best for Development)</h4>
                            <p class="text-gray-700 mb-3">MailHog catches all emails locally - perfect for testing without a real mail server</p>
                            <div class="bg-white p-4 rounded border border-blue-200 text-sm space-y-2">
                                <p><strong>Step 1:</strong> Download MailHog</p>
                                <p class="text-gray-600 ml-4">Visit: https://github.com/mailhog/MailHog/releases</p>
                                <p class="text-gray-600 ml-4">Download for Windows: mailhog_windows_amd64.exe</p>
                                
                                <p class="mt-3"><strong>Step 2:</strong> Configure php.ini</p>
                                <p class="text-gray-600 ml-4">Find your php.ini (usually: D:\xampp\php\php.ini)</p>
                                <p class="text-gray-600 ml-4">Change these settings:</p>
                                <pre class="bg-gray-100 p-3 rounded mt-2 text-xs overflow-x-auto">SMTP = localhost
smtp_port = 1025
sendmail_path = "D:\xampp\sendmail\sendmail.exe -t -i"</pre>
                                
                                <p class="mt-3"><strong>Step 3:</strong> Run MailHog</p>
                                <p class="text-gray-600 ml-4">Double-click mailhog_windows_amd64.exe</p>
                                <p class="text-gray-600 ml-4">It will start on: http://localhost:1025 (SMTP) and http://localhost:8025 (Web UI)</p>
                                
                                <p class="mt-3"><strong>Step 4:</strong> Restart Apache</p>
                                <p class="text-gray-600 ml-4">In XAMPP Control Panel, click "Restart" on Apache</p>
                                
                                <p class="mt-3"><strong>Step 5:</strong> Test Email</p>
                                <p class="text-gray-600 ml-4">Come back here and send test email</p>
                                <p class="text-gray-600 ml-4">Check http://localhost:8025 to see caught emails</p>
                            </div>
                        </div>

                    <!-- Gmail SMTP Solution -->
                        <div class="border-b border-blue-200 pb-6">
                            <h4 class="font-bold text-blue-900 mb-2">✅ Solution: Use Gmail SMTP (Recommended)</h4>
                            <p class="text-gray-700 mb-3">Send real emails using your Gmail account with PHPMailer</p>
                            <div class="bg-white p-4 rounded border border-blue-200 text-sm space-y-2">
                                <p><strong>Step 1:</strong> Get Gmail App Password</p>
                                <p class="text-gray-600 ml-4">1. Go to https://myaccount.google.com/security</p>
                                <p class="text-gray-600 ml-4">2. Enable 2-Factor Authentication (if not done)</p>
                                <p class="text-gray-600 ml-4">3. Scroll down and click "App passwords"</p>
                                <p class="text-gray-600 ml-4">4. Select "Mail" and "Windows Computer"</p>
                                <p class="text-gray-600 ml-4">5. Google will generate a 16-character password - copy it</p>
                                
                                <p class="mt-3"><strong>Step 2:</strong> Update /config/mail.php</p>
                                <p class="text-gray-600 ml-4">Find these lines (around line 17-22):</p>
                                <pre class="bg-gray-100 p-3 rounded mt-2 text-xs overflow-x-auto">define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'your-gmail@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'your-app-password');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');</pre>

                                <p class="mt-3 text-gray-600 ml-4">Replace:</p>
                                <pre class="bg-gray-100 p-3 rounded mt-2 text-xs overflow-x-auto">define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'your-gmail@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'your-app-password');</pre>

                                <p class="mt-3 text-gray-600 ml-4">With your actual Gmail and app password:</p>
                                <pre class="bg-gray-100 p-3 rounded mt-2 text-xs overflow-x-auto">define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'firozshab123@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'xxxx xxxx xxxx xxxx');</pre>

                                <p class="mt-3"><strong>Step 3:</strong> Restart Apache</p>
                                <p class="text-gray-600 ml-4">In XAMPP Control Panel: Stop and Start Apache</p>
                                
                                <p class="mt-3"><strong>Step 4:</strong> Test Email</p>
                                <p class="text-gray-600 ml-4">Come back here and click "Send Test Email"</p>
                                <p class="text-gray-600 ml-4">Check your inbox - you should receive the email!</p>
                            </div>
                        </div>

                        <!-- MailHog Solution (Optional) -->
                        <div class="border-b border-blue-200 pb-6 opacity-50">
                            <h4 class="font-bold text-blue-900 mb-2">⚙️ Alternative: Use MailHog (For Development Only)</h4>
                            <p class="text-gray-700 mb-3">Catch emails locally without a real mail server</p>
                            <details class="bg-white p-4 rounded border border-blue-200 text-sm space-y-2">
                                <summary class="cursor-pointer font-bold">Show MailHog Setup Instructions</summary>
                                <div class="mt-4 space-y-2">
                                    <p><strong>Step 1:</strong> Download MailHog</p>
                                    <p class="text-gray-600 ml-4">Download from: https://github.com/mailhog/MailHog/releases</p>
                                    <p class="text-gray-600 ml-4">For Windows: mailhog_windows_amd64.exe</p>
                                    
                                    <p class="mt-3"><strong>Step 2:</strong> Update /config/mail.php (lines 17-22)</p>
                                    <p class="text-gray-600 ml-4">Replace with:</p>
                                    <pre class="bg-gray-100 p-3 rounded mt-2 text-xs overflow-x-auto">define('MAIL_HOST', getenv('MAIL_HOST') ?: 'localhost');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 1025);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: '');</pre>
                                    
                                    <p class="mt-3"><strong>Step 3:</strong> Run MailHog</p>
                                    <p class="text-gray-600 ml-4">Double-click mailhog_windows_amd64.exe</p>
                                    <p class="text-gray-600 ml-4">SMTP: http://localhost:1025</p>
                                    <p class="text-gray-600 ml-4">Web UI: http://localhost:8025</p>
                                    
                                    <p class="mt-3"><strong>Step 4:</strong> Restart Apache and test</p>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        <?php endif; ?>

        <!-- Test Form -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Send Test Email</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="test_email" value="1">
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Recipient Email *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($test_email); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="your-email@gmail.com">
                        <p class="text-sm text-gray-500 mt-1">Enter the email where you want to receive the test email</p>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition">
                        <i class="fas fa-envelope"></i> Send Test Email
                    </button>
                </form>
                
                <div class="mt-8 pt-8 border-t">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">ℹ️ What This Test Does:</h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li>✓ Sends a test email to your specified address</li>
                        <li>✓ Uses the same email system as vendor notifications</li>
                        <li>✓ Tests HTML email formatting</li>
                        <li>✓ Validates email configuration</li>
                        <li>✓ Shows diagnostic info if it fails</li>
                    </ul>
                </div>
            </div>

            <!-- Current Configuration -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Current Configuration</h2>
                
                <div class="space-y-6">
                    <!-- PHP Mail Info -->
                    <div>
                        <h3 class="font-bold text-gray-900 mb-3">PHP Mail Setup</h3>
                        <div class="bg-gray-50 p-4 rounded border border-gray-200 space-y-2 text-sm font-mono">
                            <div>
                                <span class="text-gray-600">PHP Version:</span>
                                <span class="font-bold"><?php echo phpversion(); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">mail() Available:</span>
                                <span class="font-bold <?php echo (function_exists("mail") ? 'text-green-600' : 'text-red-600'); ?>">
                                    <?php echo (function_exists("mail") ? '✅ Yes' : '❌ No'); ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-600">SMTP Server:</span>
                                <span class="font-bold"><?php echo (ini_get("SMTP") ?: "Not set"); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">SMTP Port:</span>
                                <span class="font-bold"><?php echo (ini_get("smtp_port") ?: "Not set"); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Application Email Config -->
                    <div>
                        <h3 class="font-bold text-gray-900 mb-3">Application Email Config</h3>
                        <div class="bg-gray-50 p-4 rounded border border-gray-200 space-y-2 text-sm font-mono">
                            <div>
                                <span class="text-gray-600">Email Library:</span>
                                <span class="font-bold text-green-600">✅ PHPMailer</span>
                            </div>
                            <div>
                                <span class="text-gray-600">SMTP Host:</span>
                                <span class="font-bold"><?php echo MAIL_HOST; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">SMTP Port:</span>
                                <span class="font-bold"><?php echo MAIL_PORT; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">From Email:</span>
                                <span class="font-bold"><?php echo MAIL_FROM; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">From Name:</span>
                                <span class="font-bold"><?php echo MAIL_FROM_NAME; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Encryption:</span>
                                <span class="font-bold"><?php echo MAIL_ENCRYPTION ?: 'None'; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Auth Username:</span>
                                <span class="font-bold"><?php echo (MAIL_USERNAME ? substr(MAIL_USERNAME, 0, 5) . '...' : 'Not set'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Enabled Features -->
                    <div>
                        <h3 class="font-bold text-gray-900 mb-3">Email Notifications Enabled</h3>
                        <div class="bg-green-50 p-4 rounded border border-green-200 space-y-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="text-green-600">✅</span>
                                <span>Vendor Approval Emails</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-green-600">✅</span>
                                <span>Vendor Rejection Emails</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-green-600">✅</span>
                                <span>Vendor Re-approval Emails</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-green-600">✅</span>
                                <span>Vendor Revocation Emails</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
