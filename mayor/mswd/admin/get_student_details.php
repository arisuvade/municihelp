<?php
session_start();
include '../../../includes/db.php';

// Check for admin session
if (!isset($_SESSION['mswd_admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $query = "SELECT b.*, br.name as barangay_name 
              FROM sulong_dulong_beneficiaries b
              JOIN barangays br ON b.barangay_id = br.id
              WHERE b.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        echo json_encode($student);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID required']);
}
?>