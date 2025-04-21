<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    // validate required fields
    $required = [
        'assistance_id' => 'Uri ng tulong',
        'first_name' => 'Pangalan',
        'last_name' => 'Apelyido', 
        'barangay_id' => 'Barangay',
        'complete_address' => 'Kompletong address',
        'user_id' => 'User ID'
    ];
    
    foreach ($required as $field => $fieldName) {
        if (empty($_POST[$field])) {
            throw new Exception("Pakilagyan ng laman ang: $fieldName");
        }
    }

    // validate file uploads
    $requiredFiles = [
        'specific_request' => ['number' => 1, 'name' => 'Espesipikong kahilingan'],
        'indigency_cert' => ['number' => 2, 'name' => 'Indigency certificate'],
        'id_copy' => ['number' => 3, 'name' => 'Kopya ng ID'],
        'request_letter' => ['number' => 4, 'name' => 'Liham ng kahilingan']
    ];
    
    $uploads = [];
    foreach ($requiredFiles as $file => $fileInfo) {
        if (!isset($_FILES[$file]) || $_FILES[$file]['error'] != UPLOAD_ERR_OK) {
            throw new Exception("Kailangan i-upload ang: {$fileInfo['name']}");
        }
        
        if ($_FILES[$file]['size'] > 5 * 1024 * 1024) {
            throw new Exception("Masyadong malaki ang {$fileInfo['name']} (max: 5MB)");
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($_FILES[$file]['type'], $allowedTypes)) {
            throw new Exception("JPEG, PNG, o PDF lang ang pwede sa {$fileInfo['name']}");
        }
    }

    // prepare variables
    $assistanceId = (int)$_POST['assistance_id'];
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $barangayId = (int)$_POST['barangay_id'];
    $completeAddress = trim($_POST['complete_address']);
    $userId = (int)$_POST['user_id'];

    // check for existing request for this person
    if ($assistanceId != 3) { // skip check for burial assistance
        $query = "SELECT id FROM assistance_requests 
                 WHERE first_name = ? AND last_name = ? AND barangay_id = ? AND assistance_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                 AND status NOT IN ('declined', 'cancelled')";
        
        $params = [$firstName, $lastName, $barangayId, $assistanceId];
        $types = "ssii";

        if (!empty($middleName)) {
            $query = str_replace("WHERE first_name = ?", "WHERE first_name = ? AND middle_name = ?", $query);
            array_splice($params, 1, 0, $middleName);
            $types = "sssii";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Mayroon nang pending na kahilingan ang taong ito para sa ganitong uri ng tulong sa nakaraang buwan.");
        }
    }

    // process file uploads
    $uploadDir = '../uploads/vm_rj_assistance/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // get assistance and brgy names
    $assistanceType = $conn->query("SELECT name FROM assistance_types WHERE id = $assistanceId")->fetch_assoc();
    $barangay = $conn->query("SELECT name FROM barangays WHERE id = $barangayId")->fetch_assoc();

    if (!$assistanceType || !$barangay) {
        throw new Exception("Hindi wastong uri ng tulong o barangay ang napili");
    }

    // upload files with proper naming
    $requestId = time();
    $assistanceName = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' Request', '', $assistanceType['name']));
    $namePart = preg_replace('/[^a-zA-Z0-9]/', '', $lastName . $firstName);
    $barangayName = preg_replace('/[^a-zA-Z0-9]/', '', $barangay['name']);
    $dateTimePart = date('YmdHis');

    foreach ($requiredFiles as $file => $fileInfo) {
        $ext = pathinfo($_FILES[$file]['name'], PATHINFO_EXTENSION);
        $filename = sprintf("%d_%d_%d_%s_%s_%s_%s.%s",
            $requestId, $userId, $fileInfo['number'], $assistanceName, 
            $namePart, $barangayName, $dateTimePart, $ext);
        
        $destination = $uploadDir . $filename;
        
        if (!move_uploaded_file($_FILES[$file]['tmp_name'], $destination)) {
            throw new Exception("Hindi ma-upload ang {$fileInfo['name']}");
        }
        
        $uploads[$file . '_path'] = 'uploads/vm_rj_assistance/' . $filename;
    }

    // insert into database
    $stmt = $conn->prepare("INSERT INTO assistance_requests (
        user_id, assistance_id, first_name, middle_name, last_name,
        barangay_id, complete_address,
        specific_request_path, indigency_cert_path, id_copy_path, request_letter_path,
        status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

    if (!$stmt) {
        throw new Exception("Error sa database: " . $conn->error);
    }

    $stmt->bind_param("iisssssssss", 
        $userId, $assistanceId, $firstName, $middleName, $lastName,
        $barangayId, $completeAddress,
        $uploads['specific_request_path'], $uploads['indigency_cert_path'],
        $uploads['id_copy_path'], $uploads['request_letter_path']
    );

    if ($stmt->execute()) {
        // update filenames with actual request id
        $newRequestId = $conn->insert_id;
        foreach ($requiredFiles as $file => $fileInfo) {
            $oldPath = '../' . $uploads[$file . '_path'];
            $newFilename = str_replace($requestId, $newRequestId, basename($oldPath));
            $newPath = $uploadDir . $newFilename;
            
            if (rename($oldPath, $newPath)) {
                $newDbPath = 'uploads/vm_rj_assistance/' . $newFilename;
                $conn->query("UPDATE assistance_requests SET {$file}_path = '$newDbPath' WHERE id = $newRequestId");
            }
        }
        
        $response['success'] = true;
        $response['message'] = 'Matagumpay na naisumite ang kahilingan!';
    } else {
        throw new Exception("Hindi masave sa database: " . $stmt->error);
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // clean up uploaded files if error occurred
    if (!empty($uploads)) {
        foreach ($uploads as $path) {
            @unlink('../' . $path);
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>