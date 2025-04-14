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
// The code below uses PDO based on your original file. Keep it if that's intended.
// If you want to use Supabase API for everything, you'll need to refactor this part.

$conn = null; // Initialize connection variable
$error = null; // Initialize error variable
$amenities = []; // Initialize amenities array

try {
    // PDO connection using config details - ONLY if you are NOT using Supabase API for these actions
     $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
     $conn = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);


    // Get amenities for checkbox selection (using PDO)
    $stmt = $conn->prepare("SELECT id, name FROM amenities ORDER BY name"); // Select id and name
    $stmt->execute();
    $amenities = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $error = "Database connection or query error: " . $e->getMessage();
    // Handle error appropriately - maybe redirect or show a friendly message
    // For now, we'll let the form potentially show the error
}

// Default map center coordinates (Keep as is)
$defaultLat = -12.80532;
$defaultLng = 28.24403;


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) { // Only proceed if DB connection was successful
    // $error = null; // Reset error specific to form processing
    $uploadProgress = 0;

    try {
        // Start transaction
        $conn->beginTransaction();

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


        // Insert property (Using PDO - Ensure 'landlord_id' column exists and matches the user ID type)
        $stmt = $conn->prepare("
            INSERT INTO properties
            (title, description, price, latitude, longitude, address, landlord_id) -- Assuming landlord_id is the correct column name
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        // Use the user_id obtained from the Auth class
        $stmt->execute([
            $title,
            $description,
            $price,
            $latitude,
            $longitude,
            $address ?: null, // Use null if address is empty
            $user_id // Use the authenticated user's ID
        ]);

        $propertyId = $conn->lastInsertId();


        // Insert amenities (Keep existing logic, uses PDO)
         if (!empty($selectedAmenities)) {
             $amenityValues = [];
             $placeholders = [];


             foreach ($selectedAmenities as $amenityId) {
                 $validAmenityId = filter_var($amenityId, FILTER_VALIDATE_INT);
                 if ($validAmenityId) { // Ensure it's a valid integer
                     $amenityValues[] = $propertyId;
                     $amenityValues[] = $validAmenityId;
                     $placeholders[] = "(?, ?)";
                 }
             }


             if (!empty($placeholders)) {
                 $amenityQuery = "INSERT INTO property_amenities (property_id, amenity_id) VALUES " . implode(", ", $placeholders);
                 $stmt = $conn->prepare($amenityQuery);
                 $stmt->execute($amenityValues);
             }
         }


        // Process images (Keep existing logic, saves locally)
        // Consider using Supabase Storage instead for scalability and easier management
         $totalImages = count($_FILES['images']['name']);
         // Use a path relative to the web root, ensure permissions are correct
         $uploadDir = '../public/uploads/properties/landlord_' . $user_id . '/' . $propertyId . '/'; // Adjusted path


         if (!file_exists($uploadDir)) {
             if (!mkdir($uploadDir, 0755, true)) {
                 throw new Exception("Failed to create upload directory. Check permissions.");
             }
         }


         for ($i = 0; $i < $totalImages; $i++) {
             if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                  // Handle upload errors per file
                  throw new Exception("Error uploading file " . $_FILES['images']['name'][$i] . ": Error code " . $_FILES['images']['error'][$i]);
             }


             $tmpName = $_FILES['images']['tmp_name'][$i];
             $name = basename($_FILES['images']['name'][$i]); // Use basename for security
             $size = $_FILES['images']['size'][$i];


             // Validate file is an image (more robust check)
             $finfo = finfo_open(FILEINFO_MIME_TYPE);
             $mimeType = finfo_file($finfo, $tmpName);
             finfo_close($finfo);
             $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
             if (!in_array($mimeType, $allowedMimeTypes)) {
                 throw new Exception("File '{$name}' is not a valid image type ({$mimeType}). Allowed: JPG, PNG, GIF, WEBP.");
             }


             // Check size (5MB max)
             if ($size > 5 * 1024 * 1024) {
                 throw new Exception("Image '{$name}' exceeds the 5MB size limit.");
             }


             // Sanitize filename
             $sanitizedName = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $name);
             $sanitizedName = preg_replace('/_+/', '_', $sanitizedName);
             $fileExtension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
             $fileNameWithoutExt = pathinfo($sanitizedName, PATHINFO_FILENAME);
             // Create unique filename
             $fileName = $fileNameWithoutExt . '_' . time() . '_' . $i . '.' . strtolower($fileExtension);
             $filePath = $uploadDir . $fileName;
              $storagePathForDb = 'properties/landlord_' . $user_id . '/' . $propertyId . '/' . $fileName; // Path relative to bucket for Supabase Storage (if used) or consistent local path


             // Upload file
             if (!move_uploaded_file($tmpName, $filePath)) {
                 throw new Exception("Failed to move uploaded image '{$name}'. Check directory permissions.");
             }


             // *** IMPORTANT: If using Supabase Storage, you would upload here via API ***
             // $publicUrl = $supabaseStorage->upload($filePath, $storagePathForDb); // Example


             // For local storage, construct the URL (ensure '/public' is accessible)
             // Check $appUrl from config.php
             global $appUrl; // Make sure $appUrl is defined in config.php
             $publicUrl = rtrim($appUrl, '/') . '/uploads/' . $storagePathForDb; // URL relative to web root


             // Insert image record (Using PDO)
             // Make sure 'storage_path' and 'public_url' columns exist
             $stmt = $conn->prepare("
                 INSERT INTO property_images
                 (property_id, storage_path, is_primary, public_url)
                 VALUES (?, ?, ?, ?)
             ");


             $stmt->execute([
                 $propertyId,
                 $storagePathForDb, // Store the relative path used for Supabase or local structure
                 $i === 0 ? 1 : 0, // First image is primary
                 $publicUrl // Store the accessible URL
             ]);


            $uploadProgress = ($i + 1) / $totalImages * 100;
         }


        // Commit transaction
        $conn->commit();

        // Success: Redirect to dashboard
        header('Location: dashboard.php?success=Property added successfully');
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "An error occurred: " . $e->getMessage(); // Assign specific error message
         // Log the detailed error for debugging
         error_log("Add Property Error: " . $e->getMessage() . " | User ID: " . $user_id);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$conn) {
     // Handle case where DB connection failed before form submission attempt
     $error = "Database connection failed. Cannot process form.";
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

    <div class="form-container">
        <h1 class="text-2xl font-bold mb-6">Add New Property</h1>

         <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($uploadProgress) && $uploadProgress > 0 && $uploadProgress < 100): ?>
            <div class="progress">
                <div class="progress-bar" style="width: <?php echo $uploadProgress; ?>%"></div>
            </div>
            <p class="text-sm text-gray-600 mb-4">Processing images: <?php echo round($uploadProgress); ?>% complete</p>
        <?php endif; ?>

        <form action="add-property.php" method="post" enctype="multipart/form-data" id="propertyForm">
            <div class="form-group">
                <label for="title" class="block text-sm font-medium text-gray-700">Title *</label>
                <input type="text" id="title" name="title" class="mt-1 form-control" placeholder="e.g., Cozy Apartment near Campus" required
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="description" name="description" class="mt-1 form-control" rows="4"
                          placeholder="Provide details about the property, rules, etc."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="price" class="block text-sm font-medium text-gray-700">Price (ZMW per month) *</label>
                <input type="number" id="price" name="price" class="mt-1 form-control" step="0.01" min="1" placeholder="e.g., 3500" required
                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
            </div>


            <div class="form-group">
                <label for="address" class="block text-sm font-medium text-gray-700">Address (Optional)</label>
                <input type="text" id="address" name="address" class="mt-1 form-control" placeholder="e.g., 123 Main St, Kitwe"
                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
            </div>


            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700">Location on Map *</label>
                <div id="map" class="map-container mt-1"></div>
                 <input type="hidden" id="latitude" name="latitude" value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : $defaultLat; ?>">
                 <input type="hidden" id="longitude" name="longitude" value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : $defaultLng; ?>">
                <p class="text-xs text-gray-500 mt-1">Click or drag the marker to set the exact property location.</p>
            </div>

            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700">Amenities</label>
                 <?php if (!empty($amenities)): ?>
                 <div class="amenities-grid mt-1">
                    <?php foreach ($amenities as $amenity): ?>
                    <label class="amenity-item hover:bg-gray-50">
                        <input type="checkbox" name="amenities[]" value="<?php echo $amenity['id']; ?>"
                               <?php if (isset($_POST['amenities']) && is_array($_POST['amenities']) && in_array($amenity['id'], $_POST['amenities'])) echo 'checked'; ?>>
                        <?php echo htmlspecialchars($amenity['name']); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                 <?php else: ?>
                 <p class="text-sm text-gray-500 mt-1">No amenities found in the database.</p>
                 <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="images" class="block text-sm font-medium text-gray-700">Images * (Select one or more)</label>
                <input type="file" id="images" name="images[]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" multiple accept="image/jpeg,image/png,image/gif,image/webp" required>
                <p class="text-xs text-gray-500 mt-1">First image selected will be the primary display image. Max size: 5MB per image.</p>

                <div id="selectedFiles" class="selected-files" style="display: none;">
                    <p class="text-sm font-medium text-gray-700">Selected images:</p>
                    <ul id="fileList" class="text-sm text-gray-600"></ul>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn-primary w-full">Add Property</button>
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