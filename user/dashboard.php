<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../includes/auth/login.php');
    exit;
}

// Check if password change is required
if (isset($_SESSION['force_password_change'])) {
    header('Location: change_password.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT name, middle_name, last_name FROM users WHERE id = $user_id");
$user_data = $user_query->fetch_assoc();

$user_name = trim(
    $user_data['name'] . ' ' .
    (!empty($user_data['middle_name']) ? $user_data['middle_name'] . ' ' : '') .
    $user_data['last_name']
) ?: 'User';

// Get counts for each status across all request types
$counts = $conn->query("
    SELECT 
        SUM(CASE WHEN status IN ('pending', 'mayor_approved') THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status IN ('approved', 'scheduled', 'mswd_approved') THEN 1 ELSE 0 END) as processed_count,
        SUM(CASE WHEN status IN ('completed', 'verified') THEN 1 ELSE 0 END) as completed_count
    FROM (
        SELECT status FROM assistance_requests WHERE user_id = $user_id
        UNION ALL
        SELECT status FROM ambulance_requests WHERE user_id = $user_id
        UNION ALL
        SELECT status FROM dog_claims WHERE user_id = $user_id
        UNION ALL
        SELECT status FROM dog_adoptions WHERE user_id = $user_id
        UNION ALL
        SELECT status FROM rabid_reports WHERE user_id = $user_id
        UNION ALL
        SELECT status FROM mswd_requests WHERE user_id = $user_id
    ) as all_requests
")->fetch_assoc();

// Get recent active requests from all departments
$requests = [];

// Assistance requests
$assistance_requests = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, 'Assistance' as program, 
           status, COALESCE(updated_at, created_at) as sort_date, 'assistance' as request_type
    FROM assistance_requests 
    WHERE user_id = $user_id AND status NOT IN ('declined', 'cancelled', 'false_report')
    ORDER BY sort_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$requests = array_merge($requests, $assistance_requests);

// Ambulance requests
$ambulance_requests = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, 'Ambulance' as program, 
           status, COALESCE(updated_at, created_at) as sort_date, 'ambulance' as request_type
    FROM ambulance_requests 
    WHERE user_id = $user_id AND status NOT IN ('declined', 'cancelled', 'false_report')
    ORDER BY sort_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$requests = array_merge($requests, $ambulance_requests);

// Dog Claims
$dog_claims = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, 'Dog Claiming' as program,
           status, COALESCE(updated_at, created_at) as sort_date, 'dog_claim' as request_type
    FROM dog_claims
    WHERE user_id = $user_id AND status NOT IN ('declined', 'cancelled', 'false_report')
    ORDER BY sort_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$requests = array_merge($requests, $dog_claims);

// Dog Adoptions
$dog_adoptions = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, 'Dog Adoption' as program,
           status, COALESCE(updated_at, created_at) as sort_date, 'dog_adoption' as request_type
    FROM dog_adoptions
    WHERE user_id = $user_id AND status NOT IN ('declined', 'cancelled', 'false_report')
    ORDER BY sort_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$requests = array_merge($requests, $dog_adoptions);

// Rabid Reports
$rabid_reports = $conn->query("
    SELECT id, 
           CASE 
               WHEN first_name IS NULL AND last_name IS NULL THEN 'Anonymous'
               ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) 
           END as name,
           'Rabid Report' as program,
           status, COALESCE(updated_at, created_at) as sort_date, 'rabid_report' as request_type
    FROM rabid_reports
    WHERE user_id = $user_id AND status NOT IN ('declined', 'cancelled', 'false_report')
    ORDER BY sort_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$requests = array_merge($requests, $rabid_reports);

// MSWD requests
$mswd_requests = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, 'MSWD' as program, 
           status, COALESCE(updated_at, created_at) as sort_date, 'mswd' as request_type
    FROM mswd_requests 
    WHERE user_id = $user_id AND status NOT IN ('declined', 'cancelled', 'false_report')
    ORDER BY sort_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$requests = array_merge($requests, $mswd_requests);

// Sort all requests by sort_date and limit to 5 most recent
usort($requests, function($a, $b) {
    return strtotime($b['sort_date']) - strtotime($a['sort_date']);
});
$requests = array_slice($requests, 0, 5);

$pageTitle = "MuniciHelp";
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --mayor_approved: #FFC107;
            --mswd_approved: #28A745;
            --approved: #28A745;
            --completed: #4361ee;
            --verified: #4361ee;
            --declined: #DC3545;
            --cancelled: #6C757D;
            --false-report: #DC3545;
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
        
        .stats-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.pending {
            border-top: 4px solid var(--pending);
        }
        
        .stat-card.approved {
            border-top: 4px solid var(--approved);
        }
        
        .stat-card.completed {
            border-top: 4px solid var(--completed);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0.3rem 0;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .bg-mayor_approved {
            background-color: var(--mayor_approved) !important;
            color: #212529;
        }
        
        .bg-mswd_approved {
            background-color: var(--mswd_approved) !important;
            color: white;
        }
        
        .bg-approved {
            background-color: var(--approved) !important;
            color: white;
        }
        
        .bg-completed {
            background-color: var(--completed) !important;
            color: white;
        }
        
        .bg-verified {
            background-color: var(--verified) !important;
            color: white;
        }
        
        .bg-declined {
            background-color: var(--declined) !important;
            color: white;
        }
        
        .bg-cancelled {
            background-color: var(--cancelled) !important;
            color: white;
        }
        
        .bg-false-report {
            background-color: var(--false-report) !important;
            color: white;
        }
        
        .no-requests {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-requests i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        /* Column widths for desktop */
        .table-col-id {
            width: 6%;
            text-align: center;
        }
        
        .table-col-name {
            width: 25%;
        }
        
        .table-col-program {
            width: 25%;
            text-align: center;
        }
        
        .table-col-status {
            width: 22%;
            text-align: center;
        }
        
        .table-col-date {
            width: 22%;
            text-align: center;
        }
        
        /* Mobile specific styles - Updated to match admin dashboard */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
            
            .stats-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stat-card {
                min-width: 100%;
            }
            
            .mobile-row {
                display: flex;
                flex-direction: column;
                padding: 1rem;
                border-bottom: 1px solid #dee2e6;
                background: white;
            }
            
            .mobile-row:last-child {
                border-bottom: none;
            }
            
            .mobile-row div {
                margin-bottom: 0.5rem;
                display: flex;
            }
            
            .mobile-label {
                font-weight: 600;
                color: #495057;
                margin-right: 0.5rem;
                min-width: 80px;
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
            
            .recent-activity-card {
                border-radius: 10px;
                overflow: hidden;
            }
            
            .card-header h4 {
                font-size: 1.1rem;
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
                    <h1 class="mb-1">MuniciHelp Dashboard</h1>
                    <p class="mb-0">Welcome back, <?= htmlspecialchars($user_name) ?>! View your requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card pending">
                <div class="stat-title">Pending</div>
                <div class="stat-number"><?= $counts['pending_count'] ?: '0' ?></div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-title">Processed</div>
                <div class="stat-number"><?= $counts['processed_count'] ?: '0' ?></div>
            </div>
            
            <div class="stat-card completed">
                <div class="stat-title">Completed</div>
                <div class="stat-number"><?= $counts['completed_count'] ?: '0' ?></div>
            </div>
        </div>

        <!-- Recent Requests -->
        <?php if (empty($requests)): ?>
            <div class="no-requests">
                <i class="fas fa-inbox"></i>
                <h4>No Active Requests Found</h4>
                <p>You don't have any active requests at this time.</p>
            </div>
        <?php else: ?>
            <div class="recent-activity-card card">
                <div class="card-header">
                    <h4 class="mb-0">My Recent Active Requests</h4>
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
                                    <th class="table-col-date">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): 
                                    $statusText = $request['status'];
                                    if ($statusText === 'mswd_approved') $statusText = 'MSWD Approved';
                                    elseif ($statusText === 'mayor_approved') $statusText = 'Mayor Approved';
                                    elseif ($statusText === 'scheduled') $statusText = 'Scheduled';
                                ?>
                                <tr>
                                    <td class="table-col-id"><?= $request['id'] ?></td>
                                    <td class="table-col-name"><?= htmlspecialchars($request['name']) ?></td>
                                    <td class="table-col-program"><?= htmlspecialchars($request['program']) ?></td>
                                    <td class="table-col-status">
                                       <span class="status-badge 
    <?php
        if (in_array($request['status'], ['approved', 'scheduled', 'mswd_approved'])) {
            echo 'bg-approved';
        } elseif (in_array($request['status'], ['completed', 'verified'])) {
            echo 'bg-completed';
        } elseif ($request['status'] === 'declined') {
            echo 'bg-declined';
        } elseif ($request['status'] === 'cancelled') {
            echo 'bg-cancelled';
        } elseif ($request['status'] === 'false_report') {
            echo 'bg-false-report';
        } else {
            echo 'bg-pending';
        }
    ?>
">

                                            <?= ucfirst($statusText) ?>
                                        </span>
                                    </td>
                                    <td class="table-col-date"><?= date('M j, Y g:i A', strtotime($request['sort_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile Table - Updated to match admin style -->
                    <div class="mobile-table">
                        <?php foreach ($requests as $request): 
                            $statusText = $request['status'];
                            if ($statusText === 'mswd_approved') $statusText = 'MSWD Approved';
                            elseif ($statusText === 'mayor_approved') $statusText = 'Mayor Approved';
                            elseif ($statusText === 'scheduled') $statusText = 'Scheduled';
                        ?>
                        <div class="mobile-row">
                            <div>
                                <span class="mobile-label">ID:</span>
                                <span><?= $request['id'] ?></span>
                            </div>
                            <div>
                                <span class="mobile-label">Name:</span>
                                <span><?= htmlspecialchars($request['name']) ?></span>
                            </div>
                            <div>
                                <span class="mobile-label">Program:</span>
                                <span><?= htmlspecialchars($request['program']) ?></span>
                            </div>
                            <div>
                                <span class="mobile-label">Status:</span>
                                <?php
$classes = [
    'approved'      => 'bg-approved',
    'scheduled'     => 'bg-approved',
    'mswd_approved' => 'bg-approved',
    'completed'     => 'bg-completed',
    'verified'      => 'bg-completed',
    'declined'      => 'bg-declined',
    'cancelled'     => 'bg-cancelled',
    'false_report'  => 'bg-false-report'
];
$class = $classes[$request['status']] ?? 'bg-pending';
?>
<span class="status-badge <?= $class ?>">
    <?= ucfirst($statusText) ?>
</span>

                            </div>
                            <div>
                                <span class="mobile-label">Updated:</span>
                                <span><?= date('M j, Y g:i A', strtotime($request['sort_date'])) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>