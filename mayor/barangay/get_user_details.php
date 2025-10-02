<?php
session_start();
include '../../includes/db.php';

// Check if barangay admin is logged in
if (!isset($_SESSION['barangay_admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get the admin's barangay_id to ensure they can only view users from their barangay
$admin_id = $_SESSION['barangay_admin_id'];
$admin_query = $conn->prepare("SELECT a.department_id, d.barangay_id 
                              FROM admins a 
                              LEFT JOIN departments d ON a.department_id = d.id 
                              WHERE a.id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();
$admin_data = $admin_result->fetch_assoc();

if (!$admin_data || !$admin_data['barangay_id']) {
    echo json_encode(['error' => 'Admin barangay not found']);
    exit();
}

$admin_barangay_id = $admin_data['barangay_id'];

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    
    // Get user details - ensure the user belongs to the admin's barangay
    $user_query = $conn->prepare("
        SELECT 
            u.id, 
            u.name,
            u.middle_name,
            u.last_name,
            u.birthday, 
            u.phone, 
            u.address,
            u.barangay_id,
            b.name as barangay_name,
            a.name as created_by, 
            u.created_at 
        FROM users u 
        LEFT JOIN barangays b ON u.barangay_id = b.id
        LEFT JOIN admins a ON u.createdby_admin_id = a.id
        WHERE u.id = ? AND u.barangay_id = ?
    ");
    $user_query->bind_param("ii", $user_id, $admin_barangay_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        echo json_encode($user_data);
    } else {
        echo json_encode(['error' => 'User not found or unauthorized']);
    }
} else {
    echo json_encode(['error' => 'User ID not provided']);
}
?>