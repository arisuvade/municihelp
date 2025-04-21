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

$pageTitle = 'All Requests';
include '../includes/header.php';

$filterProgram = isset($_GET['program']) ? $_GET['program'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterBarangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchTerm = isset($_GET['search_term']) ? $_GET['search_term'] : '';

$query = "
    SELECT ar.id, at.name as program, 
           ar.last_name, ar.first_name, ar.middle_name,
           u.phone, ar.created_at, ar.status,
           b.name as barangay
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    JOIN users u ON ar.user_id = u.id
    LEFT JOIN barangays b ON ar.barangay_id = b.id
    WHERE " . ($adminSection === 'Financial Assistance' ? 
        "at.name LIKE '%Assistance%'" : "at.name LIKE '%Request%'");

$conditions = [];
$params = [];
$types = '';

if (!empty($filterStatus)) {
    $conditions[] = "ar.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if (!empty($filterProgram)) {
    $conditions[] = "at.id = ?";
    $params[] = $filterProgram;
    $types .= 'i';
}

if (!empty($filterBarangay)) {
    $conditions[] = "ar.barangay_id = ?";
    $params[] = $filterBarangay;
    $types .= 'i';
}

if (!empty($filterDateFrom)) {
    $conditions[] = "DATE(ar.created_at) >= ?";
    $params[] = $filterDateFrom;
    $types .= 's';
}

if (!empty($filterDateTo)) {
    $conditions[] = "DATE(ar.created_at) <= ?";
    $params[] = $filterDateTo;
    $types .= 's';
}

if (!empty($searchTerm)) {
    $searchTermLike = "%$searchTerm%";
    $conditions[] = "(ar.id LIKE ? OR CONCAT(ar.first_name, ' ', ar.last_name) LIKE ? OR u.phone LIKE ?)";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= 'sss';
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

// latest id
$query .= " ORDER BY ar.id DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$programsQuery = "SELECT id, name FROM assistance_types WHERE " . 
    ($adminSection === 'Financial Assistance' ? "name LIKE '%Assistance%'" : "name LIKE '%Request%'") . 
    " ORDER BY name";
$programs = $conn->query($programsQuery);

$barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name");

$statuses = ['pending', 'approved', 'declined', 'completed', 'cancelled'];
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
            width: 80px;
        }
        
        .col-applicant {
            width: 250px;
        }
        
        .col-contact {
            width: 150px;
        }
        
        .col-barangay {
            width: 150px;
        }
        
        .col-program {
            width: 200px;
        }
        
        .col-status {
            width: 120px;
        }
        
        .col-date {
            width: 120px;
        }
        
        .col-actions {
            width: 100px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--munici-green), #2E7D32);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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
        
        /* Status badges */
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-radius: 50px;
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
        
        .bg-cancelled {
            background-color: #6C757D !important;
        }
        
        /* Search and Filter Section Styles */
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

        .filter-search {
            width: 120px;
        }

        .filter-program {
            width: 200px;
        }

        .filter-status {
            width: 150px;
        }

        .filter-barangay {
            width: 180px;
        }

        /* Date Range and Actions Row */
        .filter-date-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-date-group {
            flex: 1;
        }

        .filter-actions-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .filter-date-from, .filter-date-to {
            width: 100%;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group,
            .filter-search,
            .filter-program,
            .filter-status,
            .filter-barangay,
            .filter-date-from,
            .filter-date-to {
                width: 100%;
            }
        }
        
        .admin-section {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        @media (max-width: 1200px) {
            .dashboard-container {
                max-width: 100%;
                padding: 0 10px;
            }
            
            .col-applicant {
                width: 200px;
            }
            
            .col-program {
                width: 150px;
            }
        }
        
        @media (max-width: 992px) {
            .col-barangay {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .col-program {
                display: none;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .search-input {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">All Requests</h1>
                    <p class="mb-0">View and manage all requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- filter -->
        <div class="card filter-card">
            <div class="card-body">
                <h5 class="card-title">Filters</h5>
                <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group filter-search">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search_term" 
                            placeholder="Search by ID, name or contact" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    
                    <div class="filter-group filter-program">
                        <label for="program" class="form-label">Program</label>
                        <select class="form-select" id="program" name="program">
                            <option value="">All Programs</option>
                            <?php while ($program = $programs->fetch_assoc()): ?>
                                <option value="<?= $program['id'] ?>" <?= $filterProgram == $program['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group filter-status">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status ?>" <?= $filterStatus == $status ? 'selected' : '' ?>>
                                    <?= ucfirst($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group filter-barangay">
                        <label for="barangay" class="form-label">Barangay</label>
                        <select class="form-select" id="barangay" name="barangay">
                            <option value="">All Barangays</option>
                            <?php while ($barangay = $barangays->fetch_assoc()): ?>
                                <option value="<?= $barangay['id'] ?>" <?= $filterBarangay == $barangay['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($barangay['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-date-row">
                    <!-- date From -->
                    <div class="filter-date-group">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control filter-date-from" id="date_from" name="date_from" 
                            value="<?= htmlspecialchars($filterDateFrom) ?>">
                    </div>
                    
                    <!-- date To -->
                    <div class="filter-date-group">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control filter-date-to" id="date_to" name="date_to" 
                            value="<?= htmlspecialchars($filterDateTo) ?>">
                    </div>
                    
                    <!-- action buttons -->
                    <div class="filter-actions-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
                </form>
            </div>
        </div>

        <!-- results -->
        <div class="recent-activity-card card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped fixed-width-table mb-0">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th class="col-program">Program</th>
                                <th class="col-applicant">Applicant Name</th>
                                <th class="col-contact">Contact No.</th>
                                <th class="col-barangay">Barangay</th>
                                <th class="col-status">Status</th>
                                <th class="col-date">Date Submitted</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                // format lastname, firstname m.i."
                                $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                            ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td class="col-program"><?= htmlspecialchars($row['program']) ?></td>
                                <td><?= $applicant_name ?></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td class="col-barangay"><?= htmlspecialchars($row['barangay'] ?? 'N/A') ?></td>
                                <td class="col-status">
                                    <span class="badge <?= 
                                        $row['status'] === 'pending' ? 'bg-pending' :
                                        ($row['status'] === 'approved' ? 'bg-approved' :
                                        ($row['status'] === 'completed' ? 'bg-completed' :
                                        ($row['status'] === 'declined' ? 'bg-declined' : 'bg-cancelled')))
                                    ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-view view-btn" data-id="<?= $row['id'] ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($result->num_rows == 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">No requests found matching your criteria</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- view requirements ,odal -->
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
    });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>