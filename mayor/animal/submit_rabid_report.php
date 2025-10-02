<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Verify required fields
    $required = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'birthday' => 'Birthday',
        'barangay_id' => 'Barangay',
        'complete_address' => 'Complete address',
        'location' => 'Location',
        'date' => 'Date',
        'time' => 'Time',
        'description' => 'Description'
    ];
    
    foreach ($required as $field => $name) {
        if (empty($_POST[$field])) {
            throw new Exception("$name is required");
        }
    }

    // Verify file upload
    if (empty($_FILES['proof']['name'])) {
        throw new Exception('Photo evidence is required');
    }

    // Validate birthday format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['birthday'])) {
        throw new Exception("Invalid birthday format. Please use YYYY-MM-DD");
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'])) {
        throw new Exception('Invalid date format');
    }

    // Sanitize inputs
    $firstName = trim($_POST['first_name']);
    $middleName = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
    $lastName = trim($_POST['last_name']);
    $birthday = date('Y-m-d', strtotime($_POST['birthday']));
    $barangayId = (int)$_POST['barangay_id'];
    $address = trim($_POST['complete_address']);
    $location = trim($_POST['location']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $description = trim($_POST['description']);
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;

    // Handle file upload
    $uploadDir = '../../uploads/mayor/animal/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileExt = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
    $timestamp = date('YmdHis');
    $fileName = 'rabid_report_' . $timestamp . '.' . $fileExt;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload photo');
    }

    // Store relative path for database
    $dbFilePath = 'uploads/mayor/animal/' . $fileName;

    // Insert into database
    $stmt = $conn->prepare("
        INSERT INTO rabid_reports (
            user_id, first_name, middle_name, last_name, birthday,
            barangay_id, complete_address, location, date, time, description, 
            proof_path, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    if (!$stmt) {
        @unlink($filePath); // Clean up file if prepare fails
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param(
        'issssissssss',
        $userId,
        $firstName,
        $middleName,
        $lastName,
        $birthday,
        $barangayId,
        $address,
        $location,
        $date,
        $time,
        $description,
        $dbFilePath
    );

    if (!$stmt->execute()) {
        @unlink($filePath);
        throw new Exception('Failed to save report: ' . $stmt->error);
    }

    $response['success'] = true;
    $response['message'] = 'Rabid dog report submitted successfully!';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Rabid Report Error: ' . $e->getMessage());
}

echo json_encode($response);
?>