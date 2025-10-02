<?php
session_start();
require_once '../../../includes/db.php';
require_once '../../../vendor/autoload.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['mswd_admin_id'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
}

// Get admin info
$adminId = $_SESSION['mswd_admin_id'];
$adminQuery = $GLOBALS['conn']->query("SELECT name FROM admins WHERE id = $adminId");
$adminData = $adminQuery->fetch_assoc();
$adminName = $adminData['name'] ?? 'Admin';

// Basic validation
$required = ['start_date', 'end_date'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        die(json_encode(['success' => false, 'error' => "$field is required"]));
    }
}

// Create reports directory if needed
$reportsDir = '../../../reports/mayor/mswd/adhoc/';
if (!file_exists($reportsDir) && !mkdir($reportsDir, 0777, true)) {
    die(json_encode(['success' => false, 'error' => 'Failed to create directory']));
}

// Generate filename with timestamp
$timestamp = date('Ymd_His');
$fileName = isset($_POST['filename']) ? $_POST['filename'] : 'mswd_report_' . $timestamp;
$response = ['success' => true];

// Get report data with filters
$data = getReportData($adminId, $_POST);

// Process PDF if requested
if (isset($_POST['generate_pdf']) && $_POST['generate_pdf'] == '1') {
    try {
        $pdfFilename = $fileName . '.pdf';
        generatePDF($data, $adminName, $_POST['start_date'], $_POST['end_date'], $pdfFilename, isset($_POST['with_amount']) && $_POST['with_amount'] == '1');
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
        generateExcel($data, $adminName, $_POST['start_date'], $_POST['end_date'], $excelFilename, isset($_POST['with_amount']) && $_POST['with_amount'] == '1');
        $response['file'] = $reportsDir . $excelFilename;
    } catch (Exception $e) {
        error_log("Excel Error: " . $e->getMessage());
        $response = ['success' => false, 'error' => 'Excel generation failed'];
    }
}

echo json_encode($response);

function getReportData($adminId, $postData) {
    global $conn;
    
    $startDate = $postData['start_date'] . ' 00:00:00';
    $endDate = $postData['end_date'] . ' 23:59:59';
    
    $query = "SELECT 
                mr.id,
                mr.first_name,
                mr.middle_name,
                mr.last_name,
                mr.birthday,
                mr.amount,
                mt.name as program,
                b.name as barangay,
                mr.status,
                mr.created_at,
                mr.updated_at,
                mr.is_walkin,
                mr.walkin_admin_id,
                mr.approvedby_admin_id,
                mr.approved2by_admin_id,
                mr.completedby_admin_id,
                mr.declinedby_admin_id,
                mr.cancelledby_admin_id,
                mr.rescheduledby_admin_id,
                mr.complete_address,
                mr.precint_number,
                mr.relation_id,
                fr.filipino_term as relation,
                mr.queue_date,
                mr.reason,
                mr.recipient,
                mr.relation_to_recipient,
                mr.released_date,
                wa.name as walkin_admin_name,
                aa.name as approved_admin_name,
                aa2.name as approved2_admin_name,
                ca.name as completed_admin_name,
                da.name as declined_admin_name,
                cda.name as cancelled_admin_name,
                ra.name as rescheduled_admin_name,
                COALESCE(mr.updated_at, mr.created_at) as action_date
              FROM mswd_requests mr
              JOIN mswd_types mt ON mr.assistance_id = mt.id
              LEFT JOIN barangays b ON mr.barangay_id = b.id
              LEFT JOIN family_relations fr ON mr.relation_id = fr.id
              LEFT JOIN admins wa ON mr.walkin_admin_id = wa.id
              LEFT JOIN admins aa ON mr.approvedby_admin_id = aa.id
              LEFT JOIN admins aa2 ON mr.approved2by_admin_id = aa2.id
              LEFT JOIN admins ca ON mr.completedby_admin_id = ca.id
              LEFT JOIN admins da ON mr.declinedby_admin_id = da.id
              LEFT JOIN admins cda ON mr.cancelledby_admin_id = cda.id
              LEFT JOIN admins ra ON mr.rescheduledby_admin_id = ra.id
              WHERE (
                  (mr.walkin_admin_id = ?) OR 
                  (mr.approvedby_admin_id = ?) OR 
                  (mr.approved2by_admin_id = ?) OR 
                  (mr.completedby_admin_id = ?) OR 
                  (mr.declinedby_admin_id = ?) OR 
                  (mr.cancelledby_admin_id = ?) OR
                  (mr.rescheduledby_admin_id = ?)
              ) AND mr.status != 'pending' 
              AND mr.is_walkin = 0  -- EXCLUDE WALK-INS
              AND COALESCE(mr.updated_at, mr.created_at) BETWEEN ? AND ?";
    
    $params = [
        $adminId,
        $adminId,
        $adminId,
        $adminId,
        $adminId,
        $adminId,
        $adminId,
        $startDate,
        $endDate
    ];
    $types = "iiiiiiiss";
    
    // Program filter - handle parent/child relationships
    if (!empty($postData['program'])) {
        // First check if the selected program is a parent
        $programQuery = $conn->prepare("SELECT id, parent_id FROM mswd_types WHERE id = ?");
        $programQuery->bind_param("i", $postData['program']);
        $programQuery->execute();
        $programResult = $programQuery->get_result()->fetch_assoc();
        
        if ($programResult['parent_id'] === null) {
            // It's a parent - get all its children
            $query .= " AND (mr.assistance_id = ? OR mr.assistance_id IN (
                SELECT id FROM mswd_types WHERE parent_id = ?
            ))";
            $types .= "ii";
            array_push($params, $postData['program'], $postData['program']);
        } else {
            // It's a child - just get this specific program
            $query .= " AND mr.assistance_id = ?";
            $types .= "i";
            array_push($params, $postData['program']);
        }
    }
    
    // Other filters remain the same
    if (!empty($postData['status'])) {
        $query .= " AND mr.status = ?";
        $types .= "s";
        array_push($params, $postData['status']);
    }
    
    if (!empty($postData['barangay'])) {
        $query .= " AND mr.barangay_id = ?";
        $types .= "i";
        array_push($params, $postData['barangay']);
    }
    
    if (isset($postData['request_type']) && $postData['request_type'] !== '') {
        $query .= " AND mr.is_walkin = ?";
        $types .= "i";
        array_push($params, $postData['request_type'] === 'walkin' ? 1 : 0);
    }
    
    $query .= " ORDER BY action_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function calculateAge($birthday) {
    if (!$birthday) return 'N/A';
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

function formatBirthday($birthday) {
    if (!$birthday) return 'N/A';
    return date('M j, Y', strtotime($birthday));
}

function formatDateTime($datetime) {
    if (!$datetime) return 'N/A';
    return date('M j, Y h:i A', strtotime($datetime));
}

function formatDateOnly($datetime) {
    if (!$datetime) return 'N/A';
    return date('M j, Y', strtotime($datetime));
}

function formatDateRange($start, $end) {
    return date('F j, Y', strtotime($start)) . ' to ' . date('F j, Y', strtotime($end));
}

function generatePDF($data, $adminName, $startDate, $endDate, $filename, $withAmount) {
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
    $mswdApprovedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'mswd_approved'; }));
    $mayorApprovedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'mayor_approved'; }));
    $declinedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'declined'; }));
    $cancelledOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'cancelled'; }));
    
    $totalRequests = count($onlineRequests);
    
    $html = '<div style="font-family: Arial; line-height: 1.5;">
        <h2 style="text-align:center;">MSWD Report</h2>
        <h3 style="text-align:center;">' . formatDateRange($startDate, $endDate) . '</h3>
        
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
                    <td>MSWD Approved</td>
                    <td>' . $mswdApprovedOnline . '</td>
                    <td>' . $mswdApprovedOnline . '</td>
                </tr>
                <tr>
                    <td>Mayor Approved</td>
                    <td>' . $mayorApprovedOnline . '</td>
                    <td>' . $mayorApprovedOnline . '</td>
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
                    <th width="11%">Mayor Approved By</th>
                    <th width="11%">MSWD Approved By</th>
                    <th width="11%">Completed By</th>
                    <th width="11%">Declined By</th>
                    <th width="11%">Cancelled By</th>';
    
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

        // Format status display
        $statusDisplay = str_replace('_', ' ', $item['status']);
        if ($item['status'] === 'mswd_approved') {
            $statusDisplay = 'MSWD Approved';
        } else {
            $statusDisplay = ucfirst($statusDisplay);
        }

        $html .= '<tr>
            <td>' . htmlspecialchars($fullName) . '</td>
            <td>' . calculateAge($item['birthday']) . '</td>
            <td>' . htmlspecialchars($item['program']) . '</td>
            <td>' . $statusDisplay . '</td>
            <td>' . ($item['approved_admin_name'] ?? 'N/A') . '</td>
            <td>' . ($item['approved2_admin_name'] ?? 'N/A') . '</td>
            <td>' . ($item['completed_admin_name'] ?? 'N/A') . '</td>
            <td>' . ($item['declined_admin_name'] ?? 'N/A') . '</td>
            <td>' . ($item['cancelled_admin_name'] ?? 'N/A') . '</td>';

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

function generateExcel($data, $adminName, $startDate, $endDate, $filename, $withAmount) {
    global $reportsDir;
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers with Request Type as first column
    $headers = [
        'Request Type', 'ID', 'Name', 'Age', 'Birthday', 'Barangay', 
        'Complete Address', 'Precinct Number', 'Program', 'Status',
        'Mayor Approved By', 'MSWD Approved By', 'Completed By', 'Declined By', 'Cancelled By', 'Rescheduled By',
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
        
        // Format status display
        $statusDisplay = str_replace('_', ' ', $item['status']);
        if ($item['status'] === 'mswd_approved') {
            $statusDisplay = 'MSWD Approved';
        } else {
            $statusDisplay = ucfirst($statusDisplay);
        }

        $rowData = [
            'Online', // All requests are online now
            $item['id'],
            $fullName,
            calculateAge($item['birthday']),
            date('F j, Y', strtotime($item['birthday'])),
            $item['barangay'],
            $item['complete_address'],
            empty($item['precint_number']) || $item['precint_number'] == '0' ? 'N/A' : $item['precint_number'],
            $item['program'],
            $statusDisplay,
            $item['approved_admin_name'] ?? 'N/A',
            $item['approved2_admin_name'] ?? 'N/A',
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
    
    // Auto-size columns - FIXED THIS PART
    $highestColumn = $sheet->getHighestColumn();
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($reportsDir . $filename);
}