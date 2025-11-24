<?php
require_once '../config/config.php';
require_once '../config/database.php';

$page_title = 'Terms of Use - ' . getSetting('website_name', 'SASTO Hub');
include '../includes/header.php';
?>

<div class="bg-white py-6 border-b">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-gray-900">Terms of Use</h1>
        <p class="text-gray-600 mt-2">Last updated: <?php echo date('F d, Y'); ?></p>
    </div>
</div>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto">
        <!-- Table of Contents -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-12">
            <h2 class="text-lg font-bold text-blue-900 mb-4">Table of Contents</h2>
            <ul class="space-y-2 text-blue-900">
                <li><a href="#acceptance" class="hover:text-blue-700 underline">1. Acceptance of Terms</a></li>
                <li><a href="#user-accounts" class="hover:text-blue-700 underline">2. User Accounts</a></li>
                <li><a href="#products-services" class="hover:text-blue-700 underline">3. Products and Services</a></li>
                <li><a href="#user-conduct" class="hover:text-blue-700 underline">4. User Conduct</a></li>
                <li><a href="#vendor-agreement" class="hover:text-blue-700 underline">5. Vendor Agreement</a></li>
                <li><a href="#intellectual-property" class="hover:text-blue-700 underline">6. Intellectual Property</a></li>
                <li><a href="#limitation-liability" class="hover:text-blue-700 underline">7. Limitation of Liability</a></li>
                <li><a href="#dispute-resolution" class="hover:text-blue-700 underline">8. Dispute Resolution</a></li>
                <li><a href="#termination" class="hover:text-blue-700 underline">9. Termination</a></li>
                <li><a href="#amendments" class="hover:text-blue-700 underline">10. Amendments to Terms</a></li>
                <li><a href="#contact" class="hover:text-blue-700 underline">11. Contact Information</a></li>
            </ul>
        </div>

        <!-- Content -->
        <div class="space-y-8">
            <!-- Section 1 -->
            <section id="acceptance">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Acceptance of Terms</h2>
                <p class="text-gray-700 mb-3">
                    By accessing and using <?php echo htmlspecialchars(getSetting('website_name', 'SASTO Hub')); ?> (hereinafter referred to as the "Platform"), 
                    you acknowledge that you have read, understood, and agree to be bound by these Terms of Use and all applicable laws and regulations. 
                    If you do not agree to these terms, you must not use this Platform.
                </p>
                <p class="text-gray-700">
                    These terms apply to all users, including customers and vendors. Continued use of the Platform following any changes to these Terms 
                    constitutes your acceptance of the changes.
                </p>
            </section>

            <!-- Section 2 -->
            <section id="user-accounts">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">2. User Accounts</h2>
                
                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">2.1 Registration and Account Responsibility</h3>
                <p class="text-gray-700 mb-3">
                    To use certain features of the Platform, you must create an account. You agree to:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Provide accurate, current, and complete information</li>
                    <li>Maintain the confidentiality of your password and account information</li>
                    <li>Accept responsibility for all activities under your account</li>
                    <li>Notify us immediately of any unauthorized use of your account</li>
                </ul>

                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">2.2 Age Requirement</h3>
                <p class="text-gray-700">
                    You must be at least 18 years old or have the consent of a legal guardian to use this Platform. 
                    By registering, you represent and warrant that you meet this requirement.
                </p>
            </section>

            <!-- Section 3 -->
            <section id="products-services">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Products and Services</h2>
                
                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">3.1 Product Listings</h3>
                <p class="text-gray-700 mb-3">
                    The Platform displays products from multiple vendors. While we strive to ensure accuracy in product information, 
                    descriptions, pricing, and availability, we do not guarantee that all information is completely accurate, 
                    complete, or free of errors.
                </p>

                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">3.2 Right to Refuse Service</h3>
                <p class="text-gray-700 mb-3">
                    We reserve the right to refuse service, terminate accounts, or cancel orders at any time for any reason, 
                    including but not limited to:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2">
                    <li>Violation of these Terms of Use</li>
                    <li>Fraudulent or suspicious activity</li>
                    <li>Violation of applicable laws or regulations</li>
                </ul>
            </section>

            <!-- Section 4 -->
            <section id="user-conduct">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">4. User Conduct</h2>
                <p class="text-gray-700 mb-3">You agree not to use the Platform to:</p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Harass, threaten, defame, or abuse any person or entity</li>
                    <li>Post or transmit obscene, defamatory, or illegal content</li>
                    <li>Attempt to gain unauthorized access to the Platform or its systems</li>
                    <li>Interfere with or disrupt the normal operation of the Platform</li>
                    <li>Engage in any fraudulent or deceptive activity</li>
                    <li>Violate any applicable laws or regulations</li>
                    <li>Use spam, bots, or automated tools to access the Platform</li>
                    <li>Infringe upon the intellectual property rights of others</li>
                </ul>
            </section>

            <!-- Section 5 -->
            <section id="vendor-agreement">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Vendor Agreement</h2>
                
                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">5.1 Vendor Responsibilities</h3>
                <p class="text-gray-700 mb-3">Vendors using the Platform agree to:</p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Provide accurate and complete product information and images</li>
                    <li>Maintain current inventory and pricing information</li>
                    <li>Fulfill orders in accordance with product descriptions and agreed timelines</li>
                    <li>Maintain professional and courteous communication with customers</li>
                    <li>Comply with all applicable laws and regulations related to products and services</li>
                    <li>Not engage in any fraudulent, deceptive, or unethical practices</li>
                </ul>

                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">5.2 Commission and Fees</h3>
                <p class="text-gray-700 mb-3">
                    Vendors acknowledge that the Platform charges a commission on sales. The current commission rate is disclosed at the time of vendor registration.
                </p>

                <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-3">5.3 Verification and Document Requirements</h3>
                <p class="text-gray-700">
                    Vendors agree to provide necessary business documents, including but not limited to valid ID, business registration, 
                    and other documents as required by Nepali law. Vendors must undergo verification before their account is approved.
                </p>
            </section>

            <!-- Section 6 -->
            <section id="intellectual-property">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">6. Intellectual Property</h2>
                <p class="text-gray-700 mb-3">
                    All content on the Platform, including logos, text, graphics, and software, is the intellectual property of the Platform 
                    or its content suppliers and is protected by international copyright and trademark laws.
                </p>
                <p class="text-gray-700">
                    You may not reproduce, distribute, transmit, modify, or otherwise use any content without express written permission. 
                    Vendor-provided content remains the property of the respective vendors, who grant the Platform a license to display and 
                    distribute such content through the Platform.
                </p>
            </section>

            <!-- Section 7 -->
            <section id="limitation-liability">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Limitation of Liability</h2>
                <p class="text-gray-700 mb-3">
                    The Platform is provided on an "as is" and "as available" basis. We do not provide warranties, express or implied, 
                    regarding the Platform's quality, performance, or fitness for a particular purpose.
                </p>
                <p class="text-gray-700 mb-3">
                    To the maximum extent permitted by applicable law, in no event shall the Platform or its owners be liable for any indirect, 
                    incidental, special, or consequential damages, including but not limited to loss of profits or data, arising from your use 
                    of or inability to use the Platform.
                </p>
                <p class="text-gray-700">
                    Some jurisdictions do not allow the limitation of liability, so these limitations may not apply in your location.
                </p>
            </section>

            <!-- Section 8 -->
            <section id="dispute-resolution">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Dispute Resolution</h2>
                <p class="text-gray-700 mb-3">
                    Any dispute arising from the use of the Platform shall be governed by the laws of Nepal. 
                    You agree to submit any disputes to the jurisdiction of the courts located in Kathmandu, Nepal.
                </p>
                <p class="text-gray-700">
                    Before pursuing legal action, we encourage you to contact us to resolve any disputes amicably.
                </p>
            </section>

            <!-- Section 9 -->
            <section id="termination">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Termination</h2>
                <p class="text-gray-700 mb-3">
                    We reserve the right to terminate or suspend your account and access to the Platform at any time, 
                    with or without cause, and with or without notice, for any reason including:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 ml-2 mb-3">
                    <li>Violation of these Terms of Use</li>
                    <li>Fraudulent activity or suspicious behavior</li>
                    <li>Non-payment of fees</li>
                    <li>Inactivity</li>
                </ul>
                <p class="text-gray-700">
                    Upon termination, all rights granted to you are immediately revoked, and you must stop using the Platform.
                </p>
            </section>

            <!-- Section 10 -->
            <section id="amendments">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">10. Amendments to Terms</h2>
                <p class="text-gray-700">
                    We may modify these Terms of Use at any time. Changes will be effective immediately upon posting to the Platform. 
                    Your continued use of the Platform following the posting of modified Terms constitutes your acceptance of the modified Terms.
                </p>
            </section>

            <!-- Section 11 -->
            <section id="contact">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">11. Contact Information</h2>
                <p class="text-gray-700 mb-3">
                    If you have questions about these Terms of Use or wish to report a violation, please contact us:
                </p>
                <div class="bg-gray-100 rounded-lg p-6 mt-4">
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
