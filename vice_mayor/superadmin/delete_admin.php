<?php
session_start();
include '../../includes/db.php';

// Check if superadmin is logged in
if (!isset($_SESSION['vice_mayor_superadmin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Validate admin ID
    if (empty($_POST['id'])) {
        throw new Exception('Admin ID is required');
    }

    $admin_id = intval($_POST['id']);

    // Prevent deleting yourself
    if ($admin_id == $_SESSION['vice_mayor_superadmin_id']) {
        throw new Exception('You cannot delete your own account');
    }

    // Check if admin exists
    $checkAdmin = $conn->prepare("SELECT id FROM admins WHERE id = ?");
    $checkAdmin->bind_param("i", $admin_id);
    $checkAdmin->execute();
    $checkAdmin->store_result();

    if ($checkAdmin->num_rows == 0) {
        throw new Exception('Admin account not found');
    }

    // Delete the admin
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Admin deleted successfully';
        } else {
            throw new Exception('No admin was deleted');
        }
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>