<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// initialize response
$response = [
    'duplicate' => false,
    'message' => '',
    'error' => null
];

try {
    // validate required fields
    $requiredFields = ['first_name', 'last_name', 'barangay_id', 'assistance_id'];
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

    // skip duplicate check for burial assistance
    if ($assistanceId == 3) {
        echo json_encode($response);
        exit;
    }

    // check for existing request for THIS PERSON
    $query = "
        SELECT status 
        FROM assistance_requests 
        WHERE first_name = ? 
        AND last_name = ? 
        AND barangay_id = ? 
        AND assistance_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        AND status NOT IN ('declined', 'cancelled')
    ";

    $params = [$firstName, $lastName, $barangayId, $assistanceId];
    $types = "ssii";

    // include middle name if provided
    if (!empty($middleName)) {
        $query = str_replace(
            "WHERE first_name = ?", 
            "WHERE first_name = ? AND middle_name = ?", 
            $query
        );
        array_splice($params, 1, 0, $middleName);
        $types = "sssii";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['duplicate'] = true;
        $response['message'] = "Error: {$firstName} {$lastName} already has a {$row['status']} request for this assistance type this month";
    }

} catch (Exception $e) {
    error_log("Error in check_existing_request.php: " . $e->getMessage());
    $response['error'] = "System error: " . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>