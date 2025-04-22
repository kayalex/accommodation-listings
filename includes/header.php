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
    
    <!-- Additional CSS -->
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white shadow">
        <nav class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="index.php" class="text-xl font-bold text-blue-600">
                <?= $appName ?? 'Accommodation Listings' ?>
            </a>
            
            <div class="flex space-x-4">
                <a href="listings.php" class="text-gray-600 hover:text-blue-600">Browse Listings</a>
                
                <?php if ($isLoggedIn): ?>
                    <?php if ($userRole === 'landlord'): ?>
                        <a href="add-property.php" class="text-gray-600 hover:text-blue-600">Add Property</a>
                    <?php endif; ?>
                    
                    <a href="dashboard.php" class="text-gray-600 hover:text-blue-600">Dashboard</a>
                    
                    <div class="relative group">
                        <button class="text-gray-600 hover:text-blue-600">
                            <?= htmlspecialchars($userData['profile']['name'] ?? $userData['auth']['user']['email']) ?>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg hidden group-hover:block">
                            <a href="edit-profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Edit Profile
                            </a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-gray-600 hover:text-blue-600">Login</a>
                    <a href="sign-up.php" class="text-gray-600 hover:text-blue-600">Sign Up</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <main class="pb-12">
        <!-- Content goes here -->