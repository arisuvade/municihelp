<?php
session_start();
include '../../../includes/db.php';

if (!isset($_SESSION['animal_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$animal_admin_id = $_SESSION['animal_admin_id'];
$admin_query = $conn->prepare("SELECT name FROM admins WHERE id = ?");
$admin_query->bind_param("i", $animal_admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();

if (!$admin_result) {
    die("Database error: " . $conn->error);
}
$admin_data = $admin_result->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

$pageTitle = 'All Requests';
include '../../../includes/header.php';

function formatPhoneNumber($phone) {
    if (empty($phone)) return 'Walk-in';
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

// Program and status options
$programs = ['Dog Adoption', 'Dog Claiming', 'Rabid Report'];
$statuses = ['pending', 'approved', 'completed', 'declined', 'cancelled', 'false_report', 'verified'];

// Build separate queries for each table with filters
$where = [];
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(CONCAT(first_name, ' ', last_name) LIKE ? OR id = ?)";
    $params[] = $search;
    $params[] = $_GET['search'];
    $types .= 'ss';
}

if (isset($_GET['program']) && !empty($_GET['program'])) {
    $program_filter = $_GET['program'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = $_GET['status'];
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $date_filter = $_GET['date'];
    $where[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Build queries for each table separately
$queries = [];
$all_params = [];
$all_types = '';

// Rabid Reports - Join with users table to get phone number
$rabid_query = "SELECT rr.id, rr.first_name, rr.middle_name, rr.last_name, u.phone, 
        'Rabid Report' as program, rr.status, rr.created_at, NULL as dog_id
     FROM rabid_reports rr
     LEFT JOIN users u ON rr.user_id = u.id";
if (!empty($whereClause) && (!isset($program_filter) || $program_filter === 'Rabid Report')) {
    $rabid_query .= " $whereClause";
    $all_params = array_merge($all_params, $params);
    $all_types .= $types;
}
if (!isset($program_filter) || $program_filter === 'Rabid Report') {
    $queries[] = $rabid_query;
}

// Dog Adoptions
$adoption_query = "SELECT da.id, da.first_name, da.middle_name, da.last_name, u.phone, 
        'Dog Adoption' as program, da.status, da.created_at, da.dog_id
     FROM dog_adoptions da
     LEFT JOIN users u ON da.user_id = u.id";
if (!empty($whereClause) && (!isset($program_filter) || $program_filter === 'Dog Adoption')) {
    $adoption_query .= " $whereClause";
    $all_params = array_merge($all_params, $params);
    $all_types .= $types;
}
if (!isset($program_filter) || $program_filter === 'Dog Adoption') {
    $queries[] = $adoption_query;
}

// Dog Claims
$claim_query = "SELECT dc.id, dc.first_name, dc.middle_name, dc.last_name, u.phone, 
        'Dog Claiming' as program, dc.status, dc.created_at, dc.dog_id
     FROM dog_claims dc
     LEFT JOIN users u ON dc.user_id = u.id";
if (!empty($whereClause) && (!isset($program_filter) || $program_filter === 'Dog Claiming')) {
    $claim_query .= " $whereClause";
    $all_params = array_merge($all_params, $params);
    $all_types .= $types;
}
if (!isset($program_filter) || $program_filter === 'Dog Claiming') {
    $queries[] = $claim_query;
}

// Combine queries
$query = implode(" UNION ALL ", $queries) . " ORDER BY created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($all_params)) {
    $stmt->bind_param($all_types, ...$all_params);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Get result failed: " . $stmt->error);
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
        
        .bg-approved {
            background-color: var(--approved) !important;
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
        
        .no-requests h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .no-requests p {
            font-size: 1rem;
            color: #6c757d;
        }
        
        /* Column widths */
        .table-col-id {
            width: 6%;
            text-align: center;
        }
        
        .table-col-name {
            width: 22%;
        }
        
        .table-col-program {
            width: 20%;
            text-align: center;
        }
        
        .table-col-phone {
            width: 13%;
            text-align: center;
        }
        
        .table-col-date {
            width: 14%;
            text-align: center;
        }
        
        .table-col-status {
            width: 13%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 8%;
            text-align: center;
        }
        
        .walkin-badge {
            background-color: #6c757d;
            color: white;
            padding: 0.25em 0.4em;
            font-size: 0.75em;
            border-radius: 3px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">All Requests</h1>
                    <p class="mb-0">View and manage all animal-related requests</p>
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
                        <label class="form-label">Program</label>
                        <select class="form-select" id="programFilter">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= htmlspecialchars($program) ?>">
                                    <?= htmlspecialchars($program) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter">
    <option value="">All Statuses</option>
    <?php foreach ($statuses as $status): ?>
        <option value="<?= $status ?>">
            <?= match($status) {
                'false_report' => 'False Report',
                'verified' => 'Verified',
                default => ucfirst($status)
            } ?>
        </option>
    <?php endforeach; ?>
</select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Date Submitted</label>
                        <input type="date" class="form-control" id="dateFilter">
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div id="resultsContainer">
            <?php if ($result->num_rows == 0): ?>
                <!-- Initial no requests message -->
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>There are no requests to display.</p>
                </div>
            <?php else: ?>
                <!-- This div will be shown/hidden as needed when filtering -->
                <div class="no-requests" id="noResultsMessage" style="display: none;">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>No requests match your current filters.</p>
                </div>

                <!-- Wrap the entire table structure in a container -->
                <div id="requestsTableContainer">
                    <!-- Desktop Table -->
                    <div class="recent-activity-card card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="table-col-id">ID</th>
                                            <th class="table-col-name">Name</th>
                                            <th class="table-col-program">Program</th>
                                            <th class="table-col-phone">Contact No.</th>
                                            <th class="table-col-date">Date Submitted</th>
                                            <th class="table-col-status">Status</th>
                                            <th class="table-col-actions"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="requestsTableBody">
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                            $name = 'Anonymous';
                                            if (!empty($row['first_name']) || !empty($row['last_name'])) {
                                                $name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                            }
                                            $phone_number = formatPhoneNumber($row['phone']);
                                        ?>
                                            <tr class="request-row" 
                                                data-id="<?= $row['id'] ?>"
                                                data-name="<?= htmlspecialchars(strtolower($row['last_name'] . ' ' . $row['first_name'])) ?>"
                                                data-program="<?= htmlspecialchars($row['program']) ?>"
                                                data-status="<?= htmlspecialchars($row['status']) ?>"
                                                data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>"
                                                data-phone="<?= htmlspecialchars($phone_number) ?>">
                                                <td class="table-col-id"><?= $row['id'] ?></td>
                                                <td class="table-col-name"><?= $name ?></td>
                                                <td class="table-col-program"><?= htmlspecialchars($row['program']) ?></td>
                                                <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
                                                <td class="table-col-date"><?= date('F j, Y', strtotime($row['created_at'])) ?></td>
                                                <td class="table-col-status">
    <span class="status-badge 
        <?= $row['status'] === 'approved' ? 'bg-approved' : 
           ($row['status'] === 'declined' || $row['status'] === 'false_report' ? 'bg-declined' : 
           ($row['status'] === 'completed' ? 'bg-completed' : 
           ($row['status'] === 'verified' ? 'bg-approved' :  // Verified gets same color as approved
           ($row['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending')))) ?>">
        <?= $row['status'] === 'false_report' ? 'False Report' : 
            ($row['status'] === 'verified' ? 'Verified' : ucfirst($row['status'])) ?>
    </span>
</td>
                                                <td class="table-col-actions">
    <?php if ($row['program'] === 'Dog Adoption'): ?>
        <button class="btn btn-view view-btn" 
                data-id="<?= $row['id'] ?>" 
                data-type="adoption">
            <i class="fas fa-eye"></i> View
        </button>
    <?php elseif ($row['program'] === 'Dog Claiming'): ?>
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
</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
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
$(document).ready(function() {
    // View button handler
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

    // Filter functionality
    function applyFilters() {
        const searchTerm = $('#searchFilter').val().toLowerCase();
        const programFilter = $('#programFilter').val();
        const statusFilter = $('#statusFilter').val();
        const dateFilter = $('#dateFilter').val();

        let anyVisible = false;

        $('.request-row').each(function() {
            const $row = $(this);
            const id = $row.data('id').toString();
            const name = $row.data('name');
            const program = $row.data('program');
            const status = $row.data('status');
            const date = $row.data('date');
            const phone = $row.data('phone');

            const matchesSearch = !searchTerm || 
                name.includes(searchTerm) || 
                id.includes(searchTerm) || 
                phone.includes(searchTerm);

            const matchesProgram = !programFilter || program === programFilter;
            const matchesStatus = !statusFilter || status === statusFilter;
            const matchesDate = !dateFilter || date === dateFilter;

            if (matchesSearch && matchesProgram && matchesStatus && matchesDate) {
                $row.show();
                anyVisible = true;
            } else {
                $row.hide();
            }
        });

        const noResultsMsg = $('#noResultsMessage');
        const tableContainer = $('#requestsTableContainer');
        
        if (!anyVisible) {
            noResultsMsg.show();
            tableContainer.hide();
        } else {
            noResultsMsg.hide();
            tableContainer.show();
        }
    }

    $('#searchFilter, #programFilter, #statusFilter, #dateFilter').on('input change', function() {
        applyFilters();
    });
});
</script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>