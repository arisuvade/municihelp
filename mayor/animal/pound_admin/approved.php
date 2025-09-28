<?php
session_start();
include '../../../includes/db.php';

// Check for admin session
if (!isset($_SESSION['pound_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['pound_admin_id'];
$admin_name = 'Pound Admin'; // Default value

try {
    $admin_query = $conn->prepare("SELECT name FROM admins WHERE id = ?");
    $admin_query->bind_param("i", $admin_id);
    $admin_query->execute();
    $admin_result = $admin_query->get_result();

    if ($admin_result && $admin_result->num_rows > 0) {
        $admin_data = $admin_result->fetch_assoc();
        $admin_name = $admin_data['name'];
    }
} catch (Exception $e) {
    error_log("Admin query error: " . $e->getMessage());
}

$pageTitle = 'Approved';
include '../../../includes/header.php';

function formatPhoneNumber($phone) {
    if (empty($phone)) return 'N/A';
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

// Initialize empty arrays for each type
$groupedRequests = [
    'Dog Claiming' => [],
    'Dog Adoption' => []
];

$hasRequests = false;

try {
// Fetch approved claims (both online and walk-in)
$approved_claims = $conn->query("
    SELECT dc.id, 
           CONCAT(dc.last_name, ', ', dc.first_name, ' ', IF(dc.middle_name IS NULL OR dc.middle_name = '', '', CONCAT(LEFT(dc.middle_name, 1), '.'))) as name,
           dc.phone as contact_number,
           CASE WHEN dc.user_id IS NULL THEN 'Walk-in' ELSE 'Online' END as request_type,
           dc.created_at as approved_date,
           dc.name_of_dog,
           'Dog Claiming' as program_type,
           dc.status,
           dc.user_id,
           dc.offense_count,
           dc.fine_amount
    FROM dog_claims dc
    LEFT JOIN users u ON dc.user_id = u.id
    LEFT JOIN dogs d ON dc.dog_id = d.id
    WHERE dc.status = 'approved'
");

    if ($approved_claims) {
        while ($row = $approved_claims->fetch_assoc()) {
            $groupedRequests['Dog Claiming'][] = $row;
            $hasRequests = true;
        }
    } else {
        throw new Exception("Dog claims query failed: " . $conn->error);
    }

    // Fetch approved adoptions (both online and walk-in)
    $approved_adoptions = $conn->query("
        SELECT da.id, 
               CONCAT(da.last_name, ', ', da.first_name, ' ', IF(da.middle_name IS NULL OR da.middle_name = '', '', CONCAT(LEFT(da.middle_name, 1), '.'))) as name,
               u.phone as contact_number,
               CASE WHEN da.user_id IS NULL THEN 'Walk-in' ELSE 'Online' END as request_type,
               da.created_at as approved_date,
               NULL as name_of_dog,
               'Dog Adoption' as program_type,
               da.status,
               da.user_id
        FROM dog_adoptions da
        LEFT JOIN users u ON da.user_id = u.id
        LEFT JOIN dogs d ON da.dog_id = d.id
        WHERE da.status = 'approved'
    ");
    
    if ($approved_adoptions) {
        while ($row = $approved_adoptions->fetch_assoc()) {
            $groupedRequests['Dog Adoption'][] = $row;
            $hasRequests = true;
        }
    } else {
        throw new Exception("Dog adoptions query failed: " . $conn->error);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo "<div class='alert alert-danger'>Error loading approved requests: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="../../../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --approved: #28A745;
            --scheduled: #4361ee;
            --completed: #6a5acd;
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

        .btn-view {
            background-color: var(--scheduled);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-view:hover {
            background-color: #0E3B85;
            color: white;
        }
        
        .btn-complete {
            background-color: var(--approved);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-complete:hover {
            background-color: #218838;
            color: white;
        }
        
        .btn-cancel {
            background-color: var(--declined);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
            color: white;
        }
        
        .no-requests {
            text-align: center;
            padding: 3rem;
            color: #666;
            margin-top: 2rem;
        }
        
        .no-requests i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }

        .no-requests h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .no-requests p {
            font-size: 1rem;
            color: #6c757d;
        }
        
        .program-header {
            background-color: #ffffff;
            color: #000;
            padding: 15px;
            margin-top: 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .program-title {
            font-size: 1.1rem;
        }
        
        /* Column widths */
        .table-col-seq {
            width: 5%;
            text-align: center;
        }
        .table-col-name {
            width: 29%;
        }
        
        .table-col-contact {
            width: 23%;
            text-align: center;
        }

        .table-col-date {
            width: 23%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 20%;
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
            
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                min-width: 100%;
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
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Approved Requests</h1>
                    <p class="mb-0">Manage approved animal control requests</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo htmlspecialchars($admin_name); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="card filter-card">
            <div class="card-body">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchFilter" 
                            placeholder="Search by name or contact">
                    </div>
                    
                    <div class="filter-group">
                        <label for="programFilter" class="form-label">Program</label>
                        <select class="form-select" id="programFilter">
                            <option value="">All Programs</option>
                            <option value="Dog Claiming">Dog Claiming</option>
                            <option value="Dog Adoption">Dog Adoption</option>
                        </select>
                    </div>
                    
                    
                    <div class="filter-group">
                        <label for="dateFilter" class="form-label">Date Approved</label>
                        <input type="date" class="form-control" id="dateFilter">
                    </div>
                </div>
            </div>
        </div>

        <div id="resultsContainer">
            <?php if (!$hasRequests): ?>
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>There are no approved requests to display.</p>
                </div>
            <?php else: // Initialize sequence number
        $sequence_number = 1;
?>
                <?php foreach ($groupedRequests as $programType => $requests): ?>
                    <?php if (!empty($requests)): ?>
                        <div class="program-group" data-program="<?php echo htmlspecialchars($programType); ?>">
                            <div class="program-header">
                                <div>
                                    <h4 class="mb-0"><?php echo htmlspecialchars($programType); ?></h4>
                                </div>
                            </div>
                            
                            <div class="recent-activity-card card mb-4 desktop-table">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-striped mb-0">
                                            <thead>
                                                <tr>
                                <th class="table-col-seq">#</th>
                                                    <th class="table-col-name">Name</th>
                                                    <th class="table-col-contact">Contact No.</th>
                                                    <th class="table-col-date">Date Approved</th>
                                                    <th class="table-col-actions"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($requests as $row): 
                                                    $date_approved = date('F j, Y', strtotime($row['approved_date']));
                                                    $phone_number = formatPhoneNumber($row['contact_number']);
                                                ?>
                                                    <tr class="request-row" 
    data-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>"
    data-program="<?php echo htmlspecialchars($row['program_type']); ?>"
    data-type="<?php echo htmlspecialchars($row['request_type']); ?>"
    data-date="<?php echo date('Y-m-d', strtotime($row['approved_date'])); ?>"
    data-phone="<?php echo htmlspecialchars($phone_number); ?>"
    data-offense="<?php echo htmlspecialchars($row['offense_count']); ?>"
    data-fine="<?php echo htmlspecialchars($row['fine_amount']); ?>">
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                                        <td class="table-col-name"><?php echo htmlspecialchars($row['name']); ?></td>
                                                        <td class="table-col-contact"><?php echo htmlspecialchars($phone_number); ?></td>
                                                        <td class="table-col-date"><?php echo $date_approved; ?></td>
                                                        <td class="table-col-actions">
                                                            <button class="btn btn-view view-btn" 
                                                                    data-id="<?= $row['id'] ?>" 
                                                                    data-type="<?= ($programType === 'Dog Claiming') ? 'claim' : 'adoption' ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                            <button class="btn btn-complete complete-btn" 
                                                                    data-id="<?php echo htmlspecialchars($row['id']); ?>" 
                                                                    data-type="<?= ($programType === 'Dog Claiming') ? 'claim' : 'adoption' ?>">
                                                                <i class="fas fa-flag-checkered"></i> Complete
                                                            </button>
                                                            <button class="btn btn-cancel cancel-btn" 
                                                                    data-id="<?php echo htmlspecialchars($row['id']); ?>" 
                                                                    data-type="<?= ($programType === 'Dog Claiming') ? 'claim' : 'adoption' ?>">
                                                                <i class="fas fa-times"></i> Cancel
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php 
                                $sequence_number++;
                                            endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <?php 
        // Reset sequence number for mobile view
        $sequence_number = 1;
        ?>
                            
                            <div class="mobile-table" id="mobileRequestsTable-<?php echo htmlspecialchars($programType); ?>">
                                <?php foreach ($requests as $row): 
                                    $date_approved = date('F j, Y', strtotime($row['approved_date']));
                                    $phone_number = formatPhoneNumber($row['contact_number']);
                                ?>
                                    <div class="mobile-row request-row" 
    data-id="<?php echo htmlspecialchars($row['id']); ?>"
    data-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>"
    data-program="<?php echo htmlspecialchars($row['program_type']); ?>"
    data-type="<?php echo htmlspecialchars($row['request_type']); ?>"
    data-date="<?php echo date('Y-m-d', strtotime($row['approved_date'])); ?>"
    data-phone="<?php echo htmlspecialchars($phone_number); ?>"
    data-offense="<?php echo htmlspecialchars($row['offense_count']); ?>"
    data-fine="<?php echo htmlspecialchars($row['fine_amount']); ?>">
                                        <div>
                                            <span class="mobile-label">ID:</span>
                                            <?php echo htmlspecialchars($row['id']); ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Name:</span>
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Contact:</span>
                                            <?php echo htmlspecialchars($phone_number); ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Request Type:</span>
                                            <?php echo htmlspecialchars($row['request_type']); ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Date Approved:</span>
                                            <?php echo $date_approved; ?>
                                        </div>
                                        <div>
                                            <button class="btn btn-view view-btn" 
                                                    data-id="<?php echo htmlspecialchars($row['id']); ?>" 
                                                    data-type="<?= ($programType === 'Dog Claiming') ? 'claim' : 'adoption' ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-complete complete-btn" 
                                                    data-id="<?php echo htmlspecialchars($row['id']); ?>" 
                                                    data-type="<?= ($programType === 'Dog Claiming') ? 'claim' : 'adoption' ?>">
                                                <i class="fas fa-flag-checkered"></i> Complete
                                            </button>
                                            <button class="btn btn-cancel cancel-btn" 
                                                    data-id="<?php echo htmlspecialchars($row['id']); ?>" 
                                                    data-type="<?= ($programType === 'Dog Claiming') ? 'claim' : 'adoption' ?>">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- view details modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewModalContent">
                    <!-- content will be loaded via AJAX -->
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
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Complete Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="completeValidation" class="alert alert-danger d-none mb-3"></div>
                <p>Are you sure you want to mark this request as completed?</p>
                
                <!-- Fine information for dog claiming -->
<div id="fineInformation" class="d-none mt-3 mb-3 p-3 border rounded">
                    <h6 class="mb-2">Fine Details:</h6>
                    <div class="d-flex justify-content-between">
                        <span>Offense Count:</span>
                        <span id="offenseCount" class="fw-bold">1st offense</span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span>Fine Amount:</span>
                        <span id="fineAmount" class="fw-bold">₱300.00</span>
                    </div>
                </div>
                
                <!-- Receipt photo (ONLY for dog claims) -->
                <div id="receiptPhotoSection" class="mb-3 d-none">
                    <label for="receiptPhoto" class="form-label">Receipt Photo (required):</label>
                    <input type="file" class="form-control" id="receiptPhoto" accept="image/*">
                    <small class="text-muted">Proof of payment for the fine</small>
                </div>
                
                <!-- Handover photo (optional for both) -->
                <div class="mb-3">
                    <label for="handoverPhoto" class="form-label">Handover Photo (optional):</label>
                    <input type="file" class="form-control" id="handoverPhoto" accept="image/*">
                    <small class="text-muted">Photo of the adoption/claim process</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmComplete">Mark as Completed</button>
            </div>
        </div>
    </div>
</div>

    <!-- cancel confirmation modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Cancel Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="cancelValidation" class="alert alert-danger d-none mb-3"></div>
                    <p>Are you sure you want to cancel this approved request?</p>
                    <div class="mt-3">
                        <label for="cancelReason" class="form-label">Reason (required):</label>
                        <textarea class="form-control" id="cancelReason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel</button>
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
                    <i class="fas fa-flag-checkered fa-5x text-success mb-4"></i>
                    <h4>Request Marked as Completed!</h4>
                    <p>The request has been successfully completed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterComplete">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- success cancellation modal -->
    <div class="modal fade" id="successCancellationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Request Cancelled</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-times-circle fa-5x text-danger mb-4"></i>
                    <h4>Request Cancelled</h4>
                    <p>The applicant has been notified of the cancellation.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="continueAfterCancel">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- image viewer modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="imageModalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex justify-content-center align-items-center">
                    <img id="fullscreenImage" class="img-fluid" style="max-height: 90vh; max-width: 100%; object-fit: contain;">
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <a id="imageDownload" class="btn btn-primary" download>
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Store original content for resetting filters
        const originalContent = $('#resultsContainer').html();
        let hasInitialRequests = <?php echo $hasRequests ? 'true' : 'false'; ?>;
        let currentRequestId = null;
        let currentRequestType = null;
        
        // View Details
        $(document).on('click', '.view-btn', function() {
            const requestId = $(this).data('id');
            const requestType = $(this).data('type');
            
            $('#viewModalContent').html(`
                <div class="text-center my-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading request details...</p>
                </div>
            `);
            
            $('#viewModal').modal('show');
            
            $.ajax({
                url: '../../../mayor/animal/view_status.php',
                method: 'GET',
                data: {
                    id: requestId,
                    type: requestType
                },
                success: function(data) {
                    $('#viewModalContent').html(data);
                    
                    // Initialize image viewer for any images in the loaded content
                    $(document).on('click', '.document-view', function(e) {
                        e.preventDefault();
                        const src = $(this).data('src');
                        const title = $(this).data('title');
                        
                        $('#fullscreenImage').attr('src', src);
                        $('#imageDownload').attr('href', src);
                        $('#imageModalTitle').text(title);
                        $('#imageModal').modal('show');
                    });
                },
                error: function(xhr, status, error) {
                    $('#viewModalContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Failed to load request details. Please try again.
                            <br><small>${error}</small>
                        </div>
                    `);
                    console.error('Error loading request:', error);
                }
            });
        });

// Complete Button
$(document).on('click', '.complete-btn', function() {
    currentRequestId = $(this).data('id');
    currentRequestType = $(this).data('type');
    
    // Reset fields
    $('#receiptPhoto').val('');
    $('#handoverPhoto').val('');
    $('#completeValidation').addClass('d-none');
    
    if (currentRequestType === 'claim') {
        $('#receiptPhotoSection').removeClass('d-none');
        $('#fineInformation').removeClass('d-none');

        // Grab offense + fine directly from row data
        const row = $(this).closest('tr, .mobile-row');
        const offenseCount = parseInt(row.data('offense'));
        const fineAmount = parseFloat(row.data('fine'));

        // Convert offense_count to human-readable text
        let offenseText = '';
        if (offenseCount === 1) {
            offenseText = '1st offense';
        } else if (offenseCount === 2) {
            offenseText = '2nd offense';
        } else if (offenseCount === 3) {
            offenseText = '3rd offense';
        } else {
            offenseText = offenseCount + 'th offense';
        }
        
        $('#offenseCount').text(offenseText);
        $('#fineAmount').text('₱' + fineAmount.toFixed(2));
    } else {
        $('#receiptPhotoSection').addClass('d-none');
        $('#fineInformation').addClass('d-none');
    }
    
    $('#completeModal').modal('show');
});

// Helper: ordinal suffix
function getSuffix(n) {
    if (n % 10 === 1 && n % 100 !== 11) return 'st';
    if (n % 10 === 2 && n % 100 !== 12) return 'nd';
    if (n % 10 === 3 && n % 100 !== 13) return 'rd';
    return 'th';
}


// Confirm Complete
$('#confirmComplete').click(function() {
    const $validation = $('#completeValidation');
    const $btn = $(this);
    
    $validation.addClass('d-none');
    
    // Validate required photos based on request type
    if (currentRequestType === 'claim') {
        const receiptPhoto = document.getElementById('receiptPhoto');
        if (!receiptPhoto.files.length) {
            $validation.removeClass('d-none').text('Receipt photo is required for dog claims');
            return;
        }
    }
    
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
    
    // Create FormData object to handle file uploads
    const formData = new FormData();
    formData.append('id', currentRequestId);
    formData.append('type', currentRequestType);
    formData.append('action', 'complete');
    
    // Add receipt photo if selected (for claims only)
    if (currentRequestType === 'claim') {
        const receiptPhotoInput = document.getElementById('receiptPhoto');
        if (receiptPhotoInput.files.length > 0) {
            formData.append('receipt_photo', receiptPhotoInput.files[0]);
        }
    }
    
    // Add handover photo if selected (optional for both)
    const handoverPhotoInput = document.getElementById('handoverPhoto');
    if (handoverPhotoInput.files.length > 0) {
        formData.append('handover_photo', handoverPhotoInput.files[0]);
    }
    
    $.ajax({
        url: 'update_status.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false,
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
                $validation.removeClass('d-none').text(response.message || 'Failed to complete request');
            }
        },
        error: function(xhr, status, error) {
            $validation.removeClass('d-none').text('Error: ' + xhr.responseText);
            console.error('Error:', error, xhr.responseText);
        },
        complete: function() {
            $btn.prop('disabled', false).text('Mark as Completed');
        }
    });
});

        // Confirm Cancel
        $('#confirmCancel').click(function() {
            const reason = $('#cancelReason').val().trim();
            const $validation = $('#cancelValidation');
            const $btn = $(this);
            
            if (!reason) {
                $validation.removeClass('d-none').text('Please provide a reason for cancelling this request.');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: currentRequestId,
                    type: currentRequestType,
                    action: 'cancel',
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        $('#cancelModal').modal('hide');
                        const successModal = new bootstrap.Modal('#successCancellationModal', {
                            keyboard: false
                        });
                        successModal.show();
                        
                        $('#continueAfterCancel').off().click(function() {
                            successModal.hide();
                            location.reload();
                        });
                    } else {
                        $validation.removeClass('d-none').text(response.message || 'Failed to cancel request');
                    }
                },
                error: function(xhr, status, error) {
                    $validation.removeClass('d-none').text('Error: ' + xhr.responseText);
                    console.error('Error:', error, xhr.responseText);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yes, Cancel');
                }
            });
        });

        // Live Filtering
        function applyFilters() {
            const searchTerm = $('#searchFilter').val().toLowerCase();
            const programFilter = $('#programFilter').val();
            const requestTypeFilter = $('#requestTypeFilter').val();
            const dateFilter = $('#dateFilter').val();
            
            // If there are no requests to begin with, don't do any filtering
            if (!hasInitialRequests) {
                return;
            }
            
            let anyVisible = false;
            
            // Check each request row
            $('.request-row').each(function() {
                const $row = $(this);
                const $programGroup = $row.closest('.program-group');
                const id = $row.data('id').toString();
                const name = $row.data('name');
                const program = $row.data('program');
                const requestType = $row.data('type');
                const date = $row.data('date');
                const phone = $row.data('phone');
                
                // Check if this row matches all filters
                const matchesSearch = !searchTerm || 
                    name.includes(searchTerm) || 
                    id.includes(searchTerm) || 
                    phone.includes(searchTerm) ||
                    name.split(' ').some(part => part.startsWith(searchTerm));
                
                const matchesProgram = !programFilter || program === programFilter;
                const matchesRequestType = !requestTypeFilter || requestType === requestTypeFilter;
                const matchesDate = !dateFilter || date === dateFilter;
                
                if (matchesSearch && matchesProgram && matchesRequestType && matchesDate) {
                    $row.show();
                    $programGroup.show();
                    anyVisible = true;
                } else {
                    $row.hide();
                }
            });
            
            // After processing all rows, check if any program groups have visible rows
            $('.program-group').each(function() {
                const $programGroup = $(this);
                const hasVisibleRows = $programGroup.find('.request-row:visible').length > 0;
                $programGroup.toggle(hasVisibleRows);
            });
            
            // Show/hide no results message only if we started with requests
            if (hasInitialRequests) {
                if (anyVisible) {
                    $('.no-requests-filtered').remove();
                } else {
                    // Only show filtered message if we have requests but none match filters
                    if ($('.no-requests-filtered').length === 0) {
                        $('#resultsContainer').append(`
                            <div class="no-requests no-requests-filtered">
                                <i class="fas fa-inbox"></i>
                                <h4>No Requests Found</h4>
                                <p>No requests match your current filters.</p>
                            </div>
                        `);
                    }
                }
            }
        }
        
        // Apply filters when any filter changes with a slight delay for search
        let filterTimeout;
        $('#searchFilter').on('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(applyFilters, 300);
        });
        
        $('#programFilter, #requestTypeFilter, #dateFilter').on('change', function() {
            applyFilters();
        });
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>