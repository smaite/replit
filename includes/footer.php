    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- About -->
                <div>
                    <?php 
                        $footer_logo = getSetting('footer_logo');
                        $footer_name = getSetting('footer_name');
                    ?>
                    <?php if ($footer_logo): ?>
                        <img src="<?php echo htmlspecialchars($footer_logo); ?>" alt="Logo" class="h-12 mb-3">
                    <?php endif; ?>
                    <h3 class="text-white text-lg font-bold mb-4">
                        <!-- <?php echo htmlspecialchars($footer_name); ?> -->
                    </h3>
                    <p class="text-sm">
                        Your trusted multi-vendor marketplace for quality products at affordable prices.
                    </p>
                    <div class="mt-4 flex gap-3">
                        <?php 
                            $facebook_url = getSetting('facebook_url');
                            $twitter_url = getSetting('twitter_url');
                            $instagram_url = getSetting('instagram_url');
                        ?>
                        <?php if ($facebook_url): ?>
                            <a href="<?php echo htmlspecialchars($facebook_url); ?>" target="_blank" class="text-gray-400 hover:text-white"><i class="fab fa-facebook text-xl"></i></a>
                        <?php endif; ?>
                        <?php if ($twitter_url): ?>
                            <a href="<?php echo htmlspecialchars($twitter_url); ?>" target="_blank" class="text-gray-400 hover:text-white"><i class="fab fa-twitter text-xl"></i></a>
                        <?php endif; ?>
                        <?php if ($instagram_url): ?>
                            <a href="<?php echo htmlspecialchars($instagram_url); ?>" target="_blank" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-xl"></i></a>
                        <?php endif; ?>
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
                        <li><a href="/pages/privacy-policy.php" class="hover:text-white">Privacy Policy</a></li>
                        <li><a href="/pages/terms-of-use.php" class="hover:text-white">Terms of Use</a></li>
                        <li><a href="#" class="hover:text-white">Track Order</a></li>
                        <li><a href="#" class="hover:text-white">Contact Us</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div>
                    <h4 class="text-white font-bold mb-4">Contact Us</h4>
                    <ul class="space-y-3 text-sm">
                        <?php 
                            $contact_email = getSetting('contact_email');
                            $contact_phone = getSetting('contact_phone');
                            $address = getSetting('address');
                        ?>
                        <?php if ($contact_email): ?>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-envelope text-primary mt-1"></i>
                                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="hover:text-white"><?php echo htmlspecialchars($contact_email); ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if ($contact_phone): ?>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-phone text-primary mt-1"></i>
                                <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" class="hover:text-white"><?php echo htmlspecialchars($contact_phone); ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if ($address): ?>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-map-marker-alt text-primary mt-1"></i>
                                <span><?php echo htmlspecialchars($address); ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Bottom Bar -->
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
                <p><?php echo htmlspecialchars(getSetting('copyright_text')); ?></p>
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
