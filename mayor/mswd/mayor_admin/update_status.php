<?php
session_start();
require_once '../../../includes/db.php';
require_once '../../../includes/send_sms.php';
// Include the file deletion function
require_once '../../../includes/delete_request_files.php';

if (!isset($_SESSION['mayor_admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$mayor_admin_id = $_SESSION['mayor_admin_id'];
$request_id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

if (!$request_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    // Get admin name for messages
    $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
    $admin_stmt->bind_param("i", $mayor_admin_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $admin = $admin_result->fetch_assoc();
    $admin_name = $admin['name'];

    // Get request details
    $stmt = $conn->prepare("
        SELECT mr.*, u.phone, mt.name as program_name 
        FROM mswd_requests mr
        JOIN mswd_types mt ON mr.assistance_id = mt.id
        JOIN users u ON mr.user_id = u.id
        WHERE mr.id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Request not found');
    }

    $request = $result->fetch_assoc();
    $phone = $request['phone'];
    $program_name = $request['program_name'];

    if ($action === 'approve') {
        // Update request status to mayor_approved
        $stmt = $conn->prepare("
            UPDATE mswd_requests 
            SET status = 'mayor_approved',
                approved2by_admin_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $mayor_admin_id, $request_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to approve request');
        }
    } 
    elseif ($action === 'decline') {
        $reason = $_POST['reason'] ?? '';
        
        if (empty($reason)) {
            throw new Exception('Reason is required');
        }
        
        // Prepare SMS message for decline
        $message = "Municipal MSWD:\n";
        $message .= "Request #$request_id ($program_name) - DECLINED\n";
        $message .= "Reason: $reason\n";
        $message .= "Declined by: $admin_name\n";
        $message .= "You may apply again if you still need assistance.";
        
        // Update request status to declined
        $stmt = $conn->prepare("
            UPDATE mswd_requests 
            SET status = 'declined',
                reason = ?,
                declinedby_admin_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('sii', $reason, $mayor_admin_id, $request_id);
        
        if ($stmt->execute()) {
            // Delete the uploaded files for this request
            $filesDeleted = deleteRequestFiles('mswd_requests', $request_id, $conn);
            
            // Clear file references from database
            $referencesCleared = clearFileReferences('mswd_requests', $request_id, $conn);
            
            // Send SMS notification for decline
            $sms_sent = sendSMS($message, [$phone]);
            
            if (!$sms_sent) {
                // Even if SMS fails, we still consider the decline successful
                error_log("Request declined but failed to send SMS notification for request ID: $request_id");
            }
            
            $response = ['success' => true];
            
            // Add file deletion status to response for debugging
            if (!$filesDeleted || !$referencesCleared) {
                $response['warning'] = 'Request declined but some files may not have been deleted properly';
            }
            
            echo json_encode($response);
        } else {
            throw new Exception('Failed to decline request');
        }
    } 
    else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}