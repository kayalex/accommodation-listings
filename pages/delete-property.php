<?php
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();

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

try {
    // First, verify property ownership
    $endpoint = SUPABASE_URL . '/rest/v1/properties?id=eq.' . $property_id . '&select=landlord_id';
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

    if ($statusCode !== 200) {
        throw new Exception("Failed to verify property ownership");
    }

    $property = json_decode($response, true)[0] ?? null;
    if (!$property || $property['landlord_id'] !== $auth->getUserId()) {
        header('Location: dashboard.php?error=unauthorized');
        exit();
    }

    // Delete property images from storage and database
    // First, get all image records
    $endpoint = SUPABASE_URL . '/rest/v1/property_images?property_id=eq.' . $property_id;
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $images = json_decode($response, true);

    // Delete images from storage and database
    if (!empty($images)) {
        foreach ($images as $image) {
            // Delete image record from database
            $endpoint = SUPABASE_URL . '/rest/v1/property_images?id=eq.' . $image['id'];
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    // Delete property amenities
    $endpoint = SUPABASE_URL . '/rest/v1/property_amenities?property_id=eq.' . $property_id;
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    // Finally, delete the property itself
    $endpoint = SUPABASE_URL . '/rest/v1/properties?id=eq.' . $property_id;
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode !== 204) {
        throw new Exception("Failed to delete property");
    }

    header('Location: dashboard.php?success=Property deleted successfully');
    exit();

} catch (Exception $e) {
    header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>