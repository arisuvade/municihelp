<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$pageTitle = 'Pending Requests';
include '../includes/header.php';

// financial or request admin
$adminSection = $_SESSION['admin_section'] ?? 'Financial Assistance';
$displaySection = ($adminSection === 'Financial Assistance') ? 'Assistance' : 'Request';

$typeFilter = ($adminSection === 'Financial Assistance') ? 
    "AND at.name LIKE '%Assistance%'" : 
    "AND at.name LIKE '%Request%'";

// urgent priority then id
$stmt = $conn->prepare("
    SELECT ar.id, at.name as program, 
           ar.last_name, ar.first_name, ar.middle_name,
           u.phone, ar.created_at
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    JOIN users u ON ar.user_id = u.id
    WHERE ar.status = 'pending'
    $typeFilter
    ORDER BY 
        CASE 
            WHEN at.name = 'Medical Assistance' THEN 1
            WHEN at.name = 'Nebulizer Request' THEN 2
            WHEN at.name = 'Glucometer Request' THEN 3
            WHEN at.name = 'Wheelchair Request' THEN 4
            WHEN at.name = 'Laboratory Assistance' THEN 5
            WHEN at.name = 'Burial Assistance' THEN 6
            WHEN at.name = 'Educational Assistance' THEN 7
            ELSE 8
        END,
        ar.id ASC
");
$stmt->execute();
$result = $stmt->get_result();

// group program
$groupedRequests = [];
while ($row = $result->fetch_assoc()) {
    $program = $row['program'];
    if (!isset($groupedRequests[$program])) {
        $groupedRequests[$program] = [];
    }
    $groupedRequests[$program][] = $row;
}

$programOrder = [
    'Medical Assistance',
    'Nebulizer Request',
    'Glucometer Request',
    'Wheelchair Request',
    'Laboratory Assistance',
    'Burial Assistance',
    'Educational Assistance'
];

// filter programs
$filteredProgramOrder = array_filter($programOrder, function($program) use ($adminSection) {
    if ($adminSection === 'Financial Assistance') {
        return strpos($program, 'Assistance') !== false;
    } else {
        return strpos($program, 'Request') !== false;
    }
});
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
            /* Add this to your existing styles */
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
        
        .col-applicant {
            width: 220px;
        }
        
        .col-contact {
            width: 150px;
        }
        
        .col-date {
            width: 120px;
        }
        
        .col-actions {
            width: 220px;
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
        
        .btn-approve {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-decline {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        
        .btn-view:hover {
            background-color: #3a56d4;
            color: white;
        }
        
        .btn-approve:hover {
            background-color: #218838;
            color: white;
        }
        
        .btn-decline:hover {
            background-color: #c82333;
            color: white;
        }
        
        .modal[data-bs-backdrop="static"] {
            pointer-events: none;
        }
        
        @keyframes iconAppear {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .fa-check-circle, .fa-times-circle {
            animation: iconAppear 0.5s ease-in-out;
        }

        .program-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin-top: 20px;
            border-left: 4px solid var(--munici-green);
            font-weight: 600;
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
                    <h1 class="mb-1">Pending Requests</h1>
                    <p class="mb-0">Review and process requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($groupedRequests)): ?>
            <div class="alert alert-info">
                No pending requests found.
            </div>
        <?php else: ?>
            <?php foreach ($filteredProgramOrder as $program): ?>
                <?php if (!empty($groupedRequests[$program])): ?>
                    <div class="recent-activity-card card mb-4">
                        <div class="card-body">
                            <h5 class="program-header"><?= htmlspecialchars($program) ?></h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                        <th class="col-id">ID</th>
                                        <th class="col-applicant">Applicant</th>
                                        <th class="col-contact">Contact No.</th>
                                        <th class="col-date">Date Submitted</th>
                                        <th class="col-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groupedRequests[$program] as $row): 
                                            // format lastname, firstname m.i."
                                            $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                            $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                        ?>
                                        <tr>
                                            <td><?= $row['id'] ?></td>
                                            <td><?= $applicant_name ?></td>
                                            <td><?= htmlspecialchars($row['phone']) ?></td>
                                            <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-view view-btn" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-approve approve-btn" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-decline decline-btn" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-times"></i> Decline
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
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- view requirements modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Information</h5>
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

    <!-- approve confirmation modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Confirm Approval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to approve this request?
                    <div class="mt-3">
                        <label for="approveNote" class="form-label">Note (optional):</label>
                        <textarea class="form-control" id="approveNote" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApprove">Yes, Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- decline confirmation modal -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Decline</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="declineValidation" class="alert alert-danger d-none mb-3"></div>
                    Are you sure you want to decline this request?
                    <div class="mt-3">
                        <label for="declineNote" class="form-label">Note (required):</label>
                        <textarea class="form-control" id="declineNote" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDecline">Yes, Decline</button>
                </div>
            </div>
        </div>
    </div>

    <!-- success approval modal -->
    <div class="modal fade" id="successApprovalModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Request Approved</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Request Approved Successfully!</h4>
                    <p>The applicant has been notified via SMS.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterApprove">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- success decline modal -->
    <div class="modal fade" id="successDeclineModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Request Declined</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-times-circle fa-5x text-danger mb-4"></i>
                    <h4>Request Declined Successfully!</h4>
                    <p>The applicant has been notified via SMS.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="continueAfterDecline">Continue</button>
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

        // Approve Button
        $('.approve-btn').click(function() {
            currentRequestId = $(this).data('id');
            $('#approveNote').val('');
            $('#approveModal').modal('show');
        });
        
        // Decline Button
        $('.decline-btn').click(function() {
            currentRequestId = $(this).data('id');
            $('#declineNote').val('');
            $('#declineValidation').addClass('d-none');
            $('#declineModal').modal('show');
        });

        // Confirm Approve
        $('#confirmApprove').click(function() {
            const note = $('#approveNote').val();
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: currentRequestId,
                    action: 'approve',
                    note: note
                },
                success: function(response) {
                    if (response.success) {
                        $('#approveModal').modal('hide');
                        const successModal = new bootstrap.Modal('#successApprovalModal', {
                            keyboard: false
                        });
                        successModal.show();
                        
                        $('#continueAfterApprove').off().click(function() {
                            successModal.hide();
                            location.reload();
                        });
                    } else {
                        alert('Error: ' + (response.message || 'Failed to approve request'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Network error occurred. Please check your connection and try again.');
                    console.error('Error:', error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yes, Approve');
                }
            });
        });

        // Confirm Decline
        $('#confirmDecline').click(function() {
            const note = $('#declineNote').val().trim();
            const $validation = $('#declineValidation');
            const $btn = $(this);
            
            if (!note) {
                $validation.removeClass('d-none').text('Please provide a reason for declining this request.');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: currentRequestId,
                    action: 'decline',
                    note: note
                },
                success: function(response) {
                    if (response.success) {
                        $('#declineModal').modal('hide');
                        const successModal = new bootstrap.Modal('#successDeclineModal', {
                            keyboard: false
                        });
                        successModal.show();
                        
                        $('#continueAfterDecline').off().click(function() {
                            successModal.hide();
                            location.reload();
                        });
                    } else {
                        $validation.removeClass('d-none').text(response.message || 'Failed to decline request');
                    }
                },
                error: function(xhr, status, error) {
                    $validation.removeClass('d-none').text('Network error occurred. Please try again.');
                    console.error('Error:', error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yes, Decline');
                }
            });
        });
    });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>