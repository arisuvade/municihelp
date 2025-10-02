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
    // Validate required fields
    if (empty($_POST['name']) || empty($_POST['phone']) || empty($_POST['department_id'])) {
        throw new Exception('All fields are required except password when editing');
    }

    // Sanitize inputs
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $department_id = intval($_POST['department_id']);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validate phone format (should be +639XXXXXXXXX)
    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        throw new Exception('Phone number must be in the format +639XXXXXXXXX (12 digits total)');
    }

    // Check if phone already exists (for new admins or when editing)
    $checkPhone = $conn->prepare("SELECT id FROM admins WHERE phone = ? AND id != ?");
    $checkPhone->bind_param("si", $phone, $id);
    $checkPhone->execute();
    $checkPhone->store_result();

    if ($checkPhone->num_rows > 0) {
        throw new Exception('Phone number already exists for another admin');
    }

    if ($id > 0) {
        // Update existing admin
        if (!empty($password)) {
            // Update with password change
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET name = ?, phone = ?, department_id = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssisi", $name, $phone, $department_id, $hashed_password, $id);
        } else {
            // Update without password change
            $stmt = $conn->prepare("UPDATE admins SET name = ?, phone = ?, department_id = ? WHERE id = ?");
            $stmt->bind_param("ssii", $name, $phone, $department_id, $id);
        }
    } else {
        // Create new admin (password is required)
        if (empty($password)) {
            throw new Exception('Password is required for new admin');
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (name, phone, department_id, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $name, $phone, $department_id, $hashed_password);
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = $id > 0 ? 'Admin updated successfully' : 'Admin created successfully';
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