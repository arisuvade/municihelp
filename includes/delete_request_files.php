<?php
/**
 * Delete uploaded files for cancelled/declined requests (MySQLi version)
 * 
 * @param string $tableName Name of the table
 * @param int $requestId ID of the request
 * @param mysqli $conn Database connection
 * @return bool True if files were deleted successfully
 */
function deleteRequestFiles($tableName, $requestId, $conn) {
    // Get the file paths from the database
    $filePaths = [];
    
    try {
        switch ($tableName) {
            case 'ambulance_requests':
                $stmt = $conn->prepare("SELECT patient_id_path, requester_id_path FROM ambulance_requests WHERE id = ?");
                $stmt->bind_param("i", $requestId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                if ($row) {
                    if (!empty($row['patient_id_path'])) $filePaths[] = $row['patient_id_path'];
                    if (!empty($row['requester_id_path'])) $filePaths[] = $row['requester_id_path'];
                }
                break;
                
            case 'assistance_requests':
                $stmt = $conn->prepare("SELECT specific_request_path, indigency_cert_path, id_copy_path, id_copy_path_2, request_letter_path FROM assistance_requests WHERE id = ?");
                $stmt->bind_param("i", $requestId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                if ($row) {
                    $fields = ['specific_request_path', 'indigency_cert_path', 'id_copy_path', 'id_copy_path_2', 'request_letter_path'];
                    foreach ($fields as $field) {
                        if (!empty($row[$field])) $filePaths[] = $row[$field];
                    }
                }
                break;
                
            case 'mswd_requests':
                $stmt = $conn->prepare("SELECT requirement_path_1, requirement_path_2, requirement_path_3, requirement_path_4, requirement_path_5, requirement_path_6, requirement_path_7, requirement_path_8 FROM mswd_requests WHERE id = ?");
                $stmt->bind_param("i", $requestId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                if ($row) {
                    for ($i = 1; $i <= 8; $i++) {
                        $field = "requirement_path_$i";
                        if (!empty($row[$field])) $filePaths[] = $row[$field];
                    }
                }
                break;
                
            case 'rabid_reports':
                $stmt = $conn->prepare("SELECT proof_path FROM rabid_reports WHERE id = ?");
                $stmt->bind_param("i", $requestId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                if ($row && !empty($row['proof_path'])) {
                    $filePaths[] = $row['proof_path'];
                }
                break;
                
            default:
                error_log("Unknown table: $tableName");
                return false;
        }
        
        // Delete the files
        $success = true;
        foreach ($filePaths as $filePath) {
            // Handle relative paths that start with ../../
            if (strpos($filePath, '../') === 0) {
                // For relative paths, resolve them relative to the current script's directory
                $scriptDir = dirname(__FILE__);
                $fullPath = realpath($scriptDir . '/' . $filePath);
            } else {
                // For absolute paths or paths relative to document root
                // Remove any leading slashes or dots to make it relative to document root
                $cleanPath = ltrim($filePath, '/.');
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $cleanPath;
            }
            
            // Check if the file exists and is within the document root for security
            if ($fullPath && file_exists($fullPath) && strpos($fullPath, $_SERVER['DOCUMENT_ROOT']) === 0) {
                if (!unlink($fullPath)) {
                    error_log("Failed to delete file: $fullPath (original path: $filePath)");
                    $success = false;
                } else {
                    error_log("Successfully deleted file: $fullPath");
                }
            } else {
                error_log("File not found, invalid path, or outside document root: $fullPath (original path: $filePath)");
                $success = false;
            }
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log("Error in deleteRequestFiles: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear file references from database after deletion
 * 
 * @param string $tableName Name of the table
 * @param int $requestId ID of the request
 * @param mysqli $conn Database connection
 * @return bool True if update was successful
 */
function clearFileReferences($tableName, $requestId, $conn) {
    try {
        switch ($tableName) {
            case 'ambulance_requests':
                $sql = "UPDATE ambulance_requests SET patient_id_path = NULL, requester_id_path = NULL WHERE id = ?";
                break;
                
            case 'assistance_requests':
                $sql = "UPDATE assistance_requests SET specific_request_path = NULL, indigency_cert_path = NULL, id_copy_path = NULL, id_copy_path_2 = NULL, request_letter_path = NULL WHERE id = ?";
                break;
                
            case 'mswd_requests':
                $sql = "UPDATE mswd_requests SET requirement_path_1 = NULL, requirement_path_2 = NULL, requirement_path_3 = NULL, requirement_path_4 = NULL, requirement_path_5 = NULL, requirement_path_6 = NULL, requirement_path_7 = NULL, requirement_path_8 = NULL WHERE id = ?";
                break;
                
            case 'rabid_reports':
                $sql = "UPDATE rabid_reports SET proof_path = NULL WHERE id = ?";
                break;
                
            default:
                return false;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $requestId);
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error in clearFileReferences: " . $e->getMessage());
        return false;
    }
}

/**
 * Main function to handle request cancellation/declination with file deletion
 * 
 * @param string $tableName Name of the table
 * @param int $requestId ID of the request
 * @param mysqli $conn Database connection
 * @return bool True if operation was successful
 */
function handleRequestCancellation($tableName, $requestId, $conn) {
    // First delete the files
    $filesDeleted = deleteRequestFiles($tableName, $requestId, $conn);
    
    // Then clear the file references from database
    $referencesCleared = clearFileReferences($tableName, $requestId, $conn);
    
    return $filesDeleted && $referencesCleared;
}