<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please login to submit a claim request');
    }

    // Validate required fields
    $required = [
        'dog_id' => 'Dog ID',
        'first_name' => 'First name', 
        'last_name' => 'Last name',
        'birthday' => 'Birthday',
        'barangay_id' => 'Barangay',
        'complete_address' => 'Complete address',
        'phone' => 'Contact number'
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
    $dogName = isset($_POST['name_of_dog']) ? trim($_POST['name_of_dog']) : '';
    $dogAge = isset($_POST['age_of_dog']) ? (int)$_POST['age_of_dog'] : null;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Check if dog exists and is available for claiming
    $dogCheck = $conn->query("SELECT id, status FROM dogs WHERE id = $dogId");
    if ($dogCheck->num_rows === 0) {
        throw new Exception('Dog not found');
    }
    
    $dog = $dogCheck->fetch_assoc();
    if ($dog['status'] !== 'for_claiming') {
        throw new Exception('Dog is not available for claiming');
    }

    // Check if user already has a pending/approved claim for this dog
    $existingClaim = $conn->query("
        SELECT id FROM dog_claims 
        WHERE dog_id = $dogId AND user_id = $userId
        AND status IN ('pending', 'approved')
    ");
    
    if ($existingClaim->num_rows > 0) {
        throw new Exception('You already have an existing claim request for this dog');
    }

    // Insert the claim
    $stmt = $conn->prepare("
    INSERT INTO dog_claims (
        dog_id, user_id, first_name, middle_name, last_name, birthday,
        barangay_id, complete_address, phone, name_of_dog, age_of_dog, remarks, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param(
    'iissssisssis',
    $dogId,
    $userId,
    $firstName,
    $middleName,
    $lastName,
    $birthday,
    $barangayId,
    $address,
    $phone,
    $dogName,
    $dogAge,
    $remarks
);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit claim: ' . $stmt->error);
    }

    $response['success'] = true;
    $response['message'] = 'Claim request submitted successfully! Our team will review your application.';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>