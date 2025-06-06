<?php
// pages/add-property.php
// session_start(); // Ensure session is started for Auth class and $_SESSION access

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$currentUser = $auth->getCurrentUser(); // Get the full user session data
$user_id = $currentUser['auth']['user']['id'] ?? null;
$user_role = $currentUser['profile']['role'] ?? null;
$user_access_token = $currentUser['auth']['access_token'] ?? null;

if (!$user_id || $user_role !== 'landlord' || !$user_access_token) {
    // If any crucial information is missing, treat as not fully authenticated or unauthorized
    error_log("Add Property: Missing user_id, user_role not landlord, or missing access_token. User Data: " . json_encode($currentUser));
    header('Location: login.php?error=auth_issue'); // Redirect to login or an error page
    exit;
}

// Initialize form field variables for repopulation and default values
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? '';
$address = $_POST['address'] ?? '';
$defaultLat = -12.80532; // Default map center coordinates
$defaultLng = 28.24403;
$latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT) ?: $defaultLat;
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT) ?: $defaultLng;
$selectedAmenitiesPost = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];


$error = null;
$amenities = [];
$supabaseUrl = SUPABASE_URL;
$anonKey = SUPABASE_KEY;

// Define available universities
$universities = [
    'CBU' => 'Copperbelt University (CBU)',
    'UNZA' => 'University of Zambia (UNZA)',
    'UNILUS' => 'University of Lusaka (UNILUS)',
    'Mulungushi' => 'Mulungushi University',
    'Mukuba' => 'Mukuba University',
    'Copperstone' => 'Copperstone University'
];

// Standard headers for Supabase REST API (getting data using anon key, if RLS allows)
$standardHeaders = [
    'Content-Type: application/json',
    'apikey: ' . $anonKey,
    'Authorization: Bearer ' . $anonKey // Or $user_access_token if data is user-specific and RLS protected
];

// Headers for Supabase REST API when inserting/updating data and expecting representation back
// These will use the user's access token to respect RLS for data insertion.
$insertHeadersUserContext = [
    'Content-Type: application/json',
    'apikey: ' . $anonKey, // API key is still the anon key
    'Authorization: Bearer ' . $user_access_token, // User's JWT for RLS
    'Prefer: return=representation'
];


try {
    // Create storage bucket if it doesn't exist (idempotent check)
    // This operation might require service_role key if anon key doesn't have permission.
    // For simplicity, we'll try with anon key; if it fails, bucket creation needs manual setup or service_role key.
    $bucketName = 'properties'; 
    $endpointBucket = $supabaseUrl . '/storage/v1/bucket';
    $bucketData = json_encode(['id' => $bucketName, 'name' => $bucketName, 'public' => true]); 

    $chBucket = curl_init($endpointBucket);
    curl_setopt($chBucket, CURLOPT_POST, true);
    curl_setopt($chBucket, CURLOPT_POSTFIELDS, $bucketData);
    curl_setopt($chBucket, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chBucket, CURLOPT_HTTPHEADER, [ 
        'Authorization: Bearer ' . $anonKey, // Using anon key. Consider service_role if this fails.
        'apikey: ' . $anonKey,
        'Content-Type: application/json'
    ]);
    $responseBucket = curl_exec($chBucket);
    $statusCodeBucket = curl_getinfo($chBucket, CURLINFO_HTTP_CODE);
    curl_close($chBucket);

    if ($statusCodeBucket !== 200 && $statusCodeBucket !== 400) { // 400 can mean "already exists"
        $errorDetails = json_decode($responseBucket, true);
        error_log("Supabase bucket '" . $bucketName . "' creation/check failed. Status: $statusCodeBucket. Response: " . ($errorDetails['message'] ?? $responseBucket) . ". Consider using Service Role Key if permissions are an issue.");
    }


    // Fetch amenities (typically public or readable by authenticated users)
    $endpointAmenities = $supabaseUrl . '/rest/v1/amenities?select=id,name&order=name.asc';
    $chAmenities = curl_init($endpointAmenities);
    // Use standardHeaders (anon key) or $insertHeadersUserContext if amenities are RLS protected for read
    curl_setopt($chAmenities, CURLOPT_HTTPHEADER, $standardHeaders); 
    curl_setopt($chAmenities, CURLOPT_RETURNTRANSFER, true);
    $responseAmenities = curl_exec($chAmenities);
    $statusCodeAmenities = curl_getinfo($chAmenities, CURLINFO_HTTP_CODE);
    curl_close($chAmenities);

    if ($statusCodeAmenities === 200) {
        $amenities = json_decode($responseAmenities, true);
    } else {
        $error = "Failed to fetch amenities. Status: $statusCodeAmenities. Response: $responseAmenities";
    }
} catch (Exception $e) {
    $error = "Initialization error: " . $e->getMessage();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    try {
        // Collect and validate inputs
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $priceInput = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $address = trim($_POST['address']);
        $targetUniversity = trim($_POST['target_university']);
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT, ['options' => ['default' => $defaultLat]]);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT, ['options' => ['default' => $defaultLng]]);
        $selectedAmenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];


        // Basic validation
        if (empty($title)) throw new Exception("Title is required.");
        if ($priceInput === false || $priceInput <= 0) throw new Exception("A valid positive price is required.");
        if ($latitude === false || $longitude === false) throw new Exception("Valid map location is required.");
        if (empty($_FILES['images']['name'][0])) throw new Exception("Please upload at least one image.");
        if (empty($targetUniversity)) throw new Exception("Please select a target university.");

        // Insert property data
        $propertyData = [
            'title' => $title,
            'description' => $description,
            'price' => $priceInput,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address ?: null,
            'landlord_id' => $user_id,
            'target_university' => $targetUniversity
        ];

        $endpointProperty = $supabaseUrl . '/rest/v1/properties?select=id'; 
        $chProperty = curl_init($endpointProperty);
        curl_setopt($chProperty, CURLOPT_POST, true);
        curl_setopt($chProperty, CURLOPT_POSTFIELDS, json_encode($propertyData));
        curl_setopt($chProperty, CURLOPT_HTTPHEADER, $insertHeadersUserContext); // Use user context
        curl_setopt($chProperty, CURLOPT_RETURNTRANSFER, true);

        $responseProperty = curl_exec($chProperty);
        $statusCodeProperty = curl_getinfo($chProperty, CURLINFO_HTTP_CODE);
        $curlErrorProperty = curl_error($chProperty);
        curl_close($chProperty);

        if ($statusCodeProperty !== 201) { 
            $responseBody = json_decode($responseProperty, true);
            throw new Exception("Failed to add property. Status: $statusCodeProperty. Error: " . ($responseBody['message'] ?? $curlErrorProperty ?: $responseProperty));
        }

        $propertyInsertedData = json_decode($responseProperty, true);
        if (empty($propertyInsertedData) || !isset($propertyInsertedData[0]['id'])) {
            throw new Exception("Failed to retrieve property ID after insertion. Response: " . $responseProperty);
        }
        $propertyId = $propertyInsertedData[0]['id'];

        // 2. Handle image uploads now that we have propertyId
        $uploadedImageRecords = []; 

        if (!empty($_FILES['images']['name'][0])) {
            $totalImages = count($_FILES['images']['name']);
            for ($i = 0; $i < $totalImages; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error uploading file " . htmlspecialchars($_FILES['images']['name'][$i]) . ": " . $_FILES['images']['error'][$i]);
                }

                $tmpName = $_FILES['images']['tmp_name'][$i];
                $originalName = $_FILES['images']['name'][$i];
                $mimeType = mime_content_type($tmpName); 
                $fileSize = $_FILES['images']['size'][$i];

                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    throw new Exception("Invalid file type for " . htmlspecialchars($originalName) . ". Only JPG, PNG, GIF, WEBP are allowed.");
                }
                if ($fileSize > 10 * 1024 * 1024) { 
                    throw new Exception("File " . htmlspecialchars($originalName) . " is too large (max 10MB).");
                }

                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { 
                     throw new Exception("Invalid file extension for " . htmlspecialchars($originalName));
                }
                $uniqueFileName = 'img_' . uniqid() . '_' . time() . '.' . $extension;
                $storagePath = 'landlord_' . $user_id . '/' . $propertyId . '/' . $uniqueFileName;
                
                $fileContents = file_get_contents($tmpName);
                if ($fileContents === false) {
                    throw new Exception("Could not read file " . htmlspecialchars($originalName));
                }

                $endpointUpload = $supabaseUrl . '/storage/v1/object/' . $bucketName . '/' . $storagePath;
                
                $chUpload = curl_init($endpointUpload);
                curl_setopt($chUpload, CURLOPT_POST, true); 
                curl_setopt($chUpload, CURLOPT_POSTFIELDS, $fileContents);
                curl_setopt($chUpload, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chUpload, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $user_access_token, // CRITICAL: Use user's access token
                    'apikey: ' . $anonKey, // API key is still the anon key
                    'Content-Type: ' . $mimeType,
                    'x-upsert: false' 
                ]);

                $responseUpload = curl_exec($chUpload);
                $statusCodeUpload = curl_getinfo($chUpload, CURLINFO_HTTP_CODE);
                $curlErrorUpload = curl_error($chUpload);
                curl_close($chUpload);

                if ($statusCodeUpload !== 200) { 
                    $uploadResponseBody = json_decode($responseUpload, true);
                    // Log the full response for better debugging
                    error_log("Supabase Storage Upload Error. Status: $statusCodeUpload. UserID: $user_id. Path: $storagePath. Response: $responseUpload. cURL Error: $curlErrorUpload");
                    throw new Exception("Failed to upload image " . htmlspecialchars($originalName) . ". Status: $statusCodeUpload. Supabase msg: " . ($uploadResponseBody['message'] ?? 'N/A') . " Details: " . $responseUpload);
                }

                $uploadedImageRecords[] = [
                    'storage_path' => $storagePath, 
                    'is_primary' => ($i === 0)
                ];
            }
        } else {
             throw new Exception("At least one image is required.");
        }


        // 3. Insert image records into property_images table (using user's access token)
        if (!empty($uploadedImageRecords)) {
            foreach ($uploadedImageRecords as $imageRecord) {
                $imageData = [
                    'property_id' => $propertyId,
                    'storage_path' => $imageRecord['storage_path'],
                    'is_primary' => $imageRecord['is_primary']
                ];

                $endpointImageDb = $supabaseUrl . '/rest/v1/property_images';
                $chImageDb = curl_init($endpointImageDb);
                curl_setopt($chImageDb, CURLOPT_POST, true);
                curl_setopt($chImageDb, CURLOPT_POSTFIELDS, json_encode($imageData));
                curl_setopt($chImageDb, CURLOPT_HTTPHEADER, $insertHeadersUserContext); // Use user context
                curl_setopt($chImageDb, CURLOPT_RETURNTRANSFER, true);

                $responseImageDb = curl_exec($chImageDb);
                $statusCodeImageDb = curl_getinfo($chImageDb, CURLINFO_HTTP_CODE);
                curl_close($chImageDb);

                if ($statusCodeImageDb !== 201) {
                    error_log("Failed to save image record for property {$propertyId}, path {$imageRecord['storage_path']}. Status: $statusCodeImageDb. Response: $responseImageDb");
                }
            }
        }

        // 4. Insert selected amenities (using user's access token)
        if (!empty($selectedAmenities)) {
            foreach ($selectedAmenities as $amenityId) {
                $validAmenityId = filter_var($amenityId, FILTER_VALIDATE_INT);
                if ($validAmenityId) {
                    $propertyAmenityData = [
                        'property_id' => $propertyId,
                        'amenity_id' => $validAmenityId
                    ];
                    $endpointAmenityDb = $supabaseUrl . '/rest/v1/property_amenities';
                    $chAmenityDb = curl_init($endpointAmenityDb);
                    curl_setopt($chAmenityDb, CURLOPT_POST, true);
                    curl_setopt($chAmenityDb, CURLOPT_POSTFIELDS, json_encode($propertyAmenityData));
                    curl_setopt($chAmenityDb, CURLOPT_HTTPHEADER, $insertHeadersUserContext); // Use user context
                    curl_setopt($chAmenityDb, CURLOPT_RETURNTRANSFER, true);
                    
                    $responseAmenityDb = curl_exec($chAmenityDb);
                    $statusCodeAmenityDb = curl_getinfo($chAmenityDb, CURLINFO_HTTP_CODE);
                    curl_close($chAmenityDb);

                    if ($statusCodeAmenityDb !== 201) {
                        error_log("Failed to add amenity ID {$validAmenityId} for property {$propertyId}. Status: $statusCodeAmenityDb. Response: $responseAmenityDb");
                    }
                }
            }
        }

        header('Location: dashboard.php?success=Property added successfully!');
        exit;

    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
        error_log("Add Property Error: " . $e->getMessage() . " | User ID: " . $user_id . " | POST data: " . http_build_query($_POST) . " | Files: " . json_encode($_FILES));
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Property - <?php echo htmlspecialchars($appName ?? 'App'); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="../public/css/output.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom styles if needed, complement Tailwind */
        #map { height: 400px; border-radius: 0.375rem; border: 1px solid #e5e7eb; }
        .image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .preview-image-wrapper { position: relative; aspect-ratio: 1 / 1; border: 1px solid #ddd; border-radius: 0.375rem; overflow: hidden; background-color: #f9f9f9; }
        .preview-image-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .primary-badge { position: absolute; top: 0.25rem; right: 0.25rem; background-color: #4f46e5; /* brand-primary */ color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; }
        .leaflet-control-attribution a { color: #4f46e5 !important; } /* Ensure map links are styled */
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <?php include __DIR__ . '/../includes/header.php'; // Ensure your header path is correct ?>

    <div class="container mx-auto max-w-4xl p-4 sm:p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Add New Property</h1>
            <p class="mt-1 text-gray-600">Fill in the details below to list your property.</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert">
                <strong class="font-bold">Error:</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <div id="clientSideError" class="hidden mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md" role="alert"></div>


        <form id="propertyForm" action="add-property.php" method="POST" enctype="multipart/form-data" class="space-y-8 bg-white p-6 sm:p-8 rounded-lg shadow-lg border border-gray-200">
            
            <fieldset>
                <legend class="text-xl font-semibold mb-4 text-gray-700 border-b pb-2">Basic Information</legend>
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                        <input type="text" id="title" name="title" required 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               value="<?php echo htmlspecialchars($title); ?>" placeholder="e.g., Cozy 2 Bedroom Apartment">
                    </div>                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-red-500">*</span></label>
                        <textarea id="description" name="description" rows="4" required
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                  placeholder="Detailed description of the property..."><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                    <div>
                        <label for="target_university" class="block text-sm font-medium text-gray-700">Target University <span class="text-red-500">*</span></label>
                        <select id="target_university" name="target_university" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select a university</option>
                            <?php foreach ($universities as $code => $name): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">Select the university this property is targeted towards</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Price per Month (ZMW) <span class="text-red-500">*</span></label>
                            <input type="number" id="price" name="price" required min="0.01" step="0.01"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   value="<?php echo htmlspecialchars($price); ?>" placeholder="e.g., 3500">
                        </div>
                        
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold mb-4 text-gray-700 border-b pb-2">Location</legend>
                <div class="space-y-6">
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700">Street Address (Optional)</label>
                        <input type="text" id="address" name="address"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               value="<?php echo htmlspecialchars($address); ?>" placeholder="e.g., 123 Main St, Parklands">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Property Location on Map <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-500 mb-2">Click or drag the marker to set the exact location.</p>
                        <div id="map"></div>
                        <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude); ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude); ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold mb-4 text-gray-700 border-b pb-2">Amenities</legend>
                <?php if (!empty($amenities)): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <?php foreach ($amenities as $amenity): ?>
                        <label class="flex items-center space-x-2 p-2 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors cursor-pointer">
                            <input type="checkbox" name="amenities[]" value="<?php echo $amenity['id']; ?>"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                   <?php echo in_array($amenity['id'], $selectedAmenitiesPost) ? 'checked' : ''; ?>>
                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars($amenity['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <p class="text-gray-500">No amenities available to select or failed to load amenities.</p>
                <?php endif; ?>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold mb-4 text-gray-700 border-b pb-2">Property Images</legend>
                <div>
                    <label for="images-input-label" class="block text-sm font-medium text-gray-700">Upload Images <span class="text-red-500">*</span> (First image is primary)</label>
                    <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="images-input" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                    <span>Upload files</span>
                                    <input id="images-input" name="images[]" type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/gif,image/webp">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG, GIF, WEBP up to 10MB each. First selected will be primary.</p>
                        </div>
                    </div>
                </div>
                <div id="selectedFilesListContainer" class="mt-4 bg-gray-50 p-3 rounded-lg border border-gray-200 hidden">
                    <h3 class="font-medium text-gray-700 text-sm mb-1">Selected Files:</h3>
                    <ul id="fileList" class="list-disc list-inside text-gray-600 text-sm"></ul>
                </div>
                <div id="imagePreviewContainer" class="image-preview-grid">
                    </div>
            </fieldset>

            <div class="flex justify-end pt-6 border-t border-gray-200">
                 <a href="dashboard.php" class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors mr-3">
                    Cancel
                </a>
                <button type="submit" id="submitBtn" 
                        class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                    Add Property
                </button>
            </div>
        </form>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Ensure your footer path is correct ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize map
            const initialLat = parseFloat(document.getElementById('latitude').value) || <?php echo $defaultLat; ?>;
            const initialLng = parseFloat(document.getElementById('longitude').value) || <?php echo $defaultLng; ?>;
            const mapElement = document.getElementById('map');
            let map;

            if (mapElement) {
                 map = L.map('map').setView([initialLat, initialLng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                const marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);

                function updateLocationInputs(latlng) {
                    document.getElementById('latitude').value = latlng.lat.toFixed(6);
                    document.getElementById('longitude').value = latlng.lng.toFixed(6);
                }

                marker.on('dragend', function() {
                    updateLocationInputs(marker.getLatLng());
                });

                map.on('click', function(e) {
                    marker.setLatLng(e.latlng);
                    updateLocationInputs(e.latlng);
                });
            } else {
                console.error("Map element not found");
            }


            // Image preview and file list handler
            const imagesInput = document.getElementById('images-input'); 
            const selectedFilesListContainer = document.getElementById('selectedFilesListContainer');
            const fileListUl = document.getElementById('fileList');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const clientSideErrorDiv = document.getElementById('clientSideError');

            if (imagesInput && selectedFilesListContainer && fileListUl && imagePreviewContainer && clientSideErrorDiv) {
                imagesInput.addEventListener('change', function() {
                    fileListUl.innerHTML = ''; 
                    imagePreviewContainer.innerHTML = '';
                    clientSideErrorDiv.classList.add('hidden'); 

                    if (this.files.length > 0) {
                        selectedFilesListContainer.classList.remove('hidden');

                        Array.from(this.files).forEach((file, index) => {
                            const listItem = document.createElement('li');
                            listItem.textContent = file.name + (index === 0 ? ' (Primary)' : '');
                            fileListUl.appendChild(listItem);

                            if (file.type.startsWith('image/')) {
                                const previewWrapper = document.createElement('div');
                                previewWrapper.className = 'preview-image-wrapper';

                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    const img = document.createElement('img');
                                    img.src = e.target.result;
                                    img.alt = file.name;
                                    previewWrapper.appendChild(img);
                                }
                                reader.readAsDataURL(file);

                                if (index === 0) {
                                    const primaryBadge = document.createElement('span');
                                    primaryBadge.textContent = 'Primary';
                                    primaryBadge.className = 'primary-badge';
                                    previewWrapper.appendChild(primaryBadge);
                                }
                                imagePreviewContainer.appendChild(previewWrapper);
                            }
                        });
                    } else {
                        selectedFilesListContainer.classList.add('hidden');
                    }
                });
            } else {
                 console.error("One or more image handling elements are missing from the DOM.");
            }

            const propertyForm = document.getElementById('propertyForm');
            const submitButton = document.getElementById('submitBtn');

            if (propertyForm && submitButton) {
                propertyForm.addEventListener('submit', function(event) {
                    clientSideErrorDiv.classList.add('hidden'); 
                    if (imagesInput && imagesInput.files.length === 0) {
                        clientSideErrorDiv.textContent = 'Please select at least one image for the property.';
                        clientSideErrorDiv.classList.remove('hidden');
                        event.preventDefault(); 
                        imagesInput.focus(); 
                        return;
                    }
                    
                    submitButton.disabled = true;
                    submitButton.textContent = 'Processing...';
                });
            } else {
                console.error("Property form or submit button not found for event listener.");
            }
        });
    </script>
</body>
</html>
