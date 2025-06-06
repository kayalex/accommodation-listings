<?php

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../includes/header.php'; 
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$currentUser = $auth->getCurrentUser(); // Get the full user session data
$success = null;
$error = null;

// Set up Supabase API credentials
$supabaseUrl = SUPABASE_URL;
$anonApiKey = SUPABASE_KEY; // Public anon key
$bucketName = 'verification'; // Bucket for verification documents

$user_id = $currentUser['auth']['user']['id'] ?? null;
$user_access_token = $currentUser['auth']['access_token'] ?? null;

if (!$user_id || !$user_access_token) {
    error_log("Edit Profile: Critical authentication data missing. User ID: {$user_id}");
    $error = "Authentication error. Please re-login.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) { 
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($confirmPassword)) {
            $error = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $error = "New password must be at least 6 characters long.";
        } else {
            $result = $auth->updatePassword($currentPassword, $newPassword); 
            if (isset($result['success'])) {
                $success = "Password updated successfully!";
            } else {
                $error = $result['error']['message'] ?? "Failed to update password.";
            }
        }    } else { 
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        
        // Preserve existing verification information if not being updated
        $verificationFilePath = $currentUser['profile']['verification_document'] ?? null;
        $verificationStatus = isset($currentUser['profile']['is_verified']) ? intval($currentUser['profile']['is_verified']) : 0;
        $isCurrentlyVerified = ($verificationStatus === 1);


        // Handle file upload only if user is not already verified, or if they are pending/rejected
        if (!$isCurrentlyVerified && isset($_FILES['verification_document']) && $_FILES['verification_document']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['verification_document']['tmp_name'];
            $originalName = $_FILES['verification_document']['name'];
            $mimeType = mime_content_type($tmpName);
            $fileSize = $_FILES['verification_document']['size'];
            
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($mimeType, $allowedMimeTypes) || !in_array($extension, $allowedExtensions)) {
                $error = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
            } elseif ($fileSize > 5 * 1024 * 1024) { 
                $error = "File is too large (max 5MB).";
            } else {
                $safeOriginalName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", $originalName);
                $uniqueFileName = 'userid_' . $user_id . '_uploaddate_' . time() . '.' . $extension;
                $storagePath = 'user_' . $user_id . '/' . $uniqueFileName;
                
                $fileContents = file_get_contents($tmpName);
                if ($fileContents === false) {
                    $error = "Could not read file: " . htmlspecialchars($originalName);
                } else {
                    // Before uploading new, delete old if it exists (optional, but good for storage management)
                    $previousDocumentPath = $currentUser['profile']['verification_document'] ?? null;
                    if ($previousDocumentPath && strpos($previousDocumentPath, 'user_' . $user_id . '/') === 0) {
                        $endpointDelete = $supabaseUrl . '/storage/v1/object/' . $bucketName . '/' . $previousDocumentPath;
                        $chDel = curl_init($endpointDelete);
                        curl_setopt($chDel, CURLOPT_CUSTOMREQUEST, "DELETE");
                        curl_setopt($chDel, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chDel, CURLOPT_HTTPHEADER, [
                            'Authorization: Bearer ' . $user_access_token,
                            'apikey: ' . $anonApiKey
                        ]);
                        $delResponse = curl_exec($chDel);
                        $delStatusCode = curl_getinfo($chDel, CURLINFO_HTTP_CODE);
                        curl_close($chDel);
                        if ($delStatusCode == 200) {
                            error_log("Successfully deleted previous verification doc: $previousDocumentPath for user $user_id");
                        } else {
                            error_log("Failed to delete previous verification doc: $previousDocumentPath for user $user_id. Status: $delStatusCode. Response: $delResponse");
                        }
                    }


                    $endpointUpload = $supabaseUrl . '/storage/v1/object/' . $bucketName . '/' . $storagePath;
                    
                    $chUpload = curl_init($endpointUpload);
                    curl_setopt($chUpload, CURLOPT_POST, true);
                    curl_setopt($chUpload, CURLOPT_POSTFIELDS, $fileContents);
                    curl_setopt($chUpload, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chUpload, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $user_access_token,
                        'apikey: ' . $anonApiKey, 
                        'Content-Type: ' . $mimeType,
                        'x-upsert: true' // Using true to overwrite if somehow the exact same path was generated
                    ]);
                    
                    $responseUpload = curl_exec($chUpload);
                    $statusCodeUpload = curl_getinfo($chUpload, CURLINFO_HTTP_CODE);
                    $curlErrorUpload = curl_error($chUpload);
                    curl_close($chUpload);
                    
                    if ($statusCodeUpload === 200) { 
                        $verificationFilePath = $storagePath; 
                        $verificationStatus = 2; // Set status to "Pending Verification"
                        $success = "Verification document uploaded successfully. "; 
                    } else {
                        $uploadResponseBody = json_decode($responseUpload, true);
                        $supabaseMessage = $uploadResponseBody['message'] ?? 'N/A';
                        error_log("Verification Upload Failed. User: $user_id, Path: $storagePath, Status: $statusCodeUpload, SupabaseMsg: $supabaseMessage, cURL: $curlErrorUpload, Response: $responseUpload");
                        $error = "Failed to upload verification document. Supabase: " . htmlspecialchars($supabaseMessage) . " (Status: $statusCodeUpload)";
                    }
                }
            }
        } elseif ($isCurrentlyVerified && isset($_FILES['verification_document']) && $_FILES['verification_document']['error'] === UPLOAD_ERR_OK) {
            // User is verified but tried to upload a new document. Inform them.
            $error = "Your account is already verified. No new document is needed.";
        }


        if (!$error) {
            if (empty($name)) {
                $error = "Name is required.";
            } elseif (!empty($phone) && !preg_match('/^[0-9+\-() ]{10,15}$/', $phone)) {
                $error = "Invalid phone number format.";
            } else {                // Call updateProfile from Auth class with all current values
                $result = $auth->updateProfile($name, $phone, $verificationFilePath, $verificationStatus);
                
                if (isset($result['success'])) {
                    $success = ($success ?? "") . "Profile details updated successfully!";
                    // Refresh user data
                    $currentUser = $auth->getCurrentUser();
                    // Double-check that verification status is preserved
                    if ($verificationStatus !== null && 
                        (!isset($currentUser['profile']['is_verified']) || 
                         intval($currentUser['profile']['is_verified']) !== intval($verificationStatus))) {
                        error_log("Warning: Verification status mismatch after update. Expected: $verificationStatus, Got: " . 
                                ($currentUser['profile']['is_verified'] ?? 'null'));
                    }
                } else {
                    error_log("Profile update DB failed for user $user_id: " . json_encode($result));
                    $error = $result['error']['message'] ?? "Failed to update profile in database.";
                }
            }
        }
    }
}

if ($auth->isAuthenticated()) { // Re-fetch current user data in case it was updated
    $currentUser = $auth->getCurrentUser();
}


$verificationDocUrl = "";
if (!empty($currentUser['profile']['verification_document'])) {
    $documentPath = $currentUser['profile']['verification_document'];
    if (strpos($documentPath, 'user_') === 0 && strpos($documentPath, '/') !== false) { 
        $verificationDocUrl = $supabaseUrl . '/storage/v1/object/public/' . $bucketName . '/' . $documentPath;
    } elseif (strpos($documentPath, 'assets/verification_docs/') === 0) { 
        // Determine base URL from server variables instead of using undefined APP_URL
        $appUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $appUrl .= $_SERVER['HTTP_HOST'];
        $verificationDocUrl = $appUrl . '/' . $documentPath;
    }
}

$isVerified = isset($currentUser['profile']['is_verified']) ? intval($currentUser['profile']['is_verified']) : 0;
$canUploadVerification = ($isVerified !== 1); // User can upload if not (0), pending (2), or rejected (3)

?>

<div class="max-w-2xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-brand-gray">Edit Profile</h1>
        <p class="mt-2 text-brand-gray/70">Update your account information below.</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form action="edit-profile.php" method="POST" enctype="multipart/form-data" class="space-y-6 bg-white p-6 rounded-lg shadow-sm border border-brand-light">
        <div>
            <h2 class="text-xl font-semibold mb-4 text-brand-gray">Profile Information</h2>
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-brand-gray">Full Name</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50"
                           value="<?php echo htmlspecialchars($currentUser['profile']['name'] ?? ''); ?>">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-brand-gray">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50 bg-brand-light/10"
                           value="<?php echo htmlspecialchars($currentUser['auth']['user']['email'] ?? ''); ?>"
                           readonly>
                    <p class="mt-1 text-sm text-brand-gray/70">Email cannot be changed</p>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-brand-gray">Phone Number</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50"
                           value="<?php echo htmlspecialchars($currentUser['profile']['phone'] ?? ''); ?>">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-brand-gray">Account Type</label>
                    <input type="text" 
                           id="role" 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50 bg-brand-light/10"
                           value="<?php echo ucfirst(htmlspecialchars($currentUser['profile']['role'] ?? '')); ?>"
                           readonly>
                </div>

                <?php if (($currentUser['profile']['role'] ?? '') === 'landlord'): ?>
                <div>
                    <label for="verification_document_input" class="block text-sm font-medium text-brand-gray mb-2">Landlord Verification</label>
                    
                    <div class="bg-blue-50 p-4 mb-4 rounded-lg border border-blue-200">
                        <h4 class="text-blue-800 font-medium mb-2">Verification Instructions:</h4>
                        <ol class="list-decimal ml-4 text-sm text-blue-700 space-y-2">
                            <li>Take a clear photo of your National Registration Card (NRC)</li>
                            <li>Upload the NRC photo below (JPG or PNG format preferred)</li>
                            <li>Send K10 via mobile money to: <span class="font-medium">+260764416021</span></li>
                            <li>Important: The name on your NRC must match your mobile money payment details</li>
                        </ol>
                    </div>
                    
                    <?php
                        $status = $isVerified;
                        $statusText = 'Not Verified';
                        $statusColor = 'text-red-600';
                        $uploadMessage = "Please follow the verification steps above to verify your account.";

                        if ($status == 1) {
                            $statusText = 'Verified';
                            $statusColor = 'text-green-600';
                            $uploadMessage = "Your account is verified. No further action required.";
                        } elseif ($status == 2) {
                            $statusText = 'Pending Verification';
                            $statusColor = 'text-yellow-600';
                            $uploadMessage = "Your document is under review. You can upload a new one if needed.";
                        } elseif ($status == 3) {
                            $statusText = 'Verification Rejected';
                            $statusColor = 'text-red-600';
                            $uploadMessage = "Your verification was rejected. Please ensure your NRC photo is clear and matches your payment details.";
                        }
                    ?>

                    <?php if ($canUploadVerification): ?>
                        <input type="file" 
                               id="verification_document_input" 
                               name="verification_document" 
                               class="mt-1 block w-full text-sm text-slate-500
                                      file:mr-4 file:py-2 file:px-4
                                      file:rounded-full file:border-0
                                      file:text-sm file:font-semibold
                                      file:bg-brand-primary/10 file:text-brand-primary
                                      hover:file:bg-brand-primary/20"
                               accept=".jpg,.jpeg,.png,.pdf">
                    <?php endif; ?>
                    
                    <p class="mt-3 text-sm whitespace-pre-line text-brand-gray/70"><?php echo htmlspecialchars($uploadMessage); ?></p>
                    
                    <?php if (!empty($currentUser['profile']['verification_document'])): ?>
                        <p class="mt-2 text-sm">
                            Current document: 
                            <a href="<?php echo htmlspecialchars($verificationDocUrl); ?>" target="_blank" class="text-brand-primary underline">
                                View Document
                            </a>
                        </p>
                    <?php endif; ?>

                    <div class="mt-2 text-sm <?php echo $statusColor; ?> font-semibold">
                        Verification Status: <?php echo $statusText; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-semibold mb-4 text-brand-gray">Change Password</h2>
            <p class="text-sm text-brand-gray/70 mb-4">Leave password fields empty if you don't want to change it.</p>
            
            <div class="space-y-4">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-brand-gray">Current Password</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           autocomplete="current-password"
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50">
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-brand-gray">New Password</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50">
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-brand-gray">Confirm New Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50">
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-4 pt-4 border-t border-brand-light mt-6">
            <a href="dashboard.php" class="px-4 py-2 border border-brand-light text-brand-gray rounded hover:bg-brand-light/20 transition-colors">
                Cancel
            </a>
            <button type="submit" name="update_profile" value="1" class="px-4 py-2 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                Save Changes
            </button>
        </div>
    </form>

    <div class="mt-8">
        <h2 class="text-xl font-semibold text-red-600 mb-4">Danger Zone</h2>
        <div class="bg-red-50 p-6 rounded-lg border border-red-200">
            <h3 class="text-lg font-medium text-red-800">Delete Account</h3>
            <p class="mt-2 text-sm text-red-600">
                Once you delete your account, there is no going back. Please be certain.
            </p>
            <form action="delete-account.php" method="POST" class="mt-4">
                <button type="submit" 
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
                        onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                    Delete Account
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const verificationDocumentInput = document.getElementById('verification_document_input');
    const thumbnailPreview = document.getElementById('thumbnailPreview');
    const pdfPreviewName = document.getElementById('pdfPreviewName');

    if (verificationDocumentInput && thumbnailPreview && pdfPreviewName) {
        verificationDocumentInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        thumbnailPreview.src = e.target.result;
                        thumbnailPreview.classList.remove('hidden');
                        pdfPreviewName.classList.add('hidden');
                        pdfPreviewName.textContent = '';
                    }
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    thumbnailPreview.classList.add('hidden');
                    thumbnailPreview.src = '#'; // Clear image src
                    pdfPreviewName.textContent = 'Selected PDF: ' + file.name;
                    pdfPreviewName.classList.remove('hidden');
                } else {
                    // Not an image or PDF, clear previews
                    thumbnailPreview.classList.add('hidden');
                    thumbnailPreview.src = '#';
                    pdfPreviewName.classList.add('hidden');
                    pdfPreviewName.textContent = 'File selected: ' + file.name + ' (Preview not available)';
                    pdfPreviewName.classList.remove('hidden');

                }
            } else {
                // No file selected, hide previews
                thumbnailPreview.classList.add('hidden');
                thumbnailPreview.src = '#';
                pdfPreviewName.classList.add('hidden');
                pdfPreviewName.textContent = '';
            }
        });
    }
});
</script>

</body>
</html>
