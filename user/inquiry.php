<?php
session_start();
require_once __DIR__ . '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

// Get departments
$departments = $conn->query("
    SELECT d.id, d.name
    FROM departments d
    WHERE d.id = 8
    ORDER BY d.id
")->fetch_all(MYSQLI_ASSOC);

// Get user's inquiries with admin info
$user_inquiries = $conn->query("
    SELECT i.*, d.name as department_name, a.name as answered_by
    FROM inquiries i
    JOIN departments d ON i.department_id = d.id
    LEFT JOIN admins a ON i.answeredby_admin_id = a.id
    WHERE i.user_id = {$_SESSION['user_id']}
    ORDER BY i.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Inquiry - Form";
include __DIR__ . '../../includes/header.php';
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
            --completed: #4361ee;
            --answered: #4361ee;
            --approved: #28A745;
            --declined: #DC3545;
            --cancelled: #6C757D;
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
            margin-bottom: 0;
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

        .btn-submit {
            background-color: var(--munici-green);
            color: white;
            padding: 12px 25px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
            border-radius: 8px;
        }

        .btn-submit:hover {
            background-color: #0E3B85;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            background-color: #3a56d4;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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

        /* Column widths */
        .table-col-id {
            width: 10%;
            text-align: center;
        }

        .table-col-program {
            width: 50%;
        }

        .table-col-status {
            width: 20%;
            text-align: center;
        }

        .table-col-actions {
            width: 20%;
            text-align: center;
        }

        /* Status badges */
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

        .bg-completed {
            background-color: var(--completed) !important;
            color: white;
        }

        .bg-cancelled {
            background-color: var(--cancelled) !important;
            color: white;
        }

        /* Mobile styles - UPDATED TO MATCH STATUS.PHP */
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
                position: relative;
                padding-bottom: 3rem; /* Make space for action buttons */
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
            
            .mobile-row .action-buttons {
                position: absolute;
                bottom: 0.5rem;
                right: 0.5rem;
                display: flex;
                gap: 8px;
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
        }

        /* Action buttons */
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
            background-color: #3a56d4;
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
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Inquiry</h1>
                    <p class="mb-0">Submit your questions and we'll respond via SMS</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="status-card card mt-4">
            <div class="card-header text-center">
                Inquiry Form
            </div>
            <div class="card-body">
                <form method="POST" action="submit_inquiry.php">
    <input type="hidden" name="department_id" value="8">
    
    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label">Your Question</label>
            <textarea class="form-control" name="question" rows="4" 
                      placeholder="Type your question here (max 1000 characters)" required></textarea>
        </div>
        
        <div class="col-md-12 text-center mt-3">
            <button type="submit" class="btn btn-submit">
                <i class="fas fa-paper-plane me-2"></i>Submit Inquiry
            </button>
        </div>
    </div>
</form>
            </div>
        </div>

        <?php if (empty($user_inquiries)): ?>
            <div class="no-requests-message">
                <i class="fas fa-inbox"></i>
                <h4>No Inquiries Found</h4>
                <p>You haven't submitted any inquiries yet.</p>
            </div>
        <?php else: ?>
            <div class="status-card card mt-4">
                <div class="card-header">
                    <h4 class="mb-0">My Inquiries</h4>
                </div>
                <div class="card-body p-0">
                    <!-- Desktop Table -->
                    <div class="table-responsive desktop-table">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th class="table-col-id">ID</th>
                                    <th class="table-col-program">Department</th>
                                    <th class="table-col-status">Status</th>
                                    <th class="table-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_inquiries as $inquiry): ?>
                                <tr>
                                    <td class="table-col-id"><?= $inquiry['id'] ?></td>
                                    <td class="table-col-program"><?= htmlspecialchars($inquiry['department_name']) ?></td>
                                    <td class="table-col-status">
                                        <span class="status-badge 
                                            <?= $inquiry['status'] === 'answered' ? 'bg-completed' : 
                                            ($inquiry['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending') ?>">
                                            <?= ucfirst($inquiry['status']) ?>
                                        </span>
                                    </td>
                                    <td class="table-col-actions">
                                        <div class="action-buttons">
                                            <?php if ($inquiry['status'] === 'pending'): ?>
                                                <button class="btn btn-cancel cancel-btn" 
                                                        data-inquiry-id="<?= $inquiry['id'] ?>">
                                                    <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-view view-btn" data-id="<?= $inquiry['id'] ?>">
                                                <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile Table - UPDATED TO MATCH STATUS.PHP -->
                    <div class="mobile-table">
                        <?php foreach ($user_inquiries as $inquiry): ?>
                        <div class="mobile-row">
                            <div>
                                <span class="mobile-label">ID:</span>
                                <?= $inquiry['id'] ?>
                            </div>
                            <div>
                                <span class="mobile-label">Department:</span>
                                <?= htmlspecialchars($inquiry['department_name']) ?>
                            </div>
                            <div>
                                <span class="mobile-label">Status:</span>
                                <span class="status-badge 
                                    <?= $inquiry['status'] === 'answered' ? 'bg-completed' : 
                                    ($inquiry['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending') ?>">
                                    <?= ucfirst($inquiry['status']) ?>
                                </span>
                            </div>
                            <div class="action-buttons">
                                <?php if ($inquiry['status'] === 'pending'): ?>
                                    <button class="btn btn-cancel cancel-btn" 
                                            data-inquiry-id="<?= $inquiry['id'] ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-view view-btn" data-id="<?= $inquiry['id'] ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inquiry Details</h5>
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

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this inquiry?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h4 class="mb-3">Inquiry Submitted Successfully!</h4>
                    <p class="mb-4">Your inquiry has been received. You will be notified about the status.</p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Cancellation Modal -->
    <div class="modal fade" id="successCancelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Inquiry Cancelled</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-danger mb-4"></i>
                    <h4>Inquiry Cancelled Successfully!</h4>
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
        let currentInquiryId = null;
        let lastCancelClick = 0;
        
        // Show success modal if there's a success message
        <?php if (isset($_SESSION['success_message'])): ?>
            <?php if ($_SESSION['success_message'] === 'Your inquiry has been submitted successfully!'): ?>
                $('#successModal').modal('show');
            <?php endif; ?>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        // View Inquiry Details
        $('.view-btn').click(function() {
            const inquiryId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: 'view_inquiry.php?id=' + inquiryId,
                method: 'GET',
                success: function(data) {
                    $('#viewModalContent').html(data);
                },
                error: function() {
                    $('#viewModalContent').html('<div class="alert alert-danger">Failed to load details. Please try again.</div>');
                }
            });
        });

        let cancelClickInProgress = false;

        $(document).on('click', '.cancel-btn:visible', function(e) {
            e.preventDefault();
            
            // Prevent multiple clicks while processing
            if (cancelClickInProgress) return;
            cancelClickInProgress = true;
            
            // Add a small delay to ensure this is the only click being processed
            setTimeout(() => {
                currentInquiryId = $(this).data('inquiry-id');
                $('#cancelModal').modal('show');
                cancelClickInProgress = false;
            }, 100);
        });

        // Confirm Cancel
        $('#confirmCancel').click(function() {
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'submit_inquiry.php',
                type: 'POST',
                data: {
                    inquiry_id: currentInquiryId,
                    cancel_inquiry: true
                },
                success: function(response) {
                    $('#cancelModal').modal('hide');
                    $('#successCancelModal').modal('show');
                },
                error: function(xhr, status, error) {
                    $('#cancelModal').modal('hide');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to cancel inquiry. Please try again.'
                    });
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yes, Cancel');
                }
            });
        });

        $('#continueAfterCancel').click(function() {
            $('#successCancelModal').modal('hide');
            location.reload();
        });
    });
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>