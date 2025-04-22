<?php
require_once __DIR__ . "/../api/auth.php";
require_once __DIR__ . "/../includes/header.php";

$auth = new Auth();

// If already authenticated, redirect to dashboard
if ($auth->isAuthenticated()) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];
    $name = $_POST["name"];
    $role = $_POST["role"];

    $result = $auth->register($email, $password);

    if (isset($result["error"])) {
        $error = "Error: " . $result["error"]["message"];
    } else {
        $success = "Registration successful! Please check your email for confirmation.";
    }
}
?>

<div class="min-h-screen bg-gradient-to-br from-blue-500 to-purple-600 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-xl overflow-hidden">
        <div class="px-6 py-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Create an Account</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-blue-500 hover:text-blue-700 font-medium">
                        Sign in
                    </a>
                </p>
            </div>

            <?php if (isset($error)): ?>
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Full Name
                    </label>
                    <input type="text" id="name" name="name" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <input type="password" id="password" name="password" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">
                        I am a...
                    </label>
                    <select id="role" name="role" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select your role</option>
                        <option value="student">Student</option>
                        <option value="landlord">Landlord</option>
                    </select>
                </div>

                <div>
                    <button type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Create Account
                    </button>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <a href="index.php" class="text-sm text-gray-600 hover:text-gray-900 flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Home
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
