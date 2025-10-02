<?php
session_start();
require_once '../../../includes/db.php';
require_once '../../../vendor/autoload.php';

date_default_timezone_set('Asia/Manila');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['assistance_admin_id'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
}

// Get admin info
$adminId = $_SESSION['assistance_admin_id'];
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
$reportsDir = '../../../reports/vice_mayor/assistance/adhoc/';
if (!file_exists($reportsDir)) {
    if (!mkdir($reportsDir, 0755, true)) {
        $error = error_get_last();
        die(json_encode(['success' => false, 'error' => 'Failed to create directory: ' . $error['message']]));
    }
} else {
    // Check if directory is writable
    if (!is_writable($reportsDir)) {
        die(json_encode(['success' => false, 'error' => 'Directory is not writable: ' . $reportsDir]));
    }
}

// Increase memory limit for PDF generation
ini_set('memory_limit', '256M');
set_time_limit(300); // 5 minutes

// Generate filename with timestamp
$timestamp = date('Ymd_His');
$fileName = isset($_POST['filename']) ? $_POST['filename'] : 'vm_atty_cruz_report_' . $timestamp;
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
        $response = ['success' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()];
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
        $response = ['success' => false, 'error' => 'Excel generation failed: ' . $e->getMessage()];
    }
}

echo json_encode($response);

function getReportData($adminId, $postData) {
    global $conn;
    
    $startDate = $postData['start_date'] . ' 00:00:00';
    $endDate = $postData['end_date'] . ' 23:59:59';
    
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
              WHERE (
                  (ar.walkin_admin_id = ?) OR 
                  (ar.approvedby_admin_id = ?) OR 
                  (ar.completedby_admin_id = ?) OR 
                  (ar.declinedby_admin_id = ?) OR 
                  (ar.cancelledby_admin_id = ?) OR
                  (ar.rescheduledby_admin_id = ?)
              ) AND ar.status != 'pending' AND COALESCE(ar.updated_at, ar.created_at) BETWEEN ? AND ?";
    
    $params = [
        $adminId,
        $adminId,
        $adminId,
        $adminId,
        $adminId,
        $adminId,
        $startDate,
        $endDate
    ];
    $types = "iiiiiiss";
    
    // Request type filter - EXCLUDE WALK-INS if requested
    if (isset($postData['request_type']) && $postData['request_type'] === 'online') {
        $query .= " AND ar.is_walkin = 0";
    } else if (isset($postData['request_type']) && $postData['request_type'] === 'walkin') {
        $query .= " AND ar.is_walkin = 1";
    }
    
    // Program filter - handle parent/child relationships
    if (!empty($postData['program'])) {
        // First check if the selected program is a parent
        $programQuery = $conn->prepare("SELECT id, parent_id FROM assistance_types WHERE id = ?");
        $programQuery->bind_param("i", $postData['program']);
        $programQuery->execute();
        $programResult = $programQuery->get_result()->fetch_assoc();
        
        if ($programResult['parent_id'] === null) {
            // It's a parent - get all its children
            $query .= " AND (ar.assistance_id = ? OR ar.assistance_id IN (
                SELECT id FROM assistance_types WHERE parent_id = ?
            ))";
            $types .= "ii";
            array_push($params, $postData['program'], $postData['program']);
        } else {
            // It's a child - just get this specific program
            $query .= " AND ar.assistance_id = ?";
            $types .= "i";
            array_push($params, $postData['program']);
        }
    }
    
    // Other filters
    if (!empty($postData['status'])) {
        $query .= " AND ar.status = ?";
        $types .= "s";
        array_push($params, $postData['status']);
    }
    
    if (!empty($postData['barangay'])) {
        $query .= " AND ar.barangay_id = ?";
        $types .= "i";
        array_push($params, $postData['barangay']);
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
    
    // Separate online and walk-in requests
    $onlineRequests = array_filter($data, function($item) { return !$item['is_walkin']; });
    $walkinRequests = array_filter($data, function($item) { return $item['is_walkin']; });
    
    // Calculate summary counts
    $completedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'completed'; }));
    $approvedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'approved'; }));
    $declinedOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'declined'; }));
    $cancelledOnline = count(array_filter($onlineRequests, function($item) { return $item['status'] === 'cancelled'; }));
    
    $completedWalkin = count(array_filter($walkinRequests, function($item) { return $item['status'] === 'completed'; }));
    $approvedWalkin = count(array_filter($walkinRequests, function($item) { return $item['status'] === 'approved'; }));
    $declinedWalkin = count(array_filter($walkinRequests, function($item) { return $item['status'] === 'declined'; }));
    $cancelledWalkin = count(array_filter($walkinRequests, function($item) { return $item['status'] === 'cancelled'; }));
    
    $totalRequests = count($data);
    
    $html = '<div style="font-family: Arial; line-height: 1.5;">
        <h2 style="text-align:center;">Assistance Report</h2>
        <h3 style="text-align:center;">' . formatDateRange($startDate, $endDate) . '</h3>
        
        <h4>Summary</h4>
        <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size: 9pt;">
            <thead>
                <tr>
                    <th width="20%"></th>
                    <th width="20%">Online</th>
                    <th width="20%">Walk-In</th>
                    <th width="20%">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Completed</td>
                    <td>' . $completedOnline . '</td>
                    <td>' . $completedWalkin . '</td>
                    <td>' . ($completedOnline + $completedWalkin) . '</td>
                </tr>
                <tr>
                    <td>Approved</td>
                    <td>' . $approvedOnline . '</td>
                    <td>' . $approvedWalkin . '</td>
                    <td>' . ($approvedOnline + $approvedWalkin) . '</td>
                </tr>
                <tr>
                    <td>Declined</td>
                    <td>' . $declinedOnline . '</td>
                    <td>' . $declinedWalkin . '</td>
                    <td>' . ($declinedOnline + $declinedWalkin) . '</td>
                </tr>
                <tr>
                    <td>Cancelled</td>
                    <td>' . $cancelledOnline . '</td>
                    <td>' . $cancelledWalkin . '</td>
                    <td>' . ($cancelledOnline + $cancelledWalkin) . '</td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align:right;"><strong>Total Requests:</strong></td>
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
                <td>' . calculateAge($item['birthday']) . '</td>
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
    
    // Walk-in Requests Section
    if (!empty($walkinRequests)) {
        $html .= '<h4>Walk-in Requests</h4>
            <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size: 9pt;">
                <thead>
                    <tr>
                        <th width="12%">Name</th>
                        <th width="3%">Age</th>
                        <th width="12%">Program</th>
                        <th width="8%">Status</th>
                        <th width="19%">Approved By</th>
                        <th width="18%">Completed By</th>
                        <th width="18%">Cancelled By</th>';
        
        if ($withAmount) {
            $html .= '<th width="10%">Amount</th>';
        }
        
        $html .= '</tr>
                </thead>
                <tbody>';
        
        foreach ($walkinRequests as $item) {
            $fullName = $item['last_name'] . ', ' . $item['first_name'];
            if (!empty($item['middle_name'])) {
                $fullName .= ' ' . substr($item['middle_name'], 0, 1) . '.';
            }

            $html .= '<tr>
                <td>' . htmlspecialchars($fullName) . '</td>
                <td>' . calculateAge($item['birthday']) . '</td>
                <td>' . htmlspecialchars($item['program']) . '</td>
                <td>' . ucfirst($item['status']) . '</td>
                <td>' . htmlspecialchars($item['walkin_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['completed_admin_name'] ?? 'N/A') . '</td>
                <td>' . ($item['cancelled_admin_name'] ?? 'N/A') . '</td>';

            if ($withAmount) {
                $html .= '<td style="text-align:right;">' . 
                    ($item['amount'] ? '₱' . number_format($item['amount'], 2) : 'N/A') . '</td>';
            }

            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
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
        'Approved By', 'Completed By', 'Declined By', 'Cancelled By', 'Rescheduled By',
        'Queue Date', 'Declined Reason', 'Recipient', 'Relation to Recipient', 'Released Date'
    ];
    
    if ($withAmount) {
        $headers[] = 'Amount';
    }
    
    $sheet->fromArray($headers, null, 'A1');
    
    // Make headers bold
    $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    
    $row = 2; // Start data from row 2
    
    // Process all requests
    foreach ($data as $item) {
        $fullName = $item['last_name'] . ', ' . $item['first_name'];
        if (!empty($item['middle_name'])) {
            $fullName .= ' ' . substr($item['middle_name'], 0, 1) . '.';
        }
        
        // Determine approved by based on request type
        $approvedBy = $item['is_walkin'] 
            ? ($item['walkin_admin_name'] ?? 'N/A') 
            : ($item['approved_admin_name'] ?? 'N/A');
        
        $rowData = [
            $item['is_walkin'] ? 'Walk-in' : 'Online', // Request Type
            $item['id'],
            $fullName,
            calculateAge($item['birthday']),
            date('F j, Y', strtotime($item['birthday'])),
            $item['barangay'],
            $item['complete_address'],
            empty($item['precint_number']) || $item['precint_number'] == '0' ? 'N/A' : $item['precint_number'],
            $item['program'],
            ucfirst($item['status']),
            $approvedBy,
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