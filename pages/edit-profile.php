<?php
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../includes/header.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);

        // Basic validation
        if (empty($name)) {
            $error = "Name is required.";
        } elseif (!empty($phone) && !preg_match('/^[0-9+\-() ]{10,15}$/', $phone)) {
            $error = "Invalid phone number format.";
        } else {
            $result = $auth->updateProfile($name, $phone);
            if (isset($result['success'])) {
                $success = "Profile updated successfully!";
                $user = $auth->getCurrentUser(); // Refresh user data
            } else {
                $error = $result['error']['message'] ?? "Failed to update profile.";
            }
        }
    } elseif (isset($_POST['update_password'])) {
        // Handle password update
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Password validation
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
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
        }
    }
}
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

    <form action="edit-profile.php" method="POST" class="space-y-6 bg-white p-6 rounded-lg shadow-sm border border-brand-light">
        <!-- Profile Information -->
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
                           value="<?php echo htmlspecialchars($user['profile']['name'] ?? ''); ?>">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-brand-gray">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50 bg-brand-light/10"
                           value="<?php echo htmlspecialchars($user['auth']['user']['email'] ?? ''); ?>"
                           readonly>
                    <p class="mt-1 text-sm text-brand-gray/70">Email cannot be changed</p>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-brand-gray">Phone Number</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50"
                           value="<?php echo htmlspecialchars($user['profile']['phone'] ?? ''); ?>">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-brand-gray">Account Type</label>
                    <input type="text" 
                           id="role" 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50 bg-brand-light/10"
                           value="<?php echo ucfirst(htmlspecialchars($user['profile']['role'] ?? '')); ?>"
                           readonly>
                    <p class="mt-1 text-sm text-brand-gray/70">Account type cannot be changed</p>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div>
            <h2 class="text-xl font-semibold mb-4 text-brand-gray">Change Password</h2>
            <p class="text-sm text-brand-gray/70 mb-4">Leave password fields empty if you don't want to change it.</p>
            
            <div class="space-y-4">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-brand-gray">Current Password</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50">
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-brand-gray">New Password</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50">
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-brand-gray">Confirm New Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="mt-1 block w-full rounded-md border-brand-light shadow-sm focus:border-brand-primary focus:ring focus:ring-brand-primary focus:ring-opacity-50">
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex justify-end space-x-4">
            <a href="dashboard.php" class="px-4 py-2 border border-brand-light text-brand-gray rounded hover:bg-brand-light/20 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-brand-primary text-white rounded hover:bg-brand-secondary transition-colors">
                Save Changes
            </button>
        </div>
    </form>

    <!-- Delete Account Section -->
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