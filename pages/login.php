<?php
require_once __DIR__ . "/../api/auth.php";

$auth = new Auth();
$email = '';  // Initialize email variable
$error = '';  // Initialize error variable

// If already authenticated, redirect to dashboard
if ($auth->isAuthenticated()) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"] ?? '';
    $password = $_POST["password"] ?? '';

    $loginResult = $auth->login($email, $password);

    if ($loginResult === true) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = $loginResult;
    }
}

// Include header AFTER all potential redirects
require_once __DIR__ . "/../includes/header.php";
?>

<div class="min-h-screen flex items-center justify-center bg-brand-light/10 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-md border border-brand-light">
        <div>
            <a href="index.php" class="flex justify-center mb-8 hover:opacity-90 transition-opacity">
                <!-- Logo SVG -->
                <svg width="48" height="36" viewBox="0 0 300 220" xmlns="http://www.w3.org/2000/svg">
                    // ...existing code...
                </svg>
            </a>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-brand-gray">
                Sign in to your account
            </h2>
            <p class="mt-2 text-center text-sm text-brand-gray/70">
                Or
                <a href="sign-up.php" class="font-medium text-brand-primary hover:text-brand-secondary">
                    create a new account
                </a>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="login.php" method="POST">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input id="email" 
                           name="email" 
                           type="email" 
                           required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light placeholder-brand-gray/50 text-brand-gray rounded-t-md focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm" 
                           autocomplete="email" 
                           autofocus 
                           <?php if ($error): ?>aria-invalid="true"<?php endif; ?>
                           <?php if (isset($email)): ?>value="<?php echo htmlspecialchars($email); ?>"<?php endif; ?>
                           placeholder="Email address"
                           value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" 
                           name="password" 
                           type="password" 
                           required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-brand-light placeholder-brand-gray/50 text-brand-gray rounded-b-md focus:outline-none focus:ring-brand-primary focus:border-brand-primary focus:z-10 sm:text-sm" 
                           placeholder="Password">
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember-me" 
                           name="remember-me" 
                           type="checkbox"
                           class="h-4 w-4 text-brand-primary focus:ring-brand-primary border-brand-light rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-brand-gray">
                        Remember me
                    </label>
                </div>

                <div class="text-sm">
                    <a href="#" class="font-medium text-brand-primary hover:text-brand-secondary">
                        Forgot your password?
                    </a>
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-brand-primary hover:bg-brand-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-primary transition-colors">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fa-solid fa-lock text-brand-primary/50 group-hover:text-brand-primary/70"></i>
                    </span>
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
