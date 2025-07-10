<?php
require_once __DIR__ . "/../api/auth.php";
require_once __DIR__ . "/../includes/header.php";

$auth = new Auth();

// If already authenticated, redirect to dashboard
if ($auth->isAuthenticated()) {
    header("Location: dashboard.php");
    exit();
}

// Handle email confirmation
if (isset($_GET['token'])) {
    $result = $auth->confirmEmail($_GET['token']);
    
    if (!isset($result['error'])) {
        header("Location: login.php?message=Email confirmed successfully! Please login.");
        exit();
    } else {
        $error = "Error confirming email: " . ($result['error']['message'] ?? 'Unknown error');
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];
    $name = $_POST["name"];
    $role = $_POST["role"];
    $phone = $_POST["phone"];

    // Basic validation
    if (empty($email) || empty($password) || empty($name) || empty($role) || empty($phone)) {
        $error = "All fields are required, including phone number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!in_array($role, ['student', 'landlord'])) {
        $error = "Invalid role selected.";
    } elseif (!preg_match('/^[0-9+\-() ]{10,15}$/', $phone)) {
        $error = "Invalid phone number format.";
    } else {
        $result = $auth->register($email, $password, $name, $role, $phone);

        if (isset($result["error"])) {
            $error = "Error: " . $result["error"]["message"];
        } else {
            $success = "Registration successful! Please check your email to confirm your account.";
        }
    }
}
?>

<div class="min-h-screen flex items-center justify-center bg-brand-light/10 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-md border border-brand-light">
        <div>
            <a href="index.php" class="flex justify-center mb-8 hover:opacity-90 transition-opacity">
                <!-- Logo SVG -->
                <svg width="48" height="36" viewBox="0 0 300 220" xmlns="http://www.w3.org/2000/svg">
                    <!-- Logo content -->
                </svg>
            </a>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-brand-gray">
                Create your account
            </h2>
            <p class="mt-2 text-center text-sm text-brand-gray/70">
                Or
                <a href="login.php" class="font-medium text-brand-primary hover:text-brand-secondary">
                    sign in to your account
                </a>
            </p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="sign-up.php" method="POST">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="name" class="sr-only">Full Name</label>
                    <input id="name" 
                           name="name" 
                           type="text" 
                           required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light placeholder-brand-gray/50 text-brand-gray rounded-t-md focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm" 
                           placeholder="Full Name"
                           value="<?php echo htmlspecialchars($name ?? ''); ?>">
                </div>
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input id="email" 
                           name="email" 
                           type="email" 
                           required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light placeholder-brand-gray/50 text-brand-gray focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm" 
                           placeholder="Email address"
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                <div>
                    <label for="phone" class="sr-only">Phone Number</label>
                    <input id="phone" 
                           name="phone" 
                           type="tel" 
                           required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light placeholder-brand-gray/50 text-brand-gray focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm" 
                           placeholder="Phone Number"
                           value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>
                <div>
                    <label for="role" class="sr-only">Role</label>
                    <select id="role" 
                            name="role" 
                            required 
                            class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light text-brand-gray focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm">
                        <option value="">Select Role</option>
                        <option value="student" <?php echo ($role ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="landlord" <?php echo ($role ?? '') === 'landlord' ? 'selected' : ''; ?>>Landlord</option>
                    </select>
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" 
                           name="password" 
                           type="password" 
                           required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light placeholder-brand-gray/50 text-brand-gray focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm" 
                           placeholder="Password">
                </div>
                <div>
                    <label for="confirm_password" class="sr-only">Confirm Password</label>
                    <input id="confirm_password" 
                           name="confirm_password" 
                           type="password" 
                           required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light placeholder-brand-gray/50 text-brand-gray rounded-b-md focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm" 
                           placeholder="Confirm Password">
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-brand-primary hover:bg-brand-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-primary transition-colors">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fa-solid fa-user-plus text-brand-primary/50 group-hover:text-brand-primary/70"></i>
                    </span>
                    Sign up
                </button>
            </div>
        </form>

        <div class="text-sm text-center text-brand-gray/70">
            By signing up, you agree to our 
            <a href="#" class="font-medium text-brand-primary hover:text-brand-secondary">Terms</a> 
            and 
            <a href="#" class="font-medium text-brand-primary hover:text-brand-secondary">Privacy Policy</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
