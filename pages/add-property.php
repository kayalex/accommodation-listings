<?php
// pages/add-property.php

// Include the updated Auth class
// Ensure the path is correct relative to add-property.php
require_once __DIR__ . '/../api/auth.php'; // Use __DIR__ for reliability
require_once __DIR__ . '/../config/config.php'; // Include DB config (if still using PDO for this page)

$auth = new Auth();

// Check if the user is authenticated (includes profile check now)
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

// Get user ID and Role using the Auth class methods
$user_id = $auth->getUserId();
$user_role = $auth->getUserRole();

// Check if the user has the correct role to add properties
// Using 'tenant' based on your description, change to 'landlord' if that's the role name in your DB
if ($user_role !== 'landlord') {
    // Optional: Redirect to dashboard or show an error message
    // echo "Access Denied: You do not have permission to add properties.";
    header('Location: dashboard.php?error=permission_denied'); // Redirect to dashboard
    exit;
}

// *** Database Connection for Property/Amenity Operations ***
// Decide if you are using Supabase API calls via PHP (like in Auth/PropertyListings)
// OR a direct PDO connection for these operations.
// The code below uses Supabase API based on the provided suggestion.

$error = null; // Initialize error variable
$amenities = []; // Initialize amenities array

try {
    // Supabase API call to fetch amenities
    $supabaseUrl = SUPABASE_URL;
    $supabaseKey = SUPABASE_KEY;

    $endpoint = $supabaseUrl . '/rest/v1/amenities?select=id,name&order=name.asc';
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode === 200) {
        $amenities = json_decode($response, true);
    } else {
        $error = "Failed to fetch amenities. Status code: $statusCode";
    }
} catch (Exception $e) {
    $error = "Error fetching amenities: " . $e->getMessage();
}

// Default map center coordinates (Keep as is)
$defaultLat = -12.80532;
$defaultLng = 28.24403;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Only proceed if DB connection was successful
    $uploadProgress = 0;

    try {
        // Validate inputs (Keep existing validation)
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT); // Use filter_input
        $address = trim($_POST['address']);
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
        $selectedAmenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];

        // Basic validation examples (enhance as needed)
        if (empty($title)) throw new Exception("Title is required");
        if ($price === false || $price <= 0) throw new Exception("Valid price is required");
        if ($latitude === false || $longitude === false) throw new Exception("Valid map location is required");
        if (empty($_FILES['images']['name'][0])) throw new Exception("Please upload at least one image");

        // Insert property (Using Supabase API)
        $propertyData = [
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address ?: null,
            'landlord_id' => $user_id
        ];

        $endpoint = $supabaseUrl . '/rest/v1/properties';
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($propertyData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 201) {
            throw new Exception("Failed to add property. Status code: $statusCode");
        }

        $propertyId = json_decode($response, true)['id'];

        // Insert amenities (Using Supabase API)
        if (!empty($selectedAmenities)) {
            foreach ($selectedAmenities as $amenityId) {
                $validAmenityId = filter_var($amenityId, FILTER_VALIDATE_INT);
                if ($validAmenityId) {
                    $amenityData = [
                        'property_id' => $propertyId,
                        'amenity_id' => $validAmenityId
                    ];

                    $endpoint = $supabaseUrl . '/rest/v1/property_amenities';
                    $ch = curl_init($endpoint);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($amenityData));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $response = curl_exec($ch);
                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($statusCode !== 201) {
                        throw new Exception("Failed to add amenity. Status code: $statusCode");
                    }
                }
            }
        }

        // Process images (Keep existing logic, saves locally)
        $totalImages = count($_FILES['images']['name']);
        $uploadDir = '../public/uploads/properties/landlord_' . $user_id . '/' . $propertyId . '/';

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory. Check permissions.");
            }
        }

        for ($i = 0; $i < $totalImages; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading file " . $_FILES['images']['name'][$i] . ": Error code " . $_FILES['images']['error'][$i]);
            }

            $tmpName = $_FILES['images']['tmp_name'][$i];
            $name = basename($_FILES['images']['name'][$i]);
            $size = $_FILES['images']['size'][$i];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedMimeTypes)) {
                throw new Exception("File '{$name}' is not a valid image type ({$mimeType}). Allowed: JPG, PNG, GIF, WEBP.");
            }

            if ($size > 5 * 1024 * 1024) {
                throw new Exception("Image '{$name}' exceeds the 5MB size limit.");
            }

            $sanitizedName = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $name);
            $sanitizedName = preg_replace('/_+/', '_', $sanitizedName);
            $fileExtension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
            $fileNameWithoutExt = pathinfo($sanitizedName, PATHINFO_FILENAME);
            $fileName = $fileNameWithoutExt . '_' . time() . '_' . $i . '.' . strtolower($fileExtension);
            $filePath = $uploadDir . $fileName;
            $storagePathForDb = 'properties/landlord_' . $user_id . '/' . $propertyId . '/' . $fileName;

            if (!move_uploaded_file($tmpName, $filePath)) {
                throw new Exception("Failed to move uploaded image '{$name}'. Check directory permissions.");
            }

            $imageData = [
                'property_id' => $propertyId,
                'storage_path' => $storagePathForDb,
                'is_primary' => $i === 0 ? 1 : 0,
                'public_url' => rtrim($appUrl, '/') . '/uploads/' . $storagePathForDb
            ];

            $endpoint = $supabaseUrl . '/rest/v1/property_images';
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($imageData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($statusCode !== 201) {
                throw new Exception("Failed to add image. Status code: $statusCode");
            }

            $uploadProgress = ($i + 1) / $totalImages * 100;
        }

        // Success: Redirect to dashboard
        header('Location: dashboard.php?success=Property added successfully');
        exit;

    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
        error_log("Add Property Error: " . $e->getMessage() . " | User ID: " . $user_id);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Property</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../public/css/output.css"> <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* Basic styles for form elements if not covered by Tailwind/DaisyUI */
         .form-container { max-width: 800px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
         .form-group { margin-bottom: 1.5rem; }
         .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
         .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
         .map-container { height: 400px; margin-bottom: 1rem; border-radius: 4px; border: 1px solid #d1d5db; }
         .amenities-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.75rem; }
         .amenity-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; cursor: pointer; }
         .amenity-item input { margin-right: 0.5rem; }
         .btn-primary { background-color: #4f46e5; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
         .btn-primary:hover { background-color: #4338ca; }
         .btn-primary:disabled { background-color: #a5b4fc; cursor: not-allowed; }
         .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; border: 1px solid transparent; }
         .alert-danger { background-color: #fef2f2; border-color: #fecaca; color: #dc2626; }
         .alert-success { background-color: #f0fdf4; border-color: #bbf7d0; color: #16a34a; }
         .progress { height: 10px; margin-bottom: 1rem; background-color: #e5e7eb; border-radius: 5px; overflow: hidden; }
         .progress-bar { height: 100%; background-color: #4f46e5; transition: width 0.3s ease; }
         .selected-files { background-color: #f3f4f6; padding: 1rem; border-radius: 4px; margin-top: 1rem; }
         .selected-files ul { list-style-type: disc; padding-left: 1.5rem; margin-top: 0.5rem; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-4xl mx-auto p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-brand-gray">Add New Property</h1>
            <p class="mt-2 text-brand-gray/70">Fill in the details below to list your property.</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="add-property.php" method="POST" enctype="multipart/form-data" class="space-y-6 bg-white p-6 rounded-lg shadow-sm border border-brand-light">
            <!-- Basic Information -->
            <div>
                <h2 class="text-xl font-semibold mb-4 text-brand-gray">Basic Information</h2>
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-brand-gray">Title</label>
                        <input type="text" 
                               id="title" 
                               name="title" 
                               required 
                               class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50"
                               value="<?php echo htmlspecialchars($title ?? ''); ?>">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-brand-gray">Description</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="4" 
                                  required 
                                  class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="price" class="block text-sm font-medium text-brand-gray">Price per Month (ZMW)</label>
                            <input type="number" 
                                   id="price" 
                                   name="price" 
                                   required 
                                   min="0" 
                                   class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50"
                                   value="<?php echo htmlspecialchars($price ?? ''); ?>">
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-brand-gray">Property Type</label>
                            <select id="type" 
                                    name="type" 
                                    required 
                                    class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50">
                                <option value="">Select Type</option>
                                <option value="apartment" <?php echo ($type ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                                <option value="shared" <?php echo ($type ?? '') === 'shared' ? 'selected' : ''; ?>>Shared Room</option>
                                <option value="hostel" <?php echo ($type ?? '') === 'hostel' ? 'selected' : ''; ?>>Hostel</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div>
                <h2 class="text-xl font-semibold mb-4 text-brand-gray">Location</h2>
                <div>
                    <label for="address" class="block text-sm font-medium text-brand-gray">Address</label>
                    <input type="text" 
                           id="address" 
                           name="address" 
                           required 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50"
                           value="<?php echo htmlspecialchars($address ?? ''); ?>">
                </div>
                <div id="map" class="mt-4 h-64 rounded-lg border border-brand-light"></div>
                <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude ?? ''); ?>">
                <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude ?? ''); ?>">
            </div>

            <!-- Amenities -->
            <div>
                <h2 class="text-xl font-semibold mb-4 text-brand-gray">Amenities</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($amenities as $amenity): ?>
                        <label class="inline-flex items-center">
                            <input type="checkbox" 
                                   name="amenities[]" 
                                   value="<?php echo $amenity['id']; ?>"
                                   class="rounded border-brand-light text-brand-primary focus:ring-brand-primary">
                            <span class="ml-2 text-brand-gray"><?php echo htmlspecialchars($amenity['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Images -->
            <div>
                <h2 class="text-xl font-semibold mb-4 text-brand-gray">Property Images</h2>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-brand-light border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <i class="fa-solid fa-cloud-upload-alt text-4xl text-brand-gray/50"></i>
                        <div class="flex text-sm text-brand-gray/70">
                            <label for="images" class="relative cursor-pointer rounded-md font-medium text-brand-primary hover:text-brand-secondary focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-brand-primary">
                                <span>Upload images</span>
                                <input id="images" name="images[]" type="file" class="sr-only" multiple accept="image/*">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-brand-gray/70">PNG, JPG, GIF up to 10MB each</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-4">
                <a href="dashboard.php" class="px-4 py-2 border border-brand-light text-brand-gray rounded hover:bg-brand-light/20 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                    Add Property
                </button>
            </div>
        </form>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Get initial coordinates from hidden inputs
        const initialLat = parseFloat(document.getElementById('latitude').value) || <?php echo $defaultLat; ?>;
        const initialLng = parseFloat(document.getElementById('longitude').value) || <?php echo $defaultLng; ?>;

        // Initialize map
        const map = L.map('map').setView([initialLat, initialLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Add a draggable marker at the initial coordinates
        const marker = L.marker([initialLat, initialLng], {
            draggable: true
        }).addTo(map);

        // Update hidden fields when marker is moved or map is clicked
        function updateLocation(latlng) {
            document.getElementById('latitude').value = latlng.lat.toFixed(6); // Limit precision
            document.getElementById('longitude').value = latlng.lng.toFixed(6);
        }

        marker.on('dragend', function(e) {
            updateLocation(marker.getLatLng());
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateLocation(e.latlng);
        });

        // Display selected files and handle primary image indication
        const imagesInput = document.getElementById('images');
        const selectedFilesContainer = document.getElementById('selectedFiles');
        const fileList = document.getElementById('fileList');

        imagesInput.addEventListener('change', function() {
            fileList.innerHTML = ''; // Clear previous list

            if (this.files.length > 0) {
                selectedFilesContainer.style.display = 'block';

                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const listItem = document.createElement('li');
                    listItem.textContent = file.name + (i === 0 ? ' (Primary)' : ''); // Mark first as primary
                    fileList.appendChild(listItem);
                }
            } else {
                selectedFilesContainer.style.display = 'none';
            }
        });

        // Disable submit button during form submission
        const form = document.getElementById('propertyForm');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', function() {
             // Basic client-side check for file selection
             if (imagesInput.files.length === 0) {
                 alert("Please select at least one image.");
                 // Prevent form submission if needed, though the 'required' attribute should handle this
                 event.preventDefault();
                 return;
             }
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        });
    </script>
</body>
</html>