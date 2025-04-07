<?php
// includes/header.php
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
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
                <a href="listings.php" class="text-gray-600 hover:text-blue-600">Properties</a>
                
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php" class="text-gray-600 hover:text-blue-600">Dashboard</a>
                    <a href="create_listing.php" class="text-gray-600 hover:text-blue-600">Create Listing</a>
                    <a href="../api/auth.php?action=logout" class="text-gray-600 hover:text-blue-600">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="text-gray-600 hover:text-blue-600">Login</a>
                    <a href="register.php" class="text-gray-600 hover:text-blue-600">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <main class="pb-12">
        <!-- Content goes here -->