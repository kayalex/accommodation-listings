<?php
// api/fetch_listings.php

require_once '../config/config.php';

class PropertyListings {
    private $supabaseUrl;
    private $supabaseKey;
    private $headers;

    public function __construct() {
        // Get credentials from config
        global $supabaseUrl, $supabaseKey;
        $this->supabaseUrl = SUPABASE_URL;
        $this->supabaseKey = SUPABASE_KEY;
        
        // Set headers for all requests
        $this->headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->supabaseKey,
            'apikey: ' . $this->supabaseKey
        ];
    }    /**
     * Fetch all property listings with optional filters
     * 
     * @param string $targetUniversity Filter by target university
     * @param string $type Filter by property type
     * @param float $priceMin Minimum price
     * @param float $priceMax Maximum price
     * @return array Properties data
     */
    public function getAllProperties($targetUniversity = null, $type = null, $priceMin = null, $priceMax = null) {
        // Fetch all properties with landlord profile info (name and is_verified)
        $endpoint = $this->supabaseUrl . '/rest/v1/properties?select=*,profiles(name,is_verified)';
        
        // Add ordering by created_at
        $endpoint .= '&order=created_at.desc';
        
        // Apply filters
        if ($targetUniversity) {
            $endpoint .= '&target_university=eq.' . urlencode($targetUniversity);
        }
        
        if ($type && $type !== 'all') {
            $endpoint .= '&type=eq.' . urlencode($type);
        }
        
        if ($priceMin) {
            $endpoint .= '&price=gte.' . floatval($priceMin);
        }
        
        if ($priceMax) {
            $endpoint .= '&price=lte.' . floatval($priceMax);
        }
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode !== 200) {
            return ['error' => 'Failed to fetch properties', 'status' => $statusCode];
        }
        
        $properties = json_decode($response, true);
        
        // Fetch primary images for each property
        return $this->attachPrimaryImages($properties);
    }
    
    /**
     * Fetch a single property by ID with all related data
     * 
     * @param int $id Property ID
     * @return array|null Property data or null if not found
     */
    public function getPropertyById($id) {
        // Fetch a single property by ID with all related data, including landlord's is_verified status
        $endpoint = $this->supabaseUrl . '/rest/v1/properties?id=eq.' . urlencode($id);
        $endpoint .= '&select=*,profiles(email,phone,name,is_verified),property_images(storage_path,is_primary),property_amenities(amenities(name))';
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode !== 200) {
            return ['error' => 'Failed to fetch property', 'status' => $statusCode];
        }
        
        $properties = json_decode($response, true);
        
        if (empty($properties)) {
            return null;
        }
        
        $property = $properties[0];
        
        // Generate public URLs for all images
        if (!empty($property['property_images'])) {
            foreach ($property['property_images'] as &$image) {
                $image['public_url'] = $this->getPublicUrl($image['storage_path']);
            }
            
            // Sort images with primary first
            usort($property['property_images'], function($a, $b) {
                return $b['is_primary'] - $a['is_primary'];
            });
        }
        
        return $property;
    }
    
    /**
     * Attach primary images to a list of properties
     * 
     * @param array $properties List of properties
     * @return array Properties with image URLs
     */
    private function attachPrimaryImages($properties) {
        if (empty($properties)) {
            return [];
        }
        
        // Extract all property IDs
        $propertyIds = array_map(function($property) {
            return $property['id'];
        }, $properties);
        
        // Create a comma-separated list for the "in" filter
        $idsString = implode(',', $propertyIds);
        
        // Fetch primary images
        $endpoint = $this->supabaseUrl . '/rest/v1/property_images?select=property_id,storage_path&is_primary=eq.true&property_id=in.(' . $idsString . ')';
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $images = json_decode($response, true) ?: [];
        
        // Create a lookup map for quick access
        $imageMap = [];
        foreach ($images as $image) {
            $imageMap[$image['property_id']] = $image['storage_path'];
        }
        
        // Attach image URLs to properties
        foreach ($properties as &$property) {
            $property['image_url'] = isset($imageMap[$property['id']]) 
                ? $this->getPublicUrl($imageMap[$property['id']])
                : '/images/placeholder.svg';
        }
        
        return $properties;
    }
    
    /**
     * Get a public URL for a storage path
     * 
     * @param string $path Storage path
     * @return string Public URL
     */
    private function getPublicUrl($path) {
        $endpoint = $this->supabaseUrl . '/storage/v1/object/public/properties/' . $path;
        return $endpoint;
    }

    // Add this method inside the PropertyListings class in fetch_listings.php

    /**
     * Fetch properties owned by a specific landlord/tenant
     *
     * @param string $landlordId The UUID of the landlord/tenant
     * @return array Properties data
     */
    public function getPropertiesByLandlord($landlordId) {
        // Ensure landlord_id column name matches your 'properties' table schema
        $endpoint = $this->supabaseUrl . '/rest/v1/properties?select=*&landlord_id=eq.' . urlencode($landlordId);
        // Add ordering if desired, e.g., by creation date
        $endpoint .= '&order=created_at.desc';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers); // Use class headers
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            error_log("Supabase error fetching properties for landlord {$landlordId}: " . $response);
            return ['error' => 'Failed to fetch your properties', 'status' => $statusCode];
        }

        $properties = json_decode($response, true);

        // Optionally, fetch and attach primary images if needed for the dashboard view
         return $this->attachPrimaryImages($properties); // Reuse existing method

        // Or return raw properties if images aren't needed on this specific dashboard view
        // return $properties;
    }
}

// Handle API requests if this file is accessed directly
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    header('Content-Type: application/json');
    
    $listingApi = new PropertyListings();
    
    // Check if we're requesting a specific property
    if (isset($_GET['id'])) {
        $property = $listingApi->getPropertyById($_GET['id']);
        echo json_encode($property);
        exit;
    }
    
    // Otherwise, get all properties with optional filters
    $location = isset($_GET['location']) ? $_GET['location'] : null;
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $priceMin = isset($_GET['priceMin']) ? $_GET['priceMin'] : null;
    $priceMax = isset($_GET['priceMax']) ? $_GET['priceMax'] : null;
    
    $properties = $listingApi->getAllProperties($location, $type, $priceMin, $priceMax);
    echo json_encode($properties);
}
?>