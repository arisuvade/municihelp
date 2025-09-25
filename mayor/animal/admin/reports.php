<?php
session_start();
include '../../../includes/db.php';

if (!isset($_SESSION['animal_admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get admin info
$animal_admin_id = $_SESSION['animal_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $animal_admin_id");
$admin_data = $admin_query->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

$pageTitle = 'Reports';
include '../../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="../../../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../../../assets/images/logo-pulilan.png">
    <style>
    :root {
        --munici-green: #2C80C6;
        --munici-green-light: #42A5F5;
        --approved: #28A745;
        --declined: #DC3545;
        --completed: #4361ee;
        --cancelled: #6C757D;
        --verified: #20c997;
        --false-report: #fd7e14;
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
    
 .admin-badge {
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
    
    .admin-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
    }
    
    .admin-badge i {
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
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        color: white;
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
    
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 0 10px;
            width: 100%;
        }
        
        .dashboard-header h1 {
            font-size: 1.5rem;
        }
        
        .admin-badge {
            position: relative;
            right: auto;
            top: auto;
            margin-top: 10px;
            justify-content: center;
            width: 100%;
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
    }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Ad-Hoc Reports</h1>
                    <p class="mb-0">Generate custom reports for animal-related requests</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($admin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
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
                    
                    <div class="row mb-4">
    <div class="col-md-4">
        <div class="form-group">
            <label>Request Type</label>
            <select class="form-control" name="request_type">
                <option value="all">All Requests</option>
                <option value="online">Online Request</option>
                <!-- <option value="walkin">Walk-in Request</option> -->
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Program</label>
            <select class="form-control" name="program">
                <option value="all">All Programs</option>
                <option value="dog_claiming">Dog Claiming</option>
                <option value="dog_adoption">Dog Adoption</option>
                <option value="rabid_report">Rabid Report</option>
                <!-- Add any other program options here if needed -->
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
                <option value="all">All Statuses</option>
                <option value="approved">Approved</option>
                <option value="declined">Declined</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="verified">Verified</option>
                <option value="false_report">False Report</option>
            </select>
        </div>
    </div>
</div>
                    
                    <div class="report-type-buttons">
                        <button type="button" id="generateReport" class="btn btn-primary btn-report-type">
                            <i class="fas fa-file-alt"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Set default dates
        var today = new Date().toISOString().split('T')[0];
        $('input[name="start_date"]').val(today);
        $('input[name="end_date"]').val(today);

        // Date presets
        $('.date-preset').click(function() {
            var days = $(this).data('days');
            var endDate = new Date();
            var startDate = new Date();
            startDate.setDate(endDate.getDate() - days);
            
            $('input[name="start_date"]').val(startDate.toISOString().split('T')[0]);
            $('input[name="end_date"]').val(endDate.toISOString().split('T')[0]);
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

        // Report generation function
        async function generateReport() {
            const btn = $('#generateReport');
            const originalText = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Generating...');
            
            try {
                // Collect form data
                const formData = $('#reportForm').serializeArray();
                
                // Use fixed filenames that will be overwritten each time
                const baseFilename = 'animal_report';
                
                // 1. Generate and download PDF
                const pdfResponse = await $.ajax({
                    url: 'generate_report.php',
                    method: 'POST',
                    data: formData.concat([
                        {name: 'generate_pdf', value: '1'},
                        {name: 'filename', value: baseFilename}
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
                    url: 'generate_report.php',
                    method: 'POST',
                    data: formData.concat([
                        {name: 'generate_excel', value: '1'},
                        {name: 'filename', value: baseFilename}
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

        // Button handler
        $('#generateReport').click(generateReport);
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>