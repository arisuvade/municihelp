<?php
session_start();
include '../../../includes/db.php';

if (!isset($_SESSION['mswd_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$mswd_admin_id = $_SESSION['mswd_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $mswd_admin_id");
$admin_data = $admin_query->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

// Get current date and calculate last week's Monday to Sunday
$today = new DateTime();
$currentDay = $today->format('N'); // 1 (Monday) to 7 (Sunday)

// Calculate last Monday (7 days ago if today is Monday)
$lastMonday = clone $today;
$lastMonday->modify('last monday');

// Calculate last Sunday (last Monday + 6 days)
$lastSunday = clone $lastMonday;
$lastSunday->modify('+6 days');

// Format dates for SQL
$lastMondayStr = $lastMonday->format('Y-m-d 00:00:00');
$lastSundayStr = $lastSunday->format('Y-m-d 23:59:59');

$counts = [];
$statuses = ['pending', 'mayor_approved', 'mswd_approved', 'completed', 'declined', 'cancelled'];

// Get weekly counts for All Requests (including walk-ins)
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM mswd_requests mr
    JOIN mswd_types mt ON mr.assistance_id = mt.id
    WHERE mr.updated_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $lastMondayStr, $lastSundayStr);
$stmt->execute();
$stmt->bind_result($weeklyTotalRequests);
$stmt->fetch();
$stmt->close();

// Get total counts for other statuses (including walk-ins)
foreach ($statuses as $status) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM mswd_requests mr
        JOIN mswd_types mt ON mr.assistance_id = mt.id
        WHERE mr.status = ?
    ");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $counts[$status] = $count;
    $stmt->close();
}

function formatPhoneNumber($phone) {
    if (empty($phone)) return 'Walk-in';
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

$pageTitle = 'Dashboard';
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
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --mayor_approved: #FFC107;
            --mswd_approved: #28A745;
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
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.all {
            border-top: 4px solid var(--munici-green);
        }
        
        .stat-card.pending {
            border-top: 4px solid var(--pending);
        }
        
        .stat-card.mayor_approved {
            border-top: 4px solid var(--mayor_approved);
        }
        
        .stat-card.mswd_approved {
            border-top: 4px solid var(--mswd_approved);
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
        
        .week-range {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
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
        
        .bg-completed {
            background-color: var(--completed) !important;
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
            width: 30%;
        }
        
        .table-col-program {
            text-align: center;
            width: 23%;
        }
        
        .table-col-phone {
            text-align: center;
            width: 23%;
        }
        
        .table-col-status {
            text-align: center;
            width: 18%;
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
                    <h1 class="mb-1">MSWD Dashboard</h1>
                    <p class="mb-0">Welcome back! Here's what's happening today</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($admin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Stats Cards -->
        <div class="stats-container">
            <a href="requests.php" class="stat-card all">
                <div class="stat-title">All Requests</div>
                <div class="stat-number"><?= $weeklyTotalRequests ?></div>
                <div class="week-range"><?= $lastMonday->format('M j') ?> - <?= $lastSunday->format('M j, Y') ?></div>
            </a>
            
            <a href="mayor_approved.php" class="stat-card mayor_approved">
                <div class="stat-title">Mayor Approved</div>
                <div class="stat-number"><?= $counts['mayor_approved'] ?></div>
            </a>
            
            <a href="mswd_approved.php" class="stat-card mswd_approved">
                <div class="stat-title">MSWD Approved</div>
                <div class="stat-number"><?= $counts['mswd_approved'] ?></div>
            </a>
        </div>

        <!-- Recent Requests -->
        <div id="resultsContainer">
            <?php
            $stmt = $conn->prepare("
    SELECT mr.id, mt.name as program, 
           mr.last_name, mr.first_name, mr.middle_name,
           mr.birthday, 
           COALESCE(u.phone, mr.contact_no) AS contact_no,  -- Changed this line
           mr.updated_at, mr.status, mr.is_walkin
    FROM mswd_requests mr
    JOIN mswd_types mt ON mr.assistance_id = mt.id
    LEFT JOIN users u ON mr.user_id = u.id
    WHERE mr.status != 'cancelled'
    ORDER BY mr.updated_at DESC
    LIMIT 5
");
            $stmt->execute();
            $result = $stmt->get_result();
            $hasRequests = $result->num_rows > 0;
            ?>
            
            <?php if (!$hasRequests): ?>
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h4>No Recent Requests Found</h4>
                    <p>There are no recent requests to display.</p>
                </div>
            <?php else: ?>
                <div class="recent-activity-card card">
                    <div class="card-header">
                        <h4 class="mb-0">Recent Activity</h4>
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
                                        <th class="table-col-phone">Contact No.</th>
                                        <th class="table-col-status">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                        $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                        $phone_number = formatPhoneNumber($row['contact_no']);
                                        $is_walkin = $row['is_walkin'] == 1;
                                    ?>
                                        <tr>
                                            <td class="table-col-id"><?= $row['id'] ?></td>
                                            <td class="table-col-name"><?= $applicant_name ?></td>
                                            <td class="table-col-program"><?= htmlspecialchars($row['program']) ?></td>
                                            <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
                                            <td class="table-col-status">
                                                <span class="status-badge 
                                                    <?= $row['status'] === 'mswd_approved' ? 'bg-mswd_approved' : 
                                                    ($row['status'] === 'mayor_approved' ? 'bg-mayor_approved' : 
                                                    ($row['status'] === 'declined' ? 'bg-declined' : 
                                                    ($row['status'] === 'completed' ? 'bg-completed' : 
                                                    ($row['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending')))) ?>">
                                                    <?= 
                                                        $row['status'] === 'mswd_approved' ? 'MSWD Approved' : 
                                                        ucwords(str_replace('_', ' ', $row['status'])) 
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Table -->
                        <div class="mobile-table">
                            <?php 
                            $result->data_seek(0);
                            while ($row = $result->fetch_assoc()): 
                                $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                $phone_number = formatPhoneNumber($row['contact_no']);
                                $is_walkin = $row['is_walkin'] == 1;
                            ?>
                                <div class="mobile-row">
                                    <div>
                                        <span class="mobile-label">ID:</span>
                                        <span><?= $row['id'] ?></span>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Name:</span>
                                        <span><?= $applicant_name ?></span>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Program:</span>
                                        <span><?= htmlspecialchars($row['program']) ?></span>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Contact:</span>
                                        <span><?= htmlspecialchars($phone_number) ?></span>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Status:</span>
                                        <span class="status-badge 
                                            <?= $row['status'] === 'mswd_approved' ? 'bg-mswd_approved' : 
                                            ($row['status'] === 'mayor_approved' ? 'bg-mayor_approved' : 
                                            ($row['status'] === 'declined' ? 'bg-declined' : 
                                            ($row['status'] === 'completed' ? 'bg-completed' : 
                                            ($row['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending')))) ?>">
                                            <?= 
                                                $row['status'] === 'mswd_approved' ? 'MSWD Approved' : 
                                                ucwords(str_replace('_', ' ', $row['status'])) 
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>