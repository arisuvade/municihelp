<?php
session_start();
require_once '../../../includes/db.php';
require '../../../includes/send_sms.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['pwd_admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['pwd_admin_id'];

header('Content-Type: application/json');

// Helper function to format phone numbers
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($phone, '0') === 0) {
        return '+63' . substr($phone, 1);
    }
    if (strpos($phone, '63') === 0) {
        return '+' . $phone;
    }
    if (strpos($phone, '+') === 0) {
        return $phone;
    }
    return '+63' . $phone;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['action'])) {
            throw new Exception('No action specified');
        }

        $action = $_POST['action'];

        // Handle inquiry answer action
        if ($action === 'answer_inquiry' && isset($_POST['inquiry_id']) && isset($_POST['answer'])) {
            $inquiry_id = intval($_POST['inquiry_id']);
            $answer = trim($_POST['answer']);
            
            if (empty($answer)) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['success' => false, 'error' => 'Answer cannot be empty']);
                exit;
            }
            
            // Get inquiry details
            $stmt = $conn->prepare("
                SELECT i.*, u.phone, u.name as user_name, d.name as department_name
                FROM inquiries i
                JOIN users u ON i.user_id = u.id
                JOIN departments d ON i.department_id = d.id
                WHERE i.id = ?
            ");
            $stmt->bind_param("i", $inquiry_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                header('HTTP/1.1 404 Not Found');
                echo json_encode(['success' => false, 'error' => 'Inquiry not found']);
                exit;
            }
            
            $inquiry = $result->fetch_assoc();
            
            // Get admin name
            $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
            $admin_stmt->bind_param("i", $admin_id);
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            $admin = $admin_result->fetch_assoc();
            $admin_name = $admin['name'] ?? 'PWD Admin';
            
            // Update inquiry
            $update_stmt = $conn->prepare("
                UPDATE inquiries 
                SET answer = ?, status = 'answered', 
                    answeredby_admin_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->bind_param("sii", $answer, $admin_id, $inquiry_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update inquiry');
            }
            
            // Send SMS notification
            $phone = formatPhoneNumber($inquiry['phone']);
            $sms_message = "Municipal PWD Inquiry Response:\n";
            $sms_message .= "Your question: " . substr($inquiry['question'], 0, 50) . (strlen($inquiry['question']) > 50 ? "..." : "") . "\n";
            $sms_message .= "Our response: " . substr($answer, 0, 100) . (strlen($answer) > 100 ? "..." : "") . "\n";
            $sms_message .= "Answered by: $admin_name\n";
            $sms_message .= "Thank you for contacting us!";

            $sms_sent = sendSMS($sms_message, [$phone]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Inquiry answered successfully' . ($sms_sent ? '' : ' (but SMS failed to send)')
            ]);
            exit;
        }

        throw new Exception('Invalid action');
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}