<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminSection = $_SESSION['admin_section'] ?? 'Financial Assistance';
$displaySection = ($adminSection === 'Financial Assistance') ? 'Assistance' : 'Request';
$pageTitle = 'Ad-Hoc Reports';
include '../includes/header.php';

// filter option
$programs = $conn->query("SELECT id, name FROM assistance_types WHERE name " . 
    ($adminSection === 'Financial Assistance' ? "LIKE '%Assistance%'" : "LIKE '%Request%'") . 
    " ORDER BY name");
$barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name");
$statuses = ['pending', 'approved', 'declined', 'completed', 'cancelled'];

// scheduled reports
$reportsDir = '../scripts/reports/' . strtolower(str_replace(' ', '_', $adminSection)) . '/';
$scheduledReports = [];
if (file_exists($reportsDir)) {
    $files = scandir($reportsDir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $scheduledReports[] = $file;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --munici-green: #4CAF50;
            --munici-green-light: #E8F5E9;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .fixed-width-table {
            table-layout: fixed;
            width: 100%;
        }
        
        .fixed-width-table th, 
        .fixed-width-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Set specific widths for each column */
        .col-id {
            width: 80px;
        }
        
        .col-program {
            width: 160px;
        }
        
        .col-applicant {
            width: 220px;
        }
        
        .col-contact {
            width: 150px;
        }
        
        .col-date {
            width: 120px;
        }
        
        .col-notes {
            width: 250px;
        }
        
        .col-actions {
            width: 100px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--munici-green), #2E7D32);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .recent-activity-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
        }
        
        .recent-activity-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.2rem;
            padding: 1.25rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .table-responsive {
            border-radius: 0 0 10px 10px;
        }
        
        .table th {
            font-weight: 1600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-top: none;
            padding: 1rem 1.5rem;
            background-color: #f8f9fa;
        }
        
        .table td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .btn-view {
            background-color: #4361ee;
            color: white;
            border: none;
        }
        
        .btn-download {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-view:hover {
            background-color: #3a56d4;
            color: white;
        }
        
        .btn-download:hover {
            background-color: #218838;
            color: white;
        }
        
        .notes-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
            display: inline-block;
        }
        
        .admin-section {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .date-range-container {
            display: flex;
            gap: 10px;
        }
        
        .date-input {
            flex-grow: 1;
        }

        /* Output Format Cards */
        .output-format-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            height: 100%;
        }
        
        .output-format-card:hover {
            border-color: var(--munici-green);
            background-color: rgba(76, 175, 80, 0.05);
        }
        
        .output-format-card.active {
            border-color: var(--munici-green);
            background-color: var(--munici-green-light);
        }
        
        .format-icon {
            font-size: 2rem;
            margin-right: 15px;
        }
        
        .format-icon .fa-file-pdf {
            color: #d32f2f;
        }
        
        .format-icon .fa-file-excel {
            color: #1e8449;
        }
        
        .format-content h6 {
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .format-content p {
            margin-bottom: 0;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .download-buttons {
            display: flex;
            gap: 10px;
            margin-left: 15px;
        }
        
        .btn-download-pdf {
            background-color: #d32f2f;
            color: white;
        }
        
        .btn-download-excel {
            background-color: #1e8449;
            color: white;
        }
        
        .form-actions {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Ad-Hoc Reports - <?= $displaySection ?></h1>
                    <p class="mb-0">Generate custom reports for requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="recent-activity-card card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Generate Custom Report</h4>
            </div>
            <div class="card-body">
                <form id="reportForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date Range</label>
                                <div class="date-range-container">
                                    <input type="date" class="form-control date-input" name="start_date" required>
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control date-input" name="end_date" required>
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
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Program</label>
                                <select class="form-control" name="program">
                                    <option value="">All Programs</option>
                                    <?php while($program = $programs->fetch_assoc()): ?>
                                        <option value="<?= $program['id'] ?>"><?= $program['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach($statuses as $status): ?>
                                        <option value="<?= $status ?>"><?= ucfirst($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
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
                    
                    <!-- output format -->
                    <div class="form-section mt-4 mb-4">
                        <h5 class="mb-3">Output Format</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="output-format-card" id="pdfCard">
                                    <div class="format-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="format-content">
                                        <h6>PDF Summary</h6>
                                        <p>Generates a summary report in PDF format</p>
                                    </div>
                                    <input type="hidden" name="pdf_summary" value="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="output-format-card" id="excelCard">
                                    <div class="format-icon">
                                        <i class="fas fa-file-excel"></i>
                                    </div>
                                    <div class="format-content">
                                        <h6>Excel Full Data</h6>
                                        <p>Generates complete data in Excel format</p>
                                    </div>
                                    <input type="hidden" name="excel_full_data" value="1">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-file-export"></i> Generate Report
                                </button>
                                <div class="download-buttons" id="downloadButtons" style="display: none;">
                                    <!-- download buttons -->
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($scheduledReports)): ?>
        <div class="recent-activity-card card">
            <div class="card-header">
                <h4 class="mb-0">Download Scheduled Reports</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped fixed-width-table">
                        <thead>
                            <tr>
                                <th class="col-id">Type</th>
                                <th class="col-program">Period</th>
                                <th class="col-date">Generated</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($scheduledReports as $report): 
                                $isWeekly = strpos($report, 'week') !== false;
                                $fileDate = date('Y-m-d H:i', filemtime($reportsDir . $report));
                                $reportName = $isWeekly ? 
                                    'Weekly ' . str_replace(['_', '.pdf', '.xlsx'], ' ', $report) : 
                                    'Monthly ' . str_replace(['_', '.pdf', '.xlsx'], ' ', $report);
                            ?>
                            <tr>
                                <td><?= $isWeekly ? 'Weekly' : 'Monthly' ?></td>
                                <td><?= $reportName ?></td>
                                <td><?= $fileDate ?></td>
                                <td>
                                    <a href="<?= $reportsDir . $report ?>" class="btn btn-sm btn-download" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // default is today
        var today = new Date().toISOString().split('T')[0];
        $('input[name="start_date"]').val(today);
        $('input[name="end_date"]').val(today);

        // auto select excel pdf
        $('#pdfCard, #excelCard').addClass('active');
        $('input[name="pdf_summary"], input[name="excel_full_data"]').val('1');

        // date presets
        $('.date-preset').click(function() {
            var days = $(this).data('days');
            var endDate = new Date();
            var startDate = new Date();
            startDate.setDate(endDate.getDate() - days);
            
            $('input[name="start_date"]').val(startDate.toISOString().split('T')[0]);
            $('input[name="end_date"]').val(endDate.toISOString().split('T')[0]);
        });

        // output format
        $('.output-format-card').click(function() {
            $(this).toggleClass('active');
            var input = $(this).find('input');
            input.val(input.val() == '1' ? '0' : '1');
        });

        // form submission
        $('#reportForm').on('submit', function(e) {
            e.preventDefault();
            
            // check if at least one format is selected
            var pdfSelected = $('input[name="pdf_summary"]').val() == '1';
            var excelSelected = $('input[name="excel_full_data"]').val() == '1';
            
            if (!pdfSelected && !excelSelected) {
                Swal.fire({
                    icon: 'error',
                    title: 'No Format Selected',
                    text: 'Please select at least one output format (PDF or Excel)',
                    confirmButtonColor: '#4CAF50'
                });
                return;
            }
            
            var btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Generating...');
            
            $.ajax({
                url: 'generate_report.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // clear previous download buttons
                        $('#downloadButtons').empty().hide();
                        
                        // show download buttons for generated files
                        if (response.pdf) {
                            $('#downloadButtons').append(
                                '<a href="' + response.pdf + '" class="btn btn-download-pdf" download>' +
                                '<i class="fas fa-file-pdf"></i> Download PDF</a>'
                            );
                        }
                        
                        if (response.excel) {
                            $('#downloadButtons').append(
                                '<a href="' + response.excel + '" class="btn btn-download-excel" download>' +
                                '<i class="fas fa-file-excel"></i> Download Excel</a>'
                            );
                        }
                        
                        // show the download buttons
                        if (response.pdf || response.excel) {
                            $('#downloadButtons').show();
                        }
                        
                        // show success notification
                        Swal.fire({
                            icon: 'success',
                            title: 'Report Generated',
                            text: 'Your report has been generated successfully',
                            confirmButtonColor: '#4CAF50',
                            timer: 2000
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Generation Failed',
                            text: response.message || 'Failed to generate report',
                            confirmButtonColor: '#4CAF50'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred: ' + error,
                        confirmButtonColor: '#4CAF50'
                    });
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-file-export"></i> Generate Report');
                }
            });
        });
    });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>