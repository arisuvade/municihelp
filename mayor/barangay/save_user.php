<?php
session_start();
include '../../includes/db.php';
require '../../includes/send_sms.php'; // Include SMS functionality

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
    if (empty($_POST['name']) || empty($_POST['last_name']) || empty($_POST['phone']) || empty($_POST['birthday']) || empty($_POST['address'])) {
        throw new Exception('First name, last name, phone, birthday, and address are required');
    }

    // Sanitize inputs
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = trim($_POST['name']);
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last_name = trim($_POST['last_name']);
    $birthday = trim($_POST['birthday']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $barangay_id = isset($_POST['barangay_id']) ? intval($_POST['barangay_id']) : null;

    // Get the barangay admin's department_id (which represents their barangay)
    $admin_id = $_SESSION['barangay_admin_id'];
    $admin_query = $conn->prepare("SELECT department_id FROM admins WHERE id = ?");
    $admin_query->bind_param("i", $admin_id);
    $admin_query->execute();
    $admin_result = $admin_query->get_result();
    $admin_data = $admin_result->fetch_assoc();
    
    if (!$admin_data || !$admin_data['department_id']) {
        throw new Exception('Admin department not found');
    }
    
    $department_id = $admin_data['department_id'];

    // Validate phone format (should be +639XXXXXXXXX)
    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        throw new Exception('Phone number must be in the format +639XXXXXXXXX (12 digits total)');
    }

    // Validate birthday format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        throw new Exception('Birthday must be in YYYY-MM-DD format');
    }

    // Handle regular user account updates
    $checkPhone = $conn->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
    $checkPhone->bind_param("si", $phone, $id);
    $checkPhone->execute();
    $checkPhone->store_result();

    if ($checkPhone->num_rows > 0) {
        throw new Exception('Phone number already exists for another user');
    }

    if ($id > 0) {
        // Update existing user - set is_verified to 1
        $stmt = $conn->prepare("UPDATE users SET name = ?, middle_name = ?, last_name = ?, birthday = ?, address = ?, phone = ?, barangay_id = ?, department_id = ?, is_verified = 1 WHERE id = ?");
        $stmt->bind_param("ssssssiii", $name, $middle_name, $last_name, $birthday, $address, $phone, $barangay_id, $department_id, $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'User updated successfully';
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
    } else {
        // Create new user - generate 8-character random password
        $password = generateRandomPassword(8);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $createdby_admin_id = $_SESSION['barangay_admin_id'];
        // In the INSERT statement for new users, add the is_temp_password flag
$stmt = $conn->prepare("INSERT INTO users (name, middle_name, last_name, birthday, address, phone, password_hash, barangay_id, department_id, createdby_admin_id, is_verified, is_temp_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
        $stmt->bind_param("sssssssiii", $name, $middle_name, $last_name, $birthday, $address, $phone, $hashed_password, $barangay_id, $department_id, $createdby_admin_id);
        
        if ($stmt->execute()) {
            // Send password via SMS
            $message = "Welcome to MuniciHelp! Your temporary password is: $password\n";
            $message .= "Please change it after login.";
             
            $phone_numbers = [$phone];
            
            $smsResponse = sendSMS($message, $phone_numbers);
            
            if ($smsResponse !== false) {
                $response['success'] = true;
                $response['message'] = 'User created successfully. Temporary password sent via SMS.';
            } else {
                // If SMS fails, we still created the user but need to inform admin
                $response['success'] = true;
                $response['message'] = 'User created but failed to send SMS with password. Please contact the user directly.';
            }
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}
?>