<?php
session_start();
include '../../../includes/db.php';

if (!isset($_SESSION['mswd_admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$date = $_POST['date'] ?? null;

if (!$date) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit();
}

try {
    // Count already approved requests for the selected date
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
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => $count,
        'date' => $date
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}