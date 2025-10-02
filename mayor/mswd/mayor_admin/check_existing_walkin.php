<?php
session_start();
require_once '../../../includes/db.php';

header('Content-Type: application/json');

$response = [
    'exists' => false,
    'message' => '',
    'error' => null,
    'status' => null,
    'days_left' => null,
    'is_sulong_dulong_beneficiary' => false
];

try {
    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'barangay_id', 'birthday', 'assistance_id'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $barangayId = (int)$_POST['barangay_id'];
    $birthday = trim($_POST['birthday']);
    $assistanceId = (int)$_POST['assistance_id'];

    // Validate birthday format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        throw new Exception("Invalid birthday format. Please use YYYY-MM-DD");
    }

    // Check if this is a Sulong Dulong request (IDs 33, 34, 35)
    $isSulongDulongRequest = in_array($assistanceId, [33, 34, 35]);

    // Check beneficiary limit for Sulong Dulong (max 800)
    if ($isSulongDulongRequest) {
        $countQuery = "SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries";
        $countResult = $conn->query($countQuery);
        $totalBeneficiaries = $countResult->fetch_assoc()['total'];

        if ($totalBeneficiaries >= 800) {
            $response['exists'] = true;
            $response['message'] = "We have reached the maximum number of Sulong Dulong beneficiaries (800). No more applications can be accepted at this time.";
            echo json_encode($response);
            exit;
        }
    }

    // Check for existing requests first (applies to ALL assistance types)
    $query = "
        SELECT id, status, created_at, updated_at, assistance_id 
        FROM mswd_requests 
        WHERE first_name = ? 
        AND last_name = ? 
        AND barangay_id = ?
        AND birthday = ?
        AND (
            status IN ('pending', 'mayor_approved', 'mswd_approved') OR 
            (status = 'completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))
        )
        ORDER BY 
            CASE 
                WHEN status IN ('pending', 'mayor_approved', 'mswd_approved') THEN 0
                WHEN status = 'completed' THEN 1
                ELSE 2
            END,
            created_at DESC
        LIMIT 1
    ";

    $params = [$firstName, $lastName, $barangayId, $birthday];
    $types = "ssis";

    // Include middle name if provided
    if (!empty($middleName)) {
        $query = str_replace(
            "WHERE first_name = ?", 
            "WHERE first_name = ? AND middle_name = ?", 
            $query
        );
        array_splice($params, 1, 0, $middleName);
        $types = "sssis";
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $status = $row['status'];
        $response['status'] = $status;
        
        if (in_array($status, ['pending', 'mayor_approved', 'mswd_approved'])) {
            $createdDate = new DateTime($row['created_at']);
            $currentDate = new DateTime();
            $interval = $currentDate->diff($createdDate);
            
            $response['exists'] = true;
            
            if ($status === 'pending') {
                $response['message'] = "This person has a pending request submitted " . $interval->days . " days ago.";
            } 
            elseif ($status === 'mayor_approved') {
                $response['message'] = "This person's request was approved by the mayor's office " . $interval->days . " days ago and is awaiting MSWD processing.";
            }
            elseif ($status === 'mswd_approved') {
                $response['message'] = "This person's request was fully approved by MSWD " . $interval->days . " days ago.";
            }
        } 
        elseif ($status === 'completed') {
            $completedDate = new DateTime($row['updated_at']);
            $currentDate = new DateTime();
            $interval = $currentDate->diff($completedDate);
            
            if ($interval->days < 90) {
                $daysLeft = 90 - $interval->days;
                $response['exists'] = true;
                $response['message'] = "This person can submit again after $daysLeft days from their last completed request.";
                $response['days_left'] = $daysLeft;
            }
        }
    }

    // For Sulong Dulong requests, check beneficiary status with semester logic
    if ($isSulongDulongRequest && !$response['exists']) {
        $beneficiaryQuery = "
            SELECT id, duration 
            FROM sulong_dulong_beneficiaries 
            WHERE first_name = ? 
            AND last_name = ? 
            AND barangay_id = ?
            AND birthday = ?
        ";

        $beneficiaryParams = [$firstName, $lastName, $barangayId, $birthday];
        $beneficiaryTypes = "ssis";

        // Include middle name if provided
        if (!empty($middleName)) {
            $beneficiaryQuery = str_replace(
                "WHERE first_name = ?", 
                "WHERE first_name = ? AND middle_name = ?", 
                $beneficiaryQuery
            );
            array_splice($beneficiaryParams, 1, 0, $middleName);
            $beneficiaryTypes = "sssis";
        }

        $stmt = $conn->prepare($beneficiaryQuery);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param($beneficiaryTypes, ...$beneficiaryParams);
        $stmt->execute();
        $beneficiaryResult = $stmt->get_result();

        if ($beneficiaryResult->num_rows > 0) {
            $beneficiary = $beneficiaryResult->fetch_assoc();
            
            // ID 33 - Monthly (always block if already registered)
            if ($assistanceId == 33) {
                $response['exists'] = true;
                $response['is_sulong_dulong_beneficiary'] = true;
                $response['message'] = "This person is already registered as a Sulong Dulong monthly beneficiary and cannot submit another request.";
            }
            // ID 34 - 1st Sem (block if current duration is Monthly or 1st Sem)
            elseif ($assistanceId == 34 && in_array($beneficiary['duration'], ['Every Month', '1st Sem'])) {
                $response['exists'] = true;
                $response['is_sulong_dulong_beneficiary'] = true;
                $response['message'] = "This person is already registered for ".$beneficiary['duration']." Sulong Dulong assistance.";
            }
            // ID 35 - 2nd Sem (block if current duration is Monthly or 2nd Sem)
            elseif ($assistanceId == 35 && in_array($beneficiary['duration'], ['Every Month', '2nd Sem'])) {
                $response['exists'] = true;
                $response['is_sulong_dulong_beneficiary'] = true;
                $response['message'] = "This person is already registered for ".$beneficiary['duration']." Sulong Dulong assistance.";
            }
            // Else allow (different semester or not yet enrolled for this type)
        }
    }

} catch (Exception $e) {
    error_log("Error in check_existing_walkin.php: " . $e->getMessage());
    $response['error'] = "System error: " . $e->getMessage();
    $response['exists'] = true;
    $response['message'] = "System error occurred. Please try again later.";
    http_response_code(500);
}

echo json_encode($response);
?>