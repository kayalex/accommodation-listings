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
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        /* Button hover effect */
        .hover-grow {
            transition: transform 0.2s;
        }
        .hover-grow:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body class="bg-brand-light/10 min-h-screen">
    <header class="bg-white shadow-md">
        <nav class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-3 hover-grow">
                <svg width="32" height="24" viewBox="0 0 300 220" xmlns="http://www.w3.org/2000/svg">
                    <!-- House Roof -->
                    <path d="M30 100 L150 20 L270 100 L250 100 L250 60 L230 60 L230 80 L210 80" fill="none" stroke="#333" stroke-width="12" stroke-linejoin="round" stroke-linecap="round"/>
                    
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
                <span class="text-xl font-bold text-brand-primary"><?= $appName ?? 'Accommodation Listings' ?></span>
            </a>
            
            <div class="flex space-x-6 items-center">
                <a href="listings.php" class="text-brand-gray hover:text-brand-primary flex items-center hover-grow">
                    <i class="fa-solid fa-building mr-2"></i>Browse Listings
                </a>
                
                <?php if ($isLoggedIn): ?>
                    <?php if ($userRole === 'landlord'): ?>
                        <a href="add-property.php" class="px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-secondary flex items-center hover-grow transition-colors">
                            <i class="fa-solid fa-plus mr-2"></i>Add Property
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'admin'): ?>
                        <a href="admin.php" class="text-brand-gray hover:text-brand-primary flex items-center hover-grow">
                            <i class="fa-solid fa-shield-alt mr-2"></i>Admin Dashboard
                        </a>
                    <?php endif; ?>
                    
                    <a href="dashboard.php" class="text-brand-gray hover:text-brand-primary flex items-center hover-grow">
                        <i class="fa-solid fa-gauge-high mr-2"></i>Dashboard
                    </a>
                    
                    <div class="relative group">
                        <button class="text-brand-gray hover:text-brand-primary flex items-center hover-grow">
                            <i class="fa-solid fa-user mr-2"></i>
                            <?= htmlspecialchars($userData['profile']['name'] ?? $userData['auth']['user']['email']) ?>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg hidden group-hover:block z-50">
                            <a href="edit-profile.php" class="block px-4 py-2 text-sm text-brand-gray hover:bg-brand-light/20 flex items-center">
                                <i class="fa-solid fa-pen-to-square mr-2"></i>Edit Profile
                            </a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center">
                                <i class="fa-solid fa-right-from-bracket mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-brand-gray hover:text-brand-primary flex items-center hover-grow">
                        <i class="fa-solid fa-sign-in-alt mr-2"></i>Login
                    </a>
                    <a href="sign-up.php" class="px-4 py-2 bg-brand-primary text-white rounded-md hover:bg-brand-secondary flex items-center hover-grow transition-colors">
                        <i class="fa-solid fa-user-plus mr-2"></i>Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <main class="pb-12">
        <!-- Content goes here -->