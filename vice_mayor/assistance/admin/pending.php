<?php
session_start();
include '../../../includes/db.php';

if (!isset($_SESSION['assistance_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$assistance_admin_id = $_SESSION['assistance_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $assistance_admin_id");
$admin_data = $admin_query->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

$pageTitle = 'Pending';
include '../../../includes/header.php';

function formatPhoneNumber($phone) {
    // Convert +639 to 09
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

function calculateAge($birthday) {
    if (empty($birthday)) return 'N/A';
    
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age . ' years old';
}

// Get all pending requests
$stmt = $conn->prepare("
    SELECT ar.id, at.name as program, at.parent_id,
           ar.last_name, ar.first_name, ar.middle_name, ar.birthday,
           u.phone, ar.created_at, b.name as barangay
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    JOIN users u ON ar.user_id = u.id
    LEFT JOIN barangays b ON ar.barangay_id = b.id
    WHERE ar.status = 'pending'
    ORDER BY ar.id ASC
");
$stmt->execute();
$result = $stmt->get_result();

// Get all programs for filter dropdown
$programsQuery = "SELECT id, name, parent_id FROM assistance_types ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, name";
$programs = $conn->query($programsQuery);

// Store programs in an array for later use
$programsArray = [];
while ($program = $programs->fetch_assoc()) {
    $programsArray[] = $program;
}

// Reset pointer for dropdown display
$programs->data_seek(0);

// Get all barangays for filter dropdown
$barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name");

// Calculate next Monday's date
$nextMonday = new DateTime();
$nextMonday->modify('next monday');
$nextMondayStr = $nextMonday->format('Y-m-d');

// Prepare the queue date for JavaScript
$queueDateData = json_encode([
    'success' => true,
    'queue_date' => $nextMondayStr
]);
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

        /* Updated button styles from approved.php */
        .btn-view {
            background-color: var(--completed);
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
        
        .day-header {
            background-color: #ffffff;
            color: #000;
            padding: 15px;
            margin-top: 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .day-title {
            font-size: 1.1rem;
        }
        
        .day-date {
            font-weight: normal;
            color: #6c757d;
        }
        
        .reschedule-count {
            font-size: 0.8rem;
            color: #6c757d;
            margin-left: 5px;
        }
        
        @keyframes iconAppear {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .fa-check-circle {
            animation: iconAppear 0.5s ease-in-out;
        }
        
        /* Column widths */
        .table-col-seq {
            width: 5%;
            text-align: center;
        }
        .table-col-name {
            width: 22%;
        }
        
        
        .table-col-program {
            width: 15%;
            text-align: center;
        }
        
        .table-col-phone {
            width: 10%;
            text-align: center;
        }
        
        .table-col-barangay {
            width: 13%;
            text-align: center;
        }
        
        .table-col-date {
            width: 15%;
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
                    <p class="mb-0">Review and process requests</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($admin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Filter Section -->
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
                            <?php 
                            $currentParent = null;
                            foreach ($programsArray as $program): 
                                if ($program['parent_id'] === null) {
                                    $currentParent = $program['id'];
                                    ?>
                                    <option value="<?= htmlspecialchars($program['name']) ?>">
                                        <?= htmlspecialchars($program['name']) ?>
                                    </option>
                                    <?php
                                    // Find and display children
                                    foreach ($programsArray as $child) {
                                        if ($child['parent_id'] == $currentParent) {
                                            ?>
                                            <option value="<?= htmlspecialchars($child['name']) ?>">
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
                    
                    <div class="filter-group">
                        <label for="barangayFilter" class="form-label">Barangay</label>
                        <select class="form-select" id="barangayFilter">
                            <option value="">All Barangays</option>
                            <?php while ($barangay = $barangays->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($barangay['name']) ?>">
                                    <?= htmlspecialchars($barangay['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="dateFilter" class="form-label">Date Submitted</label>
                        <input type="date" class="form-control" id="dateFilter">
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
<div id="resultsContainer">
    <?php if ($result->num_rows == 0): ?>
    <div class="no-requests">
        <i class="fas fa-inbox"></i>
        <h4>No Requests Found</h4>
        <p>There are no pending requests to display.</p>
    </div>
<?php else: 
        // Initialize sequence number
        $sequence_number = 1;
?>
        <!-- Desktop Table -->
        <div class="recent-activity-card card mb-4 desktop-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th class="table-col-seq">#</th>
                                <th class="table-col-name">Name</th>
                                <th class="table-col-program">Program</th>
                                <th class="table-col-phone">Contact No.</th>
                                <th class="table-col-barangay">Barangay</th>
                                <th class="table-col-date">Date Submitted</th>
                                <th class="table-col-actions"></th>
                            </tr>
                        </thead>
                        <tbody id="requestsTableBody">
                            <?php while ($row = $result->fetch_assoc()): 
                                $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                $phone_number = formatPhoneNumber($row['phone']);
                                
                                // Get parent program name if this is a child program
                                $parent_program = null;
                                if ($row['parent_id']) {
                                    foreach ($programsArray as $program) {
                                        if ($program['id'] == $row['parent_id']) {
                                            $parent_program = $program['name'];
                                            break;
                                        }
                                    }
                                }
                            ?>
                                <tr class="request-row" 
                                    data-id="<?= $row['id'] ?>"
                                    data-name="<?= htmlspecialchars(strtolower($row['last_name'] . ' ' . $row['first_name'])) ?>"
                                    data-program="<?= htmlspecialchars($row['program']) ?>"
                                    data-parent-program="<?= htmlspecialchars($parent_program ?? '') ?>"
                                    data-barangay="<?= htmlspecialchars($row['barangay'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($phone_number) ?>"
                                    data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>">
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                    <td class="table-col-name"><?= $applicant_name ?></td>
                                    <td class="table-col-program"><?= htmlspecialchars($row['program']) ?></td>
                                    <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
                                    <td class="table-col-barangay"><?= htmlspecialchars($row['barangay'] ?? 'N/A') ?></td>
                                    <td class="table-col-date"><?= date('F j, Y', strtotime($row['created_at'])) ?></td>
                                    <td class="table-col-actions">
                                        <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-approve approve-btn" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-decline decline-btn" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-times"></i> Decline
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                                $sequence_number++;
                        endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php 
        // Reset sequence number for mobile view
        $sequence_number = 1;
        ?>
        
        <!-- Mobile Table -->
        <div class="mobile-table" id="mobileRequestsTable">
            <?php 
            $result->data_seek(0); // Reset pointer to start
            while ($row = $result->fetch_assoc()): 
                $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                $phone_number = formatPhoneNumber($row['phone']);
                
                // Get parent program name if this is a child program
                $parent_program = null;
                if ($row['parent_id']) {
                    foreach ($programsArray as $program) {
                        if ($program['id'] == $row['parent_id']) {
                            $parent_program = $program['name'];
                            break;
                        }
                    }
                }
            ?>
                <div class="mobile-row request-row" 
                    data-id="<?= $row['id'] ?>"
                    data-name="<?= htmlspecialchars(strtolower($row['last_name'] . ' ' . $row['first_name'])) ?>"
                    data-program="<?= htmlspecialchars($row['program']) ?>"
                    data-parent-program="<?= htmlspecialchars($parent_program ?? '') ?>"
                    data-barangay="<?= htmlspecialchars($row['barangay'] ?? '') ?>"
                    data-phone="<?= htmlspecialchars($phone_number) ?>"
                    data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>">
                    <div>
                        <span class="mobile-label">No:</span>
                        <?= $sequence_number ?>
                    </div>
                    <div>
                        <span class="mobile-label">ID:</span>
                        <?= $row['id'] ?>
                    </div>
                    <div>
                        <span class="mobile-label">Name:</span>
                        <?= $applicant_name ?>
                    </div>
                    <div>
                        <span class="mobile-label">Program:</span>
                        <?= htmlspecialchars($row['program']) ?>
                    </div>
                    <div>
                        <span class="mobile-label">Contact:</span>
                        <?= htmlspecialchars($phone_number) ?>
                    </div>
                    <div>
                        <span class="mobile-label">Barangay:</span>
                        <?= htmlspecialchars($row['barangay'] ?? 'N/A') ?>
                    </div>
                    <div>
                        <span class="mobile-label">Date Submitted:</span>
                        <?= date('F j, Y', strtotime($row['created_at'])) ?>
                    </div>
                    <div>
                        <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-approve approve-btn" data-id="<?= $row['id'] ?>">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-decline decline-btn" data-id="<?= $row['id'] ?>">
                            <i class="fas fa-times"></i> Decline
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
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
                    <div id="approveValidation" class="alert alert-danger d-none mb-3"></div>
                    Are you sure you want to approve this request?
                    <div class="mt-3">
                        <label for="queueDate" class="form-label">Queue Date:</label>
                        <input type="date" class="form-control" id="queueDate" value="<?= $nextMonday->format('Y-m-d') ?>" required>
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
                    <p>The applicant has been scheduled and notified via SMS.</p>
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
    // Store the queue date from PHP
    const queueDateData = <?= $queueDateData ?>;
    
    // Store original content for resetting filters
    const originalContent = $('#resultsContainer').html();
    
    $(document).ready(function() {
        let currentRequestId = null;
        
        // View Requirements
        $(document).on('click', '.view-btn', function() {
            const requestId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: '../../../vice_mayor/assistance/view_request.php?id=' + requestId,
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
        $(document).on('click', '.approve-btn', function() {
            currentRequestId = $(this).data('id');
            $('#approveValidation').addClass('d-none');
            
            // Set the queue date from the PHP-calculated value
            $('#queueDate').val(queueDateData.queue_date);
            
            $('#approveModal').modal('show');
        });
        
        // Decline Button
        $(document).on('click', '.decline-btn', function() {
            currentRequestId = $(this).data('id');
            $('#declineReason').val('');
            $('#declineValidation').addClass('d-none');
            $('#declineModal').modal('show');
        });

        // Confirm Approve
        $('#confirmApprove').click(function() {
            const queueDate = $('#queueDate').val();
            const $validation = $('#approveValidation');
            const $btn = $(this);
            
            $validation.addClass('d-none');
            
            if (!queueDate) {
                $validation.removeClass('d-none').text('Please select a queue date');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: currentRequestId,
                    action: 'approve',
                    queue_date: queueDate
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

        function applyFilters() {
    const searchTerm = $('#searchFilter').val().toLowerCase();
    const programFilter = $('#programFilter').val();
    const barangayFilter = $('#barangayFilter').val();
    const dateFilter = $('#dateFilter').val();
    
    let anyVisible = false;
    
    // Remove any existing filtered message first
    $('.no-requests-filtered').remove();
    
    // Check if we have any requests at all (initial state)
    const hasRequests = $('.request-row').length > 0;
    
    $('.request-row').each(function() {
        const $row = $(this);
        const id = $row.data('id').toString();
        const name = $row.data('name');
        const program = $row.data('program');
        const parentProgram = $row.data('parent-program');
        const barangay = $row.data('barangay');
        const date = $row.data('date');
        const phone = $row.data('phone');
        
        // Check if this row matches all filters
        const matchesSearch = !searchTerm || 
            name.includes(searchTerm) || 
            id.includes(searchTerm) || 
            phone.includes(searchTerm);
        
        const matchesProgram = !programFilter || 
            program === programFilter || 
            parentProgram === programFilter;
        
        const matchesBarangay = !barangayFilter || barangay === barangayFilter;
        const matchesDate = !dateFilter || date === dateFilter;
        
        if (matchesSearch && matchesProgram && matchesBarangay && matchesDate) {
            $row.show();
            anyVisible = true;
        } else {
            $row.hide();
        }
    });
    
    // Show/hide no results message
    if (!anyVisible) {
        // If there are no requests at all (initial state)
        if (!hasRequests) {
            // Show the initial "no requests" message
            $('.no-requests').show();
            $('.desktop-table, .mobile-table').hide();
        } else {
            // Only show filtered message if we have requests but none match filters
            $('#resultsContainer').append(`
                <div class="no-requests no-requests-filtered">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>No requests match your current filters.</p>
                </div>
            `);
            
            // Hide tables
            $('.desktop-table, .mobile-table').hide();
        }
    } else {
        // Remove filtered message if it exists
        $('.no-requests-filtered').remove();
        
        // Show tables
        if (window.innerWidth > 768) {
            $('.desktop-table').show();
            $('.mobile-table').hide();
        } else {
            $('.desktop-table').hide();
            $('.mobile-table').show();
        }
        
        // Hide initial "no requests" message if it exists
        $('.no-requests').hide();
    }
}
        
        /// Apply filters when any filter changes with a slight delay for search
        let filterTimeout;
        $('#searchFilter').on('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(applyFilters, 300);
        });
        
        $('#programFilter, #barangayFilter, #dateFilter').on('change', function() {
            applyFilters();
        });
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>