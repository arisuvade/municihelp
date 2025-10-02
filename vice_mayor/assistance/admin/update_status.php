<?php
session_start();
require_once '../../../includes/db.php';
require '../../../includes/send_sms.php';
// Include the file deletion function
require_once '../../../includes/delete_request_files.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['assistance_admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$assistance_admin_id = $_SESSION['assistance_admin_id'];

header('Content-Type: application/json');

// Helper function for ordinal numbers
function ordinal($number) {
    $suffixes = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) {
        return $number . 'th';
    }
    return $number . $suffixes[$number % 10];
}

// Helper function to format phone numbers
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 0, convert to +63 format
    if (strpos($phone, '0') === 0) {
        return '+63' . substr($phone, 1);
    }
    
    // If starts with 63, add +
    if (strpos($phone, '63') === 0) {
        return '+' . $phone;
    }
    
    // If starts with +, leave as is
    if (strpos($phone, '+') === 0) {
        return $phone;
    }
    
    // Default: assume it's missing country code
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
            
            try {
                // First get the inquiry details for SMS
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
                
                // Get admin name for SMS
                $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
                $admin_stmt->bind_param("i", $assistance_admin_id);
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();
                $admin = $admin_result->fetch_assoc();
                $admin_name = $admin['name'] ?? 'Admin';
                
                // Update the inquiry
                $update_stmt = $conn->prepare("UPDATE inquiries 
                                       SET answer = ?, status = 'answered', 
                                           answeredby_admin_id = ?, updated_at = NOW() 
                                       WHERE id = ?");
                $update_stmt->bind_param("sii", $answer, $assistance_admin_id, $inquiry_id);
                
                if (!$update_stmt->execute()) {
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(['success' => false, 'error' => 'Failed to update inquiry: ' . $conn->error]);
                    exit;
                }
                
                // Send SMS notification
                $phone = $inquiry['phone'];
                $formatted_phone = formatPhoneNumber($phone);

                $sms_message = "Vice Mayor Assistance Inquiry Response:\n";
                $sms_message .= "Your question: " . substr($inquiry['question'], 0, 50) . (strlen($inquiry['question']) > 50 ? "..." : "") . "\n";
                $sms_message .= "Our response: " . substr($answer, 0, 100) . (strlen($answer) > 100 ? "..." : "") . "\n";
                $sms_message .= "Answered by: $admin_name\n";
                $sms_message .= "Thank you for contacting us!";

                $sms_sent = false;
                try {
                    // Add debug logging
                    error_log("Attempting to send SMS to: " . $formatted_phone);
                    error_log("SMS content: " . $sms_message);
                    
                    $sms_sent = sendSMS($sms_message, [$formatted_phone]);
                    
                    if (!$sms_sent) {
                        error_log("SMS sending returned false");
                    }
                } catch (Exception $e) {
                    error_log("SMS sending failed: " . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Inquiry answered successfully' . ($sms_sent ? '' : ' (but SMS failed to send)'),
                    'debug' => [
                        'phone' => $formatted_phone,
                        'sms_function' => function_exists('sendSMS') ? 'exists' : 'missing',
                        'sms_result' => $sms_sent
                    ]
                ]);
                exit;
            } catch (Exception $e) {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }

        // Handle batch reschedule_past_due action
        if ($action === 'reschedule_past_due' && isset($_POST['date'])) {
            $currentDate = $_POST['date'];
            $newDate = $_POST['new_date'];
            
            if (empty($newDate)) {
                throw new Exception('Please select a valid date');
            }
            
            // Get admin name for the message
            $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
            $admin_stmt->bind_param("i", $assistance_admin_id);
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            $admin = $admin_result->fetch_assoc();
            $admin_name = $admin['name'];
            
            // Get all requests for the current date (including walk-ins)
            $stmt = $conn->prepare("
                SELECT ar.id, u.phone, at.name as program_name, ar.reschedule_count, ar.is_walkin
                FROM assistance_requests ar
                LEFT JOIN users u ON ar.user_id = u.id
                JOIN assistance_types at ON ar.assistance_id = at.id
                WHERE ar.status = 'approved' AND ar.queue_date = ?
            ");
            $stmt->bind_param("s", $currentDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('No requests found for this date');
            }
            
            $successCount = 0;
            $failedCount = 0;
            $formattedNewDate = date('l, F d, Y', strtotime($newDate));
            
            while ($request = $result->fetch_assoc()) {
                $request_id = $request['id'];
                $phone = $request['phone'];
                $program_name = $request['program_name'];
                $reschedule_count = $request['reschedule_count'] + 1;
                $is_walkin = $request['is_walkin'];
                
                // Only send SMS for non-walkin requests
                if (!$is_walkin) {
                    $message = "Vice Mayor Assistance:\n";
                    $message .= "Request #$request_id ($program_name) - RESCHEDULED\n";
                    $message .= "New Date: $formattedNewDate\n";
                    $message .= "This is your " . ordinal($reschedule_count) . " reschedule\n";
                    $message .= "Processed by: $admin_name";
                    
                    $sms_sent = sendSMS($message, [$phone]);
                } else {
                    $sms_sent = true; // Skip SMS for walk-ins
                }
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET queue_date = ?,
                        reschedule_count = ?,
                        rescheduledby_admin_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("siii", $newDate, $reschedule_count, $assistance_admin_id, $request_id);
                
                if ($update->execute()) {
                    if ($sms_sent) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                } else {
                    $failedCount++;
                }
            }
            
            if ($successCount > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => "Rescheduled $successCount requests" . ($failedCount > 0 ? " ($failedCount failed)" : "")
                ]);
            } else {
                throw new Exception('Failed to reschedule any requests');
            }
            exit;
        }

        if (!isset($_POST['id'])) {
            throw new Exception('Request ID not provided');
        }

        $request_id = intval($_POST['id']);
        $is_walkin = isset($_POST['is_walkin']) ? (int)$_POST['is_walkin'] : 0;
        
        // Get admin name for the message
        $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
        $admin_stmt->bind_param("i", $assistance_admin_id);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        $admin = $admin_result->fetch_assoc();
        $admin_name = $admin['name'];
        
        // Modified query to handle walk-ins (which may not have user_id)
        $stmt = $conn->prepare("
            SELECT ar.*, u.phone, at.name as program_name 
            FROM assistance_requests ar
            LEFT JOIN users u ON ar.user_id = u.id
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
        $current_status = $request['status'];

        switch ($action) {
            case 'approve':
                $queue_date = trim($_POST['queue_date'] ?? '');
                
                if (empty($queue_date)) {
                    throw new Exception('Please select a queue date');
                }
                
                if (!$is_walkin) {
                    $message = "Vice Mayor Assistance:\n";
                    $message .= "Request #$request_id ($program_name) - APPROVED\n";
                    $message .= "Scheduled on: " . date('l, F d, Y', strtotime($queue_date)) . "\n";
                    $message .= "Approved by: $admin_name\n";
                    $message .= "Please bring a valid ID and requirements on your scheduled date at the Municipal Hall.";
                }
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET status = 'approved', 
                        approvedby_admin_id = ?,
                        queue_date = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("isi", $assistance_admin_id, $queue_date, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                if (!$is_walkin && !sendSMS($message, [$phone])) {
                    throw new Exception('Failed to send SMS notification');
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'complete':
                $recipient = trim($_POST['recipient'] ?? '');
                $relation = trim($_POST['relation_to_recipient'] ?? '');
                $released_date = trim($_POST['released_date'] ?? date('Y-m-d'));
                $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
                
                if (empty($recipient)) {
                    throw new Exception('Please enter recipient name');
                }
                
                if (!$is_walkin) {
                    $formatted_date = date('l, F d, Y', strtotime($released_date));
                    $message = "Vice Mayor Assistance:\n";
                    $message .= "Request #$request_id ($program_name) - COMPLETED\n";
                    $message .= "Released on: $formatted_date\n";
                    $message .= "Recipient: $recipient\n";
                    if (!empty($relation)) {
                        $message .= "Relation: $relation\n";
                    }
                    $message .= "Completed by: $admin_name\n";
                    $message .= "You may apply again next month if you still need assistance.";
                }
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET status = 'completed', 
                        completedby_admin_id = ?,
                        recipient = ?,
                        relation_to_recipient = ?,
                        released_date = ?,
                        amount = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("isssdi", $assistance_admin_id, $recipient, $relation, $released_date, $amount, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                if (!$is_walkin && !sendSMS($message, [$phone])) {
                    throw new Exception('Failed to send SMS notification');
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'decline':
                $reason = trim($_POST['reason'] ?? '');
                
                if (empty($reason)) {
                    throw new Exception('Please provide a reason for declining');
                }
                
                // Determine if this should be a cancellation (if previously approved)
                $is_cancellation = ($request['status'] === 'approved');
                $status_to_set = $is_cancellation ? 'cancelled' : 'declined';
                
                if (!$is_walkin) {
                    $message = "Vice Mayor Assistance:\n";
                    $message .= "Request #$request_id ($program_name) - " . strtoupper($status_to_set) . "\n";
                    $message .= "Reason: $reason\n";
                    $message .= $is_cancellation ? "Cancelled by: $admin_name\n" : "Declined by: $admin_name\n";
                    $message .= "You may apply again if you still need assistance.";
                }
                
                $update = $conn->prepare("
                    UPDATE assistance_requests 
                    SET status = ?,
                        reason = ?,
                        " . ($is_cancellation ? "cancelledby_admin_id" : "declinedby_admin_id") . " = ?,
                        queue_date = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("ssii", $status_to_set, $reason, $assistance_admin_id, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                // Delete the uploaded files for this request
                $filesDeleted = deleteRequestFiles('assistance_requests', $request_id, $conn);
                
                // Clear file references from database
                $referencesCleared = clearFileReferences('assistance_requests', $request_id, $conn);
                
                if (!$is_walkin && !sendSMS($message, [$phone])) {
                    throw new Exception('Failed to send SMS notification');
                }
                
                $response = ['success' => true];
                
                // Add file deletion status to response for debugging
                if (!$filesDeleted || !$referencesCleared) {
                    $response['warning'] = 'Request processed but some files may not have been deleted properly';
                }
                
                echo json_encode($response);
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
        'error' => 'Invalid request method'
    ]);
}