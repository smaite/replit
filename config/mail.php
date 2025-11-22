<?php
/**
 * Email Configuration and Helper Functions
 * Handles sending transactional emails using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email configuration
define('MAIL_FROM', 'noreply@sastohub.com');
define('MAIL_FROM_NAME', 'SASTO Hub');

// Gmail SMTP Configuration (requires app password)
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'mail.sastohub.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'noreply@sastohub.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'noreply@sastohub.com');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');

// Alternative: localhost MailHog configuration (for development)
// define('MAIL_HOST', 'localhost');
// define('MAIL_PORT', 1025);
// define('MAIL_USERNAME', '');
// define('MAIL_PASSWORD', '');
// define('MAIL_ENCRYPTION', '');

/**
 * Send email using PHPMailer with SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body HTML email body
 * @param array $options Additional options (cc, bcc, attachments)
 * @return bool True if email sent successfully
 */
function sendEmail($to, $subject, $body, $options = []) {
    $to = filter_var($to, FILTER_VALIDATE_EMAIL);
    if (!$to) {
        error_log("Invalid email address provided");
        return false;
    }
    
    try {
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->Port = MAIL_PORT;
        $mail->SMTPAuth = !empty(MAIL_USERNAME);
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        
        // Enable debug for development (remove in production)
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };
        
        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Optional CC/BCC
        if (!empty($options['cc'])) {
            $mail->addCC($options['cc']);
        }
        if (!empty($options['bcc'])) {
            $mail->addBCC($options['bcc']);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = wrapEmailBody($body);
        $mail->AltBody = strip_tags($body);
        
        // Headers
        $mail->addReplyTo(MAIL_FROM);
        
        // Send email
        $result = $mail->send();
        
        error_log("✅ Email sent successfully to: $to - Subject: $subject");
        return true;
        
    } catch (Exception $e) {
        $error = "❌ Email failed to send. Error: " . $mail->ErrorInfo;
        error_log($error);
        error_log("Details: " . $e->getMessage());
        return false;
    }
}

/**
 * Wrap email body with HTML template
 * 
 * @param string $body Email body content
 * @return string HTML formatted email
 */
function wrapEmailBody($body) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f9f9f9;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content {
                padding: 30px 20px;
            }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background-color: #4F46E5;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin: 20px 0;
                font-weight: 600;
            }
            .button:hover {
                background-color: #4338CA;
            }
            .footer {
                background-color: #f5f5f5;
                color: #666;
                text-align: center;
                padding: 20px;
                font-size: 12px;
                border-top: 1px solid #e0e0e0;
            }
            .status-approved {
                color: #10B981;
                font-weight: 600;
            }
            .status-rejected {
                color: #EF4444;
                font-weight: 600;
            }
            .status-pending {
                color: #F59E0B;
                font-weight: 600;
            }
            .alert {
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            .alert-info {
                background-color: #EFF6FF;
                color: #1E40AF;
                border-left: 4px solid #3B82F6;
            }
            .alert-warning {
                background-color: #FFFBEB;
                color: #92400E;
                border-left: 4px solid #F59E0B;
            }
            .alert-success {
                background-color: #F0FDF4;
                color: #166534;
                border-left: 4px solid #10B981;
            }
            .alert-danger {
                background-color: #FEF2F2;
                color: #991B1B;
                border-left: 4px solid #EF4444;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>SASTO Hub</h1>
            </div>
            <div class='content'>
                $body
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " SASTO Hub. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p><a href='http://localhost' style='color: #4F46E5; text-decoration: none;'>Visit SASTO Hub</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send vendor approval notification
 * 
 * @param array $vendor Vendor data from database
 * @param array $user User data from database
 * @return bool
 */
function sendVendorApprovalEmail($vendor, $user) {
    $subject = "🎉 Your Vendor Application is Approved - SASTO Hub";
    
    $body = "
    <h2>Congratulations! Your Application is Approved</h2>
    
    <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
    
    <div class='alert alert-success'>
        <p><strong>Your vendor application has been <span class='status-approved'>APPROVED</span>!</strong></p>
    </div>
    
    <p>We're excited to have <strong>" . htmlspecialchars($vendor['shop_name']) . "</strong> as a seller on SASTO Hub.</p>
    
    <h3>What's Next?</h3>
    <ol>
        <li>Login to your vendor dashboard</li>
        <li>Start uploading your products</li>
        <li>Manage your store settings and inventory</li>
        <li>Track your sales and earnings</li>
    </ol>
    
    <a href='http://localhost/vendor/' class='button'>Go to Vendor Dashboard</a>
    
    <h3>Store Information</h3>
    <ul>
        <li><strong>Shop Name:</strong> " . htmlspecialchars($vendor['shop_name']) . "</li>
        <li><strong>Location:</strong> " . htmlspecialchars($vendor['business_city']) . ", " . htmlspecialchars($vendor['business_state']) . "</li>
        <li><strong>Commission:</strong> 10% per sale</li>
        <li><strong>Payment:</strong> Paid to your registered bank account</li>
    </ul>
    
    <h3>Need Help?</h3>
    <p>If you have any questions, please contact our support team at <a href='mailto:support@sastohub.com'>support@sastohub.com</a></p>
    
    <p>Happy selling!</p>
    <p><strong>SASTO Hub Team</strong></p>
    ";
    
    if (empty($user['email'])) {
        error_log("Cannot send approval email: empty email address");
        return false;
    }
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send vendor rejection notification
 * 
 * @param array $vendor Vendor data from database
 * @param array $user User data from database
 * @param string $reason Rejection reason
 * @return bool
 */
function sendVendorRejectionEmail($vendor, $user, $reason) {
    $subject = "Your Vendor Application Requires Review - SASTO Hub";
    
    $body = "
    <h2>Your Application Needs Attention</h2>
    
    <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
    
    <div class='alert alert-warning'>
        <p>Your vendor application for <strong>" . htmlspecialchars($vendor['shop_name']) . "</strong> has been <span class='status-rejected'>REJECTED</span>.</p>
    </div>
    
    <h3>Reason for Rejection:</h3>
    <div class='alert alert-danger'>
        <p>" . htmlspecialchars($reason) . "</p>
    </div>
    
    <h3>What Can You Do?</h3>
    <p>Please review the issue mentioned above and resubmit your application with corrected documents or information.</p>
    
    <h3>Steps to Reapply:</h3>
    <ol>
        <li>Login to your account</li>
        <li>Go to 'Become a Vendor' page</li>
        <li>Upload corrected or clear documents</li>
        <li>Review all information carefully</li>
        <li>Resubmit your application</li>
    </ol>
    
    <a href='http://localhost/auth/become-vendor.php' class='button'>Resubmit Application</a>
    
    <h3>Document Requirements:</h3>
    <ul>
        <li>✓ National ID (Front and Back) - Clear and readable</li>
        <li>✓ PAN/VAT Certificate - Valid and not expired</li>
        <li>✓ Business Registration - Official document</li>
        <li>✓ All documents must be in JPG, PNG, or PDF format</li>
        <li>✓ Maximum file size: 5MB per document</li>
    </ul>
    
    <h3>Need Help?</h3>
    <p>If you believe this was a mistake or need clarification, please contact our support team at <a href='mailto:support@sastohub.com'>support@sastohub.com</a></p>
    
    <p>We look forward to your resubmission.</p>
    <p><strong>SASTO Hub Team</strong></p>
    ";
    
    if (empty($user['email'])) {
        error_log("Cannot send rejection email: empty email address");
        return false;
    }
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send vendor re-approval notification
 * 
 * @param array $vendor Vendor data from database
 * @param array $user User data from database
 * @return bool
 */
function sendVendorReapprovalEmail($vendor, $user) {
    $subject = "✅ Your Application is Now Approved - SASTO Hub";
    
    $body = "
    <h2>Great News! Your Application is Now Approved</h2>
    
    <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
    
    <div class='alert alert-success'>
        <p>Your resubmitted application has been reviewed and <span class='status-approved'>APPROVED</span>!</p>
    </div>
    
    <p>Thank you for addressing the feedback on your previous submission. We're now happy to have <strong>" . htmlspecialchars($vendor['shop_name']) . "</strong> as a seller on SASTO Hub.</p>
    
    <a href='http://localhost/vendor/' class='button'>Start Selling Now</a>
    
    <p>Your vendor account is now fully activated. You can:</p>
    <ul>
        <li>Upload your products</li>
        <li>Manage your inventory</li>
        <li>Process customer orders</li>
        <li>Withdraw your earnings</li>
    </ul>
    
    <p><strong>SASTO Hub Team</strong></p>
    ";
    
    if (empty($user['email'])) {
        error_log("Cannot send re-approval email: empty email address");
        return false;
    }
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send vendor revocation notification
 * 
 * @param array $vendor Vendor data from database
 * @param array $user User data from database
 * @param string $reason Optional revocation reason
 * @return bool
 */
function sendVendorRevocationEmail($vendor, $user, $reason = '') {
    $subject = "⚠️ Your Vendor Status Has Changed - SASTO Hub";
    
    $reasonText = '';
    if (!empty($reason)) {
        $reasonText = "
        <h3>Reason:</h3>
        <div class='alert alert-warning'>
            <p>" . htmlspecialchars($reason) . "</p>
        </div>
        ";
    }
    
    $body = "
    <h2>Your Vendor Status Has Changed</h2>
    
    <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
    
    <div class='alert alert-info'>
        <p>Your vendor status for <strong>" . htmlspecialchars($vendor['shop_name']) . "</strong> has been changed to <span class='status-pending'>PENDING REVIEW</span>.</p>
    </div>
    
    $reasonText
    
    <p>Your store is temporarily suspended while we review your account. You won't be able to accept new orders until your account is re-approved.</p>
    
    <p>If you have questions or would like to appeal this decision, please contact our support team at <a href='mailto:support@sastohub.com'>support@sastohub.com</a></p>
    
    <p><strong>SASTO Hub Team</strong></p>
    ";
    
    if (empty($user['email'])) {
        error_log("Cannot send revocation email: empty email address");
        return false;
    }
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send vendor settings change notification
 * 
 * @param array $vendor Vendor data
 * @param array $user User data
 * @param string $changedField Field that was changed
 * @return bool
 */
function sendVendorSettingsChangeEmail($vendor, $user, $changedField) {
    $subject = "Verification Required - Your " . $changedField . " Has Changed";
    
    $body = "
    <h2>Verification Required</h2>
    
    <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
    
    <div class='alert alert-info'>
        <p>You have changed your <strong>$changedField</strong>. Your vendor account status has been changed to <span class='status-pending'>PENDING VERIFICATION</span> and requires re-approval.</p>
    </div>
    
    <h3>What Happens Next?</h3>
    <p>Our admin team will review your updated information within 3-5 business days. You'll receive an email once your account is re-approved or if we need more information.</p>
    
    <p>Until then, you can still manage your products but your store won't be visible to customers.</p>
    
    <a href='http://localhost/vendor/' class='button'>Check Your Status</a>
    
    <p>If you have any questions, please contact us at <a href='mailto:support@sastohub.com'>support@sastohub.com</a></p>
    
    <p><strong>SASTO Hub Team</strong></p>
    ";
    
    if (empty($user['email'])) {
        error_log("Cannot send settings change email: empty email address");
        return false;
    }
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send admin notification for new vendor application
 * 
 * @param array $vendor Vendor data
 * @param array $user User data
 * @param int $documentCount Number of documents uploaded
 * @return bool
 */
function sendAdminNewVendorNotification($vendor, $user, $documentCount = 0) {
    $subject = "New Vendor Application - " . htmlspecialchars($vendor['shop_name']);
    
    $body = "
    <h2>New Vendor Application Received</h2>
    
    <p>A new vendor application has been submitted and is waiting for your review.</p>
    
    <h3>Vendor Information</h3>
    <ul>
        <li><strong>Shop Name:</strong> " . htmlspecialchars($vendor['shop_name']) . "</li>
        <li><strong>Owner:</strong> " . htmlspecialchars($user['full_name']) . "</li>
        <li><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</li>
        <li><strong>Location:</strong> " . htmlspecialchars($vendor['business_city']) . ", " . htmlspecialchars($vendor['business_state']) . "</li>
        <li><strong>Documents:</strong> " . $documentCount . "/4 uploaded</li>
    </ul>
    
    <a href='http://localhost/admin/vendors-verification.php' class='button'>Review Application</a>
    
    <p>The application is ready for verification in your admin dashboard.</p>
    
    <p><strong>SASTO Hub Admin Team</strong></p>
    ";
    
    // Send to admin email (defined in config)
    $adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@sastohub.com';
    return sendEmail($adminEmail, $subject, $body);
}
