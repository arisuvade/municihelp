<?php
session_start();
require_once '../includes/db.php';
require_once '../vendor/autoload.php';

// Asia/Manila timezone
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
}

// financial or request admin
$adminSection = $_SESSION['admin_section'] ?? 'Financial Assistance';
$type = ($adminSection == 'Financial Assistance') ? 'Assistance' : 'Request';

// form data
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$programId = $_POST['program'] ?? '';
$status = $_POST['status'] ?? '';
$barangayId = $_POST['barangay'] ?? '';
$outputPdf = isset($_POST['pdf_summary']) && $_POST['pdf_summary'] == '1';
$outputExcel = isset($_POST['excel_full_data']) && $_POST['excel_full_data'] == '1';

// validate dates
if (empty($startDate) || empty($endDate)) {
    die(json_encode(['success' => false, 'error' => 'Start date and end date are required']));
}

// rreate reports directory if it doesn't exist
$reportsDir = '../reports/' . strtolower($type) . '/';
if (!file_exists($reportsDir)) {
    if (!mkdir($reportsDir, 0777, true)) {
        die(json_encode(['success' => false, 'error' => 'Failed to create reports directory']));
    }
}

// generate unique filename
$fileName = strtolower($type) . '_report_' . date('Ymd_His') . '_' . uniqid();

$data = getReportData($adminSection, $startDate, $endDate, $programId, $status, $barangayId);
$barangayStats = getBarangayStats($adminSection, $startDate, $endDate, $programId, $status, $barangayId);

// generate reports
$response = ['success' => true];
if ($outputPdf) {
    try {
        $pdfFilename = $fileName . '.pdf';
        generatePDF($type, $data, $barangayStats, ['start' => $startDate, 'end' => $endDate], $pdfFilename, 'custom');
        $response['pdf'] = $reportsDir . $pdfFilename;
    } catch (Exception $e) {
        error_log("PDF generation error: " . $e->getMessage());
        $response['pdf_error'] = 'Failed to generate PDF';
    }
}

if ($outputExcel) {
    try {
        $excelFilename = $fileName . '.xlsx';
        generateExcel($type, $data, ['start' => $startDate, 'end' => $endDate], $excelFilename);
        $response['excel'] = $reportsDir . $excelFilename;
    } catch (Exception $e) {
        error_log("Excel generation error: " . $e->getMessage());
        $response['excel_error'] = 'Failed to generate Excel';
    }
}

// return success
echo json_encode($response);

function getReportData($section, $startDate, $endDate, $programId, $status, $barangayId) {
    global $conn;
    
    $filter = ($section == 'Financial Assistance') ? "LIKE '%Assistance%'" : "LIKE '%Request%'";
    
    $query = "SELECT 
                ar.id, 
                at.name as program, 
                CONCAT(ar.last_name, ', ', ar.first_name, IF(ar.middle_name IS NULL, '', CONCAT(' ', ar.middle_name))) as applicant,
                b.name as barangay,
                ar.status,
                DATE(ar.created_at) as date_submitted,
                IF(ar.status = 'pending', NULL, DATE(ar.updated_at)) as date_processed,
                ar.note
              FROM assistance_requests ar
              JOIN assistance_types at ON ar.assistance_id = at.id
              JOIN users u ON ar.user_id = u.id
              LEFT JOIN barangays b ON ar.barangay_id = b.id
              WHERE at.name $filter
              AND ar.created_at BETWEEN ? AND ?";
    
    $params = [];
    $types = "ss";
    $params[] = $startDate . ' 00:00:00';
    $params[] = $endDate . ' 23:59:59';
    
    if (!empty($programId)) {
        $query .= " AND ar.assistance_id = ?";
        $types .= "i";
        $params[] = $programId;
    }
    
    if (!empty($status)) {
        $query .= " AND ar.status = ?";
        $types .= "s";
        $params[] = $status;
    }
    
    if (!empty($barangayId)) {
        $query .= " AND ar.barangay_id = ?";
        $types .= "i";
        $params[] = $barangayId;
    }
    
    $query .= " ORDER BY ar.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getBarangayStats($section, $startDate, $endDate, $programId, $status, $barangayId) {
    global $conn;
    
    $filter = ($section == 'Financial Assistance') ? "LIKE '%Assistance%'" : "LIKE '%Request%'";
    
    $query = "SELECT 
                b.name as barangay,
                COUNT(ar.id) as total_requests,
                SUM(ar.status = 'approved') as approved,
                SUM(ar.status = 'declined') as declined,
                SUM(ar.status = 'pending') as pending
              FROM assistance_requests ar
              JOIN assistance_types at ON ar.assistance_id = at.id
              JOIN barangays b ON ar.barangay_id = b.id
              WHERE at.name $filter
              AND ar.created_at BETWEEN ? AND ?";
    
    $params = [];
    $types = "ss";
    $params[] = $startDate . ' 00:00:00';
    $params[] = $endDate . ' 23:59:59';
    
    if (!empty($programId)) {
        $query .= " AND ar.assistance_id = ?";
        $types .= "i";
        $params[] = $programId;
    }
    
    if (!empty($status)) {
        $query .= " AND ar.status = ?";
        $types .= "s";
        $params[] = $status;
    }
    
    if (!empty($barangayId)) {
        $query .= " AND ar.barangay_id = ?";
        $types .= "i";
        $params[] = $barangayId;
    }
    
    $query .= " GROUP BY b.name
                ORDER BY b.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function generatePDF($type, $data, $barangayStats, $dateRange, $fileName, $reportType) {
    global $reportsDir;
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L'
    ]);
    
    $html = '<div style="text-align:center; margin-bottom:20px;">
                <h1>MuniciHelp ' . ucfirst($type) . ' Report</h1>
                <h3>Municipality of Pulilan, Bulacan</h3>
                <h4>For Vice Mayor RJ Peralta</h4>
                <p><strong>' . ucfirst($reportType) . ' Report</strong> | ' . 
                $dateRange['start'] . ' to ' . $dateRange['end'] . '</p>
            </div>';
    
    $html .= '<table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size:10pt;">
                <tr>
                    <th>ID</th>
                    <th>Program</th>
                    <th>Applicant</th>
                    <th>Barangay</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                </tr>';
    
    foreach ($data as $row) {
        $html .= '<tr>
                    <td>' . $row['id'] . '</td>
                    <td>' . $row['program'] . '</td>
                    <td>' . $row['applicant'] . '</td>
                    <td>' . $row['barangay'] . '</td>
                    <td>' . ucfirst($row['status']) . '</td>
                    <td>' . $row['date_submitted'] . '</td>
                  </tr>';
    }
    
    $html .= '</table>';
    
    $html .= '<h3 style="margin-top:30px;">Barangay Summary</h3>
              <table border="1" cellspacing="0" cellpadding="5" width="100%" style="font-size:10pt;">
                <tr>
                    <th>Barangay</th>
                    <th>Total Requests</th>
                    <th>Approved</th>
                    <th>Declined</th>
                    <th>Pending</th>
                </tr>';
    
    foreach ($barangayStats as $stats) {
        $html .= '<tr>
                    <td>' . $stats['barangay'] . '</td>
                    <td>' . $stats['total_requests'] . '</td>
                    <td>' . $stats['approved'] . '</td>
                    <td>' . $stats['declined'] . '</td>
                    <td>' . $stats['pending'] . '</td>
                  </tr>';
    }
    
    $html .= '</table>';
    
    $html .= '<div style="margin-top:30px;">
                <h3>Notes & Observations</h3>
                <p>1. This report covers ' . strtolower($type) . ' requests from ' . 
                   $dateRange['start'] . ' to ' . $dateRange['end'] . '</p>
                <p>2. Total ' . strtolower($type) . ' requests processed: ' . count($data) . '</p>
                <p>3. Report generated on: ' . date('Y-m-d H:i:s') . '</p>
              </div>';
    
    $mpdf->WriteHTML($html);
    $mpdf->Output($reportsDir . $fileName, 'F');
}

function generateExcel($type, $data, $dateRange, $fileName) {
    global $reportsDir;
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->setCellValue('A1', 'MuniciHelp ' . $type . ' Report - Full Data');
    $sheet->setCellValue('A2', 'Date Range: ' . $dateRange['start'] . ' to ' . $dateRange['end']);
    
    $headers = ['ID', 'Program', 'Applicant', 'Barangay', 'Status', 
                'Date Submitted', 'Date Processed', 'Notes'];
    $sheet->fromArray($headers, null, 'A4');
    
    $rowData = [];
    foreach ($data as $item) {
        $rowData[] = [
            $item['id'],
            $item['program'],
            $item['applicant'],
            $item['barangay'],
            ucfirst($item['status']),
            $item['date_submitted'],
            $item['date_processed'] ?? '-',
            $item['note'] ?? '-'
        ];
    }
    $sheet->fromArray($rowData, null, 'A5');
    
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($reportsDir . $fileName);
}