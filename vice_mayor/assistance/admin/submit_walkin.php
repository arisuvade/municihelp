<?php
session_start();
require_once '../../../includes/db.php';

// Check if admin is logged in
if (!isset($_SESSION['assistance_admin_id'])) {
    header('Location: ../../../includes/auth/login.php');
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
        'family_relation_id' => 'Family relation',
        'assistance_id' => 'Assistance program'
    ];
    
    foreach ($required as $field => $fieldName) {
        if (empty($_POST[$field])) {
            throw new Exception("Please fill in: $fieldName");
        }
    }

    // Validate family relation exists
    $relationId = (int)$_POST['family_relation_id'];
    $relationCheck = $conn->query("SELECT id FROM family_relations WHERE id = $relationId");
    if ($relationCheck->num_rows === 0) {
        throw new Exception("Invalid family relation selected");
    }

    // Validate barangay exists
    $barangayId = (int)$_POST['barangay_id'];
    $barangayCheck = $conn->query("SELECT id FROM barangays WHERE id = $barangayId");
    if ($barangayCheck->num_rows === 0) {
        throw new Exception("Invalid barangay selected");
    }

    // Validate assistance type exists
    $assistanceId = (int)$_POST['assistance_id'];
    $assistanceCheck = $conn->query("SELECT id FROM assistance_types WHERE id = $assistanceId");
    if ($assistanceCheck->num_rows === 0) {
        throw new Exception("Invalid assistance type selected");
    }

    // Determine assistance_name based on selection
    $assistanceName = null;
    $assistanceIdToUse = $assistanceId; // Default to main program ID
    
    if (isset($_POST['sub_program_id']) && !empty($_POST['sub_program_id'])) {
        if ($_POST['sub_program_id'] === 'other' && !empty($_POST['assistance_name'])) {
            // For "Others" option, use the custom name and keep the main program ID
            $assistanceName = trim($_POST['assistance_name']);
        } else {
            // For regular sub-programs, use the sub-program's ID and name
            $subProgramId = (int)$_POST['sub_program_id'];
            $subProgramCheck = $conn->query("SELECT id, name FROM assistance_types WHERE id = $subProgramId");
            
            if ($subProgramCheck->num_rows > 0) {
                $subProgram = $subProgramCheck->fetch_assoc();
                $assistanceIdToUse = $subProgram['id']; // Use the sub-program's ID
                $assistanceName = $subProgram['name'];
            }
        }
    }
    // Validate birthday format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['birthday'])) {
        throw new Exception("Invalid birthday format. Please use YYYY-MM-DD");
    }

    $birthday = date('Y-m-d', strtotime($_POST['birthday']));
    if ($birthday === false) {
        throw new Exception("Invalid birthday date");
    }

    $currentDate = new DateTime();
    $birthdayDate = new DateTime($birthday);
    $minDate = new DateTime('1900-01-01');

    if ($birthdayDate > $currentDate) {
        throw new Exception("Birthday cannot be in the future");
    }

    if ($birthdayDate < $minDate) {
        throw new Exception("Birthday is too far in the past");
    }

    // Prepare variables
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $precintNumber = isset($_POST['precint_number']) && trim($_POST['precint_number']) !== '' ? 
                     strtoupper(trim($_POST['precint_number'])) : null;
    $completeAddress = trim($_POST['complete_address']);
    $walkinAdminId = (int)$_SESSION['assistance_admin_id'];

    // Insert the walk-in request with the correct assistance_id (either main or sub-program)
    $stmt = $conn->prepare("INSERT INTO assistance_requests (
        user_id, barangay_id, first_name, middle_name, last_name, birthday,
        assistance_id, assistance_name, complete_address, relation_id, 
        precint_number, walkin_admin_id, is_walkin, status, created_at
    ) VALUES (
        NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved', NOW()
    )");

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("issssissssi", 
        $barangayId, $firstName, $middleName, $lastName, $birthday,
        $assistanceIdToUse, $assistanceName, $completeAddress, $relationId,
        $precintNumber, $walkinAdminId
    );

    if (!$stmt->execute()) {
        throw new Exception("Database save failed: " . $stmt->error);
    }

    $response['success'] = true;
    $response['message'] = 'Walk-in request successfully submitted!';
    $response['request_id'] = $conn->insert_id;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>