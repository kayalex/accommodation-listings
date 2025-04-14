<?php
// api/auth.php
session_start();
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
        ];

        // Construct the URL to fetch the profile where the 'id' column matches the user's ID
        $endpoint = $this->supabaseUrl . "/rest/v1/profiles?select=role,name&id=eq." . urlencode($userId); // Select role and name

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


    // Function to register a user (No changes needed here for role fetching)
    public function register($email, $password) {
         $data = json_encode([
            "email" => $email,
            "password" => $password
            // Add other data needed for signup if your Supabase function expects it
            // e.g., 'data' => ['role' => 'student', 'name' => 'Default Name']
            // Check your Supabase setup (e.g., trigger functions) for how 'profiles' is populated on signup.
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


         // *** IMPORTANT: Ensure your Supabase setup automatically creates a 'profiles' row on signup ***
         // This might involve a database trigger function listening to 'auth.users' insertions.
         // If not, you'd need to make another API call here to insert into 'profiles'.


        return $result;
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
     }
}

// If the script is accessed directly with a logout action (e.g., from header.php)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth = new Auth();
    $auth->logout();
}

?>