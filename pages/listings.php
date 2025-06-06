<?php
// pages/listings.php
require_once '../includes/header.php';
require_once '../api/fetch_listings.php';

// Create an instance of PropertyListings
$listingApi = new PropertyListings();

// Define available universities
$universities = [
    '' => 'All Universities',
    'CBU' => 'Copperbelt University (CBU)',
    'UNZA' => 'University of Zambia (UNZA)',
    'UNILUS' => 'University of Lusaka (UNILUS)',
    'Mulungushi' => 'Mulungushi University',
    'Mukuba' => 'Mukuba University',
    'Copperstone' => 'Copperstone University'
];



// Get and sanitize filter parameters
$targetUniversity = isset($_GET['university']) ? htmlspecialchars(trim($_GET['university'])) : '';
$type = isset($_GET['type']) ? htmlspecialchars(trim($_GET['type'])) : '';
$priceMin = filter_input(INPUT_GET, 'priceMin', FILTER_VALIDATE_FLOAT);
$priceMax = filter_input(INPUT_GET, 'priceMax', FILTER_VALIDATE_FLOAT);

// Validate price ranges
if ($priceMin !== false && $priceMax !== false && $priceMin > $priceMax) {
    $temp = $priceMin;
    $priceMin = $priceMax;
    $priceMax = $temp;
}

// Ensure non-negative prices
$priceMin = ($priceMin !== false && $priceMin >= 0) ? $priceMin : '';
$priceMax = ($priceMax !== false && $priceMax >= 0) ? $priceMax : '';

// Validate university selection
if (!empty($targetUniversity) && !array_key_exists($targetUniversity, $universities)) {
    $targetUniversity = '';
}



// Fetch properties with filters
$properties = $listingApi->getAllProperties($targetUniversity, $type, $priceMin, $priceMax);
?>

<div class="max-w-6xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6 text-brand-gray">Browse Properties</h1>

    <!-- Filters Section -->
    <form method="GET" action="listings.php" class="mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">            <div>
                <select name="university" 
                        class="w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
                    <?php foreach ($universities as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $targetUniversity === $value ? 'selected' : '' ?>>
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
                       class="w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
            </div>

            <div>
                <input type="number" 
                       name="priceMax" 
                       placeholder="Max Price" 
                       value="<?= htmlspecialchars($priceMax) ?>"
                       class="w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="px-4 py-2 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                <i class="fa-solid fa-filter mr-2"></i>Filter
            </button>

            <a href="listings.php" class="px-4 py-2 bg-brand-light text-brand-gray rounded hover:bg-brand-light/80 transition-colors">
                <i class="fa-solid fa-rotate-left mr-2"></i>Reset Filters
            </a>
        </div>
    </form>

    <!-- Property Listings -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
        <?php if (!empty($properties) && !isset($properties['error'])): ?>
            <?php foreach ($properties as $property): ?>
                <div class="border border-brand-light rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow bg-white">
                    <!-- Property Image -->
                    <div class="relative h-48 bg-brand-light/20">
                        <img src="<?= htmlspecialchars($property['image_url']) ?>"
                             alt="<?= htmlspecialchars($property['title']) ?>"
                             class="w-full h-full object-cover">
                    </div>

                    <!-- Property Info -->
                    <div class="p-4">
                        <h2 class="text-lg font-semibold mb-2 text-brand-gray"><?= htmlspecialchars($property['title']) ?></h2>
                        
                        <div class="flex justify-between mb-2">
                            <span class="text-brand-gray/70"><?= htmlspecialchars($property['address']) ?></span>
                            <span class="font-bold text-brand-primary">ZMW <?= number_format($property['price']) ?></span>
                        </div>
                        
                        <p class="text-brand-gray/70 mb-4 line-clamp-2"><?= htmlspecialchars($property['description']) ?></p>
                        
                        <!-- Landlord Info -->
                        <div class="flex items-center justify-between text-sm mb-4">
                            <div class="flex items-center">
                                <i class="fa-solid fa-user mr-1 text-brand-gray/50"></i>
                                <span class="text-brand-gray/70"><?= htmlspecialchars($property['profiles']['name'] ?? 'Unknown') ?></span>
                                
                                <?php if (!empty($property['profiles']['is_verified']) && $property['profiles']['is_verified'] == 1): ?>
                                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1 text-xs"></i>Verified
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <a href="listing_detail.php?id=<?= $property['id'] ?>" 
                           class="inline-block w-full text-center py-2 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center col-span-full text-brand-gray/70">No properties found.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>