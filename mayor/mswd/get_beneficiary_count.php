<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

try {
    // Count only ACTIVE beneficiaries
    $countQuery = "SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries WHERE status = 'Active'";
    $countResult = $conn->query($countQuery);
    $count = $countResult->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>