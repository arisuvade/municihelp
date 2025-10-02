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

$pageTitle = 'Approved';
include '../../../includes/header.php';

function calculateAge($birthday) {
    if (empty($birthday)) return 'N/A';
    
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age . ' years old';
}

// Get all approved requests with queue information, grouped by queue_date
$stmt = $conn->prepare("
    SELECT ar.id, at.name as program, at.parent_id,
           ar.last_name, ar.first_name, ar.middle_name, ar.birthday,
           u.phone, ar.updated_at, ar.queue_date,
           ar.status, ar.approvedby_admin_id, ar.completedby_admin_id, ar.released_date,
           ar.recipient, ar.relation_to_recipient, b.name as barangay,
           ar.reschedule_count, ar.amount, ar.is_walkin,
           a.name as admin_name, ar.user_id
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    LEFT JOIN users u ON ar.user_id = u.id
    LEFT JOIN barangays b ON ar.barangay_id = b.id
    LEFT JOIN admins a ON ar.walkin_admin_id = a.id
    WHERE ar.status = 'approved'
    ORDER BY ar.is_walkin DESC, ar.queue_date ASC, ar.updated_at ASC
");
$stmt->execute();
$result = $stmt->get_result();

// Separate walk-ins and regular requests
$walkInRequests = [];
$regularRequests = [];

while ($row = $result->fetch_assoc()) {
    if ($row['is_walkin'] == 1) {
        $walkInRequests[] = $row;
    } else {
        $queueDate = $row['queue_date'];
        if (!isset($regularRequests[$queueDate])) {
            $regularRequests[$queueDate] = [];
        }
        $regularRequests[$queueDate][] = $row;
    }
}

// Calculate next Monday's date for default reschedule
$nextMonday = new DateTime();
$nextMonday->modify('next monday');
$nextMondayStr = $nextMonday->format('Y-m-d');

// Get all programs for filter dropdown
$programs = $conn->query("
    SELECT id, name, parent_id 
    FROM assistance_types 
    ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, name
");

// Store programs in an array for later use
$programsArray = [];
while ($program = $programs->fetch_assoc()) {
    $programsArray[] = $program;
}

// Reset pointer for dropdown display
$programs->data_seek(0);

// Get all barangays for filter dropdown
$barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name");

function formatPhoneNumber($phone) {
    if (empty($phone)) return 'Walk-in';
    // Convert +639 to 09
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}
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

        .btn-view, .btn-approve, .btn-decline {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
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
        
        .btn-reschedule {
            background-color: #ffc107;
            color: #000;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-reschedule:hover {
            background-color: #e0a800;
            color: #000;
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
        
        .table-col-admin {
            width: 21%;
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
    </style>
</head>
<body>
<div class="dashboard-header">
    <div class="dashboard-container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Approved Requests</h1>
                <p class="mb-0">Manage approved requests and People's Day queue</p>
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
                        placeholder="Search by ID, name or contact">
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
                    <label for="dateFilter" class="form-label">Date Approved</label>
                    <input type="date" class="form-control" id="dateFilter">
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($walkInRequests) && empty($regularRequests)): ?>
    <div class="no-requests">
        <i class="fas fa-inbox"></i>
        <h4>No Requests Found</h4>
        <p>There are no approved requests to display.</p>
    </div>
    <?php else: 
        // Initialize sequence number
        $sequence_number = 1;
?>
        <!-- Walk-in List Section -->
        <?php if (!empty($walkInRequests)): ?>
            <div class="day-header">
                <div>
                    <h4 class="mb-0">Walk-in List</h4>
                </div>
            </div>
            
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
                                    <th class="table-col-barangay">Barangay</th>
                                    <th class="table-col-admin">Submitted By</th>
                                    <th class="table-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($walkInRequests as $row): 
                                    $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                    $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                    
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
                                        data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                        data-date="<?= date('Y-m-d', strtotime($row['updated_at'])) ?>"
                                        data-queue-date="<?= $row['queue_date'] ?>"
                                        data-is-walkin="1">
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                        <td class="table-col-name"><?= $applicant_name ?></td>
                                        <td class="table-col-program"><?= htmlspecialchars($row['program']) ?></td>
                                        <td class="table-col-barangay"><?= htmlspecialchars($row['barangay'] ?? 'N/A') ?></td>
                                        <td class="table-col-admin"><?= htmlspecialchars($row['admin_name'] ?? 'N/A') ?></td>
                                        <td class="table-col-actions">
                                            <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-complete complete-btn" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-flag-checkered"></i> Complete
                                            </button>
                                            <button class="btn btn-decline decline-btn" data-id="<?= $row['id'] ?>">
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
            
            <!-- Mobile Table -->
            <div class="mobile-table" id="mobileWalkinRequestsTable">
                <?php foreach ($walkInRequests as $row): 
                    $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                    $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                    
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
                        data-phone="<?= htmlspecialchars($row['phone']) ?>"
                        data-date="<?= date('Y-m-d', strtotime($row['updated_at'])) ?>"
                        data-queue-date="<?= $row['queue_date'] ?>"
                        data-is-walkin="1">
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
                            <span class="mobile-label">Barangay:</span>
                            <?= htmlspecialchars($row['barangay'] ?? 'N/A') ?>
                        </div>
                        <div>
                            <span class="mobile-label">Submitted By:</span>
                            <?= htmlspecialchars($row['admin_name'] ?? 'N/A') ?>
                        </div>
                        <div>
                            <span class="mobile-label">Date Submitted:</span>
                            <?= date('F j, Y', strtotime($row['updated_at'])) ?>
                        </div>
                        <div>
                            <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-complete complete-btn" data-id="<?= $row['id'] ?>">
                                <i class="fas fa-flag-checkered"></i> Complete
                            </button>
                            <button class="btn btn-decline decline-btn" data-id="<?= $row['id'] ?>">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                <?php 
                $sequence_number++;
            endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Regular Requests Section -->
        <?php foreach ($regularRequests as $queueDate => $requests): ?>
            <?php 
            $dateObj = new DateTime($queueDate);
            $formattedDate = $dateObj->format('F j, Y');
            ?>
            
            <div class="day-header">
                <div>
                    <h4 class="mb-0">Request List - <?= $formattedDate ?></h4>
                    <?php if ((new DateTime($queueDate)) < (new DateTime('today'))): ?>
                        <span class="badge bg-danger">Past Due</span>
                    <?php endif; ?>
                </div>
                <button class="btn btn-sm btn-reschedule reschedule-day-btn" data-date="<?= $queueDate ?>">
                    <i class="fas fa-calendar-plus"></i> Reschedule All
                </button>
            </div>
            
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
                                    <th class="table-col-date">Date Approved</th>
                                    <th class="table-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $row): 
                                    $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                    $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                    
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
                                        data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                        data-date="<?= date('Y-m-d', strtotime($row['updated_at'])) ?>"
                                        data-queue-date="<?= $row['queue_date'] ?>"
                                        data-is-walkin="0">
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                        <td class="table-col-name"><?= $applicant_name ?></td>
                                        <td class="table-col-program"><?= htmlspecialchars($row['program']) ?></td>
                                        <td class="table-col-phone"><?= htmlspecialchars(formatPhoneNumber($row['phone'])) ?></td>
                                        <td class="table-col-barangay"><?= htmlspecialchars($row['barangay'] ?? 'N/A') ?></td>
                                        <td class="table-col-date"><?= date('F j, Y', strtotime($row['updated_at'])) ?></td>
                                        <td class="table-col-actions">
                                            <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-complete complete-btn" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-flag-checkered"></i> Complete
                                            </button>
                                            <button class="btn btn-decline decline-btn" data-id="<?= $row['id'] ?>">
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
            
            <!-- Mobile Table -->
            <div class="mobile-table" id="mobileRequestsTable-<?= $queueDate ?>">
                <?php foreach ($requests as $row): 
                    $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                    $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                    
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
                        data-phone="<?= htmlspecialchars($row['phone']) ?>"
                        data-date="<?= date('Y-m-d', strtotime($row['updated_at'])) ?>"
                        data-queue-date="<?= $row['queue_date'] ?>"
                        data-is-walkin="0">
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
                            <?= htmlspecialchars(formatPhoneNumber($row['phone'])) ?>
                        </div>
                        <div>
                            <span class="mobile-label">Barangay:</span>
                            <?= htmlspecialchars($row['barangay'] ?? 'N/A') ?>
                        </div>
                        <div>
                            <span class="mobile-label">Date Approved:</span>
                            <?= date('F j, Y', strtotime($row['updated_at'])) ?>
                        </div>
                        <div>
                            <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-complete complete-btn" data-id="<?= $row['id'] ?>">
                                <i class="fas fa-flag-checkered"></i> Complete
                            </button>
                            <button class="btn btn-decline decline-btn" data-id="<?= $row['id'] ?>">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
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
                
                <div class="form-group mt-3">
                    <label for="releaseDate" class="form-label">Released Date:</label>
                    <input type="date" class="form-control" id="releaseDate" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <!-- Relation to Recipient field FIRST -->
                <div class="form-group">
                    <label for="recipientRelation" class="form-label">Relation to Recipient:</label>
                    <select class="form-select" id="recipientRelation" required>
                        <?php
                        // Get family relations from database and find the "Self" relation
                        $relations = $conn->query("SELECT * FROM family_relations ORDER BY id ASC");
                        $selfRelationValue = '';
                        
                        while ($relation = $relations->fetch_assoc()): 
                            $relationValue = htmlspecialchars($relation['english_term'] . ' (' . $relation['filipino_term'] . ')');
                            
                            // Check if this is the "Self" relation
                            if (strtolower($relation['english_term']) === 'self') {
                                $selfRelationValue = $relationValue;
                            }
                        ?>
                            <option value="<?= $relationValue ?>">
                                <?= $relationValue ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Recipient Name field SECOND -->
                <div class="form-group">
                    <label for="recipientName" class="form-label">Recipient Name:</label>
                    <input type="text" class="form-control" id="recipientName" required>
                </div>
                
                <div class="form-group">
                    <label for="amount" class="form-label">Amount (PHP):</label>
                    <input type="number" class="form-control" id="amount" placeholder="Optional">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmComplete">Mark as Completed</button>
            </div>
        </div>
    </div>
</div>

    <!-- decline confirmation modal -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="declineValidation" class="alert alert-danger d-none mb-3"></div>
                    Are you sure you want to cancel this request?
                    <div class="mt-3">
                        <label for="declineReason" class="form-label">Reason (required):</label>
                        <textarea class="form-control" id="declineReason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDecline">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- reschedule day confirmation modal -->
    <div class="modal fade" id="rescheduleDayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Confirm Reschedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="rescheduleValidation" class="alert alert-danger d-none mb-3"></div>
                    Are you sure you want to reschedule all requests?
                    <div class="mt-3">
                        <label for="rescheduleDate" class="form-label">New Date:</label>
                        <input type="date" class="form-control" id="rescheduleDate" value="<?= $nextMondayStr ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmReschedule">Confirm Reschedule</button>
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
                    <p id="completionMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterComplete">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- success decline modal -->
    <div class="modal fade" id="successDeclineModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Request Cancelled</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-times-circle fa-5x text-danger mb-4"></i>
                    <h4>Request Cancelled Successfully!</h4>
                    <p id="declineMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="continueAfterDecline">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- success reschedule modal -->
    <div class="modal fade" id="successRescheduleModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Requests Rescheduled</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-calendar-check fa-5x text-warning mb-4"></i>
                    <h4>All requests rescheduled successfully!</h4>
                    <p id="rescheduleMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" id="continueAfterReschedule">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <div id="resultsContainer">
    <?php if (empty($walkInRequests) && empty($regularRequests)): ?>
    <?php else: ?>
        <!-- Walk-in List Section -->
        <?php if (!empty($walkInRequests)): ?>
            <!-- Your existing walk-in section HTML -->
        <?php endif; ?>

        <!-- Regular Requests Section -->
        <?php foreach ($regularRequests as $queueDate => $requests): ?>
            <!-- Your existing regular requests section HTML -->
        <?php endforeach; ?>
    <?php endif; ?>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        let currentRequestId = null;
        let currentRescheduleDate = null;
        let currentIsWalkin = false;
        
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

        $(document).ready(function() {
    let selfRelationValue = "<?= addslashes($selfRelationValue) ?>";
    
    // When the complete modal is shown
    $('#completeModal').on('show.bs.modal', function() {
        // Get the requester's name from the table row
        const nameCell = $('.complete-btn[data-id="' + currentRequestId + '"]').closest('tr').find('.table-col-name');
        const fullName = nameCell.text().trim();
        
        // Parse the name from "Last, First Middle Initial" format to "First Middle Initial Last" format
        const nameParts = fullName.split(',');
        if (nameParts.length === 2) {
            const lastName = nameParts[0].trim();
            const firstMiddleParts = nameParts[1].trim().split(' ');
            const firstName = firstMiddleParts[0];
            const middleInitial = firstMiddleParts.length > 1 ? firstMiddleParts[1] : '';
            
            // Format as "First Name Middle Initial Last Name"
            const formattedName = firstName + (middleInitial ? ' ' + middleInitial : '') + ' ' + lastName;
            $('#recipientName').val(formattedName);
        } else {
            // Fallback if format is unexpected
            $('#recipientName').val(fullName);
        }
        
        // Set relation to Self and disable recipient name field
        $('#recipientRelation').val(selfRelationValue);
        $('#recipientName').prop('readonly', true);
    });
    
    // When the relation dropdown changes
    $('#recipientRelation').change(function() {
        const selectedRelation = $(this).val();
        
        if (selectedRelation === selfRelationValue) {
            // If Self is selected, set recipient name to requester and disable field
            const nameCell = $('.complete-btn[data-id="' + currentRequestId + '"]').closest('tr').find('.table-col-name');
            const fullName = nameCell.text().trim();
            
            // Parse the name from "Last, First Middle Initial" format to "First Middle Initial Last" format
            const nameParts = fullName.split(',');
            if (nameParts.length === 2) {
                const lastName = nameParts[0].trim();
                const firstMiddleParts = nameParts[1].trim().split(' ');
                const firstName = firstMiddleParts[0];
                const middleInitial = firstMiddleParts.length > 1 ? firstMiddleParts[1] : '';
                
                // Format as "First Name Middle Initial Last Name"
                const formattedName = firstName + (middleInitial ? ' ' + middleInitial : '') + ' ' + lastName;
                $('#recipientName').val(formattedName);
            } else {
                // Fallback if format is unexpected
                $('#recipientName').val(fullName);
            }
            
            $('#recipientName').prop('readonly', true);
        } else {
            // If other relation is selected, clear and enable recipient name field
            $('#recipientName').val('').prop('readonly', false);
        }
    });
    
    // The confirmComplete function remains the same as it's working correctly
    $('#confirmComplete').click(function() {
        const releaseDate = $('#releaseDate').val();
        const recipientName = $('#recipientName').val().trim();
        const recipientRelation = $('#recipientRelation').val().trim();
        const amount = $('#amount').val();
        const $validation = $('#completeValidation');
        const $btn = $(this);
        
        if (!releaseDate || !recipientName || !recipientRelation) {
            $validation.removeClass('d-none').text('Please fill in all required fields.');
            return;
        }
        
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
        
        $.ajax({
            url: 'update_status.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id: currentRequestId,
                action: 'complete',
                released_date: releaseDate,
                recipient: recipientName,
                relation_to_recipient: recipientRelation,
                amount: amount,
                is_walkin: currentIsWalkin ? 1 : 0,
                assistance_admin_id: <?= $assistance_admin_id ?>
            },
            success: function(response) {
                if (response.success) {
                    $('#completeModal').modal('hide');
                    const successModal = new bootstrap.Modal('#successCompletionModal', {
                        keyboard: false
                    });
                    successModal.show();
                    $('#continueAfterComplete').click(function() {
                        successModal.hide();
                        location.reload();
                    });
                } else {
                    $validation.removeClass('d-none').text(response.error || 'Failed to complete request');
                }
            },
            error: function(xhr) {
                $validation.removeClass('d-none').text('Error: ' + (xhr.responseJSON?.error || xhr.statusText));
            },
            complete: function() {
                $btn.prop('disabled', false).text('Mark as Completed');
            }
        });
    });
});
        
        // Complete Button
        $(document).on('click', '.complete-btn', function() {
            currentRequestId = $(this).data('id');
            currentIsWalkin = $(this).closest('.request-row').data('is-walkin') === 1;
            
            $('#releaseDate').val(new Date().toISOString().split('T')[0]);
            $('#recipientName').val('');
            $('#recipientRelation').val('');
            $('#amount').val('');
            $('#completeValidation').addClass('d-none');
            
            if (currentIsWalkin) {
                $('#completionMessage').text('Walk-in request completed.');
            } else {
                $('#completionMessage').text('The applicant has been notified via SMS.');
            }
            
            $('#completeModal').modal('show');
        });

        // Confirm Complete
$('#confirmComplete').click(function() {
    const releaseDate = $('#releaseDate').val();
    const recipientName = $('#recipientName').val().trim();
    const recipientRelation = $('#recipientRelation').val().trim();
    const amount = $('#amount').val();
    const $validation = $('#completeValidation');
    const $btn = $(this);
    
    if (!releaseDate || !recipientName || !recipientRelation) {
        $validation.removeClass('d-none').text('Please fill in all required fields.');
        return;
    }
    
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
    
    $.ajax({
        url: 'update_status.php',
        type: 'POST',
        dataType: 'json',
        data: {
            id: currentRequestId,
            action: 'complete',
            released_date: releaseDate,
            recipient: recipientName,
            relation_to_recipient: recipientRelation,
            amount: amount,
            is_walkin: currentIsWalkin ? 1 : 0,
            assistance_admin_id: <?= $assistance_admin_id ?>
        },
        success: function(response) {
            if (response.success) {
                $('#completeModal').modal('hide');
                const successModal = new bootstrap.Modal('#successCompletionModal', {
                    keyboard: false
                });
                successModal.show();
                $('#continueAfterComplete').click(function() {
                    successModal.hide();
                    location.reload();
                });
            } else {
                $validation.removeClass('d-none').text(response.error || 'Failed to complete request');
            }
        },
        error: function(xhr) {
            $validation.removeClass('d-none').text('Error: ' + (xhr.responseJSON?.error || xhr.statusText));
        },
        complete: function() {
            $btn.prop('disabled', false).text('Mark as Completed');
        }
    });
});
        
        // Decline Button
        $(document).on('click', '.decline-btn', function() {
            currentRequestId = $(this).data('id');
            currentIsWalkin = $(this).closest('.request-row').data('is-walkin') === 1;
            
            $('#declineReason').val('');
            $('#declineValidation').addClass('d-none');
            
            if (currentIsWalkin) {
                $('#declineMessage').text('Walk-in request cancelled.');
            } else {
                $('#declineMessage').text('The applicant has been notified via SMS.');
            }
            
            $('#declineModal').modal('show');
        });

        // Confirm Decline
        $('#confirmDecline').click(function() {
            const reason = $('#declineReason').val().trim();
            const $validation = $('#declineValidation');
            const $btn = $(this);
            
            if (!reason) {
                $validation.removeClass('d-none').text('Please provide a reason.');
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
                    reason: reason,
                    is_walkin: currentIsWalkin ? 1 : 0,
                    assistance_admin_id: <?= $assistance_admin_id ?>
                },
                success: function(response) {
                    if (response.success) {
                        $('#declineModal').modal('hide');
                        const successModal = new bootstrap.Modal('#successDeclineModal', {
                            keyboard: false
                        });
                        successModal.show();
                        $('#continueAfterDecline').click(function() {
                            successModal.hide();
                            location.reload();
                        });
                    } else {
                        $validation.removeClass('d-none').text(response.error || 'Failed to process request');
                    }
                },
                error: function(xhr) {
                    $validation.removeClass('d-none').text('Error: ' + (xhr.responseJSON?.error || xhr.statusText));
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Confirm Cancellation');
                }
            });
        });
        
        // Reschedule Day Button
        $(document).on('click', '.reschedule-day-btn', function() {
            currentRescheduleDate = $(this).data('date');
            $('#rescheduleDate').val('<?= $nextMondayStr ?>');
            $('#rescheduleValidation').addClass('d-none');
            $('#rescheduleMessage').text('The applicants have been notified via SMS.');
            $('#rescheduleDayModal').modal('show');
        });

        // Confirm Reschedule
        $('#confirmReschedule').click(function() {
            const newDate = $('#rescheduleDate').val();
            const $validation = $('#rescheduleValidation');
            const $btn = $(this);
            
            if (!newDate) {
                $validation.removeClass('d-none').text('Please select a valid date.');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    date: currentRescheduleDate,
                    action: 'reschedule_past_due',
                    new_date: newDate,
                    assistance_admin_id: <?= $assistance_admin_id ?>
                },
                success: function(response) {
                    if (response.success) {
                        $('#rescheduleDayModal').modal('hide');
                        const successModal = new bootstrap.Modal('#successRescheduleModal', {
                            keyboard: false
                        });
                        successModal.show();
                        $('#continueAfterReschedule').click(function() {
                            successModal.hide();
                            location.reload();
                        });
                    } else {
                        $validation.removeClass('d-none').text(response.error || 'Failed to reschedule requests');
                    }
                },
                error: function(xhr) {
                    $validation.removeClass('d-none').text('Error: ' + xhr.responseText);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Confirm Reschedule');
                }
            });
        });

        // Live Filtering
function applyFilters() {
    const searchTerm = $('#searchFilter').val().toLowerCase();
    const programFilter = $('#programFilter').val();
    const barangayFilter = $('#barangayFilter').val();
    const dateFilter = $('#dateFilter').val();
    
    let anyVisible = false;
    
    // First hide all day headers and tables
    $('.day-header').hide();
    $('.desktop-table').hide();
    $('.mobile-table').hide();
    
    // Check each request row
    $('.request-row').each(function() {
        const $row = $(this);
        const id = $row.data('id').toString();
        const name = $row.data('name');
        const program = $row.data('program');
        const parentProgram = $row.data('parent-program');
        const barangay = $row.data('barangay');
        const date = $row.data('date');
        const phone = $row.data('phone');
        const queueDate = $row.data('queue-date');
        const isDesktop = $(window).width() >= 769;
        
        // Check if this row matches all filters
        const matchesSearch = searchTerm === '' || 
            name.includes(searchTerm) || 
            id.includes(searchTerm) || 
            phone.includes(searchTerm) ||
            name.split(' ').some(part => part.startsWith(searchTerm));
        
        const matchesProgram = programFilter === '' || 
            program === programFilter || 
            parentProgram === programFilter;
        
        const matchesBarangay = barangayFilter === '' || barangay === barangayFilter;
        const matchesDate = dateFilter === '' || date === dateFilter;
        
        if (matchesSearch && matchesProgram && matchesBarangay && matchesDate) {
            // Show the appropriate view based on screen size
            if (isDesktop) {
                $row.closest('.desktop-table').show().prev('.day-header').show();
            } else {
                $row.closest('.mobile-table').show().prev('.day-header').show();
            }
            $row.show();
            anyVisible = true;
        } else {
            $row.hide();
        }
    });
    
    // Show/hide no results message
    const $noResultsMessage = $('.no-requests');
    if (!anyVisible) {
        if ($noResultsMessage.length === 0) {
            $('#resultsContainer').html(`                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>No requests match your current filters.</p>
                </div>
            `);
        } else {
            $noResultsMessage.show();
        }
    } else {
        $noResultsMessage.hide();
    }
}
        
        // Apply filters when any filter changes
        $('#searchFilter, #programFilter, #barangayFilter, #dateFilter').on('input change', function() {
            applyFilters();
        });
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>