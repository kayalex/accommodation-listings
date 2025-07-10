<?php
require_once __DIR__ . '/../config/config.php';

// Function to generate a unique 5-digit ID
function generateUniqueId() {
    return str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
}

// Headers for Supabase API calls
$headers = [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY
];

// Fetch all profiles that don't have a unique_id
$endpoint = SUPABASE_URL . '/rest/v1/profiles?select=id&unique_id=is.null';
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($statusCode !== 200) {
    die("Failed to fetch profiles: Status code $statusCode\n");
}

$profiles = json_decode($response, true);
echo "Found " . count($profiles) . " profiles without unique_id\n";

// Update each profile with a unique ID
foreach ($profiles as $profile) {
    $uniqueId = generateUniqueId();
    
    // Check if generated ID already exists
    $checkEndpoint = SUPABASE_URL . '/rest/v1/profiles?unique_id=eq.' . $uniqueId;
    $ch = curl_init($checkEndpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $checkResponse = curl_exec($ch);
    $exists = !empty(json_decode($checkResponse, true));
    curl_close($ch);
    
    // If ID exists, generate a new one
    while ($exists) {
        $uniqueId = generateUniqueId();
        $ch = curl_init($checkEndpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $checkResponse = curl_exec($ch);
        $exists = !empty(json_decode($checkResponse, true));
        curl_close($ch);
    }
    
    // Update the profile with the unique ID
    $updateEndpoint = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $profile['id'];
    $ch = curl_init($updateEndpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['unique_id' => $uniqueId]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $updateResponse = curl_exec($ch);
    $updateStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($updateStatus === 204) {
        echo "Updated profile {$profile['id']} with unique_id: $uniqueId\n";
    } else {
        echo "Failed to update profile {$profile['id']}: Status code $updateStatus\n";
    }
}

echo "Done!\n";
?>
