<?php
session_start();
require_once '../../../includes/db.php';
require '../../../includes/send_sms.php';
// Include the file deletion function
require_once '../../../includes/delete_request_files.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['animal_admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['animal_admin_id'];

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

// Function to calculate fine based on offense count
function calculateFine($totalClaims) {
    // Offense count is total_claims + 1 (current claim)
    $offenseCount = intval($totalClaims) + 1;
    
    $fineAmount = 0;
    
    // Fine structure: 1st=300, 2nd=500, 3rd=800 (capped at 3rd offense)
    if ($offenseCount === 1) {
        $fineAmount = 300;
    } else if ($offenseCount === 2) {
        $fineAmount = 500;
    } else {
        $fineAmount = 800; // 3rd offense and beyond (capped at 800)
    }
    
    return [
        'offenseCount' => $offenseCount,
        'fineAmount'   => $fineAmount,
        'offenseText'  => $offenseCount === 1 ? '1st offense' : 
                         ($offenseCount === 2 ? '2nd offense' : 
                         ($offenseCount === 3 ? '3rd offense' : 
                         $offenseCount . 'th offense'))
    ];
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
                $admin_stmt->bind_param("i", $admin_id);
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();
                $admin = $admin_result->fetch_assoc();
                $admin_name = $admin['name'] ?? 'Admin';
                
                // Update the inquiry
                $update_stmt = $conn->prepare("UPDATE inquiries 
                                       SET answer = ?, status = 'answered', 
                                           answeredby_admin_id = ?, updated_at = NOW() 
                                       WHERE id = ?");
                $update_stmt->bind_param("sii", $answer, $admin_id, $inquiry_id);
                
                if (!$update_stmt->execute()) {
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(['success' => false, 'error' => 'Failed to update inquiry: ' . $conn->error]);
                    exit;
                }
                
                // Send SMS notification
                $phone = $inquiry['phone'];
                $formatted_phone = formatPhoneNumber($phone);

                $sms_message = "Municipal Animal Control Inquiry Response:\n";
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

        if (!isset($_POST['id']) || !isset($_POST['type'])) {
            throw new Exception('Missing required parameters');
        }

        $request_id = intval($_POST['id']);
        $request_type = $_POST['type'];
        
        // Get admin name
        $admin_stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
        $admin_stmt->bind_param("i", $admin_id);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        $admin = $admin_result->fetch_assoc();
        $admin_name = $admin['name'];

        // Get user phone number and fine details for dog claiming
        $phone = null;
        $fine_details = null;
        
        if ($request_type === 'dog_claiming') {
            // Get phone from dog_claims table (not users table)
            $stmt = $conn->prepare("SELECT phone, status FROM dog_claims WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $request = $result->fetch_assoc();
                $phone = $request['phone'];
                $current_status = $request['status'];
            }
            
            // Get claimer details for fine calculation if approving
            if ($action === 'approve') {
                $stmt = $conn->prepare("SELECT first_name, last_name, birthday, barangay_id FROM dog_claims WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $claim = $result->fetch_assoc();
                    $first_name = $claim['first_name'];
                    $last_name = $claim['last_name'];
                    $birthday = $claim['birthday'];
                    $barangay_id = $claim['barangay_id'];
                    
                    // Get the claimer's data from dog_claimers table
                    $stmt = $conn->prepare("SELECT total_claims FROM dog_claimers WHERE first_name = ? AND last_name = ? AND birthday = ? AND barangay_id = ?");
                    $stmt->bind_param("sssi", $first_name, $last_name, $birthday, $barangay_id);
                    $stmt->execute();
                    $claimer_result = $stmt->get_result();
                    
                    if ($claimer_result->num_rows > 0) {
                        $claimer = $claimer_result->fetch_assoc();
                        $total_claims = $claimer['total_claims'];
                    } else {
                        $total_claims = 0; // First offense if no record exists
                    }
                    
                    $fine_details = calculateFine($total_claims);
                }
            }
        } 
        elseif ($request_type === 'dog_adoption') {
            $stmt = $conn->prepare("SELECT u.phone, r.status FROM dog_adoptions r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $request = $result->fetch_assoc();
                $phone = $request['phone'];
                $current_status = $request['status'];
            }
        } 
        elseif ($request_type === 'rabid_report') {
            $stmt = $conn->prepare("SELECT u.phone, r.status FROM rabid_reports r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $request = $result->fetch_assoc();
                $phone = $request['phone'];
                $current_status = $request['status'];
            }
        }

        $message = "Municipal Animal Control:\n";
        
        switch ($request_type) {
            case 'dog_claiming':
            case 'dog_adoption':
                $program = ($request_type === 'dog_claiming') ? 'Dog Retrieving' : 'Dog Adoption';
                
                switch ($action) {
                    case 'approve':
                        // For dog claiming, set offense_count and fine_amount
                        if ($request_type === 'dog_claiming' && $fine_details) {
                            $update = $conn->prepare("UPDATE dog_claims SET status = 'approved', approvedby_admin_id = ?, offense_count = ?, fine_amount = ?, updated_at = NOW() WHERE id = ?");
                            $update->bind_param("iiii", $admin_id, $fine_details['offenseCount'], $fine_details['fineAmount'], $request_id);
                        } else {
                            $update = $conn->prepare("UPDATE " . ($request_type === 'dog_claiming' ? 'dog_claims' : 'dog_adoptions') . " SET status = 'approved', approvedby_admin_id = ?, updated_at = NOW() WHERE id = ?");
                            $update->bind_param("ii", $admin_id, $request_id);
                        }
                        
                        if (!$update->execute()) {
                            throw new Exception('Failed to update database');
                        }
                        
                        $message .= "$program #$request_id - APPROVED\n";
                        $message .= "Approved by: $admin_name\n";
                        
                        // Add fine details for dog claiming
                        if ($request_type === 'dog_claiming' && $fine_details) {
                            $message .= "This is your " . $fine_details['offenseText'] . "\n";
                            $message .= "Fine Amount: " . $fine_details['fineAmount'] . " Pesos\n";
                            $message .= "Please prepare the amount before retrieving your dog.\n";
                        }
                        
                        $message .= "Please visit the municipal hall to complete the process.";
                        break;
                        
                    case 'decline':
                        $reason = trim($_POST['reason'] ?? '');
                        if (empty($reason)) {
                            throw new Exception('Please provide a reason');
                        }
                        
                        $is_cancellation = ($current_status === 'approved');
                        $status_to_set = $is_cancellation ? 'cancelled' : 'declined';
                        $admin_field = $is_cancellation ? 'cancelledby_admin_id' : 'declinedby_admin_id';
                        
                        $update = $conn->prepare("UPDATE " . ($request_type === 'dog_claiming' ? 'dog_claims' : 'dog_adoptions') . " SET status = ?, reason = ?, $admin_field = ?, updated_at = NOW() WHERE id = ?");
                        $update->bind_param("ssii", $status_to_set, $reason, $admin_id, $request_id);
                        
                        if (!$update->execute()) {
                            throw new Exception('Failed to update database');
                        }
                        
                        $action_word = $is_cancellation ? 'CANCELLED' : 'DECLINED';
                        $message .= "$program #$request_id - $action_word\n";
                        $message .= "Reason: $reason\n";
                        $message .= "Processed by: $admin_name\n";
                        $message .= "Please visit the municipal hall for more information.";
                        break;
                        
                    default:
                        throw new Exception('Invalid action');
                }
                break;
                
            case 'rabid_report':
                switch ($action) {
                    case 'verify':
                        $update = $conn->prepare("UPDATE rabid_reports SET status = 'verified', verifiedby_admin_id = ?, updated_at = NOW() WHERE id = ?");
                        $update->bind_param("ii", $admin_id, $request_id);
                        
                        if (!$update->execute()) {
                            throw new Exception('Failed to update database');
                        }
                        
                        $message .= "Rabid Report #$request_id - CAUGHT\n";
                        $message .= "Verified by: $admin_name\n";
                        $message .= "Thank you for your report. The dog has already been caught.";
                        break;
                        
                    case 'false_report':
                        $reason = trim($_POST['reason'] ?? '');
                        if (empty($reason)) {
                            throw new Exception('Please provide a reason');
                        }
                        
                        // Determine the report title and closing message based on the reason
                        if ($reason === "False information") {
                            $report_title = "FALSE REPORT";
                            $sms_reason = "False information";
                            $closing_message = "Please provide accurate information in future reports.";
                        } else if ($reason === "Report is irrelevant / not seen in the area") {
                            $report_title = "UNFOUNDED REPORT";
                            $sms_reason = "Irrelevant (dog no longer in the area)";
                            $closing_message = "Kindly report as soon as possible next time.";
                        } else {
                            $report_title = "FALSE REPORT";
                            $sms_reason = $reason;
                            $closing_message = "Please provide accurate information in future reports.";
                        }
                        
                        $update = $conn->prepare("UPDATE rabid_reports SET status = 'false_report', reason = ?, cancelledby_admin_id = ?, updated_at = NOW() WHERE id = ?");
                        $update->bind_param("sii", $reason, $admin_id, $request_id);
                        
                        if (!$update->execute()) {
                            throw new Exception('Failed to update database');
                        }
                        
                        // Delete the uploaded proof file for this rabid report
                        $filesDeleted = deleteRequestFiles('rabid_reports', $request_id, $conn);
                        
                        // Clear file reference from database
                        $referencesCleared = clearFileReferences('rabid_reports', $request_id, $conn);
                        
                        $message .= "Rabid Report #$request_id - $report_title\n";
                        $message .= "Reason: $sms_reason\n";
                        $message .= "Processed by: $admin_name\n";
                        $message .= $closing_message;
                        
                        $response = ['success' => true];
                        
                        // Add file deletion status to response for debugging
                        if (!$filesDeleted || !$referencesCleared) {
                            $response['warning'] = 'Report marked as false but proof file may not have been deleted properly';
                        }
                        break;
                        
                    default:
                        throw new Exception('Invalid action');
                }
                break;
                
            default:
                throw new Exception('Invalid request type');
        }

        // Send SMS if phone number exists
        if ($phone) {
            $formatted_phone = formatPhoneNumber($phone);
            if (!sendSMS($message, [$formatted_phone])) {
                error_log("Failed to send SMS for $request_type $request_id to $formatted_phone");
            } else {
                error_log("SMS sent successfully to $formatted_phone for $request_type $request_id");
            }
        } else {
            error_log("No phone number found for $request_type $request_id");
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Error in update_status.php: " . $e->getMessage());
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