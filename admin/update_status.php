<?php
session_start();
require_once '../includes/db.php';
require '../includes/send_sms.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['action'])) {
    $request_id = intval($_POST['id']);
    $action = $_POST['action'];
    $note = trim($_POST['note'] ?? '');
    
    try {
        $stmt = $conn->prepare("
            SELECT ar.*, u.phone, at.name as program_name 
            FROM assistance_requests ar
            JOIN users u ON ar.user_id = u.id
            JOIN assistance_types at ON ar.assistance_id = at.id
            WHERE ar.id = ?
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
        
        switch ($action) {
            case 'approve':
                // calculate next tuesday
                $next_tuesday = date('Y-m-d', strtotime('next tuesday'));
                
                // next queue number for next tuesday
                $queue_stmt = $conn->prepare("
                    SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_queue 
                    FROM assistance_requests 
                    WHERE status = 'approved' 
                    AND queue_date = ?
                ");
                $queue_stmt->bind_param("s", $next_tuesday);
                $queue_stmt->execute();
                $queue_result = $queue_stmt->get_result();
                $next_queue = $queue_result->fetch_assoc()['next_queue'];
                
                $message = "MuniciHelp - Ang iyong request (#$request_id) para sa $program_name ay NAAPROBAHAN.";
                if (!empty($note)) {
                    $message .= " Note: $note";
                }
                $message .= " Ang iyong queue number ay #$next_queue para sa Martes, " . date('F j', strtotime($next_tuesday)) . ". Mangyaring pumunta sa Municipal Hall sa araw na iyon.";
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET status = 'approved', 
                        note = ?,
                        queue_number = ?,
                        queue_date = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("sisi", $note, $next_queue, $next_tuesday, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                if (!sendSMS($message, [$phone])) {
                    throw new Exception('Failed to send SMS notification');
                }
                
                echo json_encode(['success' => true, 'queue_number' => $next_queue]);
                break;
                
            case 'decline':
                if (empty($note)) {
                    throw new Exception('Please provide a reason for declining this request');
                }
                
                $message = "MuniciHelp - Ang iyong request (#$request_id) para sa $program_name ay HINDI NAAPROBAHAN. Dahilan: $note\nMaaari kang mag-apply muli kung nais mong muling magsumite ng request.";
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET status = 'declined', 
                        note = ?,
                        queue_number = NULL,
                        queue_date = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("si", $note, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                if (!sendSMS($message, [$phone])) {
                    throw new Exception('Failed to send SMS notification');
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'complete':
                $message = "MuniciHelp - Ang iyong request (#$request_id) para sa $program_name ay NATAPOS NA.";
                if (!empty($note)) {
                    $message .= "\nNote: $note";
                }
                $message .= "\nMaaari kang mag-apply muli sa susunod na buwan kung kailangan mo pa ng assistance.";                
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET status = 'completed', 
                        note = ?,
                        queue_number = NULL,
                        queue_date = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("si", $note, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                if (!sendSMS($message, [$phone])) {
                    throw new Exception('Failed to send SMS notification');
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'cancel':
                $message = "MuniciHelp - Ang iyong request (#$request_id) para sa $program_name ay NAKANSELA.";
                if (!empty($note)) {
                    $message .= "\nNote: $note";
                }
                $message .= "\nMaaari kang mag-apply muli kung nais mong muling magsumite ng request.";
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET status = 'cancelled', 
                        note = ?,
                        queue_number = NULL,
                        queue_date = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("si", $note, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                if (!sendSMS($message, [$phone])) {
                    throw new Exception('Failed to send SMS notification');
                }
                
                echo json_encode(['success' => true]);
                break;
            
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request'
    ]);
}