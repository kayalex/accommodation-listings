<?php
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/fetch_listings.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';

$auth = new Auth();
$propertyListings = new PropertyListings();

// Check authentication and role
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$user_role = $auth->getUserRole();
if ($user_role !== 'landlord') {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}

// Get property ID from URL
$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$property_id) {
    header('Location: dashboard.php?error=invalid_property');
    exit();
}

// Fetch property details
$property = $propertyListings->getPropertyById($property_id);

// Verify property ownership
if (!$property || $property['landlord_id'] !== $auth->getUserId()) {
    header('Location: dashboard.php?error=unauthorized');
    exit();
}

// Fetch amenities
$error = null;
$amenities = [];

try {
    $endpoint = SUPABASE_URL . '/rest/v1/amenities?select=id,name&order=name.asc';
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode === 200) {
        $amenities = json_decode($response, true);
    }
} catch (Exception $e) {
    $error = "Error fetching amenities: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $address = trim($_POST['address']);
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
        $selectedAmenities = isset($_POST['amenities']) ? $_POST['amenities'] : [];

        // Basic validation
        if (empty($title)) throw new Exception("Title is required");
        if ($price === false || $price <= 0) throw new Exception("Valid price is required");
        if ($latitude === false || $longitude === false) throw new Exception("Valid location is required");

        // Update property data
        $propertyData = [
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude
        ];

        // Update property in Supabase
        $endpoint = SUPABASE_URL . '/rest/v1/properties?id=eq.' . $property_id;
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($propertyData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 204) {
            throw new Exception("Failed to update property");
        }

        // Update amenities
        // First, delete existing amenities
        $endpoint = SUPABASE_URL . '/rest/v1/property_amenities?property_id=eq.' . $property_id;
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        // Insert new amenities
        if (!empty($selectedAmenities)) {
            foreach ($selectedAmenities as $amenityId) {
                $amenityData = [
                    'property_id' => $property_id,
                    'amenity_id' => (int)$amenityId
                ];

                $endpoint = SUPABASE_URL . '/rest/v1/property_amenities';
                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($amenityData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
        }

        header('Location: dashboard.php?success=Property updated successfully');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Property - <?= htmlspecialchars($property['title']) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6">Edit Property</h1>

        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($property['title']) ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="4" 
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= htmlspecialchars($property['description']) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Price per Month (K)</label>
                <input type="number" name="price" value="<?= htmlspecialchars($property['price']) ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($property['address']) ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Location</label>
                <div id="map" class="h-64 mt-1 rounded-md"></div>
                <input type="hidden" name="latitude" id="latitude" value="<?= $property['latitude'] ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= $property['longitude'] ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Amenities</label>
                <div class="mt-2 grid grid-cols-2 gap-4">
                    <?php foreach ($amenities as $amenity): ?>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="amenities[]" value="<?= $amenity['id'] ?>"
                                   <?php if (in_array($amenity['id'], array_column($property['property_amenities'] ?? [], 'amenity_id'))): ?>checked<?php endif; ?>
                                   class="rounded border-gray-300 text-blue-600">
                            <span class="ml-2"><?= htmlspecialchars($amenity['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end space-x-4">
                <a href="dashboard.php" class="px-4 py-2 border rounded-md hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <script>
        // Initialize map
        const map = L.map('map').setView([<?= $property['latitude'] ?>, <?= $property['longitude'] ?>], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        
        // Add draggable marker
        const marker = L.marker([<?= $property['latitude'] ?>, <?= $property['longitude'] ?>], {
            draggable: true
        }).addTo(map);

        // Update coordinates when marker is dragged
        marker.on('dragend', function(e) {
            document.getElementById('latitude').value = marker.getLatLng().lat;
            document.getElementById('longitude').value = marker.getLatLng().lng;
        });
    </script>
</body>
</html>