<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please login to submit an adoption request');
    }

    // Validate required fields
    $required = [
        'dog_id' => 'Dog ID',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'birthday' => 'Birthday',
        'barangay_id' => 'Barangay',
        'complete_address' => 'Complete address',
        'phone' => 'Contact number',
        'adoption_reason' => 'Reason for adoption'
    ];
    
    foreach ($required as $field => $fieldName) {
        if (empty($_POST[$field])) {
            throw new Exception("Please fill in: $fieldName");
        }
    }

    // Validate phone number format
    $phone = trim($_POST['phone']);
    if (!preg_match('/^\+63[0-9]{10}$/', $phone)) {
        throw new Exception("Invalid phone number format. Please use +63 followed by 10 digits.");
    }

    // Validate birthday format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['birthday'])) {
        throw new Exception("Invalid birthday format. Please use YYYY-MM-DD");
    }

    // Sanitize inputs
    $dogId = (int)$_POST['dog_id'];
    $userId = (int)$_SESSION['user_id'];
    $firstName = trim($_POST['first_name']);
    $middleName = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $lastName = trim($_POST['last_name']);
    $birthday = date('Y-m-d', strtotime($_POST['birthday']));
    $barangayId = (int)$_POST['barangay_id'];
    $address = trim($_POST['complete_address']);
    $adoption_reason = trim($_POST['adoption_reason']);
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Check if dog exists
    $dogCheck = $conn->query("SELECT id FROM dogs WHERE id = $dogId");
    if ($dogCheck->num_rows === 0) {
        throw new Exception('Dog not found');
    }

    // Check if user already has a pending/approved adoption for this dog
    $existingRequest = $conn->query("
        SELECT id FROM dog_adoptions 
        WHERE dog_id = $dogId AND user_id = $userId
        AND status IN ('pending', 'approved')
    ");

    if ($existingRequest->num_rows > 0) {
        throw new Exception('You already have an existing adoption request for this dog');
    }

    // Insert the adoption request
    $stmt = $conn->prepare("
    INSERT INTO dog_adoptions (
        dog_id, user_id, first_name, middle_name, last_name, birthday,
        barangay_id, complete_address, phone, adoption_reason, remarks, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param(
    'iissssissss',
    $dogId,
    $userId,
    $firstName,
    $middleName,
    $lastName,
    $birthday,
    $barangayId,
    $address,
    $phone,
    $adoption_reason,
    $remarks
);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit adoption request: ' . $stmt->error);
    }

    $response['success'] = true;
    $response['message'] = 'Adoption request submitted successfully! Our team will review your application.';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Adoption Error: ' . $e->getMessage());
}

echo json_encode($response);
?>