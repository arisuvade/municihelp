<?php
session_start();
require_once '../../includes/db.php';

// Include the file deletion function
require_once '../../includes/delete_request_files.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

// Handle cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $requestId = (int)$_POST['request_id'];
    $userId = (int)$_SESSION['user_id'];
    
    // Verify the request belongs to the user before cancelling
    $stmt = $conn->prepare("UPDATE mswd_requests SET status = 'cancelled', reason = 'Cancelled by user' WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $requestId, $userId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Delete files and clear references
        handleRequestCancellation('mswd_requests', $requestId, $conn);
        $_SESSION['success_message'] = 'Request cancelled successfully';
    } else {
        $_SESSION['error_message'] = 'Unable to cancel request';
    }
    
    header("Location: status.php");
    exit;
}

// Get all requests sorted newest first
$query = "
    SELECT r.id, 
           IFNULL(r.assistance_name, t.name) as program, 
           CONCAT(r.last_name, ', ', r.first_name, ' ', IFNULL(r.middle_name, '')) as full_name,
           r.created_at, r.status, r.reason
    FROM mswd_requests r
    LEFT JOIN mswd_types t ON r.assistance_id = t.id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die('Error preparing statement: ' . $conn->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$all_requests = $result->fetch_all(MYSQLI_ASSOC);

$pageTitle = "MSWD - Status";
include '../../includes/header.php';

// Check if there are any requests
$hasRequests = !empty($all_requests);
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
        }
        
        .status-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .status-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .table-responsive {
            border-radius: 0 0 10px 10px;
            overflow-x: auto;
            width: 100%;
        }
        
        .table {
            width: 100%;
        }
        
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-top: none;
            padding: 0.75rem 1rem;
            background-color: #f8f9fa;
        }
        
        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .status-badge {
            padding: 0.5em 0.8em;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 50px;
            display: inline-block;
            text-align: center;
            min-width: 90px;
        }
        
        .bg-pending {
            background-color: var(--pending) !important;
            color: #212529;
        }
        
        .bg-mayor-approved {
            background-color: var(--pending) !important;
            color: #212529;
        }
        
        .bg-approved {
            background-color: var(--approved) !important;
            color: white;
        }
        
        .bg-declined {
            background-color: var(--declined) !important;
            color: white;
        }
        
        .bg-completed {
            background-color: var(--completed) !important;
            color: white;
        }
        
        .bg-cancelled {
            background-color: var(--cancelled) !important;
            color: white;
        }
        
        .btn-view {
            background-color: var(--completed);
            color: white;
            border: none;
            padding: 0.5rem 0.9rem;
            border-radius: 5px;
            transition: all 0.3s;
            min-width: 80px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-view:hover {
            background-color: #0E3B85;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-cancel {
            background-color: var(--declined);
            color: white;
            border: none;
            padding: 0.5rem 0.9rem;
            border-radius: 5px;
            transition: all 0.3s;
            min-width: 80px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-cancel:hover {
            background-color: #bb2d3b;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            justify-content: flex-end;
        }
        
        .no-requests-message {
            text-align: center;
            padding: 5rem 0;
            color: #666;
        }
        
        .no-requests-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        /* Column widths for desktop */
        .table-col-id {
            width: 8%;
            text-align: center;
        }

        .table-col-name {
            width: 22%;
        }

        .table-col-program {
            width: 22%;
            text-align: center;
        }
        
        .table-col-date {
            width: 15%;
            text-align: center;
        }
        
        .table-col-status {
            width: 15%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 18%;
            text-align: center;
        }
        
        /* Mobile specific styles */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
            
            .mobile-row {
                display: flex;
                flex-direction: column;
                padding: 1rem;
                border-bottom: 1px solid #dee2e6;
            }
            
            .mobile-row div {
                margin-bottom: 0.5rem;
            }
            
            .mobile-label {
                font-weight: 600;
                color: #495057;
                margin-right: 0.5rem;
            }
            
            .desktop-table {
                display: none;
            }
            
            .mobile-table {
                display: block;
            }
            
            .status-badge {
                font-size: 0.8rem;
                padding: 0.4em 0.7em;
                min-width: 80px;
            }
            
            .action-buttons {
                justify-content: flex-end;
            }
            
            .btn-view, .btn-cancel {
                padding: 0.6rem;
                font-size: 0;
                width: 40px;
                height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            .btn-view i, 
            .btn-cancel i {
                font-size: 1rem;
                margin: 0;
            }
        }
        
        @media (min-width: 769px) {
            .desktop-table {
                display: table;
                width: 100%;
            }
            
            .mobile-table {
                display: none;
            }
            
            /* Percentage-based columns for desktop */
            .table-col-id {
                width: 10%;
            }
            
            .table-col-program {
                width: 25%;
            }
            
            .table-col-name {
                width: 25%;
            }
            
            .table-col-date {
                width: 15%;
            }
            
            .table-col-status {
                width: 15%;
            }
            
            .table-col-actions {
                width: 10%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Request Status</h1>
                    <p class="mb-0">Check the status of your assistance requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (!$hasRequests): ?>
            <div class="no-requests-message">
                <i class="fas fa-inbox"></i>
                <h4>No Requests Found</h4>
                <p>You haven't submitted any requests yet.</p>
            </div>
        <?php else: ?>
            <div class="status-card card">
                <div class="card-header">
                    <h4 class="mb-0">My Requests</h4>
                </div>
                <div class="card-body p-0">
                    <!-- Desktop Table -->
                    <div class="table-responsive desktop-table">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th class="table-col-id">ID</th>
                                    <th class="table-col-name">Name</th>
                                    <th class="table-col-program">Program</th>
                                    <th class="table-col-status">Status</th>
                                    <th class="table-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_requests as $request): ?>
                                <tr>
                                    <td class="table-col-id"><?= $request['id'] ?></td>
                                    <td class="table-col-name"><?= htmlspecialchars($request['full_name']) ?></td>
                                    <td class="table-col-program"><?= htmlspecialchars($request['program']) ?></td>
                                    <td class="table-col-status">
                                        <span class="status-badge 
                                            <?= $request['status'] === 'mswd_approved' ? 'bg-approved' : 
                                            ($request['status'] === 'mayor_approved' ? 'bg-mayor-approved' : 
                                            ($request['status'] === 'declined' ? 'bg-declined' : 
                                            ($request['status'] === 'completed' ? 'bg-completed' : 
                                            ($request['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending')))) ?>">
                                            <?= 
                                                        $request['status'] === 'mswd_approved' ? 'MSWD Approved' : 
                                                        ucwords(str_replace('_', ' ', $request['status'])) 
                                                    ?>
                                        </span>
                                    </td>
                                    <td class="table-col-actions">
                                        <div class="action-buttons">
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <button class="btn btn-cancel cancel-btn" 
                                                        data-request-id="<?= $request['id'] ?>">
                                                    <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-view view-btn" data-id="<?= $request['id'] ?>">
                                                <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile Table -->
                    <div class="mobile-table">
                        <?php foreach ($all_requests as $request): ?>
                        <div class="mobile-row">
                            <div>
                                <span class="mobile-label">ID:</span>
                                <?= $request['id'] ?>
                            </div>
                            <div>
                                <span class="mobile-label">Program:</span>
                                <?= htmlspecialchars($request['program']) ?>
                            </div>
                            <div>
                                <span class="mobile-label">Name:</span>
                                <?= htmlspecialchars($request['full_name']) ?>
                            </div>
                            <div>
                                <span class="mobile-label">Status:</span>
                                <span class="status-badge 
                                    <?= $request['status'] === 'mswd_approved' ? 'bg-approved' : 
                                    ($request['status'] === 'mayor_approved' ? 'bg-mayor-approved' : 
                                    ($request['status'] === 'declined' ? 'bg-declined' : 
                                    ($request['status'] === 'completed' ? 'bg-completed' : 
                                    ($request['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending')))) ?>">
                                    <?= 
                                                        $request['status'] === 'mswd_approved' ? 'MSWD Approved' : 
                                                        ucwords(str_replace('_', ' ', $request['status'])) 
                                                    ?>
                                </span>
                            </div>
                            <div>
                                <div class="action-buttons">
                                    <button class="btn btn-view view-btn" data-id="<?= $request['id'] ?>">
                                        <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                    </button>
                                    <?php if ($request['status'] === 'pending' || $request['status'] === 'mayor_approved'): ?>
                                        <button class="btn btn-cancel cancel-btn" 
                                                data-request-id="<?= $request['id'] ?>">
                                            <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- View Request Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewModalContent">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Viewer Modal -->
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

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this request?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Cancellation Modal -->
    <div class="modal fade" id="successCancelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Request Cancelled</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-danger mb-4"></i>
                    <h4>Request Cancelled Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="continueAfterCancel">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        let currentRequestId = null;
        
        // View Request Details
        $('.view-btn').click(function() {
            const requestId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: 'view_request.php?id=' + requestId,
                method: 'GET',
                success: function(data) {
                    $('#viewModalContent').html(data);
                    
                    // Handle document viewing in modal
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

        // Cancel Button Click
        $('.cancel-btn').click(function() {
            currentRequestId = $(this).data('request-id');
            $('#cancelModal').modal('show');
        });

        // Confirm Cancel
        $('#confirmCancel').click(function() {
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'status.php',
                type: 'POST',
                data: {
                    request_id: currentRequestId,
                    cancel_request: true
                },
                success: function(response) {
                    $('#cancelModal').modal('hide');
                    const successModal = new bootstrap.Modal('#successCancelModal', {
                        keyboard: false
                    });
                    successModal.show();
                    
                    $('#continueAfterCancel').off().click(function() {
                        successModal.hide();
                        location.reload();
                    });
                },
                error: function(xhr, status, error) {
                    alert('Error cancelling request. Please try again.');
                    console.error('Error:', error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yes, Cancel');
                }
            });
        });
    });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>