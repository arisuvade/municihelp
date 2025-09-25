<?php
// Get the root directory from the global variable set by the parent script
global $root_dir;

// Now require files using the absolute path
require_once $root_dir . '/includes/db.php';
require_once $root_dir . '/vendor/autoload.php';

date_default_timezone_set('Asia/Manila');

// Use absolute path for reports directory
$reportsDir = $root_dir . '/reports/vice_mayor/assistance/scheduled/';
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
$fileName = 'assistance_report_' . $reportMonth;

// Get report data
$data = getAssistanceReportData($startDate, $endDate);

// Generate both PDF and Excel by default for scheduled reports
try {
    // Generate PDF
    $pdfFilename = $fileName . '.pdf';
    generateAssistancePDF($data, 'System Auto-Generated', $startDate, $endDate, $pdfFilename, true);
    echo "PDF generated successfully: " . $reportsDir . $pdfFilename . "\n";
    
    // Generate Excel
    $excelFilename = $fileName . '.xlsx';
    generateAssistanceExcel($data, 'System Auto-Generated', $startDate, $endDate, $excelFilename, true);
    echo "Excel generated successfully: " . $reportsDir . $excelFilename . "\n";
} catch (Exception $e) {
    error_log("Report Generation Error: " . $e->getMessage());
    echo "Report generation failed: " . $e->getMessage() . "\n";
    exit(1); // Return non-zero exit code for failure
}


function getAssistanceReportData($startDate, $endDate) {
    global $conn;
    
    $startDate .= ' 00:00:00';
    $endDate .= ' 23:59:59';
    
    $query = "SELECT 
                ar.id,
                ar.first_name,
                ar.middle_name,
                ar.last_name,
                ar.birthday,
                ar.amount,
                at.name as program,
                b.name as barangay,
                ar.status,
                ar.created_at,
                ar.updated_at,
                ar.is_walkin,
                ar.walkin_admin_id,
                ar.approvedby_admin_id,
                ar.completedby_admin_id,
                ar.declinedby_admin_id,
                ar.cancelledby_admin_id,
                ar.rescheduledby_admin_id,
                ar.complete_address,
                ar.precint_number,
                ar.relation_id,
                fr.filipino_term as relation,
                ar.queue_date,
                ar.reason,
                ar.recipient,
                ar.relation_to_recipient,
                ar.released_date,
                ar.specific_request_path,
                ar.indigency_cert_path,
                ar.id_copy_path,
                ar.id_copy_path_2,
                ar.request_letter_path,
                wa.name as walkin_admin_name,
                aa.name as approved_admin_name,
                ca.name as completed_admin_name,
                da.name as declined_admin_name,
                cda.name as cancelled_admin_name,
                ra.name as rescheduled_admin_name,
                COALESCE(ar.updated_at, ar.created_at) as action_date
              FROM assistance_requests ar
              JOIN assistance_types at ON ar.assistance_id = at.id
              LEFT JOIN barangays b ON ar.barangay_id = b.id
              LEFT JOIN family_relations fr ON ar.relation_id = fr.id
              LEFT JOIN admins wa ON ar.walkin_admin_id = wa.id
              LEFT JOIN admins aa ON ar.approvedby_admin_id = aa.id
              LEFT JOIN admins ca ON ar.completedby_admin_id = ca.id
              LEFT JOIN admins da ON ar.declinedby_admin_id = da.id
              LEFT JOIN admins cda ON ar.cancelledby_admin_id = cda.id
              LEFT JOIN admins ra ON ar.rescheduledby_admin_id = ra.id
              WHERE ar.status != 'pending' 
              AND ar.is_walkin = 0  -- EXCLUDE WALK-INS
              AND COALESCE(ar.updated_at, ar.created_at) BETWEEN ? AND ?
              ORDER BY action_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function calculateAssistanceAge($birthday) {
    if (!$birthday) return 'N/A';
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

function formatAssistanceDateRange($start, $end) {
    return date('F j, Y', strtotime($start)) . ' to ' . date('F j, Y', strtotime($end));
}

function generateAssistancePDF($data, $adminName, $startDate, $endDate, $filename, $withAmount) {
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
    $onlineRequests = array_filter($data, function($item) { return !$item['is_walkin']; });
    
    // Calculate summary counts
    $completedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'completed'; }));
    $approvedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'approved'; }));
    $declinedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'declined'; }));
    $cancelledOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'cancelled'; }));
    
    $totalRequests = count($onlineRequests);
    
    $html = '<div style="font-family: Arial; line-height: 1.5;">
        <h2 style="text-align:center;">Monthly Assistance Report</h2>
        <h3 style="text-align:center;">' . formatAssistanceDateRange($startDate, $endDate) . '</h3>
        
        <h4>Summary</h4>
        <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size: 9pt;">
            <thead>
                <tr>
                    <th width="33%"></th>
                    <th width="33%">Online</th>
                    <th width="34%">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Completed</td>
                    <td>' . $completedOnline . '</td>
                    <td>' . $completedOnline . '</td>
                </tr>
                <tr>
                    <td>Approved</td>
                    <td>' . $approvedOnline . '</td>
                    <td>' . $approvedOnline . '</td>
                </tr>
                <tr>
                    <td>Declined</td>
                    <td>' . $declinedOnline . '</td>
                    <td>' . $declinedOnline . '</td>
                </tr>
                <tr>
                    <td>Cancelled</td>
                    <td>' . $cancelledOnline . '</td>
                    <td>' . $cancelledOnline . '</td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:right;"><strong>Total Requests:</strong></td>
                    <td><strong>' . $totalRequests . '</strong></td>
                </tr>
            </tbody>
        </table>
        <hr style="border:1px solid #eee; margin:10px 0;">';
    
    // Online Requests Section
    if (!empty($onlineRequests)) {
        $html .= '<h4>Online Requests</h4>
            <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size: 9pt;">
                <thead>
                    <tr>
                        <th width="12%">Name</th>
                        <th width="3%">Age</th>
                        <th width="12%">Program</th>
                        <th width="8%">Status</th>
                        <th width="11%">Approved By</th>
                        <th width="11%">Completed By</th>
                        <th width="11%">Declined By</th>
                        <th width="11%">Cancelled By</th>
                        <th width="11%">Rescheduled By</th>';
        
        if ($withAmount) {
            $html .= '<th width="10%">Amount</th>';
        }
        
        $html .= '</tr>
                </thead>
                <tbody>';
        
        foreach ($onlineRequests as $item) {
            $fullName = $item['last_name'] . ', ' . $item['first_name'];
            if (!empty($item['middle_name'])) {
                $fullName .= ' ' . substr($item['middle_name'], 0, 1) . '.';
            }

            $html .= '<tr>
                <td>' . htmlspecialchars($fullName) . '</td>
                <td>' . calculateAssistanceAge($item['birthday']) . '</td>
                <td>' . htmlspecialchars($item['program']) . '</td>
                <td>' . ucfirst($item['status']) . '</td>
                <td>' . ($item['approved_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['completed_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['declined_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['cancelled_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['rescheduled_admin_name'] ?? 'N/A') . '</td>';

            if ($withAmount) {
                $html .= '<td style="text-align:right;">' . 
                    ($item['amount'] ? '₱' . number_format($item['amount'], 2) : 'N/A') . '</td>';
            }

            $html .= '</tr>';
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

function generateAssistanceExcel($data, $adminName, $startDate, $endDate, $filename, $withAmount) {
    global $reportsDir;
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $headers = [
        'Request Type', 'ID', 'Name', 'Age', 'Birthday', 'Barangay', 
        'Complete Address', 'Precinct Number', 'Program', 'Status',
        'Processed By', 'Completed By', 'Declined By', 'Cancelled By', 'Rescheduled By',
        'Queue Date', 'Declined/Cancelled Reason', 'Recipient', 'Relation to Recipient', 'Released Date'
    ];
    
    if ($withAmount) {
        $headers[] = 'Amount';
    }
    
    $sheet->fromArray($headers, null, 'A1');
    
    // Make headers bold
    $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    
    $row = 2; // Start data from row 2
    
    // Process only online requests (exclude walk-ins)
    foreach ($data as $item) {
        // Skip walk-in requests
        if ($item['is_walkin']) {
            continue;
        }
        
        $fullName = $item['last_name'] . ', ' . $item['first_name'];
        if (!empty($item['middle_name'])) {
            $fullName .= ' ' . substr($item['middle_name'], 0, 1) . '.';
        }
        
        $rowData = [
            'Online', // All requests are online now
            $item['id'],
            $fullName,
            calculateAssistanceAge($item['birthday']),
            date('F j, Y', strtotime($item['birthday'])),
            $item['barangay'],
            $item['complete_address'],
            $item['precint_number'],
            $item['program'],
            ucfirst($item['status']),
            $item['approved_admin_name'] ?? 'N/A',
            $item['completed_admin_name'] ?? 'N/A',
            $item['declined_admin_name'] ?? 'N/A',
            $item['cancelled_admin_name'] ?? 'N/A',
            $item['rescheduled_admin_name'] ?? 'N/A',
            $item['queue_date'] ? date('F j, Y', strtotime($item['queue_date'])) : 'N/A',
            $item['reason'] ?? 'N/A',
            $item['recipient'] ?? 'N/A',
            $item['relation_to_recipient'] ?? 'N/A',
            $item['released_date'] ? date('F j, Y', strtotime($item['released_date'])) : 'N/A'
        ];
        
        if ($withAmount) {
            $rowData[] = $item['amount'] ? '₱' . number_format($item['amount'], 2) : 'N/A';
        }
        
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