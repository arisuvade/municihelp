<?php
/**
 * Script to delete uploaded files for completed requests older than 7 days
 * Runs daily to clean up files for: mswd_requests, assistance_requests, rabid_reports
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get the root directory path
$root_dir = dirname(__DIR__);

// Include required files
require_once $root_dir . '/includes/db.php';
require_once $root_dir . '/includes/delete_request_files.php';

// Initialize log
$logContent = "=== Completed Files Cleanup Started ===\n";
$logContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
$logContent .= "Root Directory: " . $root_dir . "\n\n";

// Calculate date threshold (7 days ago)
$thresholdDate = date('Y-m-d', strtotime('-7 days'));

try {
    // Process each table
    $tables = ['mswd_requests', 'assistance_requests', 'rabid_reports'];
    $totalProcessed = 0;
    $totalFilesDeleted = 0;
    $totalErrors = 0;

    foreach ($tables as $tableName) {
        $logContent .= "Processing table: $tableName\n";
        
        // Get completed requests older than 7 days that still have file references
        $query = "SELECT id FROM $tableName 
                 WHERE status IN ('completed', 'verified')
                 AND DATE(updated_at) <= ? 
                 AND (";
        
        // Build condition to check if any file field is not NULL
        $fileFields = [];
        switch ($tableName) {
            case 'mswd_requests':
                for ($i = 1; $i <= 8; $i++) {
                    $fileFields[] = "requirement_path_$i IS NOT NULL";
                }
                break;
                
            case 'assistance_requests':
                $fileFields = [
                    "specific_request_path IS NOT NULL",
                    "indigency_cert_path IS NOT NULL", 
                    "id_copy_path IS NOT NULL",
                    "id_copy_path_2 IS NOT NULL",
                    "request_letter_path IS NOT NULL"
                ];
                break;
                
            case 'rabid_reports':
                $fileFields = ["proof_path IS NOT NULL"];
                break;
        }
        
        $query .= implode(" OR ", $fileFields) . ")";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $thresholdDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tableProcessed = 0;
        $tableFilesDeleted = 0;
        $tableErrors = 0;
        
        while ($row = $result->fetch_assoc()) {
            $requestId = $row['id'];
            $logContent .= "  Processing request ID: $requestId\n";
            
            // Delete files and clear references
            $filesDeleted = deleteRequestFiles($tableName, $requestId, $conn);
            $referencesCleared = clearFileReferences($tableName, $requestId, $conn);
            
            if ($filesDeleted && $referencesCleared) {
                $logContent .= "    ✓ Successfully deleted files and cleared references\n";
                $tableFilesDeleted++;
            } else {
                $logContent .= "    ✗ Failed to delete files or clear references\n";
                $tableErrors++;
            }
            
            $tableProcessed++;
            $totalProcessed++;
        }
        
        $stmt->close();
        
        $logContent .= "  Table summary: $tableProcessed requests processed, ";
        $logContent .= "$tableFilesDeleted files deleted, $tableErrors errors\n\n";
        
        $totalFilesDeleted += $tableFilesDeleted;
        $totalErrors += $tableErrors;
    }
    
    // Final summary
    $logContent .= "=== Summary ===\n";
    $logContent .= "Total requests processed: $totalProcessed\n";
    $logContent .= "Total files deleted: $totalFilesDeleted\n";
    $logContent .= "Total errors: $totalErrors\n";
    $logContent .= "Threshold date: $thresholdDate\n";
    
} catch (Exception $e) {
    $logContent .= "ERROR: " . $e->getMessage() . "\n";
    $logContent .= "Trace: " . $e->getTraceAsString() . "\n";
}

// Save log
$logFile = $root_dir . '/scripts/delete_completed_files_log.txt';
file_put_contents($logFile, $logContent . "\n", FILE_APPEND);

// Output for web/cli
if (php_sapi_name() === 'cli') {
    echo $logContent;
} else {
    header('Content-Type: text/plain');
    echo $logContent;
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}