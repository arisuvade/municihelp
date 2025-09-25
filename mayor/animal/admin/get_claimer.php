<?php
session_start();
include '../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'];
    
    // Get the claimer details from the dog_claims table
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, birthday, barangay_id FROM dog_claims WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $claim = $result->fetch_assoc();
        $first_name = $claim['first_name'];
        $last_name = $claim['last_name'];
        $birthday = $claim['birthday'];
        $barangay_id = $claim['barangay_id'];
        
        // Get the claimer's data from dog_claimers table by matching name, birthday, and barangay
        $stmt = $conn->prepare("SELECT total_claims FROM dog_claimers WHERE first_name = ? AND last_name = ? AND birthday = ? AND barangay_id = ?");
        $stmt->bind_param("sssi", $first_name, $last_name, $birthday, $barangay_id);
        $stmt->execute();
        $claimer_result = $stmt->get_result();
        
        if ($claimer_result->num_rows > 0) {
            $claimer = $claimer_result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'total_claims' => $claimer['total_claims']
            ]);
        } else {
            // If no record exists, it's their first claim
            echo json_encode([
                'success' => true,
                'total_claims' => 0 // First offense if no record exists
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Request not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>