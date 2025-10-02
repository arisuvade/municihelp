<?php
session_start();
require_once '../../../includes/db.php';
require '../../../includes/send_sms.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['pound_admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$admin_id = $_SESSION['pound_admin_id'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Initialize response
        $response = ['success' => false, 'message' => ''];
        
        // Get request parameters from either form data or JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, try form data
            $data = $_POST;
        }

        // Validate required parameters
        if (empty($data['action']) || empty($data['id']) || empty($data['type'])) {
            throw new Exception('Missing required parameters (action, id, or type)');
        }
        
        $action = $data['action'];
        $request_id = intval($data['id']);
        $request_type = strtolower(trim($data['type']));
        
        // Determine the table based on request type
        if ($request_type === 'claim') {
            $table = 'dog_claims';
            $program = 'Dog Retrieving';
        } elseif ($request_type === 'adoption') {
            $table = 'dog_adoptions';
            $program = 'Dog Adoption';
        } else {
            throw new Exception('Invalid request type. Must be "claim" or "adoption"');
        }

        // Start transaction
        $conn->begin_transaction();

        // Get admin name
        $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
        $admin_stmt->bind_param("i", $admin_id);
        if (!$admin_stmt->execute()) {
            throw new Exception('Failed to fetch admin details');
        }
        $admin_result = $admin_stmt->get_result();
        $admin = $admin_result->fetch_assoc();
        $admin_name = $admin['name'] ?? 'Admin';

        // Get request details including user_id to check if it's a walk-in
        $stmt = $conn->prepare("SELECT user_id, status, dog_id, first_name, middle_name, last_name, birthday, barangay_id, phone FROM $table WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        $stmt->bind_param("i", $request_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to fetch request details: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Request not found (ID: $request_id in table $table)");
        }
        
        $request = $result->fetch_assoc();
        $is_walkin = ($request['user_id'] === null);
        $current_status = $request['status'];
        $dog_id = $request['dog_id'];

        // Verify request is in approved status
        if ($current_status !== 'approved') {
            throw new Exception("Request must be in approved status (current status: $current_status)");
        }

        $message = "Municipal Animal Control:\n$program #$request_id - ";

        switch ($action) {
            case 'complete':
                // Handle receipt photo upload for dog claims (required)
                $receipt_photo_path = null;
                
                if ($request_type === 'claim') {
                    if (empty($_FILES['receipt_photo']['tmp_name'])) {
                        throw new Exception('Receipt photo is required for dog claims to verify payment');
                    }
                    
                    $upload_dir = '../../../uploads/receipts/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['receipt_photo']['name'], PATHINFO_EXTENSION);
                    $filename = 'receipt_' . $request_id . '_' . time() . '.' . $file_ext;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['receipt_photo']['tmp_name'], $target_path)) {
                        $receipt_photo_path = 'uploads/receipts/' . $filename;
                    } else {
                        throw new Exception('Failed to upload receipt photo');
                    }
                }

                // Handle handover photo upload (optional)
                $handover_photo_path = null;
                if (!empty($_FILES['handover_photo']['tmp_name'])) {
                    $upload_dir = '../../../uploads/handover_photos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['handover_photo']['name'], PATHINFO_EXTENSION);
                    $filename = 'handover_' . $request_id . '_' . time() . '.' . $file_ext;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['handover_photo']['tmp_name'], $target_path)) {
                        $handover_photo_path = 'uploads/handover_photos/' . $filename;
                    } else {
                        throw new Exception('Failed to upload handover photo');
                    }
                }

                // Update request status - different SQL for claim vs adoption
                if ($request_type === 'claim') {
                    $update_sql = "UPDATE $table SET 
                        status = 'completed', 
                        completedby_admin_id = ?, 
                        handover_photo_path = ?, 
                        receipt_photo_path = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
                    $update = $conn->prepare($update_sql);
                    $update->bind_param("issi", $admin_id, $handover_photo_path, $receipt_photo_path, $request_id);
                } else {
                    $update_sql = "UPDATE $table SET 
                        status = 'completed', 
                        completedby_admin_id = ?, 
                        handover_photo_path = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
                    $update = $conn->prepare($update_sql);
                    $update->bind_param("isi", $admin_id, $handover_photo_path, $request_id);
                }
                
                if (!$update->execute()) {
                    throw new Exception('Failed to update request status: ' . $update->error);
                }

                // CANCEL ALL OTHER PENDING/APPROVED REQUESTS FOR THIS DOG
                $other_requests = $conn->prepare("
                    SELECT r.id, r.user_id, u.phone 
                    FROM $table r
                    LEFT JOIN users u ON r.user_id = u.id
                    WHERE r.dog_id = ? 
                    AND r.id != ? 
                    AND r.status IN ('pending', 'approved')
                ");
                $other_requests->bind_param("ii", $dog_id, $request_id);
                $other_requests->execute();
                $other_requests_result = $other_requests->get_result();

                while ($other_request = $other_requests_result->fetch_assoc()) {
                    // Update status to cancelled
                    $cancel_stmt = $conn->prepare("
                        UPDATE $table 
                        SET status = 'cancelled', 
                            cancelledby_admin_id = ?,
                            reason = CONCAT('Automatically cancelled - dog already ', ?),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $cancel_reason = ($request_type === 'claim') ? 'claimed' : 'adopted';
                    $cancel_stmt->bind_param("isi", $admin_id, $cancel_reason, $other_request['id']);
                    $cancel_stmt->execute();
                    
                    // Send SMS notification if not walk-in and has phone number
                    if ($other_request['user_id'] && !empty($other_request['phone'])) {
                        $cancel_message = "Municipal Animal Control:\n";
                        $cancel_message .= "Your $program request for dog #$dog_id has been cancelled.\n";
                        $cancel_message .= "Reason: The dog has already been $cancel_reason by someone else.\n";
                        $cancel_message .= "You may submit a new request for another available dog.";
                        
                        if (!sendSMS($cancel_message, [$other_request['phone']])) {
                            error_log("Failed to send cancellation SMS for request {$other_request['id']}");
                        }
                    }
                }
                
                // Only for claim requests, update the dog_claimers table
                if ($request_type === 'claim') {
                    $first_name = trim($request['first_name']);
                    $middle_name = trim($request['middle_name']);
                    $last_name = trim($request['last_name']);
                    $birthday = $request['birthday'];
                    $barangay_id = $request['barangay_id'];
                    
                    // Check if this person already exists in dog_claimers
                    $check_claimer = $conn->prepare("
                        SELECT id, total_claims 
                        FROM dog_claimers 
                        WHERE first_name = ? 
                        AND last_name = ? 
                        AND birthday = ? 
                        AND barangay_id = ?
                    ");
                    $check_claimer->bind_param("sssi", $first_name, $last_name, $birthday, $barangay_id);
                    $check_claimer->execute();
                    $claimer_result = $check_claimer->get_result();
                    
                    if ($claimer_result->num_rows > 0) {
                        // Existing claimer - increment total_claims
                        $claimer = $claimer_result->fetch_assoc();
                        $new_total = $claimer['total_claims'] + 1;
                        $update_claimer = $conn->prepare("UPDATE dog_claimers SET total_claims = ? WHERE id = ?");
                        $update_claimer->bind_param("ii", $new_total, $claimer['id']);
                        if (!$update_claimer->execute()) {
                            throw new Exception('Failed to update claimer record: ' . $update_claimer->error);
                        }
                    } else {
                        // New claimer - insert with total_claims = 1
                        $insert_claimer = $conn->prepare("
                            INSERT INTO dog_claimers 
                            (first_name, middle_name, last_name, birthday, barangay_id, total_claims) 
                            VALUES (?, ?, ?, ?, ?, 1)
                        ");
                        $insert_claimer->bind_param("ssssi", $first_name, $middle_name, $last_name, $birthday, $barangay_id);
                        if (!$insert_claimer->execute()) {
                            throw new Exception('Failed to create claimer record: ' . $insert_claimer->error);
                        }
                    }
                }
                
                $message .= "COMPLETED\n";
                $message .= "Completed by: $admin_name\n";
                $message .= "Thank you for using our services.";
                break;
                
            case 'cancel':
                $reason = trim($data['reason'] ?? '');
                if (empty($reason)) {
                    throw new Exception('Cancellation reason is required');
                }
                
                // Update request status
                $update_sql = "UPDATE $table SET 
                    status = 'cancelled', 
                    reason = ?, 
                    cancelledby_admin_id = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
                $update = $conn->prepare($update_sql);
                $update->bind_param("sii", $reason, $admin_id, $request_id);
                
                if (!$update->execute()) {
                    throw new Exception('Failed to cancel request: ' . $update->error);
                }
                
                // Update dog status back to available
                $new_dog_status = ($request_type === 'claim') ? 'for_claiming' : 'for_adoption';
                $dog_update = $conn->prepare("UPDATE dogs SET status = ? WHERE id = ?");
                $dog_update->bind_param("si", $new_dog_status, $dog_id);
                
                if (!$dog_update->execute()) {
                    throw new Exception('Failed to reset dog status: ' . $dog_update->error);
                }
                
                $message .= "CANCELLED\n";
                $message .= "Reason: $reason\n";
                $message .= "Cancelled by: $admin_name\n";
                $message .= "You may submit a new request if needed.";
                break;
                
            default:
                throw new Exception("Invalid action: $action");
        }

        // Commit transaction if all operations succeeded
        $conn->commit();

        // Only send SMS if not walk-in and has phone number
        if (!$is_walkin && $request['user_id']) {
            $phone_stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
            $phone_stmt->bind_param("i", $request['user_id']);
            if ($phone_stmt->execute()) {
                $phone_result = $phone_stmt->get_result();
                if ($phone_result->num_rows > 0) {
                    $phone = $phone_result->fetch_assoc()['phone'];
                    if (!empty($phone)) {
                        if (!sendSMS($message, [$phone])) {
                            error_log("SMS sending failed for $request_type $request_id");
                        }
                    }
                }
            }
        }

        $response = ['success' => true, 'message' => ucfirst($action) . ' successful'];
        echo json_encode($response);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in update_status.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'error_details' => 'Action: ' . ($action ?? 'unknown') . 
                             ', Request ID: ' . ($request_id ?? 'unknown') . 
                             ', Type: ' . ($request_type ?? 'unknown')
        ];
        
        echo json_encode($response);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}