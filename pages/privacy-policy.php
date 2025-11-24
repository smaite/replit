<?php
require_once '../config/config.php';
require_once '../config/database.php';

$page_title = 'Privacy Policy - ' . getSetting('website_name', 'SASTO Hub');
include '../includes/header.php';
?>

<div class="bg-white py-6 border-b">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-gray-900">Privacy Policy</h1>
        <p class="text-gray-600 mt-2">Last updated: <?php echo date('F d, Y'); ?></p>
    </div>
</div>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto">
        <!-- Important Notice -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-12">
            <p class="text-red-900">
                <strong>Important:</strong> This Privacy Policy explains how <?php echo htmlspecialchars(getSetting('website_name', 'SASTO Hub')); ?> 
                collects, uses, discloses, and safeguards your information when you use our Platform. 
                Please read this policy carefully to understand our practices regarding your personal data.
            </p>
        </div>

        <!-- Table of Contents -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-12">
            <h2 class="text-lg font-bold text-blue-900 mb-4">Table of Contents</h2>
            <ul class="space-y-2 text-blue-900">
                <li><a href="#information-collection" class="hover:text-blue-700 underline">1. Information Collection</a></li>
                <li><a href="#information-use" class="hover:text-blue-700 underline">2. How We Use Information</a></li>
                <li><a href="#information-sharing" class="hover:text-blue-700 underline">3. Information Sharing</a></li>
                <li><a href="#data-security" class="hover:text-blue-700 underline">4. Data Security</a></li>
                <li><a href="#user-rights" class="hover:text-blue-700 underline">5. Your Rights</a></li>
                <li><a href="#cookies" class="hover:text-blue-700 underline">6. Cookies and Tracking</a></li>
                <li><a href="#vendor-data" class="hover:text-blue-700 underline">7. Vendor Data</a></li>
                <li><a href="#retention" class="hover:text-blue-700 underline">8. Data Retention</a></li>
                <li><a href="#third-party" class="hover:text-blue-700 underline">9. Third-Party Links</a></li>
                <li><a href="#contact" class="hover:text-blue-700 underline">10. Contact Information</a></li>
            </ul>
        </div>

        <!-- Content -->
        <div class="space-y-8">
            <!-- Section 1 -->
            <section id="information-collection">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Information Collection</h2>
                
                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">1.1 Information You Provide</h3>
                <p class="text-gray-700 mb-3">We collect information that you voluntarily provide, including:</p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Account registration information (name, email, phone, password)</li>
                    <li>Profile information (address, business details for vendors)</li>
                    <li>Payment and billing information</li>
                    <li>Communication preferences</li>
                    <li>Customer service inquiries and feedback</li>
                    <li>Verification documents (ID, business registration)</li>
                </ul>

                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">1.2 Automatically Collected Information</h3>
                <p class="text-gray-700 mb-3">When you use our Platform, we automatically collect:</p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2">
                    <li>Device information (IP address, browser type, operating system)</li>
                    <li>Usage data (pages visited, time spent, links clicked)</li>
                    <li>Location information (with your consent)</li>
                    <li>Log data and analytics</li>
                </ul>
            </section>

            <!-- Section 2 -->
            <section id="information-use">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">2. How We Use Information</h2>
                <p class="text-gray-700 mb-3">We use collected information for the following purposes:</p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Account creation and management</li>
                    <li>Order processing and fulfillment</li>
                    <li>Payment processing and fraud prevention</li>
                    <li>Customer support and communication</li>
                    <li>Platform improvement and optimization</li>
                    <li>Vendor verification and compliance</li>
                    <li>Marketing and promotional purposes (with consent)</li>
                    <li>Legal compliance and dispute resolution</li>
                    <li>Analytics and business intelligence</li>
                </ul>
            </section>

            <!-- Section 3 -->
            <section id="information-sharing">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Information Sharing</h2>
                
                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">3.1 Vendor Information</h3>
                <p class="text-gray-700 mb-3">
                    To facilitate transactions, we share necessary information with vendors, including order details and delivery addresses. 
                    We only share information required for order fulfillment.
                </p>

                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">3.2 Third-Party Service Providers</h3>
                <p class="text-gray-700 mb-3">
                    We may share information with trusted third parties who assist in our operations, including:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Payment processors</li>
                    <li>Shipping and logistics providers</li>
                    <li>Email service providers</li>
                    <li>Analytics services</li>
                </ul>

                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">3.3 Legal Requirements</h3>
                <p class="text-gray-700">
                    We may disclose information when required by law or when we believe in good faith that disclosure is necessary to:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2">
                    <li>Comply with legal obligations</li>
                    <li>Enforce our Terms of Use</li>
                    <li>Protect the rights, privacy, safety, or property of users</li>
                </ul>
            </section>

            <!-- Section 4 -->
            <section id="data-security">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Data Security</h2>
                <p class="text-gray-700 mb-3">
                    We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, 
                    alteration, disclosure, or destruction. These measures include:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Encrypted data transmission (SSL/TLS)</li>
                    <li>Secure password storage with hashing</li>
                    <li>Access controls and user authentication</li>
                    <li>Regular security audits and updates</li>
                    <li>Firewall protection</li>
                </ul>
                <p class="text-gray-700">
                    However, no security system is impenetrable. While we strive to protect your information, we cannot guarantee absolute security.
                </p>
            </section>

            <!-- Section 5 -->
            <section id="user-rights">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Your Rights</h2>
                <p class="text-gray-700 mb-3">Depending on your location, you may have the following rights:</p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li><strong>Access:</strong> Request access to your personal information</li>
                    <li><strong>Correction:</strong> Request correction of inaccurate data</li>
                    <li><strong>Deletion:</strong> Request deletion of your data (subject to legal requirements)</li>
                    <li><strong>Data Portability:</strong> Request a copy of your data in a portable format</li>
                    <li><strong>Opt-Out:</strong> Opt out of marketing communications</li>
                </ul>
                <p class="text-gray-700 mt-3">
                    To exercise these rights, please contact us using the information provided at the end of this policy.
                </p>
            </section>

            <!-- Section 6 -->
            <section id="cookies">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">6. Cookies and Tracking</h2>
                <p class="text-gray-700 mb-3">
                    We use cookies and similar technologies to enhance your experience, remember preferences, and analyze usage patterns. 
                    You can control cookie settings in your browser, though disabling cookies may affect Platform functionality.
                </p>
                <p class="text-gray-700">
                    Types of cookies we use:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2">
                    <li><strong>Essential Cookies:</strong> Required for basic Platform functionality</li>
                    <li><strong>Analytics Cookies:</strong> Help us understand how users interact with the Platform</li>
                    <li><strong>Preference Cookies:</strong> Remember your preferences and settings</li>
                </ul>
            </section>

            <!-- Section 7 -->
            <section id="vendor-data">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Vendor Data</h2>
                <p class="text-gray-700 mb-3">
                    For vendor verification, we collect and process sensitive information including:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Government-issued identification documents</li>
                    <li>Business registration documents</li>
                    <li>Tax identification information</li>
                    <li>Bank account details</li>
                </ul>
                <p class="text-gray-700">
                    This information is:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2">
                    <li>Encrypted and securely stored</li>
                    <li>Only accessible to authorized personnel</li>
                    <li>Used exclusively for verification and compliance purposes</li>
                    <li>Deleted or anonymized after verification or as per legal requirements</li>
                </ul>
            </section>

            <!-- Section 8 -->
            <section id="retention">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Data Retention</h2>
                <p class="text-gray-700 mb-3">
                    We retain personal information for as long as necessary to provide services and fulfill the purposes outlined in this policy. 
                    Retention periods vary based on the type of information and applicable legal requirements:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Account information: As long as your account is active</li>
                    <li>Transaction records: 7 years (as per tax requirements)</li>
                    <li>Verification documents: 5 years after account closure</li>
                    <li>Analytics data: 12 months</li>
                </ul>
                <p class="text-gray-700">
                    You may request deletion of your data subject to legal and business requirements.
                </p>
            </section>

            <!-- Section 9 -->
            <section id="third-party">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Third-Party Links</h2>
                <p class="text-gray-700">
                    The Platform may contain links to third-party websites. This Privacy Policy applies only to the Platform. 
                    We are not responsible for the privacy practices of third-party websites. We encourage you to review the privacy policies 
                    of any third-party websites before providing your information.
                </p>
            </section>

            <!-- Section 10 -->
            <section id="contact">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">10. Contact Information</h2>
                <p class="text-gray-700 mb-4">
                    If you have questions about this Privacy Policy or wish to exercise your data rights, please contact us:
                </p>
                <div class="bg-gray-100 rounded-lg p-6 mb-6">
                    <p class="text-gray-800 mb-2">
                        <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars(getSetting('contact_email')); ?>" class="text-primary hover:underline">
                            <?php echo htmlspecialchars(getSetting('contact_email')); ?>
                        </a>
                    </p>
                    <p class="text-gray-800 mb-2">
                        <strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars(getSetting('contact_phone')); ?>" class="text-primary hover:underline">
                            <?php echo htmlspecialchars(getSetting('contact_phone')); ?>
                        </a>
                    </p>
                    <p class="text-gray-800">
                        <strong>Address:</strong> <?php echo htmlspecialchars(getSetting('address')); ?>
                    </p>
                </div>
                
                <p class="text-sm text-gray-600">
                    <strong>Data Protection Officer:</strong> If you have concerns about our privacy practices, 
                    you may contact our Data Protection Officer or submit a complaint to the appropriate data protection authority in Nepal.
                </p>
            </section>
        </div>

        <!-- Back Button -->
        <div class="mt-12 text-center">
            <a href="/" class="inline-block bg-primary hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
