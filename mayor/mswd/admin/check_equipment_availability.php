<?php
session_start();
include '../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipmentTypeId = $_POST['equipment_type_id'];
    
    $query = $conn->prepare("
        SELECT available_quantity 
        FROM equipment_inventory 
        WHERE equipment_type_id = ?
    ");
    $query->bind_param("i", $equipmentTypeId);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'available_quantity' => $row['available_quantity']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Equipment not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>