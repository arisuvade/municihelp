<?php
session_start();
require_once '../../../includes/db.php';
require '../../../includes/send_sms.php';
// Include the file deletion function
require_once '../../../includes/delete_request_files.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['mswd_admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['mswd_admin_id'];

header('Content-Type: application/json');

// Daily approval limit
$dailyLimit = 250;

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

// Helper function to check daily limit
function checkDailyLimit($conn, $date) {
    global $dailyLimit;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM mswd_requests 
        WHERE DATE(queue_date) = DATE(?) 
        AND status = 'mswd_approved'
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    return [
        'count' => $count,
        'remaining' => $dailyLimit - $count,
        'limit_reached' => $count >= $dailyLimit
    ];
}

// Helper function to get next queue number for a date
function getNextQueueNumber($conn, $date) {
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(queue_no), 0) + 1 as next_queue 
        FROM mswd_requests 
        WHERE queue_date = ? AND status = 'mswd_approved'
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['next_queue'];
}

// Helper function to check equipment availability
function checkEquipmentAvailability($conn, $equipment_type_id) {
    $stmt = $conn->prepare("
        SELECT available_quantity 
        FROM equipment_inventory 
        WHERE equipment_type_id = ?
    ");
    $stmt->bind_param("i", $equipment_type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return 0;
    }
    
    $data = $result->fetch_assoc();
    return $data['available_quantity'];
}

// Helper function to deduct equipment quantity
function deductEquipment($conn, $equipment_type_id) {
    $stmt = $conn->prepare("
        UPDATE equipment_inventory 
        SET available_quantity = available_quantity - 1 
        WHERE equipment_type_id = ? AND available_quantity > 0
    ");
    $stmt->bind_param("i", $equipment_type_id);
    
    if ($stmt->execute()) {
        return $stmt->affected_rows > 0;
    }
    return false;
}

// Helper function to restore equipment quantity
function restoreEquipment($conn, $equipment_type_id) {
    $stmt = $conn->prepare("
        UPDATE equipment_inventory 
        SET available_quantity = available_quantity + 1 
        WHERE equipment_type_id = ?
    ");
    $stmt->bind_param("i", $equipment_type_id);
    
    if ($stmt->execute()) {
        return $stmt->affected_rows > 0;
    }
    return false;
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
            $admin_name = $admin['name'] ?? 'Admin';
            
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
            $sms_message = "Municipal MSWD Inquiry Response:\n";
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

        // Handle batch reschedule_past_due action
        if ($action === 'reschedule_past_due' && isset($_POST['date'])) {
            $currentDate = $_POST['date'];
            $newDate = $_POST['new_date'];
            
            if (empty($newDate)) {
                throw new Exception('Please select a valid date');
            }
            
            // Check daily limit for the new date
            $limitInfo = checkDailyLimit($conn, $newDate);
            if ($limitInfo['limit_reached']) {
                throw new Exception("Daily limit of {$dailyLimit} requests has been reached for {$newDate}. Cannot reschedule.");
            }
            
            // Get admin name for the message
            $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
            $admin_stmt->bind_param("i", $admin_id);
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            $admin = $admin_result->fetch_assoc();
            $admin_name = $admin['name'];
            
            // Get all requests for the current date (including walk-ins)
            $stmt = $conn->prepare("
                SELECT ar.id, u.phone, at.name as program_name, ar.reschedule_count, ar.is_walkin
                FROM mswd_requests ar
                LEFT JOIN users u ON ar.user_id = u.id
                JOIN mswd_types at ON ar.assistance_id = at.id
                WHERE ar.status = 'mswd_approved' AND ar.queue_date = ?
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
            
            // Get next queue number for the new date
            $nextQueueNumber = getNextQueueNumber($conn, $newDate);
            
            while ($request = $result->fetch_assoc()) {
                $request_id = $request['id'];
                $phone = $request['phone'];
                $program_name = $request['program_name'];
                $reschedule_count = $request['reschedule_count'] + 1;
                $is_walkin = $request['is_walkin'];
                
                // Only send SMS for non-walkin requests
                if (!$is_walkin) {
                    $message = "Municipal MSWD:\n";
                    $message .= "Request #$request_id ($program_name) - RESCHEDULED\n";
                    $message .= "New Date: $formattedNewDate\n";
                    $message .= "Queue Number: $nextQueueNumber\n";
                    $message .= "This is your " . ordinal($reschedule_count) . " reschedule\n";
                    $message .= "Processed by: $admin_name";
                    
                    $sms_sent = sendSMS($message, [$phone]);
                } else {
                    $sms_sent = true; // Skip SMS for walk-ins
                }
                
                $update = $conn->prepare("
                    UPDATE mswd_requests 
                    SET queue_date = ?,
                        queue_no = ?,
                        reschedule_count = ?,
                        rescheduledby_admin_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("siiii", $newDate, $nextQueueNumber, $reschedule_count, $admin_id, $request_id);
                
                if ($update->execute()) {
                    if ($sms_sent) {
                        $successCount++;
                        $nextQueueNumber++; // Increment for next request
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
                    'message' => "Rescheduled $successCount requests" . ($failedCount > 0 ? " ($failedCount failed)" : ""),
                    'limit_info' => [
                        'current_count' => $limitInfo['count'] + $successCount,
                        'remaining' => $dailyLimit - ($limitInfo['count'] + $successCount)
                    ]
                ]);
            } else {
                throw new Exception('Failed to reschedule any requests');
            }
            exit;
        }

        // Handle individual reschedule action
        if ($action === 'reschedule' && isset($_POST['id']) && isset($_POST['new_date'])) {
            $request_id = intval($_POST['id']);
            $newDate = $_POST['new_date'];
            $is_walkin = isset($_POST['is_walkin']) ? (int)$_POST['is_walkin'] : 0;
            
            if (empty($newDate)) {
                throw new Exception('Please select a valid date');
            }
            
            // Check daily limit for the new date
            $limitInfo = checkDailyLimit($conn, $newDate);
            if ($limitInfo['limit_reached']) {
                throw new Exception("Daily limit of {$dailyLimit} requests has been reached for {$newDate}. Cannot reschedule.");
            }
            
            // Get next queue number for the new date
            $nextQueueNumber = getNextQueueNumber($conn, $newDate);
            
            // Get admin name for the message
            $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
            $admin_stmt->bind_param("i", $admin_id);
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            $admin = $admin_result->fetch_assoc();
            $admin_name = $admin['name'];
            
            // Get request details
            $stmt = $conn->prepare("
                SELECT ar.*, 
                       CASE 
                           WHEN ar.is_walkin = 1 THEN ar.contact_no 
                           ELSE u.phone 
                       END as phone,
                       at.name as program_name,
                       ar.reschedule_count
                FROM mswd_requests ar
                LEFT JOIN users u ON ar.user_id = u.id
                JOIN mswd_types at ON ar.assistance_id = at.id
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
            $reschedule_count = $request['reschedule_count'] + 1;
            
            // Only send SMS for non-walkin requests
            if (!$is_walkin && !empty($phone)) {
                $formattedNewDate = date('l, F d, Y', strtotime($newDate));
                $message = "Municipal MSWD:\n";
                $message .= "Request #$request_id ($program_name) - RESCHEDULED\n";
                $message .= "New Date: $formattedNewDate\n";
                $message .= "Queue Number: $nextQueueNumber\n";
                $message .= "This is your " . ordinal($reschedule_count) . " reschedule\n";
                $message .= "Processed by: $admin_name";
                
                $sms_sent = sendSMS($message, [formatPhoneNumber($phone)]);
            } else {
                $sms_sent = true; // Skip SMS for walk-ins
            }
            
            $update = $conn->prepare("
                UPDATE mswd_requests 
                SET queue_date = ?,
                    queue_no = ?,
                    reschedule_count = ?,
                    rescheduledby_admin_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update->bind_param("siiii", $newDate, $nextQueueNumber, $reschedule_count, $admin_id, $request_id);
            
            if (!$update->execute()) {
                throw new Exception('Failed to update database');
            }
            
            if (!$is_walkin && !empty($phone) && !$sms_sent) {
                throw new Exception('Failed to send SMS notification');
            }
            
            echo json_encode([
                'success' => true,
                'limit_info' => [
                    'current_count' => $limitInfo['count'] + 1,
                    'remaining' => $dailyLimit - ($limitInfo['count'] + 1)
                ]
            ]);
            exit;
        }

        if (!isset($_POST['id'])) {
            throw new Exception('Request ID not provided');
        }

        $request_id = intval($_POST['id']);
        $is_walkin = isset($_POST['is_walkin']) ? (int)$_POST['is_walkin'] : 0;
        
        // Get admin name for the message
        $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
        $admin_stmt->bind_param("i", $admin_id);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        $admin = $admin_result->fetch_assoc();
        $admin_name = $admin['name'];
        
        // Modified query to handle walk-ins (which may not have user_id)
        $stmt = $conn->prepare("
            SELECT ar.*, 
                   CASE 
                       WHEN ar.is_walkin = 1 THEN ar.contact_no 
                       ELSE u.phone 
                   END as phone,
                   at.name as program_name,
                   at.id as assistance_id
            FROM mswd_requests ar
            LEFT JOIN users u ON ar.user_id = u.id
            JOIN mswd_types at ON ar.assistance_id = at.id
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
        $assistance_id = $request['assistance_id'];

        switch ($action) {
            case 'approve':
    $queue_date = trim($_POST['queue_date'] ?? '');
    
    if (empty($queue_date)) {
        throw new Exception('Please select a queue date');
    }
    
    // Check daily limit for the selected date
    $limitInfo = checkDailyLimit($conn, $queue_date);
    if ($limitInfo['limit_reached']) {
        throw new Exception("Daily limit of {$dailyLimit} requests has been reached for {$queue_date}. Cannot approve more requests.");
    }
    
    // Check if this is equipment (IDs 9-12) and if available
    $is_equipment = in_array($assistance_id, [9, 10, 11, 12]);
    if ($is_equipment) {
        $available_quantity = checkEquipmentAvailability($conn, $assistance_id);
        if ($available_quantity <= 0) {
            throw new Exception("No {$program_name} equipment available. Cannot approve request.");
        }
    }
    
    // Check if this is Sulong Dulong (IDs 33-35) and if slots available
    $is_sulong_dulong = in_array($assistance_id, [33, 34, 35]);
    if ($is_sulong_dulong) {
        $sulongDulongCount = $conn->query("SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries WHERE status = 'Active'")->fetch_assoc()['total'];
        if ($sulongDulongCount >= 800) {
            throw new Exception("Sulong Dulong beneficiary limit of 800 has been reached. Cannot approve more requests.");
        }
    }
    
    // Get next queue number for the selected date
    $queue_no = getNextQueueNumber($conn, $queue_date);
    
    // Check if this assistance has ID 14 or 15
    $is_special_assistance = in_array($request['assistance_id'], [14, 15]);
    
    // Determine location based on assistance ID
    $location = $is_special_assistance ? "Former PUP" : "Municipal Hall";
    
    if (!$is_walkin) {
        $message = "Municipal MSWD:\n";
        $message .= "Request #$request_id ($program_name) - APPROVED\n";
        $message .= "Scheduled on: " . date('l, F d, Y', strtotime($queue_date)) . "\n";
        $message .= "Queue Number: $queue_no\n";
        $message .= "Approved by: $admin_name\n";
        $message .= "Please bring a valid ID and requirements on your scheduled date at the $location.";
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deduct equipment if this is an equipment request
        if ($is_equipment) {
            if (!deductEquipment($conn, $assistance_id)) {
                throw new Exception("Failed to deduct equipment quantity");
            }
        }
        
        // Add to Sulong Dulong beneficiaries if this is a Sulong Dulong request
if ($is_sulong_dulong) {
    $first_name = $request['first_name'] ?? '';
    $middle_name = $request['middle_name'] ?? '';
    $last_name = $request['last_name'] ?? '';
    $barangay_id = $request['barangay_id'] ?? 0;
    $birthday = $request['birthday'] ?? null;
    
    // Determine duration based on assistance ID
    $duration = '';
    switch ($assistance_id) {
        case 33: $duration = 'Every Month'; break;
        case 34: $duration = 'Per Sem'; break; // Changed from 1st Sem/2nd Sem to Per Sem
    }
    
    if (!empty($first_name) && !empty($last_name) && !empty($duration) && $barangay_id > 0) {
        // Check if beneficiary already exists
        $check_stmt = $conn->prepare("
            SELECT id FROM sulong_dulong_beneficiaries 
            WHERE first_name = ? AND last_name = ? AND barangay_id = ?
        ");
        $check_stmt->bind_param("ssi", $first_name, $last_name, $barangay_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing beneficiary
            $existing = $check_result->fetch_assoc();
            $update_beneficiary = $conn->prepare("
                UPDATE sulong_dulong_beneficiaries 
                SET middle_name = ?, birthday = ?, duration = ?, status = 'Active'
                WHERE id = ?
            ");
            $update_beneficiary->bind_param("sssi", $middle_name, $birthday, $duration, $existing['id']);
            $update_beneficiary->execute();
        } else {
            // Insert new beneficiary
            $insert_beneficiary = $conn->prepare("
                INSERT INTO sulong_dulong_beneficiaries 
                (first_name, middle_name, last_name, birthday, barangay_id, duration, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Active')
            ");
            $insert_beneficiary->bind_param("ssssis", $first_name, $middle_name, $last_name, $birthday, $barangay_id, $duration);
            $insert_beneficiary->execute();
        }
    }
}
        
        $update = $conn->prepare("
            UPDATE mswd_requests 
            SET status = 'mswd_approved', 
                approvedby_admin_id = ?,
                queue_date = ?,
                queue_no = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update->bind_param("isii", $admin_id, $queue_date, $queue_no, $request_id);
        
        if (!$update->execute()) {
            throw new Exception('Failed to update database');
        }
        
        if (!$is_walkin && !empty($phone) && !sendSMS($message, [formatPhoneNumber($phone)])) {
            throw new Exception('Failed to send SMS notification');
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'limit_info' => [
                'current_count' => $limitInfo['count'] + 1,
                'remaining' => $dailyLimit - ($limitInfo['count'] + 1)
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    break;
                
            case 'complete':
                $recipient = trim($_POST['recipient'] ?? '');
                $relation = trim($_POST['relation_to_recipient'] ?? '');
                $released_date = trim($_POST['released_date'] ?? date('Y-m-d'));
                $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
                
                if (empty($recipient)) {
                    throw new Exception('Please enter recipient name');
                }
                
                if (!$is_walkin && !empty($phone)) {
                    $formatted_date = date('l, F d, Y', strtotime($released_date));
                    $message = "Municipal MSWD Assistance:\n";
                    $message .= "Request #$request_id ($program_name) - COMPLETED\n";
                    $message .= "Released on: $formatted_date\n";
                    $message .= "Recipient: $recipient\n";
                    if (!empty($relation)) {
                        $message .= "Relation: $relation\n";
                    }
                    $message .= "Completed by: $admin_name\n";
                    $message .= "You may apply again in 3 months if you still need assistance.";
                }
                
                $update = $conn->prepare("
                    UPDATE mswd_requests 
                    SET status = 'completed', 
                        completedby_admin_id = ?,
                        recipient = ?,
                        relation_to_recipient = ?,
                        released_date = ?,
                        amount = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("isssdi", $admin_id, $recipient, $relation, $released_date, $amount, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update database');
                }
                
                // Handle Sulong Dulong beneficiaries for assistance IDs 33, 34, 35
                $assistance_id = $request['assistance_id'];
                if (in_array($assistance_id, [33, 34, 35])) {
                    // Get the actual first name, middle name, and last name from the request
                    $first_name = $request['first_name'] ?? '';
                    $middle_name = $request['middle_name'] ?? '';
                    $last_name = $request['last_name'] ?? '';
                    
                    // Get barangay_id from the request
                    $barangay_id = $request['barangay_id'] ?? 0;
                    
                    // Get birthday from request
                    $birthday = $request['birthday'] ?? null;
                    
                    // Determine duration based on assistance ID
                    $duration = '';
                    switch ($assistance_id) {
                        case 33: $duration = 'Every Month'; break;
                        case 34: $duration = '1st Sem'; break;
                        case 35: $duration = '2nd Sem'; break;
                    }
                    
                    if (!empty($first_name) && !empty($last_name) && !empty($duration) && $barangay_id > 0) {
                        // Check if beneficiary already exists
                        $check_stmt = $conn->prepare("
                            SELECT id FROM sulong_dulong_beneficiaries 
                            WHERE first_name = ? AND last_name = ? AND barangay_id = ?
                        ");
                        $check_stmt->bind_param("ssi", $first_name, $last_name, $barangay_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            // Update existing beneficiary
                            $existing = $check_result->fetch_assoc();
                            $update_beneficiary = $conn->prepare("
                                UPDATE sulong_dulong_beneficiaries 
                                SET middle_name = ?, birthday = ?, duration = ?
                                WHERE id = ?
                            ");
                            $update_beneficiary->bind_param("sssi", $middle_name, $birthday, $duration, $existing['id']);
                            $update_beneficiary->execute();
                        } else {
                            // Insert new beneficiary
                            $insert_beneficiary = $conn->prepare("
                                INSERT INTO sulong_dulong_beneficiaries 
                                (first_name, middle_name, last_name, birthday, barangay_id, duration)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $insert_beneficiary->bind_param("ssssis", $first_name, $middle_name, $last_name, $birthday, $barangay_id, $duration);
                            $insert_beneficiary->execute();
                        }
                    }
                }
                
                if (!$is_walkin && !empty($phone) && !sendSMS($message, [formatPhoneNumber($phone)])) {
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
    $is_cancellation = ($request['status'] === 'mswd_approved');
    $status_to_set = $is_cancellation ? 'cancelled' : 'declined';
    
    // Check if this is equipment (IDs 9-12) and restore quantity if cancelled
    $is_equipment = in_array($assistance_id, [9, 10, 11, 12]);
    
    // Check if this is Sulong Dulong (IDs 33-35) and remove from beneficiaries if cancelled
    $is_sulong_dulong = in_array($assistance_id, [33, 34, 35]);
    
    if (!$is_walkin && !empty($phone)) {
        $message = "Municipal MSWD:\n";
        $message .= "Request #$request_id ($program_name) - " . strtoupper($status_to_set) . "\n";
        $message .= "Reason: $reason\n";
        $message .= $is_cancellation ? "Cancelled by: $admin_name\n" : "Declined by: $admin_name\n";
        $message .= "You may apply again if you still need assistance.";
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Restore equipment if this is a cancellation of an equipment request
        if ($is_cancellation && $is_equipment) {
            if (!restoreEquipment($conn, $assistance_id)) {
                throw new Exception("Failed to restore equipment quantity");
            }
        }
        
        // Remove from Sulong Dulong beneficiaries if this is a cancellation of a Sulong Dulong request
        if ($is_cancellation && $is_sulong_dulong) {
            $first_name = $request['first_name'] ?? '';
            $last_name = $request['last_name'] ?? '';
            $barangay_id = $request['barangay_id'] ?? 0;
            
            if (!empty($first_name) && !empty($last_name) && $barangay_id > 0) {
                $delete_beneficiary = $conn->prepare("
                    DELETE FROM sulong_dulong_beneficiaries 
                    WHERE first_name = ? AND last_name = ? AND barangay_id = ?
                ");
                $delete_beneficiary->bind_param("ssi", $first_name, $last_name, $barangay_id);
                $delete_beneficiary->execute();
            }
        }
    
        $update = $conn->prepare("
            UPDATE mswd_requests 
            SET status = ?,
                reason = ?,
                " . ($is_cancellation ? "cancelledby_admin_id" : "declinedby_admin_id") . " = ?,
                queue_date = NULL,
                queue_no = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update->bind_param("ssii", $status_to_set, $reason, $admin_id, $request_id);
        
        if (!$update->execute()) {
            throw new Exception('Failed to update database');
        }
        
        // Delete the uploaded files for this request
        $filesDeleted = deleteRequestFiles('mswd_requests', $request_id, $conn);
        
        // Clear file references from database
        $referencesCleared = clearFileReferences('mswd_requests', $request_id, $conn);
        
        if (!$is_walkin && !empty($phone) && !sendSMS($message, [formatPhoneNumber($phone)])) {
            throw new Exception('Failed to send SMS notification');
        }
        
        $conn->commit();
        
        $response = ['success' => true];
        
        // Add file deletion status to response for debugging
        if (!$filesDeleted || !$referencesCleared) {
            $response['warning'] = 'Request processed but some files may not have been deleted properly';
        }
        
        echo json_encode($response);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
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