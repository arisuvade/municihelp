<?php
session_start();
require_once '../../../includes/db.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['mayor_admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    // Validate required fields
    $required = [
        'first_name' => 'First name',
        'last_name' => 'Last name', 
        'birthday' => 'Birthday',
        'barangay_id' => 'Barangay',
        'complete_address' => 'Complete address',
        'contact_no' => 'Contact number',
        'assistance_id' => 'Assistance program'
    ];
    
    foreach ($required as $field => $fieldName) {
        if (empty($_POST[$field])) {
            throw new Exception("Please fill in: $fieldName");
        }
    }

    // Determine assistance_id and assistance_name
    $assistanceId = (int)$_POST['assistance_id'];
    $assistanceName = null;
    
    // Check if "Others" is selected and has assistance_name
    if (isset($_POST['assistance_name']) && !empty($_POST['assistance_name'])) {
        $assistanceName = trim($_POST['assistance_name']);
    }

    // Check for sub-program if parent has children
    $subProgramId = null;
    $hasChildren = $conn->query("SELECT COUNT(*) as count FROM mswd_types WHERE parent_id = $assistanceId")->fetch_assoc()['count'];
    if ($hasChildren > 0) {
        if (empty($_POST['sub_program_id'])) {
            throw new Exception("Please select a sub-program");
        }
        $subProgramId = (int)$_POST['sub_program_id'];
        $subProgramCheck = $conn->query("SELECT id FROM mswd_types WHERE id = $subProgramId AND parent_id = $assistanceId");
        if ($subProgramCheck->num_rows === 0) {
            throw new Exception("Invalid sub-program selected");
        }
        // Use sub-program as the actual assistance
        $assistanceId = $subProgramId;
    }

    // Validate assistance type
    $assistanceCheck = $conn->query("
        SELECT id, is_online, parent_id 
        FROM mswd_types 
        WHERE id = $assistanceId
    ");
    if ($assistanceCheck->num_rows === 0) {
        throw new Exception("Invalid assistance type selected");
    }

    // Validate birthday format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['birthday'])) {
        throw new Exception("Invalid birthday format. Please use YYYY-MM-DD");
    }

    $birthday = date('Y-m-d', strtotime($_POST['birthday']));
    if ($birthday === false) {
        throw new Exception("Invalid birthday date");
    }

    // Prepare variables
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $barangayId = (int)$_POST['barangay_id'];
    $completeAddress = trim($_POST['complete_address']);
    $contactNo = trim($_POST['contact_no']);
    $mayorAdminId = (int)$_SESSION['mayor_admin_id'];

    // Insert the walk-in request with valid status
    $stmt = $conn->prepare("INSERT INTO mswd_requests (
        user_id, barangay_id, first_name, middle_name, last_name, birthday,
        complete_address, contact_no, assistance_id, assistance_name, status, 
        is_walkin, walkin_admin_id, created_at
    ) VALUES (
        NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'mayor_approved', 1, ?, NOW()
    )");

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("issssssisi", 
        $barangayId, $firstName, $middleName, $lastName, $birthday,
        $completeAddress, $contactNo, $assistanceId, $assistanceName, $mayorAdminId
    );

    if (!$stmt->execute()) {
        throw new Exception("Database save failed: " . $stmt->error);
    }

    $requestId = $conn->insert_id;

    $response['success'] = true;
    $response['message'] = 'Walk-in request successfully submitted!';
    $response['request_id'] = $requestId;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>