<?php
session_start();
require_once '../../../includes/db.php';
require_once '../../../vendor/autoload.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['pound_admin_id'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
}

// Get admin info
$adminId = $_SESSION['pound_admin_id'];
$adminQuery = $GLOBALS['conn']->query("SELECT name FROM admins WHERE id = $adminId");
$adminData = $adminQuery->fetch_assoc();
$adminName = $adminData['name'] ?? 'Animal Admin';

// Basic validation
$required = ['start_date', 'end_date'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        die(json_encode(['success' => false, 'error' => "$field is required"]));
    }
}

// Create reports directory if needed
$reportsDir = '../../../reports/mayor/animal/adhoc/';
if (!file_exists($reportsDir) && !mkdir($reportsDir, 0777, true)) {
    die(json_encode(['success' => false, 'error' => 'Failed to create directory']));
}

// Generate filename
$fileName = 'animal_report';
$response = ['success' => true];

// Get report data with filters - only actions by the current admin
$data = getReportData($_POST, $adminId);

// Process PDF if requested
if (isset($_POST['generate_pdf']) && $_POST['generate_pdf'] == '1') {
    try {
        $pdfFilename = $fileName . '.pdf';
        generatePDF($data, $adminName, $_POST['start_date'], $_POST['end_date'], $pdfFilename);
        $response['file'] = $reportsDir . $pdfFilename;
    } catch (Exception $e) {
        error_log("PDF Error: " . $e->getMessage());
        $response = ['success' => false, 'error' => 'PDF generation failed'];
    }
}

// Process Excel if requested
if (isset($_POST['generate_excel']) && $_POST['generate_excel'] == '1') {
    try {
        $excelFilename = $fileName . '.xlsx';
        generateExcel($data, $adminName, $_POST['start_date'], $_POST['end_date'], $excelFilename);
        $response['file'] = $reportsDir . $excelFilename;
    } catch (Exception $e) {
        error_log("Excel Error: " . $e->getMessage());
        $response = ['success' => false, 'error' => 'Excel generation failed'];
    }
}

echo json_encode($response);

function getReportData($postData, $adminId) {
    global $conn;
    
    $startDate = $postData['start_date'] . ' 00:00:00';
    $endDate = $postData['end_date'] . ' 23:59:59';
    
    // Base query for dog claiming and adoption only - filtered by actions performed by the current admin
    // REMOVED RABID REPORT QUERY
    $query = "SELECT 
                'dog_claiming' as program_type,
                dc.id,
                dc.user_id,
                dc.first_name,
                dc.middle_name,
                dc.last_name,
                dc.complete_address,
                dc.name_of_dog,
                dc.age_of_dog,
                dc.status,
                dc.created_at,
                dc.updated_at,
                dc.reason as declined_reason,
                dc.handover_photo_path,
                a1.name as approvedby_admin_name,
                a2.name as declinedby_admin_name,
                a3.name as completedby_admin_name,
                a4.name as cancelledby_admin_name,
                u.phone as user_phone,
                NULL as adoption_reason,
                NULL as verifiedby_admin_name,
                COALESCE(dc.updated_at, dc.created_at) as action_date
              FROM dog_claims dc
              LEFT JOIN admins a1 ON dc.approvedby_admin_id = a1.id
              LEFT JOIN admins a2 ON dc.declinedby_admin_id = a2.id
              LEFT JOIN admins a3 ON dc.completedby_admin_id = a3.id
              LEFT JOIN admins a4 ON dc.cancelledby_admin_id = a4.id
              LEFT JOIN users u ON dc.user_id = u.id
              WHERE (dc.approvedby_admin_id = ? OR 
                    dc.declinedby_admin_id = ? OR 
                    dc.completedby_admin_id = ? OR 
                    dc.cancelledby_admin_id = ?)
              AND dc.user_id IS NOT NULL  -- EXCLUDE WALK-INS
              AND COALESCE(dc.updated_at, dc.created_at) BETWEEN ? AND ?
              
              UNION ALL
              
              SELECT 
                'dog_adoption' as program_type,
                da.id,
                da.user_id,
                da.first_name,
                da.middle_name,
                da.last_name,
                da.complete_address,
                NULL as name_of_dog,
                NULL as age_of_dog,
                da.status,
                da.created_at,
                da.updated_at,
                da.reason as declined_reason,
                da.handover_photo_path,
                a1.name as approvedby_admin_name,
                a2.name as declinedby_admin_name,
                a3.name as completedby_admin_name,
                a4.name as cancelledby_admin_name,
                u.phone as user_phone,
                da.adoption_reason,
                NULL as verifiedby_admin_name,
                COALESCE(da.updated_at, da.created_at) as action_date
              FROM dog_adoptions da
              LEFT JOIN admins a1 ON da.approvedby_admin_id = a1.id
              LEFT JOIN admins a2 ON da.declinedby_admin_id = a2.id
              LEFT JOIN admins a3 ON da.completedby_admin_id = a3.id
              LEFT JOIN admins a4 ON da.cancelledby_admin_id = a4.id
              LEFT JOIN users u ON da.user_id = u.id
              WHERE (da.approvedby_admin_id = ? OR 
                    da.declinedby_admin_id = ? OR 
                    da.completedby_admin_id = ? OR 
                    da.cancelledby_admin_id = ?)
              AND da.user_id IS NOT NULL  -- EXCLUDE WALK-INS
              AND COALESCE(da.updated_at, da.created_at) BETWEEN ? AND ?";
    
    $params = [
        $adminId, $adminId, $adminId, $adminId, $startDate, $endDate,
        $adminId, $adminId, $adminId, $adminId, $startDate, $endDate
    ];
    $types = "ssssssssssss";
    
    // First execute the base query to get all results
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die(json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]));
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Now apply filters in PHP (more reliable than trying to modify the complex SQL)
    $filteredResults = [];
    
    foreach ($results as $row) {
        $include = true;
        
        // Apply program filter
        if (!empty($postData['program']) && $postData['program'] !== 'all') {
            if ($row['program_type'] !== $postData['program']) {
                $include = false;
            }
        }
        
        // Apply status filter
        if (!empty($postData['status']) && $postData['status'] !== 'all') {
            $statusMatch = false;
            
            // Case-insensitive comparison for statuses
            if (strtolower($row['status']) === strtolower($postData['status'])) {
                $statusMatch = true;
            }
            
            if (!$statusMatch) {
                $include = false;
            }
        }
        
        // Apply request type filter
        if (isset($postData['request_type']) && $postData['request_type'] !== 'all') {
            if ($postData['request_type'] === 'online' && empty($row['user_id'])) {
                $include = false;
            }
            if ($postData['request_type'] === 'walkin' && !empty($row['user_id'])) {
                $include = false;
            }
        }
        
        if ($include) {
            $filteredResults[] = $row;
        }
    }
    
    // Sort by action date descending
    usort($filteredResults, function($a, $b) {
        return strtotime($b['action_date']) - strtotime($a['action_date']);
    });
    
    return $filteredResults;
}

function generatePDF($data, $adminName, $startDate, $endDate, $filename) {
    global $reportsDir;
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'default_font' => 'arial'
    ]);
    
    // Filter out walk-in requests (only keep online requests)
    $onlineRequests = array_filter($data, function($item) { return !empty($item['user_id']); });
    
    // Calculate summary counts - REMOVED RABID REPORT
    $summary = [
        'dog_claiming' => ['online' => 0],
        'dog_adoption' => ['online' => 0]
    ];
    
    $statusCounts = [
        'Approved' => 0,
        'Declined' => 0,
        'Completed' => 0,
        'Cancelled' => 0
    ];
    
    foreach ($data as $item) {
        // Only count online requests
        if (!empty($item['user_id'])) {
            $summary[$item['program_type']]['online']++;
            
            // Normalize status for counting
            $status = $item['status'];
            if ($status === 'approved') $status = 'Approved';
            if ($status === 'declined') $status = 'Declined';
            if ($status === 'completed') $status = 'Completed';
            if ($status === 'cancelled') $status = 'Cancelled';
            
            $statusCounts[$status]++;
        }
    }
    
    $totalRequests = count($onlineRequests);
    
    $html = '<div style="font-family: Arial; line-height: 1.5;">
        <h2 style="text-align:center;">Animal Requests Report</h2>
        <h3 style="text-align:center;">' . formatDateRange($startDate, $endDate) . '</h3>
        
        <h4>Summary by Program</h4>
        <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size: 9pt;">
            <thead>
                <tr>
                    <th width="33%">Program</th>
                    <th width="33%">Online Requests</th>
                    <th width="34%">Total</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($summary as $program => $counts) {
        $html .= '<tr>
            <td>' . getProgramName($program) . '</td>
            <td>' . $counts['online'] . '</td>
            <td>' . $counts['online'] . '</td>
        </tr>';
    }
    
    $html .= '<tr>
            <td colspan="2" style="text-align:right;"><strong>Total Requests:</strong></td>
            <td><strong>' . $totalRequests . '</strong></td>
        </tr>
        </tbody>
        </table>
        
        <h4 style="margin-top:20px;">Summary by Status</h4>
        <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size: 9pt;">
            <thead>
                <tr>
                    <th width="50%">Status</th>
                    <th width="50%">Count</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($statusCounts as $status => $count) {
        if ($count > 0) {
            $html .= '<tr>
                <td>' . $status . '</td>
                <td>' . $count . '</td>
            </tr>';
        }
    }
    
    $html .= '</tbody></table>';
    
    // Online Requests Section
    if (!empty($onlineRequests)) {
        $html .= '<hr style="border:1px solid #eee; margin:10px 0;">
            <h4>Online Requests</h4>
            <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size: 9pt;">
                <thead>
                    <tr>
                        <th width="15%">Name</th>
                        <th width="15%">Program</th>
                        <th width="10%">Status</th>
                        <th width="15%">Approved By</th>
                        <th width="15%">Declined By</th>
                        <th width="15%">Completed By</th>
                        <th width="15%">Cancelled By</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($onlineRequests as $item) {
            $fullName = $item['last_name'] . ', ' . $item['first_name'];
            if (!empty($item['middle_name'])) {
                $fullName .= ' ' . substr($item['middle_name'], 0, 1) . '.';
            }

            // Normalize status for display
            $status = $item['status'];
            if ($status === 'approved') $status = 'Approved';
            if ($status === 'declined') $status = 'Declined';
            if ($status === 'completed') $status = 'Completed';
            if ($status === 'cancelled') $status = 'Cancelled';

            $html .= '<tr>
                <td>' . htmlspecialchars($fullName) . '</td>
                <td>' . getProgramName($item['program_type']) . '</td>
                <td>' . $status . '</td>
                <td>' . ($item['approvedby_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['declinedby_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['completedby_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['cancelledby_admin_name'] ?? 'N/A') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table><br>';
    }
    
    $html .= '<hr style="border:1px solid #eee; margin:20px 0;">
        <p style="font-size:0.9em;">Generated by: ' . htmlspecialchars($adminName) . '</p>
        <p style="font-size:0.9em;">Generated on: ' . date('F j, Y h:i A') . '</p>
    </div>';
    
    $mpdf->WriteHTML($html);
    $mpdf->Output($reportsDir . $filename, 'F');
}

function generateExcel($data, $adminName, $startDate, $endDate, $filename) {
    global $reportsDir;
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers with Declined By added after Approved By - REMOVED VERIFIED BY
    $headers = [
        'Request Type', 'Program', 'ID', 'Name', 'Status', 
        'Approved By', 'Declined By', 'Completed By', 'Cancelled By', 
        'Declined/Cancelled Reason'
    ];
    
    $sheet->fromArray($headers, null, 'A1');
    
    // Make headers bold
    $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    
    $row = 2; // Start data from row 2
    
    // Process only online requests (exclude walk-ins)
    foreach ($data as $item) {
        // Skip walk-in requests
        if (empty($item['user_id'])) {
            continue;
        }
        
        $fullName = $item['last_name'] . ', ' . $item['first_name'];
        if (!empty($item['middle_name'])) {
            $fullName .= ' ' . substr($item['middle_name'], 0, 1) . '.';
        }
        
        // Normalize status for display
        $status = $item['status'];
        if ($status === 'approved') $status = 'Approved';
        if ($status === 'declined') $status = 'Declined';
        if ($status === 'completed') $status = 'Completed';
        if ($status === 'cancelled') $status = 'Cancelled';
        
        $rowData = [
            'Online', // All requests are online now
            getProgramName($item['program_type']),
            $item['id'],
            $fullName,
            $status, // Use the normalized status
            $item['approvedby_admin_name'] ?? 'N/A',
            $item['declinedby_admin_name'] ?? 'N/A', // Added Declined By
            $item['completedby_admin_name'] ?? 'N/A',
            $item['cancelledby_admin_name'] ?? 'N/A',
            $item['declined_reason'] ?? 'N/A'
        ];
        
        $sheet->fromArray($rowData, null, 'A' . $row);
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($reportsDir . $filename);
}

function formatDateRange($start, $end) {
    return date('F j, Y', strtotime($start)) . ' to ' . date('F j, Y', strtotime($end));
}

function getProgramName($programType) {
    switch ($programType) {
        case 'dog_claiming': return 'Dog Claiming';
        case 'dog_adoption': return 'Dog Adoption';
        default: return $programType;
    }
}