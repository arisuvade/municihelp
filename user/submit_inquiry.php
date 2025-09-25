<?php
session_start();
require_once __DIR__ . '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_inquiry'])) {
        // Handle inquiry cancellation
        $inquiry_id = intval($_POST['inquiry_id'] ?? 0);
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("UPDATE inquiries SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $inquiry_id, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Inquiry cancelled successfully';
            echo json_encode(['success' => true]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to cancel inquiry']);
            exit;
        }
    } else {
        // Handle new inquiry submission
        $department_id = intval($_POST['department_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        // Validate
        $errors = [];
        if ($department_id <= 0) {
            $errors[] = "Please select a department";
        }
        if (empty($question)) {
            $errors[] = "Please enter your question";
        } elseif (strlen($question) > 1000) {
            $errors[] = "Question is too long (max 1000 characters)";
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO inquiries (user_id, department_id, question, status) VALUES (?, ?, ?, 'pending')");
            $stmt->bind_param("iis", $user_id, $department_id, $question);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Your inquiry has been submitted successfully!';
                header("Location: inquiry.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to submit inquiry. Please try again.";
                header("Location: inquiry.php");
                exit;
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
            header("Location: inquiry.php");
            exit;
        }
    }
}

header("Location: inquiry.php");
exit;
?>