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
        <h1 class="text-3xl font-bold text-brand-gray"><?= htmlspecialchars($property['title']) ?></h1>
        <a href="listings.php" class="text-brand-primary hover:text-brand-secondary transition-colors flex items-center">
            <i class="fa-solid fa-arrow-left mr-2"></i>Back to Listings
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
                            <span class="absolute top-2 left-2 bg-brand-primary text-white px-2 py-1 rounded text-sm">
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
            <h2 class="text-2xl font-semibold text-brand-gray">Description</h2>
            <p class="mt-2 text-brand-gray/70">
                <?= nl2br(htmlspecialchars($property['description'] ?? 'No description available')) ?>
            </p>

            <h2 class="text-2xl font-semibold mt-6 text-brand-gray">Amenities</h2>
            <?php if (!empty($amenities)): ?>
                <ul class="mt-2 space-y-2">
                    <?php foreach ($amenities as $amenity): ?>
                        <li class="flex items-center text-brand-gray/70">
                            <i class="fa-solid fa-check text-brand-primary mr-2"></i>
                            <?= htmlspecialchars($amenity) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="mt-2 text-brand-gray/70">No amenities listed</p>
            <?php endif; ?>

            <h2 class="text-2xl font-semibold mt-6 text-brand-gray">Location</h2>
            <div id="property-map" class="h-[300px] w-full mt-2 rounded-lg border border-brand-light"></div>
            <p class="mt-2 text-brand-gray/70">
                <?= htmlspecialchars($property['address'] ?? 'Location set on map') ?>
            </p>
        </div>

        <div class="bg-brand-light/10 p-6 rounded-lg border border-brand-light">
            <h2 class="text-2xl font-semibold text-brand-gray">Details</h2>
            <ul class="mt-4 space-y-4">
                <li class="flex justify-between">
                    <span class="text-brand-gray/70">Price:</span>
                    <span class="font-bold text-brand-primary">ZMW <?= number_format($property['price']) ?>/month</span>
                </li>
                <li class="flex justify-between">
                    <span class="text-brand-gray/70">Target University:</span>
                    <span class="text-brand-gray"><?= htmlspecialchars($property['target_university'] ?? 'Not specified') ?></span>
                </li>
                <li class="flex justify-between">
                    <span class="text-brand-gray/70">Landlord:</span>
                    <span class="text-brand-gray">
                        <?= htmlspecialchars($property['profiles']['name'] ?? 'Unknown') ?>
                        <?php if (!empty($property['profiles']['is_verified']) && $property['profiles']['is_verified'] == 1): ?>
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i>Verified
                            </span>
                        <?php endif; ?>
                    </span>
                </li>
            </ul>

            <div class="mt-6 space-y-3">
                <a href="mailto:<?= htmlspecialchars($property['profiles']['email'] ?? '') ?>" 
                   class="block w-full py-3 bg-brand-primary text-white text-center rounded hover:bg-brand-secondary transition-colors">
                    <i class="fa-solid fa-envelope mr-2"></i> Email Landlord
                </a>
                
                <?php if (!empty($property['profiles']['phone'])): ?>
                <a href="tel:<?= htmlspecialchars($property['profiles']['phone']) ?>" 
                   class="block w-full py-3 bg-brand-secondary text-white text-center rounded hover:bg-brand-secondary/90 transition-colors">
                    <i class="fa-solid fa-phone mr-2"></i> Call Landlord
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Include Leaflet for the map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Add Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const latitude = <?= $property['latitude'] ?? 0 ?>;
    const longitude = <?= $property['longitude'] ?? 0 ?>;
    
    // Initialize map
    const map = L.map('property-map').setView([latitude, longitude], 15);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add radius circle
    L.circle([latitude, longitude], {
        color: '#8CC63F',
        fillColor: '#8CC63F',
        fillOpacity: 0.2,
        radius: 500, // 500 meters radius
        weight: 2
    }).addTo(map);
});
</script>

<?php require_once '../includes/footer.php'; ?>