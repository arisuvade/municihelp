<?php
session_start();
include '../../includes/db.php';

// Check if barangay admin is logged in
if (!isset($_SESSION['barangay_admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Validate user ID
    if (empty($_POST['id'])) {
        throw new Exception('User ID is required');
    }

    $user_id = intval($_POST['id']);

    // Check if user exists
    $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $checkUser->bind_param("i", $user_id);
    $checkUser->execute();
    $checkUser->store_result();

    if ($checkUser->num_rows == 0) {
        throw new Exception('User account not found');
    }

    // Delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'User deleted successfully';
        } else {
            throw new Exception('No user was deleted');
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