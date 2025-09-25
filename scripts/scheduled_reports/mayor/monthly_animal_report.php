<?php
// Get the root directory from the global variable set by the parent script
global $root_dir;

// Now require files using the absolute path
require_once $root_dir . '/includes/db.php';
require_once $root_dir . '/vendor/autoload.php';

date_default_timezone_set('Asia/Manila');

// Use absolute path for reports directory
$reportsDir = $root_dir . '/reports/mayor/animal/scheduled/';
if (!file_exists($reportsDir)) {
    if (!mkdir($reportsDir, 0755, true)) {
        throw new Exception("Failed to create reports directory: " . $reportsDir);
    }
}

// Calculate last month's date range (consistent with main runner)
$firstDayLastMonth = new DateTime('first day of last month');
$lastDayLastMonth = new DateTime('last day of last month');

$startDate = $firstDayLastMonth->format('Y-m-d');
$endDate = $lastDayLastMonth->format('Y-m-d');
$dateRangeForFilename = $firstDayLastMonth->format('Y-m') . '_' . $lastDayLastMonth->format('Y-m-d');

// Generate filename with timestamp
$reportMonth = $firstDayLastMonth->format('Y-m');
$fileName = 'animal_report_' . $reportMonth;

// Get report data
try {
    $data = getAnimalReportData($startDate, $endDate);
    
    // Generate both PDF and Excel by default for scheduled reports
    try {
        // Generate PDF
        $pdfFilename = $fileName . '.pdf';
        generateAnimalPDF($data, 'System Auto-Generated', $startDate, $endDate, $pdfFilename);
        echo "PDF generated successfully: " . $reportsDir . $pdfFilename . "\n";
        
        // Generate Excel
        $excelFilename = $fileName . '.xlsx';
        generateAnimalExcel($data, 'System Auto-Generated', $startDate, $endDate, $excelFilename);
        echo "Excel generated successfully: " . $reportsDir . $excelFilename . "\n";
        
    } catch (Exception $e) {
        error_log("Report Generation Error: " . $e->getMessage());
        echo "Report generation failed: " . $e->getMessage() . "\n";
        exit(1); // Return non-zero exit code for failure
    }
    
} catch (Exception $e) {
    error_log("Data Retrieval Error: " . $e->getMessage());
    echo "Data retrieval failed: " . $e->getMessage() . "\n";
    exit(1);
}

function getAnimalReportData($startDate, $endDate) {
    global $conn;
    
    $startDate .= ' 00:00:00';
    $endDate .= ' 23:59:59';
    
    // Base query for all programs - NO ADMIN FILTERING and EXCLUDES PENDING STATUS
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
              WHERE dc.status != 'pending'
              AND (dc.status != 'cancelled' OR dc.cancelledby_admin_id IS NOT NULL)
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
              WHERE da.status != 'pending'
              AND (da.status != 'cancelled' OR da.cancelledby_admin_id IS NOT NULL)
              AND da.user_id IS NOT NULL  -- EXCLUDE WALK-INS
              AND COALESCE(da.updated_at, da.created_at) BETWEEN ? AND ?
              
              UNION ALL
              
              SELECT 
                'rabid_report' as program_type,
                rr.id,
                rr.user_id,
                rr.first_name,
                rr.middle_name,
                rr.last_name,
                rr.location as complete_address,
                NULL as name_of_dog,
                NULL as age_of_dog,
                CASE 
                    WHEN rr.status = 'false_report' THEN 'False Report'
                    ELSE rr.status 
                END as status,
                rr.created_at,
                rr.updated_at,
                rr.reason as declined_reason,
                rr.proof_path as handover_photo_path,
                NULL as approvedby_admin_name,
                NULL as declinedby_admin_name,
                NULL as completedby_admin_name,
                a4.name as cancelledby_admin_name,
                u.phone as user_phone,
                NULL as adoption_reason,
                a5.name as verifiedby_admin_name,
                COALESCE(rr.updated_at, rr.created_at) as action_date
              FROM rabid_reports rr
              LEFT JOIN users u ON rr.user_id = u.id
              LEFT JOIN admins a4 ON rr.cancelledby_admin_id = a4.id
              LEFT JOIN admins a5 ON rr.verifiedby_admin_id = a5.id
              WHERE rr.status != 'pending'
              AND (rr.status != 'cancelled' OR rr.cancelledby_admin_id IS NOT NULL)
              AND rr.user_id IS NOT NULL  -- EXCLUDE WALK-INS
              AND COALESCE(rr.updated_at, rr.created_at) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssss", $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Sort by action date descending
    usort($results, function($a, $b) {
        return strtotime($b['action_date']) - strtotime($a['action_date']);
    });
    
    return $results;
}

function generateAnimalPDF($data, $adminName, $startDate, $endDate, $filename) {
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
    
    // Calculate summary counts
    $summary = [
        'dog_claiming' => ['online' => 0],
        'dog_adoption' => ['online' => 0],
        'rabid_report' => ['online' => 0]
    ];
    
    $statusCounts = [
        'Approved' => 0,
        'Declined' => 0,
        'Completed' => 0,
        'Cancelled' => 0,
        'Verified' => 0,
        'False Report' => 0
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
            if ($status === 'verified') $status = 'Verified';
            // 'False Report' remains as is
            
            $statusCounts[$status]++;
        }
    }
    
    $totalRequests = count($onlineRequests);
    
    $html = '<div style="font-family: Arial; line-height: 1.5;">
        <h2 style="text-align:center;">Animal Requests Monthly Report</h2>
        <h3 style="text-align:center;">' . formatAnimalDateRange($startDate, $endDate) . '</h3>
        
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
                        <th width="12%">Name</th>
                        <th width="12%">Program</th>
                        <th width="8%">Status</th>
                        <th width="13%">Approved By</th>
                        <th width="13%">Declined By</th>
                        <th width="13%">Completed By</th>
                        <th width="13%">Cancelled By</th>
                        <th width="13%">Verified By</th>
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
            if ($status === 'verified') $status = 'Verified';
            // 'False Report' remains as is

            $html .= '<tr>
                <td>' . htmlspecialchars($fullName) . '</td>
                <td>' . getProgramName($item['program_type']) . '</td>
                <td>' . $status . '</td>
                <td>' . ($item['approvedby_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['declinedby_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['completedby_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['cancelledby_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['verifiedby_admin_name'] ?? 'N/A') . '</td>
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

function generateAnimalExcel($data, $adminName, $startDate, $endDate, $filename) {
    global $reportsDir;
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers with Declined By added after Approved By
    $headers = [
        'Request Type', 'Program', 'ID', 'Name', 'Status', 
        'Approved By', 'Declined By', 'Completed By', 'Cancelled By', 
        'Verified By', 'Declined/Cancelled Reason'
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
        if ($status === 'verified') $status = 'Verified';
        // 'False Report' remains as is
        
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
            $item['verifiedby_admin_name'] ?? 'N/A',
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

function formatAnimalDateRange($start, $end) {
    return date('F j, Y', strtotime($start)) . ' to ' . date('F j, Y', strtotime($end));
}

function getProgramName($programType) {
    switch ($programType) {
        case 'dog_claiming': return 'Dog Claiming';
        case 'dog_adoption': return 'Dog Adoption';
        case 'rabid_report': return 'Rabid Report';
        default: return $programType;
    }
}