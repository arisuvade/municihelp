<?php
session_start();
include '../../includes/db.php';

if (!isset($_SESSION['mayor_superadmin_id'])) {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Get superadmin info
$mayor_superadmin_id = $_SESSION['mayor_superadmin_id'];
$superadmin_query = $conn->query("SELECT name FROM admins WHERE id = $mayor_superadmin_id");
$superadmin_data = $superadmin_query->fetch_assoc();
$superadmin_name = $superadmin_data['name'] ?? 'Superadmin';

$pageTitle = 'MSWD Reports';
include '../../includes/header.php';

// Get all programs and barangays (no filtering)
$programsQuery = "SELECT id, name, parent_id FROM mswd_types ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, name";
$programsResult = $conn->query($programsQuery);
$programsArray = [];
while ($program = $programsResult->fetch_assoc()) {
    $programsArray[] = $program;
}

$barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name");
$statuses = ['mswd_approved', 'mayor_approved', 'completed', 'declined', 'cancelled'];

// Function to get scheduled reports with the pattern mswd_report_YYYY-MM
function getScheduledReports($dir) {
    $reports = [];
    if (file_exists($dir)) {
        $files = scandir($dir, SCANDIR_SORT_DESCENDING);
        foreach ($files as $file) {
            if (preg_match('/^mswd_report_(\d{4})-(\d{2})\.(pdf|xlsx)$/', $file, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                $baseName = "mswd_report_{$year}-{$month}";
                $displayName = date('F Y', strtotime("{$year}-{$month}-01"));
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                
                // Group by base name (without extension)
                if (!isset($reports[$baseName])) {
                    $reports[$baseName] = [
                        'name' => $baseName,
                        'display_name' => $displayName,
                        'pdf_path' => null,
                        'excel_path' => null,
                        'modified' => date('Y-m-d H:i', filemtime($dir . $file))
                    ];
                }
                
                if ($extension === 'pdf') {
                    $reports[$baseName]['pdf_path'] = $dir . $file;
                } elseif ($extension === 'xlsx') {
                    $reports[$baseName]['excel_path'] = $dir . $file;
                }
            }
        }
    }
    // Return only reports that have both PDF and Excel versions
    return array_filter($reports, function($report) {
        return $report['pdf_path'] && $report['excel_path'];
    });
}

// Get scheduled reports
$scheduledReports = getScheduledReports('../../reports/mayor/mswd/scheduled/');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="../../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../../assets/images/logo-pulilan.png">
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --approved: #28A745;
            --completed: #4361ee;
            --declined: #DC3545;
            --cancelled: #6C757D;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            width: 95%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--munici-green), #0E3B85);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
       .superadmin-badge {
        position: absolute;
        right: 20px;
        top: 20px;
        background: #28a745;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        color: white;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .superadmin-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
    }
    
    .superadmin-badge i {
        margin-right: 8px;
        filter: brightness(0) invert(1); /* Makes the icon white */
    }
        
        .filter-card {
            margin-bottom: 1.5rem;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .recent-activity-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .recent-activity-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.2rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .report-type-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-report-type {
            min-width: 220px;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
            background-color: var(--munici-green);
            border-color: var(--munici-green);
            color: white;
        }
        
        .btn-report-type:hover {
            background-color: #0E3B85;
            border-color: #0E3B85;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .date-range-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-range-container .input-group-text {
            background-color: transparent;
            border: none;
            padding: 0 5px;
            font-weight: bold;
        }
        
        /* Scheduled Reports Card Style - Matching walkin.php */
        .scheduled-reports-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }
        
        .report-card {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .report-card:hover {
            background-color: #f8f9fa;
        }
        
        .report-card.selected {
            background-color: var(--munici-green-light);
            border-left: 3px solid var(--munici-green);
        }
        
        .no-reports {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-reports i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        .no-reports h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .no-reports p {
            font-size: 1rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
            
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .report-type-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-report-type {
                width: 100%;
            }
            
            .btn-group.w-100 {
                flex-wrap: wrap;
            }

            .btn-group.w-100 .btn {
                flex: 1 0 100%;
                margin-bottom: 5px;
            }
            
            .date-range-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-range-container .input-group-text {
                display: none;
            }
            
            .superadmin-badge {
                position: static;
                margin-top: 10px;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">MSWD Reports</h1>
                    <p class="mb-0">Generate comprehensive MSWD reports</p>
                </div>
                <div class="superadmin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($superadmin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- MSWD Request Report Section -->
        <div class="recent-activity-card card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Generate MSWD Request Report</h4>
            </div>
            <div class="card-body">
                <form id="reportForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date Range</label>
                                <div class="date-range-container">
                                    <input type="date" class="form-control" name="start_date" required>
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Quick Date Range</label>
                                <div class="btn-group w-100">
                                    <button type="button" class="btn btn-outline-secondary date-preset" data-days="0">Today</button>
                                    <button type="button" class="btn btn-outline-secondary date-preset" data-days="7">Last 7 Days</button>
                                    <button type="button" class="btn btn-outline-secondary date-preset" data-days="30">Last 30 Days</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Request Type</label>
                                <select class="form-control" name="request_type">
                                    <option value="">All Requests</option>
                                    <option value="online">Online Request</option>
                                    <!-- <option value="walkin">Walk-in Request</option> -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Program</label>
                                <select class="form-control" name="program">
                                    <option value="">All Programs</option>
                                    <?php 
                                    $currentParent = null;
                                    foreach ($programsArray as $program): 
                                        if ($program['parent_id'] === null) {
                                            $currentParent = $program['id'];
                                            ?>
                                            <option value="<?= $program['id'] ?>">
                                                <?= htmlspecialchars($program['name']) ?>
                                            </option>
                                            <?php
                                            // Find and display children
                                            foreach ($programsArray as $child) {
                                                if ($child['parent_id'] == $currentParent) {
                                                    ?>
                                                    <option value="<?= $child['id'] ?>">
                                                        &nbsp;&nbsp;&#8627; <?= htmlspecialchars($child['name']) ?>
                                                    </option>
                                                    <?php
                                                }
                                            }
                                        }
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach($statuses as $status): 
                                        $displayStatus = str_replace('_', ' ', $status);
                                        if ($status === 'mswd_approved') {
                                            $displayStatus = 'MSWD Approved';
                                        } else {
                                            $displayStatus = ucfirst($displayStatus);
                                        }
                                    ?>
                                        <option value="<?= $status ?>"><?= $displayStatus ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Barangay</label>
                                <select class="form-control" name="barangay">
                                    <option value="">All Barangays</option>
                                    <?php while($barangay = $barangays->fetch_assoc()): ?>
                                        <option value="<?= $barangay['id'] ?>"><?= $barangay['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="report-type-buttons">
                        <button type="button" id="generateReport" class="btn btn-report-type">
                            <i class="fas fa-file-invoice-dollar"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Scheduled Monthly Reports Section -->
        <div class="recent-activity-card card">
            <div class="card-header">
                <h4 class="mb-0">Scheduled Monthly Reports</h4>
            </div>
            <div class="card-body">
                <?php if (empty($scheduledReports)): ?>
                    <div class="no-reports">
                        <i class="fas fa-file-alt"></i>
                        <h4>No Reports Found</h4>
                        <p>There are no scheduled monthly reports available.</p>
                    </div>
                <?php else: ?>
                    <div class="scheduled-reports-container">
                        <?php foreach($scheduledReports as $report): ?>
                            <div class="report-card" 
                                 data-pdf="<?= $report['pdf_path'] ?>" 
                                 data-excel="<?= $report['excel_path'] ?>"
                                 data-name="<?= $report['name'] ?>">
                                <strong><?= $report['name'] ?></strong>
                                <div class="text-muted">
                                    <i class="fas fa-calendar-alt"></i> <?= $report['display_name'] ?>
                                    <span class="float-end">
                                        <i class="fas fa-clock"></i> <?= $report['modified'] ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
$(document).ready(function() {
    // Set default dates for both forms
    var today = new Date().toISOString().split('T')[0];
    $('input[name="start_date"]').val(today);
    $('input[name="end_date"]').val(today);

    // Update the date preset handler to work correctly
    $('.date-preset').click(function() {
        var days = $(this).data('days');
        var endDate = new Date();
        var startDate = new Date();
        startDate.setDate(endDate.getDate() - days);
        
        // Find the date inputs in the same form
        var form = $(this).closest('form');
        form.find('input[name="start_date"]').val(startDate.toISOString().split('T')[0]);
        form.find('input[name="end_date"]').val(endDate.toISOString().split('T')[0]);
    });

    // Function to trigger single download
    function triggerDownload(url, filename) {
        // Create temporary link
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Report generation function for MSWD requests
    async function generateReport() {
        const btn = $('#generateReport');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Generating...');
        
        try {
            // Collect form data
            const formData = $('#reportForm').serializeArray();
            formData.push({name: 'with_amount', value: '1'});
            
            // Use fixed filenames that will be overwritten each time
            const baseFilename = 'mswd_report';
            
            // 1. Generate and download PDF
            const pdfResponse = await $.ajax({
                url: 'generate_mswd_report.php',
                method: 'POST',
                data: formData.concat([
                    {name: 'generate_pdf', value: '1'},
                    {name: 'filename', value: baseFilename},
                    {name: 'superadmin', value: '1'} // Flag for superadmin report
                ]),
                dataType: 'json'
            });

            if (!pdfResponse.success) {
                throw new Error(pdfResponse.error || 'Failed to generate PDF');
            }

            // Trigger PDF download
            triggerDownload(pdfResponse.file, baseFilename + '.pdf');
            
            // 2. Generate and download Excel
            const excelResponse = await $.ajax({
                url: 'generate_mswd_report.php',
                method: 'POST',
                data: formData.concat([
                    {name: 'generate_excel', value: '1'},
                    {name: 'filename', value: baseFilename},
                    {name: 'superadmin', value: '1'} // Flag for superadmin report
                ]),
                dataType: 'json'
            });

            if (!excelResponse.success) {
                throw new Error(excelResponse.error || 'Failed to generate Excel');
            }

            // Trigger Excel download
            triggerDownload(excelResponse.file, baseFilename + '.xlsx');
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Downloads Complete!',
                html: `Reports downloaded:<br>
                      <strong>PDF:</strong> ${baseFilename}.pdf<br>
                      <strong>Excel:</strong> ${baseFilename}.xlsx`,
                confirmButtonColor: '#4CAF50',
                timer: 5000,
                timerProgressBar: true
            });

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to generate reports',
                confirmButtonColor: '#4CAF50'
            });
        } finally {
            btn.prop('disabled', false).html(originalText);
        }
    }

    // Handle report card click
    $('.report-card').click(function() {
        const pdfPath = $(this).data('pdf');
        const excelPath = $(this).data('excel');
        const fileName = $(this).data('name');
        
        // Highlight selected card
        $('.report-card').removeClass('selected');
        $(this).addClass('selected');
        
        // Trigger both downloads with a small delay between them
        triggerDownload(pdfPath, fileName + '.pdf');
        
        // Small delay to ensure both downloads work
        setTimeout(() => {
            triggerDownload(excelPath, fileName + '.xlsx');
        }, 500);
        
        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Downloads Started!',
            html: `Downloading both report files for ${fileName.replace('mswd_report_', '').replace('_', ' ')}`,
            confirmButtonColor: '#4CAF50',
            timer: 3000,
            timerProgressBar: true
        });
    });

    // Button handler
    $('#generateReport').click(generateReport);
});
</script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>