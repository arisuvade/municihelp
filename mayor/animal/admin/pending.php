<?php
session_start();
include '../../../includes/db.php';

// Check for admin session
if (!isset($_SESSION['animal_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['animal_admin_id'];
$admin_name = 'Animal Control Admin'; // Default value

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

$pageTitle = 'Pending';
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
    'Dog Adoption' => [],
    'Rabid Report' => []
];

$hasRequests = false;

try {
    // Fetch pending claims - FIXED THE QUERY
    $pending_claims = $conn->query("
    SELECT dc.id, 
           CONCAT(dc.last_name, ', ', dc.first_name, ' ', IF(dc.middle_name IS NULL OR dc.middle_name = '', '', CONCAT(LEFT(dc.middle_name, 1), '.'))) AS name,
           dc.phone as contact_number,  -- Changed from u.phone to dc.phone
           dc.created_at,
           dc.name_of_dog,
           'Dog Claiming' as program_type
    FROM dog_claims dc
    WHERE dc.status = 'pending'
");
    
    if ($pending_claims) {
        while ($row = $pending_claims->fetch_assoc()) {
            $groupedRequests['Dog Claiming'][] = $row;
            $hasRequests = true;
        }
    } else {
        throw new Exception("Dog claims query failed: " . $conn->error);
    }

    // ADDED: Fetch pending adoptions (this was missing entirely)
    $pending_adoptions = $conn->query("
        SELECT da.id, 
               CONCAT(da.last_name, ', ', da.first_name, ' ', IF(da.middle_name IS NULL OR da.middle_name = '', '', CONCAT(LEFT(da.middle_name, 1), '.'))) AS name,
               u.phone as contact_number,
               da.created_at,
               NULL as name_of_dog,
               'Dog Adoption' as program_type
        FROM dog_adoptions da
        LEFT JOIN users u ON da.user_id = u.id
        WHERE da.status = 'pending'
    ");
    
    if ($pending_adoptions) {
        while ($row = $pending_adoptions->fetch_assoc()) {
            $groupedRequests['Dog Adoption'][] = $row;
            $hasRequests = true;
        }
    } else {
        throw new Exception("Dog adoptions query failed: " . $conn->error);
    }

    // Fetch pending reports
    $pending_reports = $conn->query("
        SELECT rr.id, 
               CONCAT(rr.last_name, ', ', rr.first_name, ' ', IF(rr.middle_name IS NULL OR rr.middle_name = '', '', CONCAT(LEFT(rr.middle_name, 1), '.'))) AS name,
               u.phone as contact_number,
               rr.created_at,
               NULL as name_of_dog,
               'Rabid Report' as program_type
        FROM rabid_reports rr
        LEFT JOIN users u ON rr.user_id = u.id
        WHERE rr.status = 'pending'
    ");
    
    if ($pending_reports) {
        while ($row = $pending_reports->fetch_assoc()) {
            $groupedRequests['Rabid Report'][] = $row;
            $hasRequests = true;
        }
    } else {
        throw new Exception("Rabid reports query failed: " . $conn->error);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo "<div class='alert alert-danger'>Error loading pending requests: " . htmlspecialchars($e->getMessage()) . "</div>";
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
        
        .btn-approve {
            background-color: var(--approved);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-approve:hover {
            background-color: #218838;
            color: white;
        }
        
        .btn-decline {
            background-color: var(--declined);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-decline:hover {
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
            width: 26%;
        }
        
        .table-col-contact {
            width: 20%;
            text-align: center;
        }
        
        .table-col-date {
            width: 29%;
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
                    <h1 class="mb-1">Pending Requests</h1>
                    <p class="mb-0">Review and process animal control requests</p>
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
                            placeholder="Search by ID, name or contact">
                    </div>
                    
                    <div class="filter-group">
                        <label for="programFilter" class="form-label">Program</label>
                        <select class="form-select" id="programFilter">
                            <option value="">All Programs</option>
                            <option value="Dog Claiming">Dog Claiming</option>
                            <option value="Dog Adoption">Dog Adoption</option>
                            <option value="Rabid Report">Rabid Report</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="dateFilter" class="form-label">Date Submitted</label>
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
                    <p>There are no pending requests to display.</p>
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
                                                    <th class="table-col-date">Date Submitted</th>
                                                    <th class="table-col-actions"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($requests as $row): 
                                                    $phone_number = formatPhoneNumber($row['contact_number']);
                                                    $date_submitted = date('F j, Y', strtotime($row['created_at']));
                                                ?>
                                                    <tr class="request-row" 
                                                        data-id="<?php echo htmlspecialchars($row['id']); ?>"
                                                        data-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>"
                                                        data-program="<?php echo htmlspecialchars($row['program_type']); ?>"
                                                        data-date="<?php echo date('Y-m-d', strtotime($row['created_at'])); ?>"
                                                        data-phone="<?php echo htmlspecialchars($phone_number); ?>">
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                                        <td class="table-col-name"><?php echo htmlspecialchars($row['name']); ?></td>
                                                        <td class="table-col-contact"><?php echo htmlspecialchars($phone_number); ?></td>
                                                        <td class="table-col-date"><?php echo $date_submitted; ?></td>
                                                        <td class="table-col-actions">
                                                            <?php if ($programType === 'Dog Adoption'): ?>
                                                                <button class="btn btn-view view-btn" 
                                                                        data-id="<?= $row['id'] ?>" 
                                                                        data-type="adoption">
                                                                    <i class="fas fa-eye"></i> View
                                                                </button>
                                                            <?php elseif ($programType === 'Dog Claiming'): ?>
                                                                <button class="btn btn-view view-btn" 
                                                                        data-id="<?= $row['id'] ?>" 
                                                                        data-type="claim">
                                                                    <i class="fas fa-eye"></i> View
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-view view-btn" 
                                                                        data-id="<?= $row['id'] ?>" 
                                                                        data-type="report">
                                                                    <i class="fas fa-eye"></i> View
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($programType == 'Rabid Report'): ?>
                                                                <button class="btn btn-approve verify-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                                    <i class="fas fa-check"></i> Verify
                                                                </button>
                                                                <button class="btn btn-decline false-report-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                                    <i class="fas fa-times"></i> False Report
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-approve approve-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                                <button class="btn btn-decline decline-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                                    <i class="fas fa-times"></i> Decline
                                                                </button>
                                                            <?php endif; ?>
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
                                    $phone_number = formatPhoneNumber($row['contact_number']);
                                    $date_submitted = date('F j, Y', strtotime($row['created_at']));
                                ?>
                                    <div class="mobile-row request-row" 
                                        data-id="<?php echo htmlspecialchars($row['id']); ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>"
                                        data-program="<?php echo htmlspecialchars($row['program_type']); ?>"
                                        data-date="<?php echo date('Y-m-d', strtotime($row['created_at'])); ?>"
                                        data-phone="<?php echo htmlspecialchars($phone_number); ?>">
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
                                            <span class="mobile-label">Date Submitted:</span>
                                            <?php echo $date_submitted; ?>
                                        </div>
                                        <div>
                                            <button class="btn btn-view view-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if ($programType == 'Rabid Report'): ?>
                                                <button class="btn btn-approve verify-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-decline false-report-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                    <i class="fas fa-times"></i> Decline
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-approve approve-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-decline decline-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-type="<?php echo strtolower(str_replace(' ', '_', $row['program_type'])); ?>">
                                                    <i class="fas fa-times"></i> Decline
                                                </button>
                                            <?php endif; ?>
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

    <!-- approve confirmation modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveModalTitle">Confirm Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="approveValidation" class="alert alert-danger d-none mb-3"></div>
                <p id="approveModalMessage">Are you sure you want to approve this request?</p>
                
                <!-- Fine information for dog claiming -->
                <div id="fineInformation" class="d-none mt-3 p-3 border rounded">
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
                        <label for="declineReason" class="form-label">Reason (required):</label>
                        <textarea class="form-control" id="declineReason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDecline">Yes, Decline</button>
                </div>
            </div>
        </div>
    </div>

    <!-- false report confirmation modal -->
<div class="modal fade" id="falseReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm False Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="falseReportValidation" class="alert alert-danger d-none mb-3"></div>
                Are you sure you want to mark this as false report?
                <div class="mt-3">
                    <label for="falseReportReason" class="form-label">Reason (required):</label>
                    <select class="form-select" id="falseReportReason" required>
                        <option value="" disabled selected>Select a reason</option>
                        <option value="False information">False information</option>
                        <option value="Report is irrelevant / not seen in the area">Report is irrelevant / not seen in the area</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmFalseReport" disabled>Yes, Mark as False</button>
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
                    <h4>Request Approved!</h4>
                    <p>The applicant will be notified of the approval.</p>
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
                    <h4>Request Declined</h4>
                    <p>The applicant has been notified of the decision.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="continueAfterDecline">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- success false report modal -->
    <div class="modal fade" id="successFalseReportModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Report Marked as False</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-times-circle fa-5x text-danger mb-4"></i>
                    <h4>Report Marked as False</h4>
                    <p>The reporter has been notified of the decision.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="continueAfterFalseReport">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this modal for image viewing -->
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
// Function to calculate fine based on offense count
function calculateFine(totalClaims) {
    // Offense count is total_claims + 1 (current claim)
    const offenseCount = parseInt(totalClaims) + 1;
    
    let fineAmount = 0;
    
    // Fine structure: 1st=300, 2nd=500, 3rd=800 (capped at 3rd offense)
    if (offenseCount === 1) {
        fineAmount = 300;
    } else if (offenseCount === 2) {
        fineAmount = 500;
    } else {
        fineAmount = 800; // 3rd offense and beyond (capped at 800)
    }
    
    return {
        offenseCount: offenseCount,
        fineAmount: fineAmount,
        offenseText: offenseCount === 1 ? '1st offense' : 
                    offenseCount === 2 ? '2nd offense' : 
                    offenseCount === 3 ? '3rd offense' : 
                    offenseCount + 'th offense'
    };
}

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

        // Approve/Verify Button
    $(document).on('click', '.approve-btn, .verify-btn', function() {
        currentRequestId = $(this).data('id');
        currentRequestType = $(this).data('type');
        $('#approveValidation').addClass('d-none');
        
        // Reset modal to default state
        $('#approveModalTitle').text('Confirm Approval');
        $('#approveModalMessage').text('Are you sure you want to approve this request?');
        $('#fineInformation').addClass('d-none');
        $('#confirmApprove').text('Yes, Approve');
        
        // For dog claiming, fetch claimer data to calculate fine
        if (currentRequestType === 'dog_claiming') {
            // Show loading state
            $('#approveModalMessage').html('<div class="spinner-border spinner-border-sm" role="status"></div> Loading fine information...');
            
            $('#approveModal').modal('show');
            
            // Fetch claimer data to calculate fine
            $.ajax({
                url: 'get_claimer.php',
                method: 'POST',
                data: {
                    request_id: currentRequestId
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            const totalClaims = data.total_claims || 0;
                            const fineDetails = calculateFine(totalClaims);
                            
                            // Update modal with fine information
                            $('#offenseCount').text(fineDetails.offenseText);
                            $('#fineAmount').text('₱' + fineDetails.fineAmount.toFixed(2));
                            $('#fineInformation').removeClass('d-none');
                            
                            // Update message
                            $('#approveModalMessage').text('Are you sure you want to approve this dog claiming request?');
                        } else {
                            $('#approveModalMessage').text('Are you sure you want to approve this request?');
                            console.error('Error fetching claimer data:', data.message);
                        }
                    } catch (e) {
                        $('#approveModalMessage').text('Are you sure you want to approve this request?');
                        console.error('Error parsing response:', e);
                    }
                },
                error: function(xhr, status, error) {
                    $('#approveModalMessage').text('Are you sure you want to approve this request?');
                    console.error('Error fetching claimer data:', error);
                }
            });
        } 
        // For rabid reports, change the wording
        else if (currentRequestType === 'rabid_report') {
            $('#approveModalTitle').text('Confirm Verification');
            $('#approveModalMessage').text('Are you sure you want to verify this rabid report?');
            $('#confirmApprove').text('Yes, Verify');
            $('#approveModal').modal('show');
        }
        // For other types
        else {
            $('#approveModal').modal('show');
        }
    });

        
        // Verify Button (for Rabid Reports)
        $(document).on('click', '.verify-btn', function() {
            currentRequestId = $(this).data('id');
            currentRequestType = $(this).data('type');
            $('#approveValidation').addClass('d-none');
            $('#approveModal').modal('show');
        });
        
        // Decline Button
        $(document).on('click', '.decline-btn', function() {
            currentRequestId = $(this).data('id');
            currentRequestType = $(this).data('type');
            $('#declineReason').val('');
            $('#declineValidation').addClass('d-none');
            $('#declineModal').modal('show');
        });
        
        // False Report Button
        $(document).on('click', '.false-report-btn', function() {
            currentRequestId = $(this).data('id');
            currentRequestType = $(this).data('type');
            $('#falseReportReason').val('');
            $('#falseReportValidation').addClass('d-none');
            $('#falseReportModal').modal('show');
        });

        // Confirm Approve
        $('#confirmApprove').click(function() {
            const $validation = $('#approveValidation');
            const $btn = $(this);
            
            $validation.addClass('d-none');
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: currentRequestId,
                    type: currentRequestType,
                    action: currentRequestType === 'rabid_report' ? 'verify' : 'approve'
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
                        $validation.removeClass('d-none').text(response.message || 'Failed to approve request');
                    }
                },
                error: function(xhr, status, error) {
                    $validation.removeClass('d-none').text('Error: ' + xhr.responseText);
                    console.error('Error:', error, xhr.responseText);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yes, Approve');
                }
            });
        });

        // Confirm Decline
        $('#confirmDecline').click(function() {
            const reason = $('#declineReason').val().trim();
            const $validation = $('#declineValidation');
            const $btn = $(this);
            
            if (!reason) {
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
                    type: currentRequestType,
                    action: 'decline',
                    reason: reason
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
                    $validation.removeClass('d-none').text('Error: ' + xhr.responseText);
                    console.error('Error:', error, xhr.responseText);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yes, Decline');
                }
            });
        });
        
        // Confirm False Report
        $('#confirmFalseReport').click(function() {
    const reason = $('#falseReportReason').val();
    const $validation = $('#falseReportValidation');
    const $btn = $(this);
    
    if (!reason) {
        $validation.removeClass('d-none').text('Please select a reason for marking this as false report.');
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
            action: 'false_report',
            reason: reason
        },
        success: function(response) {
            if (response.success) {
                $('#falseReportModal').modal('hide');
                const successModal = new bootstrap.Modal('#successFalseReportModal', {
                    keyboard: false
                });
                successModal.show();
                
                $('#continueAfterFalseReport').off().click(function() {
                    successModal.hide();
                    location.reload();
                });
            } else {
                $validation.removeClass('d-none').text(response.message || 'Failed to mark as false report');
            }
        },
        error: function(xhr, status, error) {
            $validation.removeClass('d-none').text('Error: ' + xhr.responseText);
            console.error('Error:', error, xhr.responseText);
        },
        complete: function() {
            $btn.prop('disabled', false).text('Yes, Mark as False');
        }
    });
});

// Add this event handler for the dropdown change
$('#falseReportReason').on('change', function() {
    const reason = $(this).val();
    $('#confirmFalseReport').prop('disabled', !reason);
});

// Update the confirmFalseReport click handler
$('#confirmFalseReport').click(function() {
    const reason = $('#falseReportReason').val();
    const $validation = $('#falseReportValidation');
    const $btn = $(this);
    
    if (!reason) {
        $validation.removeClass('d-none').text('Please select a reason for marking this as false report.');
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
            action: 'false_report',
            reason: reason
        },
        success: function(response) {
            if (response.success) {
                $('#falseReportModal').modal('hide');
                const successModal = new bootstrap.Modal('#successFalseReportModal', {
                    keyboard: false
                });
                successModal.show();
                
                $('#continueAfterFalseReport').off().click(function() {
                    successModal.hide();
                    location.reload();
                });
            } else {
                $validation.removeClass('d-none').text(response.message || 'Failed to mark as false report');
                $btn.prop('disabled', false).text('Yes, Mark as False');
            }
        },
        error: function(xhr, status, error) {
            $validation.removeClass('d-none').text('Error: ' + xhr.responseText);
            console.error('Error:', error, xhr.responseText);
            $btn.prop('disabled', false).text('Yes, Mark as False');
        }
    });
});

// Reset the modal when it's closed
$('#falseReportModal').on('hidden.bs.modal', function() {
    $('#falseReportReason').val('');
    $('#confirmFalseReport').prop('disabled', true);
    $('#falseReportValidation').addClass('d-none');
});

        // Live Filtering
        function applyFilters() {
            const searchTerm = $('#searchFilter').val().toLowerCase();
            const programFilter = $('#programFilter').val();
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
                const date = $row.data('date');
                const phone = $row.data('phone');
                
                // Check if this row matches all filters
                const matchesSearch = !searchTerm || 
                    name.includes(searchTerm) || 
                    id.includes(searchTerm) || 
                    phone.includes(searchTerm) ||
                    name.split(' ').some(part => part.startsWith(searchTerm));
                
                const matchesProgram = !programFilter || program === programFilter;
                const matchesDate = !dateFilter || date === dateFilter;
                
                if (matchesSearch && matchesProgram && matchesDate) {
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
        
        // Reset filters
        function resetFilters() {
            $('#searchFilter').val('');
            $('#programFilter').val('');
            $('#dateFilter').val('');
            
            if (hasInitialRequests) {
                $('.request-row').show();
                $('.program-group').show();
                $('.no-requests-filtered').remove();
            }
        }
        
        // Apply filters when any filter changes with a slight delay for search
        let filterTimeout;
        $('#searchFilter').on('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(applyFilters, 300);
        });
        
        $('#programFilter, #dateFilter').on('change', function() {
            applyFilters();
        });
        
        // Reset button functionality if you add one
        $(document).on('click', '.reset-filters', function() {
            resetFilters();
        });
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>