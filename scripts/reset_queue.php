<!-- run at wednesday 12am -->

<?php
require_once '../includes/db.php';

// set timezone to Asia/Manila for accurate time
date_default_timezone_set('Asia/Manila');

// cancel all approved requests that haven't been completed
$cancel_stmt = $conn->prepare("
    UPDATE assistance_requests 
    SET status = 'cancelled', 
        queue_number = NULL,
        queue_date = NULL,
        note = CONCAT(IFNULL(note, ''), ' [Automatically cancelled - Queue reset]')
    WHERE status = 'approved'
");

if (!$cancel_stmt->execute()) {
    // log error if needed
    file_put_contents('../logs/queue_reset.log', date('Y-m-d H:i:s') . " - Error cancelling requests\n", FILE_APPEND);
}

// log the reset
file_put_contents('logs/queue_reset.log', date('Y-m-d H:i:s') . " - Queue reset completed\n", FILE_APPEND);

echo "Queue reset completed successfully at " . date('Y-m-d H:i:s');