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
    // Validate required fields
    if (empty($_POST['name']) || empty($_POST['phone'])) {
        throw new Exception('Name and phone are required');
    }

    // Sanitize inputs
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validate phone format (should be +639XXXXXXXXX)
    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        throw new Exception('Phone number must be in the format +639XXXXXXXXX (12 digits total)');
    }

    // Check if phone already exists (for other admins)
    $checkPhone = $conn->prepare("SELECT id FROM admins WHERE phone = ? AND id != ?");
    $checkPhone->bind_param("si", $phone, $id);
    $checkPhone->execute();
    $checkPhone->store_result();

    if ($checkPhone->num_rows > 0) {
        throw new Exception('Phone number already exists for another admin');
    }

    if (!empty($password)) {
        // Validate password length (minimum 8 characters)
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET name = ?, phone = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $phone, $hashed_password, $id);
    } else {
        // Update without password change
        $stmt = $conn->prepare("UPDATE admins SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $id);
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Admin updated successfully';
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