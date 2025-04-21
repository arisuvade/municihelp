<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$pageTitle = 'Approved Requests';
include '../includes/header.php';

// financial or request admin
$adminSection = $_SESSION['admin_section'] ?? 'Financial Assistance';
$displaySection = ($adminSection === 'Financial Assistance') ? 'Assistance' : 'Request';

$typeFilter = ($adminSection === 'Financial Assistance') ? 
    "AND at.name LIKE '%Assistance%'" : 
    "AND at.name LIKE '%Request%'";

// filter approved the order by queue
$stmt = $conn->prepare("
    SELECT ar.id, at.name as program, 
           ar.last_name, ar.first_name, ar.middle_name,
           u.phone, ar.updated_at, ar.queue_number, ar.queue_date
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    JOIN users u ON ar.user_id = u.id
    WHERE ar.status = 'approved'
    $typeFilter
    ORDER BY ar.queue_number ASC, ar.id ASC
");
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
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
            width: 60px;
        }
        
        .col-queue {
            width: 85px;
        }

        .col-applicant {
            width: 210px;
        }
        
        .col-contact {
            width: 140px;
        }
        
        .col-date {
            width: 120px;
        }
        
        .col-program {
            width: 160px;
        }
        
        .col-actions {
            width: 170px;
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
        
        .btn-complete {
            background-color: #007bff;
            color: white;
            border: none;
        }
        
        .btn-view:hover {
            background-color: #3a56d4;
            color: white;
        }
        
        .btn-complete:hover {
            background-color: #0069d9;
            color: white;
        }
        
        .queue-badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 0.875em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            background-color: var(--munici-green);
            color: white;
        }
        
        @keyframes iconAppear {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .fa-check-circle {
            animation: iconAppear 0.5s ease-in-out;
        }
        
        .admin-section {
            font-size: 0.9rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Approved Requests</h1>
                    <p class="mb-0">Manage approved requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($requests)): ?>
            <div class="alert alert-info">
                No approved requests found.
            </div>
        <?php else: ?>
            <div class="recent-activity-card card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped fixed-width-table">
                            <thead>
                                <tr>
                                    <th class="col-id">ID</th>
                                    <th class="col-queue">Queue #</th>
                                    <th class="col-program">Program</th>
                                    <th class="col-applicant">Applicant</th>
                                    <th class="col-contact">Contact No.</th>
                                    <th class="col-date">Date Approved</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $row): 
                                    // format lastname, firstname m.i."
                                    $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                    $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                ?>
                                <tr>
                                    <td class="col-id"><?= $row['id'] ?></td>
                                    <td class="col-queue">
                                        <?php if ($row['queue_number']): ?>
                                            <span class="queue-badge">#<?= $row['queue_number'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-program"><?= htmlspecialchars($row['program']) ?></td>
                                    <td class="col-applicant" title="<?= $applicant_name ?>"><?= $applicant_name ?></td>
                                    <td class="col-contact"><?= htmlspecialchars($row['phone']) ?></td>
                                    <td class="col-date"><?= date('M d, Y', strtotime($row['updated_at'])) ?></td>
                                    <td class="col-actions">
                                        <button class="btn btn-sm btn-view view-btn" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-complete complete-btn" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-flag-checkered"></i> Complete
                                        </button>
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

    <!-- view requirements modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Requirements</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewModalContent">
                    <!-- content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- document viewer modal -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalTitle">Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="documentImage" src="" class="img-fluid" style="max-height: 80vh;" alt="Document">
                    <iframe id="documentPdf" src="" style="width: 100%; height: 80vh; display: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- complete confirmation modal -->
    <div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Confirm Completion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="completeValidation" class="alert alert-danger d-none mb-3"></div>
                    Are you sure you want to mark this request as completed?
                    <div class="mt-3">
                        <label for="completeNote" class="form-label">Completion Notes (optional):</label>
                        <textarea class="form-control" id="completeNote" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmComplete">Mark as Completed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- success completion modal -->
    <div class="modal fade" id="successCompletionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Request Completed</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Request Completed Successfully!</h4>
                    <p>The applicant has been notified via SMS.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterComplete">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        let currentRequestId = null;
        
        // View Requirements
        $('.view-btn').click(function() {
            const requestId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: '../includes/view_request.php?id=' + requestId,
                method: 'GET',
                success: function(data) {
                    $('#viewModalContent').html(data);
                    
                    $('.document-view').click(function(e) {
                        e.preventDefault();
                        const src = $(this).data('src');
                        const type = $(this).data('type');
                        const title = $(this).data('title');
                        
                        $('#documentModalTitle').text(title);
                        
                        if (type === 'image') {
                            $('#documentImage').attr('src', src).show();
                            $('#documentPdf').hide();
                        } else {
                            $('#documentPdf').attr('src', src).show();
                            $('#documentImage').hide();
                        }
                        
                        $('#documentModal').modal('show');
                    });
                },
                error: function() {
                    $('#viewModalContent').html('<div class="alert alert-danger">Failed to load request details</div>');
                }
            });
        });
        
        // Complete Button
        $('.complete-btn').click(function() {
            currentRequestId = $(this).data('id');
            $('#completeNote').val('');
            $('#completeValidation').addClass('d-none');
            $('#completeModal').modal('show');
        });
        
        // Confirm Complete
        $('#confirmComplete').click(function() {
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: currentRequestId,
                    action: 'complete',
                    note: $('#completeNote').val()
                },
                success: function(response) {
                    if (response.success) {
                        $('#completeModal').modal('hide');
                        const successModal = new bootstrap.Modal('#successCompletionModal', {
                            keyboard: false
                        });
                        successModal.show();
                        
                        $('#continueAfterComplete').off().click(function() {
                            successModal.hide();
                            location.reload();
                        });
                    } else {
                        $('#completeValidation').removeClass('d-none').text(response.error || 'Failed to complete request');
                    }
                },
                error: function(xhr, status, error) {
                    $('#completeValidation').removeClass('d-none').text('Network error occurred. Please try again.');
                    console.error('Error:', error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Mark as Completed');
                }
            });
        });
    });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>