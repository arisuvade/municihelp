<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

// financial or request admin
$adminSection = $_SESSION['admin_section'] ?? 'Financial Assistance';
$displaySection = ($adminSection === 'Financial Assistance') ? 'Assistance' : 'Request';

$typeFilter = ($adminSection === 'Financial Assistance') ? 
    "AND at.name LIKE '%Assistance%'" : 
    "AND at.name LIKE '%Request%'";

$counts = [];
$statuses = ['pending', 'approved', 'completed', 'declined', 'cancelled'];

foreach ($statuses as $status) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM assistance_requests ar
        JOIN assistance_types at ON ar.assistance_id = at.id
        WHERE ar.status = ? 
        $typeFilter
    ");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $counts[$status] = $count;
    $stmt->close();
}

$totalRequests = array_sum($counts);

$pageTitle = 'Admin Dashboard';
include '../includes/header.php';
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
        
        .dashboard-container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--munici-green), #2E7D32);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            overflow: hidden;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
            position: relative;
        }
        
        .stat-card .card-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 2.5rem;
            opacity: 0.2;
        }
        
        .recent-activity-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
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
        
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-radius: 50px;
        }
        
        .progress {
            height: 6px;
            border-radius: 3px;
            margin-top: 10px;
        }
        
        .progress-bar {
            background-color: var(--munici-green);
        }
        
        .bg-pending {
            background-color: #FFC107 !important;
            color: #212529 !important;
        }
        
        .bg-approved {
            background-color: #28A745 !important;
        }
        
        .bg-completed {
            background-color: #007BFF !important;
        }
        
        .bg-declined {
            background-color: #DC3545 !important;
        }
        
        .btn-view {
            background-color: #4361ee;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .btn-view:hover {
            background-color: #3a56d4;
            color: white;
        }
        
        .admin-badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .admin-section {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
            }
            
            .admin-badge {
                padding: 0.4rem 0.8rem;
            }
            
            .admin-section {
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
                    <h1 class="mb-1"><?= $displaySection ?> Dashboard</h1>
                    <p class="mb-0">Welcome back! Here's what's happening today.</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <div class="admin-section"><?= $displaySection ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- status cards -->
    <div class="row">
        <!-- all requests -->
        <div class="col-md-2 mb-4">
            <div class="stat-card bg-white">
                <div class="card-body">
                    <h6 class="card-title text-muted">All Requests</h6>
                    <h2 class="card-value text-dark"><?= $totalRequests ?></h2>
                </div>
            </div>
        </div>
        
        <!-- pending -->
        <div class="col-md-2 mb-4">
            <div class="stat-card bg-white">
                <div class="card-body">
                    <h6 class="card-title text-muted">Pending</h6>
                    <h2 class="card-value text-dark"><?= $counts['pending'] ?></h2>
                </div>
            </div>
        </div>
        
        <!-- approved -->
        <div class="col-md-2 mb-4">
            <div class="stat-card bg-white">
                <div class="card-body">
                    <h6 class="card-title text-muted">Approved</h6>
                    <h2 class="card-value text-dark"><?= $counts['approved'] ?></h2>
                </div>
            </div>
        </div>
        
        <!-- completed -->
        <div class="col-md-2 mb-4">
            <div class="stat-card bg-white">
                <div class="card-body">
                    <h6 class="card-title text-muted">Completed</h6>
                    <h2 class="card-value text-dark"><?= $counts['completed'] ?></h2>
                </div>
            </div>
        </div>
        
        <!-- declined -->
        <div class="col-md-2 mb-4">
            <div class="stat-card bg-white">
                <div class="card-body">
                    <h6 class="card-title text-muted">Declined</h6>
                    <h2 class="card-value text-dark"><?= $counts['declined'] ?></h2>
                </div>
            </div>
        </div>
        
        <!-- cancelled -->
        <div class="col-md-2 mb-4">
            <div class="stat-card bg-white">
                <div class="card-body">
                    <h6 class="card-title text-muted">Cancelled</h6>
                    <h2 class="card-value text-dark"><?= $counts['cancelled'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
    </div>
        
        <!-- recent activity -->
        <div class="recent-activity-card card">
            <div class="card-header">
                <h4 class="mb-0">Recent Activity</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Program</th>
                                <th>Applicant Name</th>
                                <th>Contact No.</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare("
                                SELECT ar.id, at.name as program, 
                                    ar.last_name, ar.first_name, ar.middle_name,
                                    u.phone, ar.created_at, ar.status
                                FROM assistance_requests ar
                                JOIN assistance_types at ON ar.assistance_id = at.id
                                JOIN users u ON ar.user_id = u.id
                                WHERE ar.status != 'cancelled'
                                $typeFilter
                                ORDER BY ar.created_at DESC
                                LIMIT 5
                            ");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            while ($row = $result->fetch_assoc()): 
                                // format lastname, firstname m.i."
                                $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                            ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['program']) ?></td>
                                    <td><?= $applicant_name ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <span class="badge 
                                            <?= $row['status'] === 'approved' ? 'bg-approved' : 
                                            ($row['status'] === 'declined' ? 'bg-declined' : 
                                            ($row['status'] === 'completed' ? 'bg-completed' : 'bg-pending')) ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-view view-btn" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // view requirements
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
    });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>