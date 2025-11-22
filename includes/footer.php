    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- About -->
                <div>
                    <h3 class="text-white text-xl font-bold mb-4">
                        <i class="fas fa-shopping-bag"></i> SASTO Hub
                    </h3>
                    <p class="text-sm">
                        Your trusted multi-vendor marketplace for quality products at affordable prices.
                    </p>
                    <div class="mt-4 flex gap-3">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-xl"></i></a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-white font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/pages/products.php" class="hover:text-white">All Products</a></li>
                        <li><a href="/pages/products.php?featured=1" class="hover:text-white">Featured Products</a></li>
                        <li><a href="/pages/products.php?sale=1" class="hover:text-white">Flash Sale</a></li>
                        <li><a href="/auth/become-vendor.php" class="hover:text-white">Become a Vendor</a></li>
                    </ul>
                </div>
                
                <!-- Customer Service -->
                <div>
                    <h4 class="text-white font-bold mb-4">Customer Service</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white">Help Center</a></li>
                        <li><a href="#" class="hover:text-white">Track Order</a></li>
                        <li><a href="#" class="hover:text-white">Returns</a></li>
                        <li><a href="#" class="hover:text-white">Contact Us</a></li>
                    </ul>
                </div>
                
                <!-- Why Choose Us -->
                <div>
                    <h4 class="text-white font-bold mb-4">Why Choose Us?</h4>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-truck text-primary mt-1"></i>
                            <div>
                                <strong class="text-white">Fast Delivery</strong>
                                <p class="text-xs">Quick and reliable delivery</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-shield-alt text-primary mt-1"></i>
                            <div>
                                <strong class="text-white">Secure Payment</strong>
                                <p class="text-xs">Safe and secure transactions</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-headset text-primary mt-1"></i>
                            <div>
                                <strong class="text-white">24/7 Support</strong>
                                <p class="text-xs">Always here to help</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Bottom Bar -->
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
                <p>&copy; <?php echo date('Y'); ?> SASTO Hub. All rights reserved. Built with ❤️ for multi-vendor shopping.</p>
            </div>
        </div>
    </footer>
    
    <!-- Custom JS -->
    <script src="/assets/js/main.js"></script>
    
    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.log('Service Worker registration failed'));
        }
    </script>
</body>
</html>
