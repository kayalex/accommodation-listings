<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

class Reports {
    private $supabaseUrl;
    private $headers;

    public function __construct() {
        $this->supabaseUrl = SUPABASE_URL;
        
        // Get the user's access token from the session
        $accessToken = isset($_SESSION['access_token']) ? $_SESSION['access_token'] : SUPABASE_KEY;
        
        $this->headers = [
            "Content-Type: application/json",
            "apikey: " . SUPABASE_KEY,
            "Authorization: Bearer " . $accessToken  // Use user's token for RLS
        ];
    }

    public function submitReport($listingId, $landlordId, $reportedBy, $reason) {
        try {
            // Validate inputs
            if (!$listingId || !$landlordId || !$reportedBy || !$reason) {
                throw new Exception("Missing required fields for report submission");
            }

            $data = json_encode([
                'listing_id' => $listingId,
                'landlord_id' => $landlordId,
                'reported_by' => $reportedBy,
                'reason' => $reason,
                'status' => 'pending', // pending, reviewed, resolved
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (!$data) {
                throw new Exception("JSON encoding failed: " . json_last_error_msg());
            }

            $endpoint = $this->supabaseUrl . "/rest/v1/reports";
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $this->headers,
                CURLOPT_POSTFIELDS => $data
            ]);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Return debug info for troubleshooting
            $debug = [
                'statusCode' => $statusCode,
                'response' => $response
            ];

            if ($statusCode === 200 || $statusCode === 201) {
                return [
                    'success' => true,
                    'message' => 'Report submitted successfully',
                    'debug' => $debug
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Supabase error: ' . $response,
                    'debug' => $debug
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => [ 'exception' => $e->getMessage() ]
            ];
        }
    }

    public function getReports($status = null) {
        $endpoint = $this->supabaseUrl . "/rest/v1/reports?select=*";
        
        if ($status) {
            $endpoint .= "&status=eq." . urlencode($status);
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }

    /**
     * Update the status of a report (e.g., to 'reviewed' or 'resolved')
     * @param int $reportId
     * @param string $newStatus ('reviewed' or 'resolved')
     * @return array [success, message, debug]
     */
    public function updateReportStatus($reportId, $newStatus) {
        try {
            if (!$reportId || !$newStatus) {
                throw new Exception("Missing report ID or new status");
            }
            $endpoint = $this->supabaseUrl . "/rest/v1/reports?id=eq." . urlencode($reportId);
            $data = json_encode(['status' => $newStatus]);
            if (!$data) {
                throw new Exception("JSON encoding failed: " . json_last_error_msg());
            }
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $debug = [
                'statusCode' => $statusCode,
                'response' => $response
            ];
            if ($statusCode === 204) {
                return [
                    'success' => true,
                    'message' => 'Report status updated successfully',
                    'debug' => $debug
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Supabase error: ' . $response,
                    'debug' => $debug
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => [ 'exception' => $e->getMessage() ]
            ];
        }
    }
}
?>
