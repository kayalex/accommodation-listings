<?php
session_start();
include_once __DIR__ . "/../config/config.php"; // Include Supabase config

class Auth {
    private $supabaseUrl;
    private $apiKey;

    public function __construct() {
        $this->supabaseUrl = SUPABASE_URL;
        $this->apiKey = SUPABASE_KEY;
    }

    // Function to register a user
    public function register($email, $password) {
        $data = json_encode([
            "email" => $email,
            "password" => $password
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
        return $result;
    }

    // Function to log in a user
    public function login($email, $password) {
        $data = json_encode([
            "email" => $email,
            "password" => $password
        ]);

        $ch = curl_init($this->supabaseUrl . "/auth/v1/token?grant_type=password");
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

        if (isset($result["access_token"])) {
            $_SESSION["user"] = $result;
            return true;
        } else {
            return $result["error"]["message"] ?? "Login failed";
        }
    }

    // Function to check if the user is logged in
    public function isAuthenticated() {
        return isset($_SESSION["user"]);
    }

    // Function to log out
    public function logout() {
        session_destroy();
        header("Location: login.php");
        exit();
    }
}
?>
