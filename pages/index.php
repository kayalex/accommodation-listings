<?php

// Include necessary files
include_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../api/fetch_listings.php";
require_once __DIR__ . '/../includes/header.php'; // Include standard header


// Initialize PropertyListings class
$listingApi = new PropertyListings();
$properties = [];
$error = null;

try {
    // Get latest 6 properties using the PropertyListings class
    $properties = $listingApi->getAllProperties(null, null, null, null);
    
    // Limit to 6 properties if we got more
    if (!isset($properties['error'])) {
        $properties = array_slice($properties, 0, 6);
    } else {
        $error = $properties['error'];
        $properties = [];
    }
} catch (Exception $e) {
    $error = "Error fetching property listings: " . $e->getMessage();
    $properties = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Housing - Find Your Perfect Off-Campus Home</title>
    <meta name="description" content="Browse and list off-campus student accommodations with ease.">
    
    <!-- Import Google Font (Geist equivalent) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .animate-fade-in {
            animation: fadeIn 1s ease-in;
        }
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        .transition-transform {
            transition: transform 0.3s ease;
        }
        .transform.hover\:scale-105:hover {
            transform: scale(1.05);
        }
        /* Dark/Light mode styles */
        :root {
            --background: #ffffff;
            --foreground: #333333;
            --muted-foreground: #6b7280;
            --primary: #8CC63F;
            --secondary: #5E8F2D;
            --border: #DDDDDD;
        }
        .dark-mode {
            --background: #333333;
            --foreground: #f7fafc;
            --muted-foreground: #a0aec0;
            --primary: #8CC63F;
            --secondary: #5E8F2D;
            --border: #4a5568;
        }
        body {
            background-color: var(--background);
            color: var(--foreground);
        }
        .bg-background { background-color: var(--background); }
        .text-foreground { color: var(--foreground); }
        .text-muted-foreground { color: var(--muted-foreground); }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .border-border { border-color: var(--border); }
    </style>
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <!-- Main content -->
        <main class="flex-1">
            <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="space-y-12">
                    <!-- Hero Section with Fade-In Animation -->
                    <section class="text-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-brand-primary to-brand-secondary text-white animate-fade-in rounded-lg">
                        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl md:text-6xl">
                            Find Your Perfect Student Home at CBU
                        </h1>
                        
                        <p class="mt-6 max-w-2xl mx-auto text-xl">
                            Discover comfortable and affordable off-campus accommodations tailored
                            for Copperbelt University students.
                        </p>
                        <div class="mt-10 flex justify-center gap-4">                            <a href="listings.php" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-primary/90 transition-transform transform hover:scale-105">
                                Browse Listings
                            </a>
                            <a href="add-property.php" class="inline-flex items-center justify-center px-5 py-3 border border-primary text-base font-medium rounded-md text-primary bg-white hover:bg-primary/5 transition-transform transform hover:scale-105">
                                List Your Property
                            </a>
                        </div>
                    </section>

                    <!-- Latest Listings Section -->
                    <section class="max-w-7xl mx-auto">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold tracking-tight text-brand-gray">Latest Listings</h2>
                            <a href="properties.php" class="text-brand-primary hover:text-brand-secondary">
                                View all listings
                            </a>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php if (!empty($properties)): ?>
                                <?php foreach ($properties as $property): ?>
                                    <div class="border border-brand-light rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow bg-white">
                                        <div class="relative h-48">
                                            <?php if (!empty($property['image_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($property['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($property['title'] ?? 'Property Image'); ?>" 
                                                     class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <div class="h-full w-full bg-gray-200 flex items-center justify-center">
                                                    <span class="text-gray-400">No image available</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-4">
                                            <h3 class="font-semibold text-lg text-brand-gray">
                                                <?php echo htmlspecialchars($property['title'] ?? 'Untitled Property'); ?>
                                            </h3>
                                            <p class="text-sm text-brand-gray/70 mt-1">
                                                <?php echo htmlspecialchars($property['address'] ?? 'Location not specified'); ?>
                                            </p>
                                            <div class="flex justify-between items-center mt-4">
                                                <span class="font-bold text-brand-primary">
                                                    K<?php echo number_format($property['price'] ?? 0); ?>/month
                                                </span>
                                                <a href="listing_detail.php?id=<?php echo $property['id']; ?>" 
                                                   class="px-3 py-1 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                                                    View details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-3 text-center py-12">
                                    <p class="text-brand-gray/70">
                                        <?php echo $error ?? 'No properties available at the moment.'; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Why Choose Section with Fade-In Animation -->
                    <section class="text-center py-12 px-4 sm:px-6 lg:px-8 animate-fade-in">
                        <h2 class="text-3xl font-bold tracking-tight text-brand-gray">
                            Why Choose CBU Accommodation Listing?
                        </h2>
                        <p class="mt-6 max-w-2xl mx-auto text-xl text-brand-gray/70">
                            Our platform connects CBU students with trusted landlords, offering a
                            seamless way to find and list off-campus housing.
                        </p>
                        <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="bg-white p-6 rounded-lg shadow-md transition-transform transform hover:scale-105 border border-brand-light">
                                <h3 class="text-xl font-semibold text-brand-primary">For Students</h3>
                                <p class="mt-4 text-brand-gray/70">
                                    Find safe, affordable, and convenient housing near campus.
                                </p>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow-md transition-transform transform hover:scale-105 border border-brand-light">
                                <h3 class="text-xl font-semibold text-brand-primary">For Landlords</h3>
                                <p class="mt-4 text-brand-gray/70">
                                    Reach a targeted audience of CBU students looking for housing.
                                </p>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow-md transition-transform transform hover:scale-105 border border-brand-light">
                                <h3 class="text-xl font-semibold text-brand-primary">Community Focused</h3>
                                <p class="mt-4 text-brand-gray/70">
                                    Built with the CBU community in mind, ensuring a smooth experience
                                    for all.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

        
    </div>

    <script>
        // Theme toggler functionality
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');
            const themeLight = document.querySelector('.theme-light');
            const themeDark = document.querySelector('.theme-dark');

            // Check for saved theme preference or respect OS preference
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.body.classList.add('dark-mode');
                themeLight.classList.remove('hidden');
                themeDark.classList.add('hidden');
            } else {
                themeLight.classList.add('hidden');
                themeDark.classList.remove('hidden');
            }

            // Theme toggle click handler
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                themeLight.classList.toggle('hidden');
                themeDark.classList.toggle('hidden');

                // Save preference
                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('theme', 'dark');
                } else {
                    localStorage.setItem('theme', 'light');
                }
            });
        });
    </script>
</body>
</html>