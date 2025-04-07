<?php
// pages/listings.php
require_once '../includes/header.php';
require_once '../api/fetch_listings.php';

// Create an instance of PropertyListings
$listingApi = new PropertyListings();

// Get filter parameters
$location = isset($_GET['location']) ? $_GET['location'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$priceMin = isset($_GET['priceMin']) ? $_GET['priceMin'] : '';
$priceMax = isset($_GET['priceMax']) ? $_GET['priceMax'] : '';

// Fetch properties with filters
$properties = $listingApi->getAllProperties($location, $type, $priceMin, $priceMax);

// Property types for the dropdown
$propertyTypes = [
    'all' => 'All Types',
    'apartment' => 'Apartment',
    'shared' => 'Shared',
    'hostel' => 'Hostel'
];
?>

<div class="max-w-6xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Browse Properties</h1>

    <!-- Filters Section -->
    <form method="GET" action="listings.php" class="mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
                <input type="text" 
                       name="location" 
                       placeholder="Search by location..." 
                       value="<?= htmlspecialchars($location) ?>"
                       class="w-full p-2 border rounded">
            </div>

            <div>
                <select name="type" class="w-full p-2 border rounded">
                    <?php foreach ($propertyTypes as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <input type="number" 
                       name="priceMin" 
                       placeholder="Min Price" 
                       value="<?= htmlspecialchars($priceMin) ?>"
                       class="w-full p-2 border rounded">
            </div>

            <div>
                <input type="number" 
                       name="priceMax" 
                       placeholder="Max Price" 
                       value="<?= htmlspecialchars($priceMax) ?>"
                       class="w-full p-2 border rounded">
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Filter
            </button>

            <a href="listings.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                Reset Filters
            </a>
        </div>
    </form>

    <!-- Property Listings -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
        <?php if (!empty($properties) && !isset($properties['error'])): ?>
            <?php foreach ($properties as $property): ?>
                <div class="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                    <!-- Property Image -->
                    <div class="relative h-48">
                        <img src="<?= htmlspecialchars($property['image_url']) ?>"
                             alt="<?= htmlspecialchars($property['title']) ?>"
                             class="w-full h-full object-cover">
                    </div>

                    <!-- Property Info -->
                    <div class="p-4">
                        <h2 class="text-lg font-semibold mb-2"><?= htmlspecialchars($property['title']) ?></h2>
                        
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600"><?= htmlspecialchars($property['address']) ?></span>
                            <span class="font-bold">ZMW <?= number_format($property['price']) ?></span>
                        </div>
                        
                        <p class="text-gray-700 mb-4 line-clamp-2"><?= htmlspecialchars($property['description']) ?></p>
                        
                        <a href="listing_detail.php?id=<?= $property['id'] ?>" 
                           class="inline-block w-full text-center py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center col-span-full text-gray-500">No properties found.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>