<?php
// pages/dashboard.php

// Include necessary classes
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/fetch_listings.php';
require_once __DIR__ . '/../includes/header.php';

$auth = new Auth();
$propertyListings = new PropertyListings();

// Check if user is logged in
if (!$auth->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

// Get user details
$user = $auth->getCurrentUser();
$user_role = $user['profile']['role'] ?? 'unknown';
$user_name = $user['profile']['name'] ?? $user['auth']['user']['email'];
$user_id = $user['auth']['user']['id'];

// Initialize arrays for properties
$properties = [];
$dashboardError = null;

try {
    if ($user_role === 'landlord') {
        // Fetch properties owned by this landlord
        $properties = $propertyListings->getPropertiesByLandlord($user_id);
        if (isset($properties['error'])) {
            $dashboardError = "Could not load your properties: " . $properties['error'];
            $properties = [];
        }
    } elseif ($user_role === 'student') {
        // Fetch all listings for students
        $properties = $propertyListings->getAllProperties(null, null, null, null);
        if (isset($properties['error'])) {
            $dashboardError = "Could not load listings: " . $properties['error'];
            $properties = [];
        }
    }
} catch (Exception $e) {
    $dashboardError = "An error occurred: " . $e->getMessage();
    error_log("Dashboard Error: " . $e->getMessage() . " | User ID: " . $user_id);
}

// Handle messages
$successMessage = $_GET['success'] ?? null;
$errorMessage = $_GET['error'] ?? null;
?>

<div class="max-w-6xl mx-auto p-6">
    <!-- Dashboard Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p class="text-lg text-gray-600 mt-2">Role: <span class="font-semibold"><?php echo ucfirst(htmlspecialchars($user_role)); ?></span></p>
        </div>
        <?php if ($user_role === 'landlord'): ?>
            <a href="add-property.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                + Add New Property
            </a>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if ($successMessage): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($dashboardError): ?>
        <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
            <?php echo htmlspecialchars($dashboardError); ?>
        </div>
    <?php endif; ?>

    <!-- Properties/Listings Section -->
    <div class="mb-8">
        <h2 class="text-2xl font-semibold mb-4">
            <?php echo $user_role === 'landlord' ? 'Your Properties' : 'Available Listings'; ?>
        </h2>

        <?php if (!empty($properties)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($properties as $property): ?>
                    <div class="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow bg-white">
                        <div class="relative h-48 bg-gray-200">
                            <?php if (!empty($property['image_url']) && $property['image_url'] !== '/images/placeholder.svg'): ?>
                                <img src="<?= htmlspecialchars($property['image_url']) ?>"
                                     alt="<?= htmlspecialchars($property['title']) ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-500">
                                    <span>No Image Available</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <h3 class="text-lg font-semibold mb-1"><?= htmlspecialchars($property['title']) ?></h3>
                            <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($property['address'] ?? 'Location not specified') ?></p>
                            <p class="font-bold text-lg mb-3">K<?= number_format($property['price']) ?>/month</p>
                            
                            <div class="flex gap-2">
                                <?php if ($user_role === 'landlord'): ?>
                                    <a href="edit-property.php?id=<?= $property['id'] ?>" 
                                       class="px-3 py-1 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600">
                                        Edit
                                    </a>
                                    <a href="delete-property.php?id=<?= $property['id'] ?>" 
                                       class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600"
                                       onclick="return confirm('Are you sure you want to delete this property?')">
                                        Delete
                                    </a>
                                <?php endif; ?>
                                <a href="listing_detail.php?id=<?= $property['id'] ?>" 
                                   class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 <?= $user_role === 'student' ? 'w-full text-center' : '' ?>">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8 bg-gray-50 rounded-lg">
                <?php if ($user_role === 'landlord'): ?>
                    <p class="text-gray-600">You haven't listed any properties yet.</p>
                    <a href="add-property.php" class="text-blue-500 hover:underline mt-2 inline-block">Add your first property</a>
                <?php else: ?>
                    <p class="text-gray-600">No properties are currently available.</p>
                    <a href="listings.php" class="text-blue-500 hover:underline mt-2 inline-block">Check back later</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Account Actions -->
    <div class="mt-8 border-t pt-6">
        <h3 class="text-xl font-semibold mb-3">Account Actions</h3>
        <div class="space-x-4">
            <a href="edit-profile.php" class="text-blue-500 hover:underline">Edit Profile</a>
            <a href="logout.php" class="text-red-500 hover:underline">Logout</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>