<?php
// api/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . "/../config/config.php"; // Include Supabase config

class Auth {
    private $supabaseUrl;
    private $apiKey;
    private $headers;

    public function __construct() {
        $this->supabaseUrl = SUPABASE_URL;
        $this->apiKey = SUPABASE_KEY;
        // Common headers for Supabase API calls
        $this->headers = [
            "Content-Type: application/json",
            "apikey: " . $this->apiKey,
            "Authorization: Bearer " . $this->apiKey // Use API key for server-side calls
        ];
    }

    // Function to fetch user profile from Supabase 'profiles' table
    private function getUserProfile($userId, $accessToken) {
        // Use the user's access token for row-level security if needed,
        // otherwise, use the service key (apiKey) for broader access.
        // Here we use the apiKey as an example for server-side fetching.
        $profileHeaders = [
            "Content-Type: application/json",
            "apikey: " . $this->apiKey,
            // Optionally use the user's token if RLS policies require it:
            // "Authorization: Bearer " . $accessToken
            "Authorization: Bearer " . $this->apiKey
        ];        // Construct the URL to fetch the profile where the 'id' column matches the user's ID
        $endpoint = $this->supabaseUrl . "/rest/v1/profiles?select=*&id=eq." . urlencode($userId); // Select all fields

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $profileHeaders);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $profiles = json_decode($response, true);
            // Return the first profile found (should be unique by ID)
            return !empty($profiles) ? $profiles[0] : null;
        } else {
            // Log error or handle appropriately
             error_log("Supabase profile fetch failed with code: " . $httpCode . " Response: " . $response);
            return null; // Failed to fetch profile
        }
    }

    // Function to register a user with profile creation
    private function generateUniqueId() {
        return str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function register($email, $password, $name, $role, $phone = null) {
        $uniqueId = $this->generateUniqueId();
        
        $data = json_encode([
            "email" => $email,
            "password" => $password,
            "data" => [
                "name" => $name,
                "role" => $role,
                "unique_id" => $uniqueId
            ]
        ]);

        $ch = curl_init($this->supabaseUrl . "/auth/v1/signup");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "apikey: " . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['id'])) {
            // Create profile in the profiles table
            $profileData = json_encode([
                "id" => $result['id'],
                "name" => $name,
                "role" => $role,
                "email" => $email,
                "phone" => $phone,
                "unique_id" => $uniqueId
            ]);

            $ch = curl_init($this->supabaseUrl . "/rest/v1/profiles");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $profileData);
            
            $profileResponse = curl_exec($ch);
            curl_close($ch);
        }

        return $result;
    }

    // Function to confirm email
    public function confirmEmail($token, $type = 'signup') {
        $data = json_encode([
            "type" => $type,
            "token" => $token
        ]);

        $ch = curl_init($this->supabaseUrl . "/auth/v1/verify");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "apikey: " . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    // Function to log in a user
    public function login($email, $password) {
        $data = json_encode([
            "email" => $email,
            "password" => $password
        ]);

        // Use /token endpoint for password grant type
        $ch = curl_init($this->supabaseUrl . "/auth/v1/token?grant_type=password");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ // Use API key for this specific auth endpoint
             "Content-Type: application/json",
             "apikey: " . $this->apiKey
         ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode === 200 && isset($result["access_token"])) {
            // Login successful, now fetch the profile
            $userId = $result['user']['id'];
            $accessToken = $result['access_token']; // We might need this if RLS is strict

            $profile = $this->getUserProfile($userId, $accessToken);

            if ($profile) {
                 // Store both auth info and profile info in the session
                $_SESSION["user"] = [
                    'auth' => $result, // Original authentication response
                    'profile' => $profile // Profile data including 'role' and 'name'
                 ];
                return true; // Indicate successful login and profile fetch
            } else {
                 // Logged in but couldn't fetch profile - handle this case
                 // Maybe log out the user or show an error
                 session_destroy(); // Clean up session if profile is essential
                 return "Login successful, but failed to retrieve user profile.";
            }

        } else {
            // Login failed
            return $result["error_description"] ?? $result["error"] ?? $result['msg'] ?? "Login failed due to an unknown error.";
        }
    }

    // Function to check if the user is logged in
    public function isAuthenticated() {
        // Check if both auth and profile data exist in the session
        return isset($_SESSION["user"]) && isset($_SESSION["user"]['auth']) && isset($_SESSION["user"]['profile']);
    }

    // Function to log out
    public function logout() {
        session_destroy();
        header("Location: login.php"); // Redirect to login page after logout
        exit();
    }

     // Function to get current user data (auth + profile)
     public function getCurrentUser() {
         return $this->isAuthenticated() ? $_SESSION["user"] : null;
     }

     // Function to get just the user's role
     public function getUserRole() {
         $user = $this->getCurrentUser();
         return $user ? ($user['profile']['role'] ?? null) : null; // Return role or null if not found/logged in
     }

      // Function to get just the user's ID
     public function getUserId() {
         $user = $this->getCurrentUser();
         // Get ID from the 'auth' part which comes directly from Supabase auth
         return $user ? ($user['auth']['user']['id'] ?? null) : null;
     }    // Function to update user profile
    public function updateProfile($name, $phone = null, $verificationFilePath = null, $verificationStatus = null) {
        if (!$this->isAuthenticated()) {
            return ['error' => ['message' => 'Not authenticated']];
        }

        $userId = $this->getUserId();
        
        // Get current profile data first
        $currentProfile = $this->getUserProfile($userId, null);
        
        // Prepare update data while preserving existing values
        $data = [
            'name' => $name,
            'phone' => $phone,
            'verification_document' => $verificationFilePath ?? $currentProfile['verification_document'] ?? null,
            'is_verified' => $verificationStatus ?? $currentProfile['is_verified'] ?? 0,
            'role' => $currentProfile['role'] ?? null,
            'email' => $currentProfile['email'] ?? null
        ];

        error_log("Updating profile in Supabase with data: " . json_encode($data));

        $endpoint = $this->supabaseUrl . '/rest/v1/profiles?id=eq.' . urlencode($userId);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("Supabase profile update response: Status: $statusCode, Response: $response, Error: $curlError");

        if ($statusCode === 204) {
            // Update successful, refresh session data
            $profile = $this->getUserProfile($userId, null);
            if ($profile) {
                $_SESSION['user']['profile'] = $profile;
                return ['success' => true];
            } else {
                error_log("Failed to refresh profile data after update");
                return ['error' => ['message' => 'Profile updated but failed to refresh data']];
            }
        }

        $errorResponse = json_decode($response, true);
        $errorMessage = isset($errorResponse['message']) ? $errorResponse['message'] : 
                       (isset($errorResponse['error']) ? $errorResponse['error'] : 'Unknown error');
        
        return ['error' => ['message' => 'Failed to update profile: ' . $errorMessage]];
    }

    public function updatePassword($currentPassword, $newPassword) {
        if (!$this->isAuthenticated()) {
            return ['error' => ['message' => 'Not authenticated']];
        }

        $endpoint = $this->supabaseUrl . '/auth/v1/user';
        $data = [
            'password' => $newPassword
        ];

        // First verify current password
        $loginResult = $this->login($_SESSION['user']['auth']['user']['email'], $currentPassword);
        if ($loginResult !== true) {
            return ['error' => ['message' => 'Current password is incorrect']];
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $_SESSION['user']['auth']['access_token']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode === 200) {
            return ['success' => true];
        }

        return ['error' => ['message' => 'Failed to update password']];
    }
}

// If the script is accessed directly with a logout action (e.g., from header.php)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth = new Auth();
    $auth->logout();
}

?>