<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$response = [
    'exists' => false,
    'message' => '',
    'error' => null,
    'status' => null,
    'days_left' => null,
    'is_sulong_dulong_beneficiary' => false,
    'is_blocked' => false
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

    // Check beneficiary limit for Sulong Dulong (max 800) - Only count ACTIVE beneficiaries
    if ($isSulongDulongRequest) {
        $countQuery = "SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries WHERE status = 'Active'";
        $countResult = $conn->query($countQuery);
        $totalBeneficiaries = $countResult->fetch_assoc()['total'];

        if ($totalBeneficiaries >= 800) {
            $response['exists'] = true;
            $response['message'] = "We have reached the maximum number of Sulong Dunong beneficiaries (800). No more applications can be accepted at this time.";
            echo json_encode($response);
            exit;
        }
    }

    // ========== CRITICAL CHANGE: CHECK BENEFICIARY STATUS FIRST ==========
    
    // For Sulong Dulong requests, check beneficiary status BEFORE checking pending requests
    if ($isSulongDulongRequest) {
        // FIRST: Check if person is BLOCKED (only for Sulong Dulong requests)
        $blockedCheckQuery = "
            SELECT id 
            FROM sulong_dulong_beneficiaries 
            WHERE first_name = ? 
            AND last_name = ? 
            AND barangay_id = ?
            AND birthday = ?
            AND status = 'Blocked'
        ";

        $blockedParams = [$firstName, $lastName, $barangayId, $birthday];
        $blockedTypes = "ssis";

        // Include middle name if provided
        if (!empty($middleName)) {
            $blockedCheckQuery = str_replace(
                "WHERE first_name = ?", 
                "WHERE first_name = ? AND middle_name = ?", 
                $blockedCheckQuery
            );
            array_splice($blockedParams, 1, 0, $middleName);
            $blockedTypes = "sssis";
        }

        $stmt = $conn->prepare($blockedCheckQuery);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param($blockedTypes, ...$blockedParams);
        $stmt->execute();
        $blockedResult = $stmt->get_result();

        if ($blockedResult->num_rows > 0) {
            $response['exists'] = true;
            $response['is_blocked'] = true;
            $response['message'] = "You are blocked from applying for Sulong Dunong assistance. Please contact the MSWD office for more information.";
            echo json_encode($response);
            exit;
        }

        // SECOND: Check if person is already an active beneficiary
        $beneficiaryQuery = "
            SELECT id, duration, status
            FROM sulong_dulong_beneficiaries 
            WHERE first_name = ? 
            AND last_name = ? 
            AND barangay_id = ?
            AND birthday = ?
            AND status = 'Active'
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
            
            // ID 33 - Monthly (cannot apply for any Sulong Dulong program)
            if ($beneficiary['duration'] === 'Every Month') {
                $response['exists'] = true;
                $response['is_sulong_dulong_beneficiary'] = true;
                $response['message'] = "You are already registered as a Monthly Sulong Dunong beneficiary and cannot apply for any other Sulong Dunong program. If you wish to renew it, please proceed to the MSWD Department and bring the necessary requirements.";
                echo json_encode($response);
                exit;
            }
            // ID 34 - Per Sem (cannot apply for any Sulong Dulong program)
            elseif ($beneficiary['duration'] === 'Per Sem') {
                $response['exists'] = true;
                $response['is_sulong_dulong_beneficiary'] = true;
                $response['message'] = "You are already registered for Per Semester Sulong Dunong assistance and cannot apply for any other Sulong Dunong program. If you wish to renew it, please proceed to the MSWD Department and bring the necessary requirements.";
                echo json_encode($response);
                exit;
            }
        }
    }

    // ========== ONLY AFTER checking beneficiary status, check for pending requests ==========
    
    // Check for existing requests (applies to ALL assistance types)
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
                $response['message'] = "You have a pending request submitted " . $interval->days . " days ago. Please wait for processing.";
            } 
            elseif ($status === 'mayor_approved') {
                $response['message'] = "Your request was approved by the mayor's office " . $interval->days . " days ago and is awaiting MSWD processing.";
            }
            elseif ($status === 'mswd_approved') {
                $response['message'] = "Your request was fully approved by MSWD " . $interval->days . " days ago.";
            }
        } 
        elseif ($status === 'completed') {
            $completedDate = new DateTime($row['updated_at']);
            $currentDate = new DateTime();
            $interval = $currentDate->diff($completedDate);
            
            if ($interval->days < 90) {
                $daysLeft = 90 - $interval->days;
                $response['exists'] = true;
                $response['message'] = "You can submit again after $daysLeft days from your last completed request.";
                $response['days_left'] = $daysLeft;
            }
        }
    }

} catch (Exception $e) {
    error_log("Error in check_existing_request.php: " . $e->getMessage());
    $response['error'] = "System error: " . $e->getMessage();
    $response['exists'] = true;
    $response['message'] = "System error occurred. Please try again later.";
    http_response_code(500);
}

echo json_encode($response);
?>