<?php
// pages/listing_detail.php
require_once '../includes/header.php';
require_once '../api/fetch_listings.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: listings.php');
    exit;
}

$propertyId = intval($_GET['id']);
$listingApi = new PropertyListings();
$property = $listingApi->getPropertyById($propertyId);

// If property not found, redirect to listings
if (!$property || isset($property['error'])) {
    header('Location: listings.php');
    exit;
}

// Extract amenities for easier display
$amenities = [];
if (!empty($property['property_amenities'])) {
    foreach ($property['property_amenities'] as $amenity) {
        $amenities[] = $amenity['amenities']['name'];
    }
}
?>

<div class="max-w-4xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold"><?= htmlspecialchars($property['title']) ?></h1>
        <a href="listings.php" class="text-blue-500 hover:underline">
            Back to Listings
        </a>
    </div>

    <!-- Image Gallery -->
    <div class="my-6">
        <?php if (!empty($property['property_images'])): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach ($property['property_images'] as $index => $image): ?>
                    <div class="relative">
                        <img src="<?= htmlspecialchars($image['public_url']) ?>"
                             alt="<?= htmlspecialchars($property['title']) ?> - Image <?= $index + 1 ?>"
                             class="rounded-lg w-full h-auto object-cover">
                        
                        <?php if ($image['is_primary']): ?>
                            <span class="absolute top-2 left-2 bg-blue-500 text-white px-2 py-1 rounded text-sm">
                                Primary
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <img src="https://via.placeholder.com/800x400"
                 alt="No images available"
                 class="rounded-lg w-full h-auto object-cover">
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h2 class="text-2xl font-semibold">Description</h2>
            <p class="mt-2 text-gray-700">
                <?= nl2br(htmlspecialchars($property['description'] ?? 'No description available')) ?>
            </p>

            <h2 class="text-2xl font-semibold mt-6">Amenities</h2>
            <?php if (!empty($amenities)): ?>
                <ul class="mt-2 list-disc list-inside">
                    <?php foreach ($amenities as $amenity): ?>
                        <li><?= htmlspecialchars($amenity) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="mt-2 text-gray-700">No amenities listed</p>
            <?php endif; ?>

            <h2 class="text-2xl font-semibold mt-6">Location</h2>
            <div id="property-map" class="h-[300px] w-full mt-2"></div>
            <p class="mt-2 text-gray-500">
                <?= htmlspecialchars($property['address'] ?? 'Location set on map') ?>
            </p>
        </div>

        <div class="bg-gray-100 p-4 rounded-lg">
            <h2 class="text-2xl font-semibold">Details</h2>
            <ul class="mt-2 space-y-2">
                <li>
                    <strong>Price:</strong> ZMW <?= number_format($property['price']) ?>/month
                </li>
                <li>
                    <strong>Type:</strong> <?= ucfirst(htmlspecialchars($property['type'] ?? 'Not specified')) ?>
                </li>
                <li>
                    <strong>Landlord:</strong> <?= htmlspecialchars($property['profiles']['name'] ?? 'Unknown') ?>
                </li>
                <li>
                    <strong>Email:</strong> <?= htmlspecialchars($property['profiles']['email'] ?? 'N/A') ?>
                </li>
                <?php if (!empty($property['profiles']['phone'])): ?>
                <li>
                    <strong>Phone:</strong> <?= htmlspecialchars($property['profiles']['phone']) ?>
                </li>
                <?php endif; ?>
            </ul>

            <a href="mailto:<?= htmlspecialchars($property['profiles']['email'] ?? '') ?>" 
               class="block mt-4 w-full py-2 bg-blue-500 text-white text-center rounded hover:bg-blue-600">
                Contact Landlord
            </a>
        </div>
    </div>
</div>

<!-- Include Leaflet for the map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const latitude = <?= $property['latitude'] ?? 0 ?>;
    const longitude = <?= $property['longitude'] ?? 0 ?>;
    
    // Initialize map
    const map = L.map('property-map').setView([latitude, longitude], 16);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add marker
    L.marker([latitude, longitude]).addTo(map);
});
</script>

<?php require_once '../includes/footer.php'; ?>