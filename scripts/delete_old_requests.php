<!-- run at 12am everyday -->

<?php
require_once '../includes/db.php';

// delete pictures
function deleteFile($filePath) {
    if (!empty($filePath)) {
        $fullPath = '../' . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
            return true;
        }
    }
    return false;
}

// delete request over 1 month
function deleteOldRequests($conn) {
    $currentDate = new DateTime();
    $currentDay = $currentDate->format('d');
    
    // for fewer days months
    if ($currentDay > 28) {
        $lastMonth = clone $currentDate;
        $lastMonth->modify('first day of last month');
        $lastDayOfLastMonth = $lastMonth->format('t');
        
        // if date exceed then use the first day of the month
        if ($currentDay > $lastDayOfLastMonth) {
            $cutoffDay = $lastDayOfLastMonth;
        } else {
            $cutoffDay = $currentDay;
        }
    } else {
        $cutoffDay = $currentDay;
    }
    
    // cutoff
    $cutoffDate = clone $currentDate;
    $cutoffDate->modify('first day of last month');
    
    if ($cutoffDay <= $cutoffDate->format('t')) {
        $cutoffDate->setDate($cutoffDate->format('Y'), $cutoffDate->format('m'), $cutoffDay);
    } else {
        $cutoffDate->setDate($cutoffDate->format('Y'), $cutoffDate->format('m'), $cutoffDate->format('t'));
    }
    
    $cutoffDate->setTime(0, 0, 0);
    $formattedCutoff = $cutoffDate->format('Y-m-d H:i:s');
    
    // get all requests to be deleted with their file paths
    $stmt = $conn->prepare("
        SELECT 
            specific_request_path, 
            indigency_cert_path, 
            id_copy_path, 
            request_letter_path 
        FROM assistance_requests 
        WHERE created_at < ?
    ");
    $stmt->bind_param("s", $formattedCutoff);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $filesDeleted = 0;
    while ($row = $result->fetch_assoc()) {
        // delete each file
        deleteFile($row['specific_request_path']) && $filesDeleted++;
        deleteFile($row['indigency_cert_path']) && $filesDeleted++;
        deleteFile($row['id_copy_path']) && $filesDeleted++;
        deleteFile($row['request_letter_path']) && $filesDeleted++;
    }
    
    // Now delete the records
    $stmt = $conn->prepare("DELETE FROM assistance_requests WHERE created_at < ?");
    $stmt->bind_param("s", $formattedCutoff);
    $stmt->execute();
    $recordsDeleted = $stmt->affected_rows;
    
    return [
        'records_deleted' => $recordsDeleted,
        'files_deleted' => $filesDeleted
    ];
}

// execute the function
try {
    $result = deleteOldRequests($conn);
    echo "Successfully deleted {$result['records_deleted']} records and {$result['files_deleted']} files older than 1 month.";
} catch (Exception $e) {
    echo "Error deleting old requests: " . $e->getMessage();
}

// log the deletion
file_put_contents('logs/old_requests_deleted.log', date('Y-m-d H:i:s') . " - Old requests deleted\n", FILE_APPEND);