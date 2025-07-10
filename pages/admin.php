<?php
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/reports.php';
require_once __DIR__ .'/../config/config.php';

$auth = new Auth();
if (!$auth->isAuthenticated() || $auth->getUserRole() !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables for messages
$successMessage = $_GET['success'] ?? null;
$errorMessage = $_GET['error'] ?? null;

// Get active tab (default to users)
$activeTab = $_GET['tab'] ?? 'users';

// Handle POST actions (report status updates and property deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'review_report' || $_POST['action'] === 'resolve_report') {
        $reportId = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
        $newStatus = $_POST['action'] === 'review_report' ? 'reviewed' : 'resolved';
        if ($reportId) {
            $reports = new Reports();
            $result = $reports->updateReportStatus($reportId, $newStatus);
            if ($result['success']) {
                $successMessage = 'Report marked as ' . $newStatus . ' successfully.';
            } else {
                $errorMessage = 'Failed to update report status: ' . $result['message'];
            }
            $activeTab = 'reports';
        }
    } elseif ($_POST['action'] === 'delete_property') {
        $propertyId = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
        if ($propertyId) {
            try {
                // Delete property images from storage and database
                $endpoint = SUPABASE_URL . '/rest/v1/property_images?property_id=eq.' . $propertyId;
                $headers = [
                    "Content-Type: application/json",
                    "apikey: " . SUPABASE_KEY,
                    "Authorization: Bearer " . SUPABASE_KEY
                ];
                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
                // Delete property amenities
                $endpoint = SUPABASE_URL . '/rest/v1/property_amenities?property_id=eq.' . $propertyId;
                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
                // Delete the property itself
                $endpoint = SUPABASE_URL . '/rest/v1/properties?id=eq.' . $propertyId;
                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($statusCode === 204) {
                    $successMessage = 'Property deleted successfully';
                    $activeTab = 'properties';
                } else {
                    throw new Exception("Failed to delete property");
                }
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                $activeTab = 'properties';
            }
        }
    }
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_id'], $_POST['action'])) {
    try {
        $profileId = $_POST['profile_id'];
        $newStatus = $_POST['action'] === 'approve' ? 1 : 3; // 1 for approved, 3 for rejected
        
        // Get supabase credentials from Auth class
        $supabaseUrl = SUPABASE_URL;
        $headers = [
            "Content-Type: application/json",
            "apikey: " . SUPABASE_KEY,
            "Authorization: Bearer " . SUPABASE_KEY
        ];
          // If rejecting, first get the current profile to get the document path
        if ($_POST['action'] === 'reject') {
            $endpoint = $supabaseUrl . "/rest/v1/profiles?id=eq." . urlencode($profileId) . "&select=verification_document";
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($statusCode === 200) {
                $profile = json_decode($response, true)[0] ?? null;
                if ($profile && !empty($profile['verification_document'])) {
                    $docPath = $profile['verification_document'];
                    
                    // If document is in Supabase storage, delete it
                    if (strpos($docPath, 'user_') === 0) {
                        $endpointDelete = $supabaseUrl . '/storage/v1/object/' . $bucketName . '/' . $docPath;
                        $ch = curl_init($endpointDelete);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        $delResponse = curl_exec($ch);
                        $delStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($delStatusCode !== 200) {
                            error_log("Failed to delete verification document: $docPath. Status: $delStatusCode");
                        }
                    }
                }
            }
        }

        // Update profile status with empty document path if rejecting
        $updateData = ['is_verified' => $newStatus];
        if ($_POST['action'] === 'reject') {
            $updateData['verification_document'] = null; // Clear the document path
        }

        $endpoint = $supabaseUrl . "/rest/v1/profiles?id=eq." . urlencode($profileId);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode === 204) {
            $successMessage = $_POST['action'] === 'approve' ? 'Landlord verified successfully' : 'Landlord rejected successfully';
            $activeTab = 'verifications';
        } else {
            throw new Exception("Failed to update verification status");
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $activeTab = 'verifications';
    }
}

// Set up Supabase API credentials
$supabaseUrl = SUPABASE_URL;
$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_KEY,
    "Authorization: Bearer " . SUPABASE_KEY
];

// Get active tab (default to users)
$activeTab = $_GET['tab'] ?? 'users';

// Get filter parameters for users tab
$filterRole = isset($_GET['role']) ? $_GET['role'] : '';
$filterVerified = isset($_GET['verified']) ? $_GET['verified'] : ''; // Can be 0, 1, 2 or empty

// Set up bucket name for verification documents
$bucketName = 'verification';

// Now include the header after all logic is done
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Admin Dashboard</h1>

    <?php if ($successMessage): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_GET['success']) ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <nav class="flex space-x-4" aria-label="Tabs">
            <a href="?tab=users" class="<?= $activeTab === 'users' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                All Users
            </a>
            <a href="?tab=properties" class="<?= $activeTab === 'properties' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Manage Properties
            </a>
            <a href="?tab=reports" class="<?= $activeTab === 'reports' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                <span>Reports</span>
                <?php
                $reports = new Reports();
                $pendingReports = count(array_filter($reports->getReports(), function($report) {
                    return $report['status'] === 'pending';
                }));
                if ($pendingReports > 0):
                ?>
                <span class="bg-red-100 text-red-600 text-xs font-medium px-2 py-0.5 rounded-full"><?= $pendingReports ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=verifications" class="<?= $activeTab === 'verifications' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Verification Requests
            </a>
        </nav>
    </div>

    <?php if ($activeTab === 'users'): ?>
        <!-- All Users Tab -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">All Users</h2>

            <!-- Filters for All Users -->
            <form method="GET" action="admin.php" class="mb-6">
                <input type="hidden" name="tab" value="users">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="filter_role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select id="filter_role" name="role" class="mt-1 block w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
                            <option value="">All Roles</option>
                            <option value="student" <?= $filterRole === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="landlord" <?= $filterRole === 'landlord' ? 'selected' : '' ?>>Landlord</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter_verified" class="block text-sm font-medium text-gray-700">Verification Status</label>
                        <select id="filter_verified" name="verified" class="mt-1 block w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
                            <option value="">All Statuses</option>
                            <option value="1" <?= $filterVerified === '1' ? 'selected' : '' ?>>Verified</option>
                            <option value="0" <?= $filterVerified === '0' ? 'selected' : '' ?>>Not Verified</option>
                            <option value="2" <?= $filterVerified === '2' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-transparent">&nbsp;</label> <!-- Spacer -->
                        <button type="submit" class="mt-1 w-full px-4 py-2 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                            Filter Users
                        </button>
                    </div>
                </div>
            </form>
            
            <?php
            // Fetch all users from profiles table
            $endpoint = $supabaseUrl . "/rest/v1/profiles?select=*";
            
            // Apply role filter
            if (!empty($filterRole)) {
                $endpoint .= "&role=eq." . urlencode($filterRole);
            }
            // Apply verification status filter
            if ($filterVerified !== '') { // Check for '' to allow filtering for '0'
                $endpoint .= "&is_verified=eq." . intval($filterVerified);
            }
            
            $endpoint .= "&order=created_at.desc"; // Optional: order by creation date

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            curl_close($ch);
            $allUsers = json_decode($result, true) ?: [];
            ?>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($allUsers as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['name'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap capitalize"><?= htmlspecialchars($user['role'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['role'] === 'landlord'): ?>
                                        <?php if (!empty($user['is_verified']) && $user['is_verified'] == 1): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Verified</span>                                        <?php elseif (!empty($user['is_verified']) && $user['is_verified'] == 2): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php elseif (!empty($user['is_verified']) && $user['is_verified'] == 3): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Not Verified</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($activeTab === 'properties'): ?>
        <!-- Properties Management Tab -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Property Listings Management</h2>
            
            <!-- Properties Filter -->
            <form method="GET" action="admin.php" class="mb-6">
                <input type="hidden" name="tab" value="properties">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="filter_university" class="block text-sm font-medium text-gray-700">University</label>
                        <select id="filter_university" name="university" class="mt-1 block w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
                            <option value="">All Universities</option>
                            <option value="CBU" <?= isset($_GET['university']) && $_GET['university'] === 'CBU' ? 'selected' : '' ?>>Copperbelt University (CBU)</option>
                            <option value="UNZA" <?= isset($_GET['university']) && $_GET['university'] === 'UNZA' ? 'selected' : '' ?>>University of Zambia (UNZA)</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter_price_min" class="block text-sm font-medium text-gray-700">Min Price</label>
                        <input type="number" id="filter_price_min" name="price_min" value="<?= $_GET['price_min'] ?? '' ?>" 
                               class="mt-1 block w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
                    </div>
                    <div>
                        <label for="filter_price_max" class="block text-sm font-medium text-gray-700">Max Price</label>
                        <input type="number" id="filter_price_max" name="price_max" value="<?= $_GET['price_max'] ?? '' ?>"
                               class="mt-1 block w-full p-2 border border-brand-light rounded focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-transparent">&nbsp;</label>
                        <button type="submit" class="mt-1 w-full px-4 py-2 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                            Filter Properties
                        </button>
                    </div>
                </div>
            </form>

            <?php
            // Fetch all properties with filters
            require_once __DIR__ . '/../api/fetch_listings.php';
            $listingApi = new PropertyListings();
            
            $university = isset($_GET['university']) ? $_GET['university'] : null;
            $priceMin = isset($_GET['price_min']) ? floatval($_GET['price_min']) : null;
            $priceMax = isset($_GET['price_max']) ? floatval($_GET['price_max']) : null;
            
            $properties = $listingApi->getAllProperties($university, null, $priceMin, $priceMax);
            ?>

            <?php if (!empty($properties) && !isset($properties['error'])): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">University</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Landlord</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($properties as $property): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($property['title']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($property['address']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">K<?= number_format($property['price']) ?>/month</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($property['target_university'] ?? 'Not specified') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($property['profiles']['name'] ?? 'Unknown') ?></div>
                                        <?php if (!empty($property['profiles']['is_verified']) && $property['profiles']['is_verified'] == 1): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>Verified
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($property['is_published']) && $property['is_published'] == 1): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Published</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <a href="listing_detail.php?id=<?= $property['id'] ?>" class="text-brand-primary hover:text-brand-secondary">View</a>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                                            <button type="submit" name="action" value="delete_property" 
                                                    onclick="return confirm('Are you sure you want to delete this property? This action cannot be undone.')"
                                                    class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-gray-100 border border-gray-200 text-gray-700 px-4 py-3 rounded">
                    No properties found matching the current filters.
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($activeTab === 'verifications'): ?>
        <!-- Verification Requests Tab -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Landlord Verification Requests</h2>
            
            <?php
            // Fetch all pending verifications
            $endpoint = $supabaseUrl . "/rest/v1/profiles?is_verified=eq.2&role=eq.landlord";
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            curl_close($ch);
            $pendingLandlords = json_decode($result, true) ?: [];
            ?>

            <?php if (empty($pendingLandlords)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    No pending verification requests.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Document</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($pendingLandlords as $landlord): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($landlord['name'] ?? 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($landlord['email'] ?? 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($landlord['phone'] ?? 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($landlord['verification_document'])): ?>
                                            <?php 
                                            // Check if the document is stored in Supabase or locally
                                            $docPath = $landlord['verification_document'];
                                            if (strpos($docPath, 'user_') === 0) {
                                                // Supabase storage path
                                                $docUrl = $supabaseUrl . '/storage/v1/object/public/' . $bucketName . '/' . $docPath;
                                            } else {
                                                // Local storage path
                                                $docUrl = '/' . $docPath;
                                            }
                                            ?>
                                            <a href="<?= htmlspecialchars($docUrl) ?>" target="_blank" class="text-brand-primary hover:text-brand-secondary underline">
                                                View Document
                                            </a>
                                        <?php else: ?>
                                            <span class="text-red-600">No document</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="POST" class="flex space-x-2">
                                            <input type="hidden" name="profile_id" value="<?= htmlspecialchars($landlord['id']) ?>">
                                            <button type="submit" name="action" value="approve" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                                Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                                Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($activeTab === 'reports'): ?>
        <!-- Reports Tab -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Reported Listings</h2>
                <div class="flex gap-2">
                    <a href="?tab=reports&filter=pending" class="px-3 py-1 text-sm rounded-full <?= (!isset($_GET['filter']) || $_GET['filter'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600' ?>">
                        Pending
                    </a>
                    <a href="?tab=reports&filter=reviewed" class="px-3 py-1 text-sm rounded-full <?= isset($_GET['filter']) && $_GET['filter'] === 'reviewed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' ?>">
                        Reviewed
                    </a>
                    <a href="?tab=reports&filter=resolved" class="px-3 py-1 text-sm rounded-full <?= isset($_GET['filter']) && $_GET['filter'] === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                        Resolved
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Listing ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Landlord Unique ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $filter = $_GET['filter'] ?? 'pending';
                        $reports = new Reports();
                        $reportsList = $reports->getReports($filter);
                        
                        foreach ($reportsList as $report):
                            $statusClass = match($report['status']) {
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'reviewed' => 'bg-blue-100 text-blue-800',
                                'resolved' => 'bg-green-100 text-green-800',
                                default => 'bg-gray-100 text-gray-600'
                            };
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-gray">
                                <a href="listing_detail.php?id=<?= htmlspecialchars($report['listing_id']) ?>" class="text-brand-primary hover:text-brand-secondary">
                                    #<?= htmlspecialchars($report['listing_id']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-gray">
                                <?php
                                // Fetch landlord unique_id for this report
                                $landlordUniqueId = '';
                                if (!empty($report['landlord_id'])) {
                                    $endpoint = $supabaseUrl . "/rest/v1/profiles?id=eq." . urlencode($report['landlord_id']) . "&select=unique_id";
                                    $ch = curl_init($endpoint);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                    $response = curl_exec($ch);
                                    curl_close($ch);
                                    $profile = json_decode($response, true);
                                    if (!empty($profile[0]['unique_id'])) {
                                        $landlordUniqueId = $profile[0]['unique_id'];
                                    }
                                }
                                ?>
                                <?= $landlordUniqueId ? '#' . htmlspecialchars($landlordUniqueId) : '<span class="text-gray-400">N/A</span>' ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-brand-gray max-w-xs truncate">
                                <?= htmlspecialchars($report['reason']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                    <?= ucfirst(htmlspecialchars($report['status'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-gray">
                                <?= date('M j, Y', strtotime($report['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-3">
                                    <?php if ($report['status'] === 'pending'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="report_id" value="<?= htmlspecialchars($report['id']) ?>">
                                        <button type="submit" name="action" value="review_report" class="text-blue-600 hover:text-blue-900">
                                            Mark as Reviewed
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($report['status'] === 'reviewed'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="report_id" value="<?= htmlspecialchars($report['id']) ?>">
                                        <button type="submit" name="action" value="resolve_report" class="text-green-600 hover:text-green-900">
                                            Mark as Resolved
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>