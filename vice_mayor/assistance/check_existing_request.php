<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$response = [
    'exists' => false,
    'message' => '',
    'error' => null
];

try {
    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'barangay_id', 'assistance_id', 'birthday'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $barangayId = (int)$_POST['barangay_id'];
    $assistanceId = (int)$_POST['assistance_id'];
    $birthday = trim($_POST['birthday']);

    // Debug: Log the received data
    error_log("Check existing request - First: $firstName, Last: $lastName, Birthday: $birthday, Barangay: $barangayId");

    // Validate birthday format - accept both YYYY-MM-DD and other formats
    $birthdayDate = DateTime::createFromFormat('Y-m-d', $birthday);
    if (!$birthdayDate) {
        // Try other common formats if YYYY-MM-DD fails
        $birthdayDate = DateTime::createFromFormat('m/d/Y', $birthday);
        if (!$birthdayDate) {
            $birthdayDate = DateTime::createFromFormat('d/m/Y', $birthday);
        }
    }

    if (!$birthdayDate) {
        throw new Exception("Invalid birthday format: $birthday");
    }

    $formattedBirthday = $birthdayDate->format('Y-m-d');

    // Check for existing requests for this person
    $query = "
        SELECT status, updated_at, created_at
        FROM assistance_requests 
        WHERE first_name = ? 
        AND last_name = ? 
        AND barangay_id = ?
        AND birthday = ?
        AND status NOT IN ('declined', 'cancelled')
        ORDER BY created_at DESC
        LIMIT 1
    ";

    $params = [$firstName, $lastName, $barangayId, $formattedBirthday];
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
        throw new Exception("Database prepare error: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $status = $row['status'];
        
        if ($status === 'pending' || $status === 'approved') {
            $response['exists'] = true;
            $response['message'] = "You already have a $status request. Please wait until it's processed.";
        } elseif ($status === 'completed') {
            // Check if 30 days have passed since completion
            $completedDate = new DateTime($row['updated_at'] ?? $row['created_at']);
            $currentDate = new DateTime();
            $interval = $currentDate->diff($completedDate);
            
            if ($interval->days < 30) {
                $daysLeft = 30 - $interval->days;
                $response['exists'] = true;
                $response['message'] = "You can submit again after $daysLeft days from your last completed request.";
            }
        }
    }

} catch (Exception $e) {
    error_log("Error in check_existing_request.php: " . $e->getMessage());
    $response['error'] = "System error: " . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>