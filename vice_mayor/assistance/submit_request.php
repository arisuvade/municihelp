<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
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
        'user_id' => 'User ID',
        'family_relation_id' => 'Family relation'
    ];
    
    foreach ($required as $field => $fieldName) {
        if (empty($_POST[$field])) {
            throw new Exception("Please fill in: $fieldName");
        }
    }

    // Validate family relation
    $relationId = (int)$_POST['family_relation_id'];
    $relationCheck = $conn->query("SELECT id FROM family_relations WHERE id = $relationId");
    if ($relationCheck->num_rows === 0) {
        throw new Exception("Invalid family relation selected");
    }

    // Determine assistance_id and assistance_name
    $assistanceId = null;
    $assistanceName = null;
    
    if (isset($_POST['sub_program_id']) && $_POST['sub_program_id'] !== 'other') {
        $assistanceId = (int)$_POST['sub_program_id'];
    } elseif (isset($_POST['assistance_id'])) {
        $assistanceId = (int)$_POST['assistance_id'];
        
        if (isset($_POST['assistance_name']) && !empty($_POST['assistance_name'])) {
            $assistanceName = trim($_POST['assistance_name']);
        }
    } else {
        throw new Exception("Please fill in: Assistance type");
    }

    // Validate birthday format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['birthday'])) {
        throw new Exception("Invalid birthday format. Please use YYYY-MM-DD");
    }

    // Prepare variables for database
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

    // Prepare variables for database
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $birthday = $_POST['birthday'];
    $barangayId = (int)$_POST['barangay_id'];
    $precintNumber = isset($_POST['precint_number']) && trim($_POST['precint_number']) !== '' ? 
                    strtoupper(trim($_POST['precint_number'])) : null;
    $completeAddress = trim($_POST['complete_address']);
    $userId = (int)$_POST['user_id'];
    $relationId = (int)$_POST['family_relation_id'];
    $remarks = trim($_POST['remarks'] ?? '');

    // Validate file uploads - common required files
    $requiredFiles = [
        'specific_request' => ['number' => 1, 'name' => 'Specific request document'],
        'indigency_cert' => ['number' => 2, 'name' => 'Indigency certificate'],
        'id_copy' => ['number' => 3, 'name' => 'ID copy'],
        'request_letter' => ['number' => 4, 'name' => 'Request letter']
    ];

    // Add second ID copy requirement only if relation is not self (relation_id != 1)
    if ($relationId != 1) {
        $requiredFiles['id_copy_2'] = ['number' => 5, 'name' => 'Requester ID copy'];
    }
    
    foreach ($requiredFiles as $file => $fileInfo) {
        if (!isset($_FILES[$file]) || $_FILES[$file]['error'] != UPLOAD_ERR_OK) {
            throw new Exception("Please upload: {$fileInfo['name']}");
        }
        
        // Check if file is actually uploaded
        if (!is_uploaded_file($_FILES[$file]['tmp_name'])) {
            throw new Exception("Invalid file upload for {$fileInfo['name']}");
        }
        
        if ($_FILES[$file]['size'] > 20 * 1024 * 1024) {
            throw new Exception("File too large for {$fileInfo['name']} (max: 20MB)");
        }
        
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES[$file]['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception("Invalid file type for {$fileInfo['name']}. Only images and PDFs are allowed.");
        }
    }

    // Process file uploads
    $uploadDir = '../../uploads/vice_mayor/assistance/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // First insert the request to get the ID
    $stmt = $conn->prepare("INSERT INTO assistance_requests (
        user_id, assistance_id, assistance_name, remarks, first_name, middle_name, last_name, birthday,
        barangay_id, precint_number, complete_address, relation_id, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("iissssssissi", 
        $userId, $assistanceId, $assistanceName, $remarks, $firstName, $middleName, $lastName, $birthday,
        $barangayId, $precintNumber, $completeAddress, $relationId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Database save failed: " . $stmt->error);
    }

    $requestId = $conn->insert_id;
    $timestamp = date('YmdHis');
    $namePart = preg_replace('/[^a-zA-Z0-9]/', '', $firstName.$lastName);

    // Upload all files with exact requested format
    $filePaths = [];
    foreach ($requiredFiles as $file => $fileInfo) {
        $ext = pathinfo($_FILES[$file]['name'], PATHINFO_EXTENSION);
        $filename = sprintf("%d_%d_%d_%s_%s.%s",
            $requestId,
            $userId,
            $fileInfo['number'],
            $namePart,
            $timestamp,
            $ext);
        
        $destination = $uploadDir . $filename;
        
        if (!move_uploaded_file($_FILES[$file]['tmp_name'], $destination)) {
            throw new Exception("Failed to upload {$fileInfo['name']}");
        }
        
        $filePaths[$file.'_path'] = 'uploads/vice_mayor/assistance/' . $filename;
    }

    // Update with all file paths
    $updateSql = "UPDATE assistance_requests SET ";
    $params = [];
    $types = '';
    
    if ($relationId != 1) {
        $updateSql .= "specific_request_path=?, indigency_cert_path=?, id_copy_path=?, id_copy_path_2=?, request_letter_path=? ";
        $params = [
            $filePaths['specific_request_path'],
            $filePaths['indigency_cert_path'],
            $filePaths['id_copy_path'],
            $filePaths['id_copy_2_path'],
            $filePaths['request_letter_path']
        ];
        $types = 'sssss';
    } else {
        $updateSql .= "specific_request_path=?, indigency_cert_path=?, id_copy_path=?, request_letter_path=? ";
        $params = [
            $filePaths['specific_request_path'],
            $filePaths['indigency_cert_path'],
            $filePaths['id_copy_path'],
            $filePaths['request_letter_path']
        ];
        $types = 'ssss';
    }
    
    $updateSql .= "WHERE id=?";
    $params[] = $requestId;
    $types .= 'i';
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param($types, ...$params);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update file paths: " . $updateStmt->error);
    }

    $response['success'] = true;
    $response['message'] = 'Request successfully submitted!';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Clean up any uploaded files on error
    if (!empty($filePaths)) {
        foreach ($filePaths as $path) {
            @unlink('../../' . $path);
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>