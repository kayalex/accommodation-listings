<?php
// Force output buffering and disable all error display to browser
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/reports.php';
require_once __DIR__ . '/../api/auth.php';

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    if (!isset($_POST['report_listing'])) {
        throw new Exception('Missing required parameters');
    }
    $auth = new Auth();
    $reports = new Reports();
    if (!$auth->isAuthenticated()) {
        throw new Exception('Please login to report a listing');
    }
    $required_fields = ['listing_id', 'landlord_id', 'reason'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    $result = $reports->submitReport(
        $_POST['listing_id'],
        $_POST['landlord_id'],
        $auth->getUserId(),
        $_POST['reason']
    );
    // Add debug info to response for troubleshooting
    if (isset($result['debug'])) {
        $response['debug'] = $result['debug'];
    }
    if (isset($result['success']) && $result['success']) {
        $response['success'] = true;
        $response['message'] = 'Report submitted successfully';
    } else {
        $response['success'] = false;
        $response['message'] = $result['message'] ?? 'Failed to submit report';
    }
} catch (Exception $e) {
    error_log("Report submission error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['error'] = true;
}

// Clean all output buffers before sending JSON
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit();
