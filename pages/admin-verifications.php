ss<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/supabase.php';

// Fetch all pending landlord verification requests
$endpointLandlords = $supabaseUrl . "/rest/v1/profiles?select=id,name,email,is_verified&is_verified=eq.0";
$ch = curl_init($endpointLandlords);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = curl_exec($ch);
curl_close($ch);
$pendingLandlords = json_decode($result, true) ?: [];

// Fetch all properties with their landlord details
$endpointProperties = $supabaseUrl . "/rest/v1/properties?select=*,profiles(name,email,is_verified)";
$ch = curl_init($endpointProperties);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = curl_exec($ch);
curl_close($ch);
$properties = json_decode($result, true) ?: [];

// Handle property actions (delete/verify)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['property_action'], $_POST['property_id'])) {
    $propertyId = $_POST['property_id'];
    $action = $_POST['property_action'];

    if ($action === 'delete') {
        // Delete property
        $endpoint = $supabaseUrl . "/rest/v1/properties?id=eq." . urlencode($propertyId);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode === 204) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=Property deleted successfully');
            exit;
        }
    }
}

?>
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-brand-gray">Admin Dashboard</h1>
    </div>

    <!-- Landlord Verification Requests -->
    <div class="mb-12">
        <h2 class="text-2xl font-semibold mb-4">Verification Requests</h2>
        <?php if (empty($pendingLandlords)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">No pending verification requests.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-brand-light rounded">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 border">Name</th>
                            <th class="px-4 py-2 border">Email</th>
                            <th class="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingLandlords as $landlord): ?>
                        <tr>
                            <td class="px-4 py-2 border"><?php echo htmlspecialchars($landlord['name']); ?></td>
                            <td class="px-4 py-2 border"><?php echo htmlspecialchars($landlord['email']); ?></td>
                            <td class="px-4 py-2 border">
                                <div class="flex space-x-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="landlord_id" value="<?php echo $landlord['id']; ?>">
                                        <input type="hidden" name="landlord_action" value="verify">
                                        <button type="submit" 
                                                class="px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-sm">
                                            Verify
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this request?');">
                                        <input type="hidden" name="landlord_id" value="<?php echo $landlord['id']; ?>">
                                        <input type="hidden" name="landlord_action" value="delete">
                                        <button type="submit" 
                                                class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Property Management -->
    <div>
        <h2 class="text-2xl font-semibold mb-4">Property Listings Management</h2>
        <?php if (empty($properties)): ?>
            <div class="bg-gray-100 border border-gray-400 text-gray-700 px-4 py-3 rounded">No properties found.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-brand-light rounded">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 border">Title</th>
                            <th class="px-4 py-2 border">Address</th>
                            <th class="px-4 py-2 border">Price</th>
                            <th class="px-4 py-2 border">University</th>
                            <th class="px-4 py-2 border">Landlord</th>
                            <th class="px-4 py-2 border">Status</th>
                            <th class="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($properties as $property): ?>
                        <tr>
                            <td class="px-4 py-2 border"><?php echo htmlspecialchars($property['title']); ?></td>
                            <td class="px-4 py-2 border"><?php echo htmlspecialchars($property['address']); ?></td>
                            <td class="px-4 py-2 border">ZMW <?php echo number_format($property['price']); ?></td>
                            <td class="px-4 py-2 border"><?php echo htmlspecialchars($property['target_university']); ?></td>
                            <td class="px-4 py-2 border">
                                <?php echo htmlspecialchars($property['profiles']['name'] ?? 'Unknown'); ?>
                                <?php if (!empty($property['profiles']['is_verified']) && $property['profiles']['is_verified'] == 1): ?>
                                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Verified
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 border">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?php echo $property['is_approved'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $property['is_approved'] ? 'Approved' : 'Pending'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 border">
                                <div class="flex space-x-2">
                                    <a href="listing_detail.php?id=<?php echo $property['id']; ?>" 
                                       class="px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
                                       target="_blank">
                                        View
                                    </a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this property?');">
                                        <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                        <input type="hidden" name="property_action" value="delete">
                                        <button type="submit" 
                                                class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>