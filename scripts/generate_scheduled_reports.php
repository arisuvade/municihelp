<!-- run at weekly and monthly -->

<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';

// set timezone to Asia/Manila for accurate time
date_default_timezone_set('Asia/Manila');

// determine report type based on argument
$reportType = $argv[1] ?? 'weekly'; // can be 'weekly' or 'monthly'

// generate reports for both sections
generateReports('Financial Assistance', $reportType);
generateReports('Request Processing', $reportType);

function generateReports($section, $reportType) {
    $type = ($section == 'Financial Assistance') ? 'Assistance' : 'Request';
    $dateRange = getDateRange($reportType);
    $fileName = generateFileName($type, $reportType);
    
    // get data from database
    $data = getReportData($section, $dateRange['start'], $dateRange['end']);
    $barangayStats = getBarangayStats($section, $dateRange['start'], $dateRange['end']);
    
    // generate PDF (simplified version)
    generatePDF($type, $data, $barangayStats, $dateRange, $fileName, $reportType);
    
    // generate Excel (full data version)
    generateExcel($type, $data, $dateRange, $fileName);
}

function getDateRange($reportType) {
    if ($reportType == 'weekly') {
        $start = date('Y-m-d', strtotime('last tuesday'));
        $end = date('Y-m-d');
    } else { // monthly
        $start = date('Y-m-01');
        $end = date('Y-m-t');
    }
    return ['start' => $start, 'end' => $end];
}

function generateFileName($type, $reportType) {
    if ($reportType == 'weekly') {
        $weekNumber = ceil(date('j') / 7);
        return strtolower($type) . '_report_week' . $weekNumber . '_' . strtolower(date('F_Y'));
    } else {
        return strtolower($type) . '_report_' . strtolower(date('F_Y'));
    }
}

function getReportData($section, $startDate, $endDate) {
    global $conn;
    
    $filter = ($section == 'Financial Assistance') ? "LIKE '%Assistance%'" : "LIKE '%Request%'";
    
    $query = "SELECT 
                ar.id, 
                at.name as program, 
                CONCAT(ar.last_name, ', ', ar.first_name) as applicant,
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
              AND ar.created_at BETWEEN ? AND ?
              ORDER BY ar.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $endDate = $endDate . ' 23:59:59';
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getBarangayStats($section, $startDate, $endDate) {
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
              AND ar.created_at BETWEEN ? AND ?
              GROUP BY b.name
              ORDER BY b.name";
    
    $stmt = $conn->prepare($query);
    $endDate = $endDate . ' 23:59:59';
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function generatePDF($type, $data, $barangayStats, $dateRange, $fileName, $reportType) {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L'
    ]);
    
    // report header
    $html = '<div style="text-align:center; margin-bottom:20px;">
                <h1>MuniciHelp ' . ucfirst($type) . ' Report</h1>
                <h3>Municipality of Pulilan, Bulacan</h3>
                <h4>For Vice Mayor RJ Peralta</h4>
                <p><strong>' . ucfirst($reportType) . ' Report</strong> | ' . 
                $dateRange['start'] . ' to ' . $dateRange['end'] . '</p>
            </div>';
    
    // main data table
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
    
    // brgy stats
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
    
    // notes section
    $html .= '<div style="margin-top:30px;">
                <h3>Notes & Observations</h3>
                <p>1. This report covers ' . strtolower($type) . ' requests from ' . 
                   $dateRange['start'] . ' to ' . $dateRange['end'] . '</p>
                <p>2. Total ' . strtolower($type) . ' requests processed: ' . count($data) . '</p>
                <p>3. Report generated on: ' . date('Y-m-d H:i:s') . '</p>
              </div>';
    
    $mpdf->WriteHTML($html);
    $filePath = __DIR__ . '/reports/' . strtolower($type) . '/' . $fileName . '.pdf';
    ensureDirectoryExists(dirname($filePath));
    $mpdf->Output($filePath, 'F');
}

function generateExcel($type, $data, $dateRange, $fileName) {
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
    
    // save file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $filePath = __DIR__ . '/reports/' . strtolower($type) . '/' . $fileName . '.xlsx';
    ensureDirectoryExists(dirname($filePath));
    $writer->save($filePath);
}

function ensureDirectoryExists($dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}