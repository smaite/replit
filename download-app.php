<?php
require_once 'config/config.php';
require_once 'config/database.php';

$page_title = 'Download Sasto Hub App - SASTO Hub';
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto">
        <!-- Header Section -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Download Sasto Hub App</h1>
            <p class="text-xl text-gray-600">Shop smarter with our mobile app - Available for Android</p>
        </div>

        <!-- App Preview & Download Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="md:flex">
                <!-- Left Side - App Info -->
                <div class="md:w-1/2 p-8 bg-gradient-to-br from-orange-500 to-orange-600 text-white">
                    <div class="mb-6">
                        <h2 class="text-3xl font-bold mb-4">Sasto Hub Mobile</h2>
                        <p class="text-orange-100 mb-6">Experience seamless shopping on the go with our feature-rich mobile application.</p>
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-2xl mr-3 mt-1"></i>
                            <div>
                                <h3 class="font-semibold text-lg">Browse Thousands of Products</h3>
                                <p class="text-orange-100 text-sm">Access our complete catalog anytime, anywhere</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-2xl mr-3 mt-1"></i>
                            <div>
                                <h3 class="font-semibold text-lg">Secure Payments</h3>
                                <p class="text-orange-100 text-sm">Multiple payment options with secure checkout</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-2xl mr-3 mt-1"></i>
                            <div>
                                <h3 class="font-semibold text-lg">Track Your Orders</h3>
                                <p class="text-orange-100 text-sm">Real-time order tracking and notifications</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-2xl mr-3 mt-1"></i>
                            <div>
                                <h3 class="font-semibold text-lg">Exclusive Deals</h3>
                                <p class="text-orange-100 text-sm">Get app-only discounts and flash sales</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <p class="text-orange-100">Version</p>
                                <p class="font-semibold">1.0.0</p>
                            </div>
                            <div>
                                <p class="text-orange-100">Size</p>
                                <p class="font-semibold">~53 MB</p>
                            </div>
                            <div>
                                <p class="text-orange-100">Updated</p>
                                <p class="font-semibold">Jan 2026</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Download Section -->
                <div class="md:w-1/2 p-8 flex flex-col justify-center">
                    <div class="text-center mb-8">
                        <i class="fab fa-android text-8xl text-green-500 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">Android App</h3>
                        <p class="text-gray-600">Compatible with Android 5.0 and above</p>
                    </div>

                    <!-- Download Button -->
                    <a href="https://github.com/smaite/replit/releases/download/1.0.0/app-release.apk" download class="block w-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold py-4 px-8 rounded-xl text-center text-lg shadow-lg transform transition hover:scale-105 mb-4">
                        <i class="fas fa-download mr-2"></i>
                        Download APK
                    </a>

                    <!-- Installation Instructions -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-blue-900 mb-2 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Installation Instructions
                        </h4>
                        <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                            <li>Download the APK file</li>
                            <li>Open the downloaded file</li>
                            <li>Allow installation from unknown sources if prompted</li>
                            <li>Follow the on-screen instructions</li>
                            <li>Launch the app and start shopping!</li>
                        </ol>
                    </div>

                    <!-- Security Note -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-sm text-green-800 flex items-start">
                            <i class="fas fa-shield-alt mr-2 mt-1"></i>
                            <span><strong>Safe & Secure:</strong> This APK is officially signed and verified by Sasto Hub. Your data and privacy are protected.</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="mt-12">
            <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">Frequently Asked Questions</h3>
            <div class="space-y-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-800 mb-2">Why isn't the app on Google Play Store?</h4>
                    <p class="text-gray-600">We're currently in the process of submitting our app to the Play Store. In the meantime, you can download it directly from our website.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-800 mb-2">Is it safe to install APK files?</h4>
                    <p class="text-gray-600">Yes! Our APK is digitally signed and safe to install. Make sure you only download from our official website to ensure security.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-800 mb-2">Will I get automatic updates?</h4>
                    <p class="text-gray-600">Currently, you'll need to download new versions manually from this page. We'll notify you via email when updates are available.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-800 mb-2">What if I have issues installing?</h4>
                    <p class="text-gray-600">Contact our support team at <a href="mailto:support@sastohub.com" class="text-orange-600 hover:underline">support@sastohub.com</a> and we'll help you get set up.</p>
                </div>
            </div>
        </div>

        <!-- Back to Home -->
        <div class="text-center mt-12">
            <a href="/" class="text-orange-600 hover:text-orange-700 font-semibold">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Home
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
