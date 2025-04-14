<?php
// pages/dashboard.php

// Include necessary classes
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/fetch_listings.php'; // To fetch listings
require_once __DIR__ . '/../includes/header.php'; // Include standard header

$auth = new Auth();
$propertyListings = new PropertyListings(); // Instantiate listings class

// Check if user is logged in
if (!$auth->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

// Get user details
$user = $auth->getCurrentUser();
$user_role = $user['profile']['role'] ?? 'unknown'; // Get role from profile
$user_name = $user['profile']['name'] ?? $user['auth']['user']['email']; // Use name if available, else email
$user_id = $user['auth']['user']['id']; // Get user ID

// Fetch data based on role
$tenantProperties = [];
$latestListings = [];
$dashboardError = null;

try {
    if ($user_role === 'tenant') {
        // Fetch properties owned by this tenant
        $tenantProperties = $propertyListings->getPropertiesByLandlord($user_id);
        if (isset($tenantProperties['error'])) {
            $dashboardError = "Could not load your properties: " . $tenantProperties['error'];
            $tenantProperties = []; // Reset to empty array on error
        }
    } elseif ($user_role === 'student') {
        // Fetch latest listings (e.g., top 6)
        // Add limit parameter to getAllProperties if needed, or fetch all and limit here
        $allListings = $propertyListings->getAllProperties(null, null, null, null); // Add filters/limit if needed
         if (isset($allListings['error'])) {
            $dashboardError = "Could not load latest listings: " . $allListings['error'];
         } else {
            $latestListings = array_slice($allListings, 0, 6); // Get the first 6
         }
    }
} catch (Exception $e) {
    $dashboardError = "An error occurred while loading dashboard data: " . $e->getMessage();
    error_log("Dashboard Load Error: " . $e->getMessage() . " | User ID: " . $user_id);
}

// Handle success/error messages from redirects (e.g., after adding property)
$successMessage = $_GET['success'] ?? null;
$errorMessage = $_GET['error'] ?? null;

?>

<div class="max-w-6xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-4">Welcome to your Dashboard, <?php echo htmlspecialchars($user_name); ?>!</h1>
    <p class="text-lg text-gray-600 mb-6">Your role: <span class="font-semibold"><?php echo ucfirst(htmlspecialchars($user_role)); ?></span></p>

    <?php if ($successMessage): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
         <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
             <?php
                 switch ($errorMessage) {
                     case 'permission_denied':
                         echo "You do not have permission to access that page.";
                         break;
                     default:
                         echo htmlspecialchars($errorMessage);
                 }
             ?>
        </div>
    <?php endif; ?>
     <?php if ($dashboardError): ?>
         <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
             <?php echo htmlspecialchars($dashboardError); ?>
        </div>
    <?php endif; ?>


    <?php if ($user_role === 'tenant'): ?>
        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                 <h2 class="text-2xl font-semibold">Your Properties</h2>
                 <a href="add-property.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                     + Add New Property
                 </a>
            </div>

            <?php if (!empty($tenantProperties)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($tenantProperties as $property): ?>
                        <div class="border rounded-lg overflow-hidden shadow-sm bg-white">
                             <div class="relative h-40 bg-gray-200"> <?php if (!empty($property['image_url']) && $property['image_url'] !== '/images/placeholder.svg'): ?>
                                     <img src="<?= htmlspecialchars($property['image_url']) ?>"
                                          alt="<?= htmlspecialchars($property['title']) ?>"
                                          class="w-full h-full object-cover">
                                 <?php else: ?>
                                     <div class="w-full h-full flex items-center justify-center text-gray-500">No Image</div>
                                 <?php endif; ?>
                            </div>
                             <div class="p-4">
                                 <h3 class="text-lg font-semibold mb-1 truncate"><?= htmlspecialchars($property['title']) ?></h3>
                                 <p class="text-sm text-gray-500 mb-2 truncate"><?= htmlspecialchars($property['address'] ?? 'Address not set') ?></p>
                                 <p class="font-semibold mb-3">ZMW <?= number_format($property['price'] ?? 0) ?> / month</p>
                                 <div class="flex space-x-2">
                                     <a href="edit-property.php?id=<?= $property['id'] ?>" class="text-sm px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">Edit</a>
                                     <a href="delete-property.php?id=<?= $property['id'] ?>" class="text-sm px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600" onclick="return confirm('Are you sure you want to delete this property?');">Delete</a>
                                     <a href="listing_detail.php?id=<?= $property['id'] ?>" class="text-sm px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">View</a>
                                 </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                 <?php if(!$dashboardError): // Show only if there wasn't a fetch error ?>
                 <p class="text-gray-600">You haven't added any properties yet. <a href="add-property.php" class="text-blue-500 hover:underline">Add your first property now!</a></p>
                 <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($user_role === 'student'): ?>
        <div class="mb-8">
            <h2 class="text-2xl font-semibold mb-4">Latest Listings</h2>
             <?php if (!empty($latestListings)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                     <?php foreach ($latestListings as $property): ?>
                        <div class="border rounded-lg overflow-hidden shadow-sm bg-white">
                            <div class="relative h-40 bg-gray-200">
                                <?php if (!empty($property['image_url']) && $property['image_url'] !== '/images/placeholder.svg'): ?>
                                    <img src="<?= htmlspecialchars($property['image_url']) ?>"
                                         alt="<?= htmlspecialchars($property['title']) ?>"
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                     <div class="w-full h-full flex items-center justify-center text-gray-500">No Image</div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-1 truncate"><?= htmlspecialchars($property['title']) ?></h3>
                                <p class="text-sm text-gray-500 mb-2 truncate"><?= htmlspecialchars($property['address'] ?? 'Address not set') ?></p>
                                <p class="font-semibold mb-3">ZMW <?= number_format($property['price'] ?? 0) ?> / month</p>
                                <a href="listing_detail.php?id=<?= $property['id'] ?>"
                                   class="inline-block w-full text-center py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                 <div class="mt-6 text-center">
                     <a href="listings.php" class="text-blue-500 hover:underline">View All Listings &raquo;</a>
                 </div>
            <?php else: ?>
                 <?php if(!$dashboardError): // Show only if there wasn't a fetch error ?>
                 <p class="text-gray-600">No listings found at the moment. Check back later!</p>
                 <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mt-8 border-t pt-6">
         <h3 class="text-xl font-semibold mb-3">Account Actions</h3>
        <a href="edit-profile.php" class="text-blue-500 hover:underline mr-4">Edit Profile</a>
        <a href="logout.php" class="text-red-500 hover:underline">Logout</a>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; // Include standard footer ?>