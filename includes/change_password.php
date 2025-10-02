<?php
session_start();

include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if user is logged in - check all possible session variables
$isLoggedIn = false;
$sessionTypes = [
    'user_id', 'mayor_superadmin_id', 'vice_mayor_superadmin_id', 'mswd_admin_id', 
    'mayor_admin_id', 'pwd_admin_id', 'animal_admin_id', 'pound_admin_id', 
    'assistance_admin_id', 'ambulance_admin_id', 'barangay_admin_id', 'admin_name'
];

foreach ($sessionTypes as $sessionType) {
    if (isset($_SESSION[$sessionType])) {
        $isLoggedIn = true;
        break;
    }
}

if (!$isLoggedIn) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated. Please log in again.']);
    exit();
}

// Get POST data
$currentPassword = $_POST['currentPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Validate inputs
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit();
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit();
}

// Check database connection
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if user is changing password (user table) or admin (admins table)
if (isset($_SESSION['user_id'])) {
    // User changing password
    $userId = $_SESSION['user_id'];
    
    // Get current password hash
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $updateStmt->bind_param("si", $newPasswordHash, $userId);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . $updateStmt->error]);
    }
    
    $updateStmt->close();
    
} else {
    // Admin changing password - determine which admin type
    $adminTypes = [
        'mayor_superadmin_id', 'vice_mayor_superadmin_id', 'mswd_admin_id', 
        'mayor_admin_id', 'pwd_admin_id', 'animal_admin_id', 'pound_admin_id', 
        'assistance_admin_id', 'ambulance_admin_id', 'barangay_admin_id'
    ];
    
    $adminId = null;
    $adminType = null;
    
    foreach ($adminTypes as $type) {
        if (isset($_SESSION[$type])) {
            $adminId = $_SESSION[$type];
            $adminType = $type;
            break;
        }
    }
    
    if (!$adminId) {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
        exit();
    }
    
    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
        exit();
    }
    
    $admin = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($currentPassword, $admin['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $updateStmt->bind_param("si", $newPasswordHash, $adminId);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . $updateStmt->error]);
    }
    
    $updateStmt->close();
}

if (isset($conn)) {
    $conn->close();
}
?>