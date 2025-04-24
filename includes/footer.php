<?php
// includes/footer.php
?>
    </main>
    
    <footer class="bg-brand-gray text-white py-6">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between">
                <div class="mb-4 md:mb-0">
                    <div class="flex items-center space-x-3 mb-4">
                        <svg width="32" height="24" viewBox="0 0 300 220" xmlns="http://www.w3.org/2000/svg">
                            <!-- House Roof -->
                            <path d="M30 100 L150 20 L270 100 L250 100 L250 60 L230 60 L230 80 L210 80" fill="none" stroke="currentColor" stroke-width="12" stroke-linejoin="round" stroke-linecap="round"/>
                            
                            <!-- Windows -->
                            <rect x="110" y="70" width="25" height="25" rx="5" fill="currentColor"/>
                            <rect x="145" y="70" width="25" height="25" rx="5" fill="currentColor"/>
                            <rect x="110" y="105" width="25" height="25" rx="5" fill="currentColor"/>
                            <rect x="145" y="105" width="25" height="25" rx="5" fill="currentColor"/>
                            
                            <!-- Green Waves -->
                            <path d="M40 140 C70 120, 110 150, 150 130 C190 110, 230 140, 260 120" fill="none" stroke="#8CC63F" stroke-width="10" stroke-linecap="round"/>
                            <path d="M40 160 C80 140, 120 170, 160 150 C200 130, 240 160, 260 140" fill="none" stroke="#5E8F2D" stroke-width="10" stroke-linecap="round"/>
                            
                            <!-- Shadow -->
                            <ellipse cx="150" cy="190" rx="100" ry="8" fill="#555555"/>
                        </svg>
                        <h3 class="text-lg font-semibold"><?= $appName ?? 'Accommodation Listings' ?></h3>
                    </div>
                    <p>Find your perfect accommodation with ease.</p>
                    
                    <div class="mt-4 flex space-x-4">
                        <a href="#" class="text-brand-light hover:text-brand-primary transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-brand-light hover:text-brand-primary transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-brand-light hover:text-brand-primary transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-2 text-brand-primary">Quick Links</h4>
                    <ul class="space-y-2">
                        <li>
                            <a href="index.php" class="hover:text-brand-primary transition-colors flex items-center">
                                <i class="fa-solid fa-home mr-2"></i>Home
                            </a>
                        </li>
                        <li>
                            <a href="listings.php" class="hover:text-brand-primary transition-colors flex items-center">
                                <i class="fa-solid fa-building mr-2"></i>Properties
                            </a>
                        </li>
                        <li>
                            <a href="about.php" class="hover:text-brand-primary transition-colors flex items-center">
                                <i class="fa-solid fa-info-circle mr-2"></i>About Us
                            </a>
                        </li>
                        <li>
                            <a href="contact.php" class="hover:text-brand-primary transition-colors flex items-center">
                                <i class="fa-solid fa-envelope mr-2"></i>Contact
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-700 text-center text-sm">
                <p>&copy; <?= date('Y') ?> <?= $appName ?? 'Accommodation Listings' ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Custom JavaScript -->
    <script src="../public/js/main.js"></script>
</body>
</html>