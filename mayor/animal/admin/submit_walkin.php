<?php
session_start();
require_once '../../../includes/db.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['animal_admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data with error checking
$isClaiming = isset($_POST['is_claiming']) ? ($_POST['is_claiming'] === '1') : false;
$dogId = isset($_POST['dog_id']) ? (int)$_POST['dog_id'] : 0;
$adminId = (int)$_SESSION['animal_admin_id'];

// Validate required fields
$requiredFields = [
    'first_name' => isset($_POST['first_name']) ? trim($_POST['first_name']) : '',
    'last_name' => isset($_POST['last_name']) ? trim($_POST['last_name']) : '',
    'complete_address' => isset($_POST['complete_address']) ? trim($_POST['complete_address']) : ''
];

if ($isClaiming) {
    // For claims, dog name and age are optional
    $nameOfDog = isset($_POST['name_of_dog']) ? trim($_POST['name_of_dog']) : null;
    $ageOfDog = isset($_POST['age_of_dog']) ? (int)$_POST['age_of_dog'] : null;
} else {
    // For adoptions, reason is required
    $requiredFields['adoption_reason'] = isset($_POST['adoption_reason']) ? trim($_POST['adoption_reason']) : '';
}

// Check for empty required fields
foreach ($requiredFields as $field => $value) {
    if (empty($value)) {
        echo json_encode(['success' => false, 'message' => "Please fill in all required fields"]);
        exit;
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Check dog exists and get current status
    $dogQuery = $conn->prepare("SELECT status FROM dogs WHERE id = ? FOR UPDATE");
    $dogQuery->bind_param("i", $dogId);
    $dogQuery->execute();
    $dogResult = $dogQuery->get_result();
    
    if ($dogResult->num_rows === 0) {
        throw new Exception("Dog not found");
    }

    $dog = $dogResult->fetch_assoc();
    $validClaimStatus = ($dog['status'] === 'for_claiming');
    $validAdoptStatus = ($dog['status'] === 'for_adoption');

    if (($isClaiming && !$validClaimStatus) || (!$isClaiming && !$validAdoptStatus)) {
        throw new Exception("Dog is not available for this action");
    }

    // Insert into appropriate table
    if ($isClaiming) {
        // Insert claim
        $stmt = $conn->prepare("
            INSERT INTO dog_claims (
                dog_id, 
                first_name, 
                middle_name, 
                last_name, 
                complete_address, 
                name_of_dog, 
                age_of_dog, 
                status, 
                approvedby_admin_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
        ");
        
        $middleName = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $stmt->bind_param(
            "isssssii",
            $dogId,
            $requiredFields['first_name'],
            $middleName,
            $requiredFields['last_name'],
            $requiredFields['complete_address'],
            $nameOfDog,
            $ageOfDog,
            $adminId
        );
    } else {
        // Insert adoption
        $stmt = $conn->prepare("
            INSERT INTO dog_adoptions (
                dog_id, 
                first_name, 
                middle_name, 
                last_name, 
                complete_address, 
                adoption_reason, 
                status, 
                approvedby_admin_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
        ");
        
        $middleName = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $stmt->bind_param(
            "isssssi",
            $dogId,
            $requiredFields['first_name'],
            $middleName,
            $requiredFields['last_name'],
            $requiredFields['complete_address'],
            $requiredFields['adoption_reason'],
            $adminId
        );
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to save request: " . $stmt->error);
    }

    // Commit transaction without changing dog status
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $isClaiming ? 'Dog claim approved successfully' : 'Dog adoption approved successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in submit_walkin.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}