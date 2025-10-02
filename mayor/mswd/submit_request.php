<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
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
    
    // Check if "Others" (id=16) is selected and has assistance_name
    if ($assistanceId == 16) {
        if (empty($_POST['assistance_name'])) {
            throw new Exception("Please specify the assistance you need");
        }
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

    // Validate assistance type and check parent ID
    $assistanceCheck = $conn->query("
        SELECT id, is_online, parent_id 
        FROM mswd_types 
        WHERE id = $assistanceId
    ");
    if ($assistanceCheck->num_rows === 0) {
        throw new Exception("Invalid assistance type selected");
    }
    $assistanceData = $assistanceCheck->fetch_assoc();
    $isOnline = $assistanceData['is_online'];
    $parentId = $assistanceData['parent_id'];

    // Determine initial status
    $initialStatus = 'pending'; // Default status
    if ($parentId == 13) {
        $initialStatus = 'mayor_approved'; // Bypass mayor's office for these requests
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
    $barangayId = (int)$_POST['barangay_id'];
    $completeAddress = trim($_POST['complete_address']);
    $userId = (int)$_SESSION['user_id'];
    $remarks = trim($_POST['remarks'] ?? '');

    // Only proceed with file uploads if it's an online program
    if ($isOnline == 1) {
        // Validate file uploads
        if (empty($_FILES['requirements'])) {
            throw new Exception("No requirement files uploaded");
        }

        // Get requirements for this assistance type
        $requirements = $conn->query("SELECT id, name FROM mswd_types_requirements WHERE mswd_types_id = $assistanceId");
        if ($requirements->num_rows === 0) {
            throw new Exception("No requirements found for this assistance type");
        }

        // Check all required files are present
        $requiredFiles = [];
        while ($req = $requirements->fetch_assoc()) {
            $reqId = $req['id'];
            if (!isset($_FILES['requirements']['name'][$reqId])) {
                throw new Exception("Missing required file: " . $req['name']);
            }
            if ($_FILES['requirements']['error'][$reqId] != UPLOAD_ERR_OK) {
                throw new Exception("Error uploading file for: " . $req['name']);
            }
            if ($_FILES['requirements']['size'][$reqId] > 5 * 1024 * 1024) {
                throw new Exception("File too large for: " . $req['name'] . " (max: 5MB)");
            }
            $requiredFiles[$reqId] = $_FILES['requirements']['tmp_name'][$reqId];
        }
    }

    // Process database insertion
    $stmt = $conn->prepare("INSERT INTO mswd_requests (
    user_id, barangay_id, first_name, middle_name, last_name, birthday,
    complete_address, assistance_id, assistance_name, remarks, status, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("iisssssisss", 
    $userId, $barangayId, $firstName, $middleName, $lastName, $birthday,
    $completeAddress, $assistanceId, $assistanceName, $remarks, $initialStatus
);

    if (!$stmt->execute()) {
        throw new Exception("Database save failed: " . $stmt->error);
    }

    $requestId = $conn->insert_id;

    // Only process file uploads if it's an online program
    if ($isOnline == 1) {
        // Process file uploads
        $uploadDir = '../../uploads/mayor/mswd/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $timestamp = date('YmdHis');
        $namePart = preg_replace('/[^a-zA-Z0-9]/', '', $firstName.$lastName);

        // Upload all requirement files
        $filePaths = [];
        $updateFields = [];
        $updateValues = [];
        $i = 1;
        
        foreach ($requiredFiles as $reqId => $tmpName) {
            $ext = pathinfo($_FILES['requirements']['name'][$reqId], PATHINFO_EXTENSION);
            $filename = sprintf("%d_%d_%d_%s_%s.%s",
                $requestId,
                $userId,
                $reqId,
                $namePart,
                $timestamp,
                $ext);
            
            $destination = $uploadDir . $filename;
            
            if (!move_uploaded_file($tmpName, $destination)) {
                throw new Exception("Failed to upload requirement file");
            }
            
            $filePaths[] = 'uploads/mayor/mswd/' . $filename;
            $updateFields[] = "requirement_path_$i";
            $updateValues[] = 'uploads/mayor/mswd/' . $filename;
            $i++;
            
            // We only have 8 requirement_path columns in the table
            if ($i > 8) break;
        }

        // Update with all file paths
        if (!empty($updateFields)) {
            $updateSql = "UPDATE mswd_requests SET ";
            $setParts = [];
            for ($j = 0; $j < count($updateFields); $j++) {
                $setParts[] = $updateFields[$j] . " = ?";
            }
            $updateSql .= implode(", ", $setParts) . " WHERE id = ?";
            
            $updateValues[] = $requestId;
            
            $updateStmt = $conn->prepare($updateSql);
            $types = str_repeat('s', count($updateFields)) . 'i';
            $updateStmt->bind_param($types, ...$updateValues);
            
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update file paths: " . $updateStmt->error);
            }
        }
    }

    $response['success'] = true;
    $response['message'] = 'Request successfully submitted!';
    if ($parentId == 13) {
        $response['message'] .= ' (Automatically approved by mayor\'s office)';
    }

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