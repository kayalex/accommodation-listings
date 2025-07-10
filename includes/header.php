<?php
// includes/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/auth.php';

// Initialize Auth class
$auth = new Auth();

// Check if user is logged in and get user data
$isLoggedIn = $auth->isAuthenticated();
$userData = $isLoggedIn ? $auth->getCurrentUser() : null;
$userRole = $isLoggedIn ? $auth->getUserRole() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?? 'Accommodation Listings' ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand': {
                            'primary': '#8CC63F',
                            'secondary': '#5E8F2D',
                            'gray': '#333333',
                            'light': '#DDDDDD'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Additional CSS -->
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        /* Button hover effect */
        .hover-grow {
            transition: transform 0.2s;
        }
        .hover-grow:hover {
            transform: scale(1.02);
        }        /* Mobile menu styles */
        .mobile-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;
        }
        .mobile-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        @media (max-width: 768px) {
            .desktop-menu {
                display: none;
            }
            .mobile-menu {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                padding: 1rem;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                z-index: 50;
                border-top: 2px solid #8CC63F;
            }
        }
    </style>
</head>
<body class="bg-brand-light/10 min-h-screen">    <header class="bg-white shadow-sm relative h-12">
        <nav class="max-w-6xl mx-auto px-4 h-full">
            <div class="flex items-center justify-between h-full">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <a href="index.php" class="flex items-center space-x-1.5 hover-grow">                        <svg width="24" height="18" viewBox="0 0 300 220" xmlns="http://www.w3.org/2000/svg">
                        <!-- House Roof -->
                        <path d="M30 100 L150 20 L270 100 L250 100 L250 60 L230 60 L230 80 L210 80" fill="none" stroke="#333"stroke-width="12" stroke-linejoin="round" stroke-linecap="round"/>
                        
                        <!-- Windows -->
                        <rect x="110" y="70" width="25" height="25" rx="5" fill="#333"/>
                        <rect x="145" y="70" width="25" height="25" rx="5" fill="#333"/>
                        <rect x="110" y="105" width="25" height="25" rx="5" fill="#333"/>
                        <rect x="145" y="105" width="25" height="25" rx="5" fill="#333"/>
                        
                        <!-- Green Waves -->
                        <path d="M40 140 C70 120, 110 150, 150 130 C190 110, 230 140, 260 120" fill="none" stroke="#8CC63F" stroke-width="10" stroke-linecap="round"/>
                        <path d="M40 160 C80 140, 120 170, 160 150 C200 130, 240 160, 260 140" fill="none" stroke="#5E8F2D" stroke-width="10" stroke-linecap="round"/>
                              <!-- Shadow -->
                        <ellipse cx="150" cy="190" rx="100" ry="8" fill="#DDDDDD"/>
                    </svg>
                    <span class="text-sm font-medium text-brand-primary truncate max-w-[150px]"><?= $appName ?? 'Accommodation Listings' ?></span>
                </a>
                </div>

                <!-- Desktop Navigation Menu -->                <div class="desktop-menu hidden md:flex items-center gap-3">
                    <a href="index.php" class="text-brand-gray hover:text-brand-primary transition-colors text-sm flex items-center gap-1.5">
                        <i class="fas fa-home text-brand-primary/80"></i>
                        <span>Home</span>
                    </a>
                    <a href="listings.php" class="text-brand-gray hover:text-brand-primary transition-colors text-sm flex items-center gap-1.5">
                        <i class="fas fa-building text-brand-primary/80"></i>
                        <span>Listings</span>
                    </a>
                    <a href="about.php" class="text-brand-gray hover:text-brand-primary transition-colors text-sm flex items-center gap-1.5">
                        <i class="fas fa-info-circle text-brand-primary/80"></i>
                        <span>About</span>
                    </a>
                    <a href="contact.php" class="text-brand-gray hover:text-brand-primary transition-colors text-sm flex items-center gap-1.5">
                        <i class="fas fa-envelope text-brand-primary/80"></i>
                        <span>Contact</span>
                    </a><?php if ($isLoggedIn): ?>                        <a href="add-property.php" class="bg-brand-primary hover:bg-brand-secondary text-white px-2 py-1 rounded text-xs font-medium flex items-center gap-1.5">
                            <i class="fas fa-plus-circle"></i>
                            <span>Post Listing</span>
                        </a>
                        <div class="relative group">
                            <button class="flex items-center space-x-1 text-brand-gray hover:text-brand-primary text-xs py-1">
                                <i class="fas fa-user text-brand-primary/80"></i>
                                <span class="max-w-[100px] truncate"><?= htmlspecialchars($userData['profile']['name'] ?? 'User') ?></span>
                                <i class="fas fa-chevron-down text-[10px]"></i>
                            </button>                            <div class="absolute right-0 mt-0.5 w-48 bg-white rounded-md shadow-lg py-0.5 hidden group-hover:block">
                                <a href="edit-profile.php" class="block px-3 py-1 text-xs text-brand-gray hover:bg-brand-light/10 flex items-center gap-2">
                                    <i class="fas fa-user-edit w-4"></i>
                                    <span>Profile</span>
                                </a>
                                <a href="dashboard.php" class="block px-3 py-1 text-xs text-brand-gray hover:bg-brand-light/10 flex items-center gap-2">
                                    <i class="fas fa-list w-4"></i>
                                    <span>My Listings</span>
                                </a>
                                <?php if ($userRole === 'admin'): ?>
                                    <a href="admin.php" class="block px-3 py-1 text-xs text-brand-gray hover:bg-brand-light/10 flex items-center gap-2">
                                        <i class="fas fa-shield-alt w-4"></i>
                                        <span>Admin Dashboard</span>
                                    </a>
                                <?php endif; ?>
                                <a href="logout.php" class="block px-3 py-1 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2">
                                    <i class="fas fa-sign-out-alt w-4"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>                    <?php else: ?>                        <a href="login.php" class="text-brand-gray hover:text-brand-primary transition-colors text-xs flex items-center gap-1.5">
                            <i class="fas fa-sign-in-alt text-brand-primary/80"></i>
                            <span>Login</span>
                        </a>
                        <a href="sign-up.php" class="bg-brand-primary hover:bg-brand-secondary text-white px-2 py-1 rounded text-xs font-medium flex items-center gap-1.5">
                            <i class="fas fa-user-plus"></i>
                            <span>Register</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-button" class="md:hidden text-brand-gray hover:text-brand-primary">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Mobile Navigation Menu -->
            <div id="mobile-menu" class="mobile-menu">                <div class="flex flex-col space-y-4 py-4">
                    <a href="index.php" class="text-brand-gray hover:text-brand-primary transition-colors flex items-center gap-2">
                        <i class="fas fa-home w-5 text-brand-primary/80"></i>
                        <span>Home</span>
                    </a>
                    <a href="listings.php" class="text-brand-gray hover:text-brand-primary transition-colors flex items-center gap-2">
                        <i class="fas fa-building w-5 text-brand-primary/80"></i>
                        <span>Listings</span>
                    </a>
                    <a href="about.php" class="text-brand-gray hover:text-brand-primary transition-colors flex items-center gap-2">
                        <i class="fas fa-info-circle w-5 text-brand-primary/80"></i>
                        <span>About</span>
                    </a>
                    <a href="contact.php" class="text-brand-gray hover:text-brand-primary transition-colors flex items-center gap-2">
                        <i class="fas fa-envelope w-5 text-brand-primary/80"></i>
                        <span>Contact</span>
                    </a>
                    <?php if ($isLoggedIn): ?>
                        <a href="add-property.php" class="bg-brand-primary hover:bg-brand-secondary text-white px-4 py-2 rounded-md transition-colors text-center">Post Listing</a>
                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex flex-col space-y-2">
                                <a href="edit-profile.php" class="text-brand-gray hover:text-brand-primary">Profile</a>
                                <a href="dashboard.php" class="text-brand-gray hover:text-brand-primary">My Listings</a>
                                <?php if ($userRole === 'admin'): ?>
                                    <a href="admin.php" class="text-brand-gray hover:text-brand-primary">Admin Dashboard</a>
                                <?php endif; ?>
                                <a href="logout.php" class="text-red-600 hover:text-red-700">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col space-y-2">
                            <a href="login.php" class="text-brand-gray hover:text-brand-primary transition-colors text-center">Login</a>
                            <a href="sign-up.php" class="bg-brand-primary hover:bg-brand-secondary text-white px-4 py-2 rounded-md transition-colors text-center">Register</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="pb-12">
        <!-- Content goes here -->
        <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            let isMenuOpen = false;

            function toggleMenu() {
                isMenuOpen = !isMenuOpen;
                mobileMenu.classList.toggle('show');
                
                // Update button icon
                const icon = mobileMenuButton.querySelector('i');
                icon.classList.remove(isMenuOpen ? 'fa-bars' : 'fa-times');
                icon.classList.add(isMenuOpen ? 'fa-times' : 'fa-bars');
            }

            mobileMenuButton.addEventListener('click', toggleMenu);

            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                const isClickInside = mobileMenu.contains(event.target) || mobileMenuButton.contains(event.target);
                if (!isClickInside && isMenuOpen) {
                    toggleMenu();
                }
            });

            // Close menu when window is resized to desktop view
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768 && isMenuOpen) {
                    toggleMenu();
                }
            });
        });
    </script>
</body>
</html>