<?php
// includes/footer.php
?>
    </main>
    
    <footer class="bg-gray-800 text-white py-6">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between">
                <div class="mb-4 md:mb-0">
                    <h3 class="text-lg font-semibold mb-2"><?= $appName ?? 'Accommodation Listings' ?></h3>
                    <p>Find your perfect accommodation with ease.</p>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-2">Quick Links</h4>
                    <ul class="space-y-1">
                        <li><a href="index.php" class="hover:text-blue-300">Home</a></li>
                        <li><a href="listings.php" class="hover:text-blue-300">Properties</a></li>
                        <li><a href="about.php" class="hover:text-blue-300">About Us</a></li>
                        <li><a href="contact.php" class="hover:text-blue-300">Contact</a></li>
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